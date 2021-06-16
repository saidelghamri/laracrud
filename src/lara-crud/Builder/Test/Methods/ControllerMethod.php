<?php


namespace LaraCrud\Builder\Test\Methods;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use LaraCrud\Services\ModelRelationReader;

abstract class ControllerMethod
{

    protected array $testMethods = [];
    /**
     * List of full namespaces that will be import on top of controller.
     *
     * @var array
     */
    protected array $namespaces = [];

    /**
     * Whether its an API method or not.
     *
     * @var bool
     */
    protected bool $isApi = false;

    /**
     * @var \ReflectionMethod
     */
    protected $reflectionMethod;

    /**
     * @var \Illuminate\Routing\Route
     */
    protected $route;

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $parentModel;

    /**
     * @var \Illuminate\Database\Eloquent\Model
     */
    protected $model;

    /**
     * @var string
     */
    protected string $modelFactory;

    /**
     * @var string
     */
    protected string $parentModelFactory;

    public array $authMiddleware = ['auth', 'auth:sanctum', 'auth:api'];

    /**
     * @var bool
     */
    protected bool $isSanctumAuth = false;

    /**
     * @var bool
     */
    protected bool $isPassportAuth = false;

    /**
     * @var bool
     */
    protected bool $isWebAuth = false;

    /**
     * @var bool
     */
    public static bool $hasSuperAdminRole = false;


    protected ModelRelationReader $modelRelationReader;

    /**
     * ControllerMethod constructor.
     *
     * @param \ReflectionMethod         $reflectionMethod
     * @param \Illuminate\Routing\Route $route
     */
    public function __construct(\ReflectionMethod $reflectionMethod, Route $route)
    {
        $this->reflectionMethod = $reflectionMethod;
        $this->route = $route;
    }

    /**
     * @return static
     */
    public abstract function before();

    /**
     * Get Inside code of a Controller Method.
     *
     * @return string
     *
     * @throws \ReflectionException
     */
    public function getCode(): string
    {
        $this->before();
        return $this->getRoute();
        // return implode("\n", $this->testMethods);
    }

    /**
     * Get list of importable Namespaces.
     *
     * @return array
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return $this
     */
    public function setModel(Model $model): self
    {
        $this->model = $model;
        $this->modelRelationReader = (new ModelRelationReader($model))->read();

        return $this;
    }

    /**
     * Set Parent Model when creating a child Resource Controller.
     *
     * @param \Illuminate\Database\Eloquent\Model $parentModel
     *
     * @return \LaraCrud\Builder\Test\Methods\ControllerMethod
     */
    public function setParent(Model $parentModel): self
    {
        $this->parentModel = $parentModel;
        $this->namespaces[] = 'use ' . get_class($parentModel);

        return $this;
    }

    /**
     * @return string
     */
    protected function getModelFactory(): string
    {
        return $this->modelFactory;
    }

    /**
     * @return string
     */
    protected function getParentModelFactory(): string
    {
        return $this->parentModelFactory;
    }

    /**
     * Whether Current route need Auth.
     *
     * @return bool
     */
    protected function isAuthRequired(): bool
    {
        $auth = array_intersect($this->authMiddleware, $this->route->gatherMiddleware());

        if (count($auth) > 0) {
            if (in_array('auth', $auth)) {
                $this->isWebAuth = true;
            }
            if (in_array('auth:sanctum', $auth)) {
                $this->isSanctumAuth = true;
            }
            if (in_array('auth:api', $auth)) {
                $this->isPassportAuth = true;
            }
            return true;
        }
        return false;
    }

    /**
     * @return false|string
     */
    protected function getSanctumActingAs()
    {
        if (!$this->isSanctumAuth) {
            return false;
        }
        $this->namespaces[] = 'use Laravel\Sanctum\Sanctum';
        return 'Sanctum::actingAs($user, [\' * \']);';
    }

    /**
     * @return false|string
     */
    protected function getPassportActingAs()
    {
        if (!$this->isPassportAuth) {
            return false;
        }

        $this->namespaces[] = 'use Laravel\Passport\Passport';

        return 'Passport::actingAs($user, [\'*\']);';
    }

    protected function getWebAuthActingAs()
    {
        if (!$this->isWebAuth) {
            return false;
        }

        return 'actingAs($user)->';
    }

    /**
     * Whether current application has Super Admin Role.
     *
     * @return bool
     */
    protected function hasSuperAdminRole(): bool
    {
        return static::$hasSuperAdminRole;
    }

    /**
     *
     */
    protected function getRoute()
    {
        $params = '';
        $name = $this->route->getName();
        if (empty($this->route->parameterNames())) {
            return 'route("' . $name . '")';
        }
        foreach ($this->route->parameterNames() as $name) {
            if (strtolower($name) == strtolower($this->modelRelationReader->getShortName())) {
                $value = $this->getModelVariable() . '->' . $this->model->getRouteKeyName();
            } else {
                $value = '';
            }
            $params .= '"' . $name . '" => ' . $value . ', ';
        }

        return 'route("' . $name . '",[' . $params . '])';
    }

    protected function getModelVariable(): string
    {
        return '$' . lcfirst($this->modelRelationReader->getShortName());
    }

    protected function getApiActingAs()
    {
        if ($this->isSanctumAuth) {
            return $this->getSanctumActingAs();
        }
        if ($this->isPassportAuth) {
            return $this->getPassportActingAs();
        }
        return '';
    }

    protected function getGlobalVariables(): array
    {
        return [
            'modelVariable' => $this->getModelVariable(),
            'modelShortName' => $this->modelRelationReader->getShortName(),
            'route' => $this->getRoute(),
            'modelMethodName' => Str::snake($this->modelRelationReader->getShortName()),
            'apiActingAs' => $this->getApiActingAs(),
            'webActingAs' => $this->isWebAuth ? $this->getWebAuthActingAs() : '',
            'table' => $this->model->getTable(),
        ];
    }


}

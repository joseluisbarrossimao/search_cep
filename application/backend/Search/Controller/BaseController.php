<?php

declare(strict_types=1);

namespace Search\Controller;

use Search\Container\Instances;
use Search\Error\Exceptions;
use Search\Event\Event;
use Search\Http\Request;
use Search\Http\Response;
use Search\Controller\Component\AuthComponent;
use Search\ORM\BaseTable;

/**
 *
 */
class BaseController extends Controller
{
    /**
     * @var bool
     */
    protected bool $errorValidateExist = false;

    /**
     * @var array
     */
    private array $components = [];

    /**
     * @param Request $request
     * @param Response $response
     * @param Instances $instance
     * @throws Exceptions
     */
    public function __construct(Request $request, Response $response, Instances $instance)
    {
        $this->request = $request;
        $this->response = $response;
        $this->instance = $instance;
        $this->control()->initialize();
    }

    /**
     * @return $this
     */
    public function initialize(): BaseController
    {
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $datas
     * @return Controller
     */
    public function changeDataView(string $name, mixed $datas): Controller
    {
        $this->view[$name] = $datas;
        return $this;
    }

    /**
     * @param array $values
     * @return Controller
     * @throws Exceptions
     */
    public function datasView(array $values): Controller
    {
        if (count($this->view) > 0) {
            [$name, $valid] = $this->validKeyExist(array_keys($values));
            if ($valid) {
                throw new Exceptions(sprintf('This %s key already exists in the view attribute.', $name), 404);
            }
            $this->view = array_merge($this->view, $values);
            return $this;
        }
        $this->view = $values;
        return $this;
    }

    /**
     * @param bool $general
     * @param bool $internal
     * @param bool $linkInternalWithExternalAccess
     * @return $this
     * @throws Exceptions
     */
    public function changeRequestEncryptionKeys(
        bool $general,
        bool $internal,
        bool $linkInternalWithExternalAccess,
    ): BaseController {
        if ($this->request->encryptionKeys['general'] !== $general) {
            $encryptionKeys['general'] = $general;
        }
        if ($this->request->encryptionKeys['internal'] !== $internal) {
            $encryptionKeys['internal'] = $internal;
        }
        if ($this->request->encryptionKeys['linkInternalWithExternalAccess'] !== $linkInternalWithExternalAccess) {
            $encryptionKeys['linkInternalWithExternalAccess'] = $linkInternalWithExternalAccess;
        }
        $cryptography = $this->request->bootstrap('security')->criptography();
        $cryptography->changeConfig($cryptography->levelEncrypt(), false, $encryptionKeys);
        return $this;
    }

    /**
     * @param array $tables
     * @param string|null $field
     * @return array
     * @throws Exceptions
     */
    public function dataColumnsRegistry(array $tables, ?string $field = null): array
    {
        $datasType = [];
        if (isset($tables['join'])) {
            foreach ($tables['join'] as $join) {
                $repository = $this->tableRegistry(
                    $this->instance->resolveClass(
                        Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                        ['datas' => []]
                    ),
                    ['main' => [$join]],
                    ['return' => 'repository']
                );
                $datasType = count($datasType) > 0 ? array_merge(
                    $datasType,
                    $repository->columns[$join['table']],
                ) : $repository->columns[$join['table']];
            }
        }
        foreach ($tables['main'] as $main) {
            $repository = $this->tableRegistry(
                $this->instance->resolveClass(
                    Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                    ['datas' => []]
                ),
                ['main' => [$main]],
                ['return' => 'repository']
            );
            $datasType = count($datasType) > 0 ? array_merge(
                $datasType,
                $repository->columns[$main['table']],
            ) : $repository->columns[$main['table']];
        }
        if (!is_null($field)) {
            foreach ($datasType as $datatype) {
                if ($datatype['name'] === $field) {
                    $result = $datatype;
                }
            }
            if (!isset($result)) {
                throw new Exceptions(
                    sprintf("Column %s doesn't exist in the repositorys of the requested tables.", $field),
                    404
                );
            }
            return $result;
        }
        return $datasType;
    }

    /**
     * @param array $options
     * @return bool
     */
    public function validingAuthentication(array $options = []): bool
    {
        $validateAuthExist = isset($this->Auth) && is_object($this->Auth);
        if (count($options) === 0) {
            if ($validateAuthExist) {
                return $this->validatingLoggedOrNot();
            }
            return false;
        }
        if ($validateAuthExist) {
            $joins = $this->identifyTheJoinTable($options['joins'], $options['table']['main'][0]['table']);
            if ($this->validateConditionToLogin($options['conditions'])) {
                return false;
            }
            $this->tableRegistry(
                $this->instance->resolveClass(
                    Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                    ['datas' => []]
                ),
                array_merge($options['table'], ['join' => $joins])
            );
            return !$this->validateAuth($options['conditions'], $options['fieldNotValidate']);
        }
        return false;
    }

    /**
     * @param array $options
     * @return string
     * @throws Exceptions
     */
    public function linkEncrypt(array $options = []): string
    {
        if(!isset($options['link'])){
            throw new Exceptions("The link key must be informed in the options array.", 404);
        }
        if(!isset($options['prefix'])){
            $options['prefix'] = 'app';
        }
        if(!isset($options['urlParams'])){
            $options['urlParams'] = '';
        }
        if ($this->encrypted) {
            foreach (['link', 'prefix', 'urlParams', 'notDeleteVariableParams'] as $key) {
                if ($key !== 'notDeleteVariableParams') {
                    $data = !isset($options[$key]) ? '' : $options[$key];
                } else {
                    $data = !isset($options[$key]) ? false : $options[$key];
                }
                $$key = $data;
            }
            $link = !isset($notDeleteVariableParams) ? $this->encryptLinks($link, $prefix, $urlParams) : $this->encryptLinks($link, $prefix, $urlParams, $notDeleteVariableParams);
        }
        return $link;
    }

    /**
     * @param string $link
     * @return $this
     * @throws Exceptions
     */
    public function redirectToThisLink(string $link, string $prefix): BaseController
    {
        if (substr($link, 0, 1) !== DS) {
            $link = DS . $link;
        }
        $this->redirect($link);
        return $this;
    }

    /**
     * @param string $place
     * @return $this
     * @throws Exceptions
     */
    public function config(string $place): BaseController
    {
        if ($place !== 'default') {
            $this->anotherDatabase($place);
            return $this;
        }
        $this->checkAnotherDatabase = $this->app->changeConnectionDatabase($this->request, $place);
        return $this;
    }

    /**
     * @return $this
     */
    public function errorHeadRender(): Controller
    {
        if (!isset($this->view['title'], $this->view['description'], $this->view['url'], $this->view['icon'], $this->view['app'], $this->view['author'], $this->view['page'], $this->view['creator'], $this->view['site'], $this->view['domain'])) {
            $this->view = array_merge($this->view, [
                'title' => 'Rest-Full App',
                'description' => '',
                'url' => '',
                'icon' => 'favicons' . DS . 'favicon.png',
                'app' => '502887054378855',
                'author' => 'joseluisbarrossimao',
                'page' => 'Simão-Web-Solutions-246186562718604',
                'creator' => '',
                'site' => '',
                'domain' => ''
            ]);
        } else {
            $this->view['title'] = 'Rest-Full App';
        }
        return $this;
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return Controller
     * @throws Exceptions
     */
    public function dataView(string $name, mixed $value): Controller
    {
        if (count($this->view) > 0) {
            if ($this->validKeyExist($name)) {
                throw new Exceptions(sprintf('This %s key already exists in the view attribute.', $name), 404);
            }
            $this->view = array_merge($this->view, [$name => $value]);
            return $this;
        }
        $this->view = [$name => $value];
        return $this;
    }

    /**
     * @param array $options
     * @return $this
     * @throws Exceptions
     */
    public function loadComponents(array $options = ['component' => null, 'startComponent' => false]): BaseController
    {
        $classComponent = Search_NAMESPACE[1] . DS_REVERSE;
        $classComponent .= MVC[0] . DS_REVERSE;
        $classComponent .= SUBMVC[0] . DS_REVERSE;
        $classComponent .= $options['component'] . SUBMVC[0];
        if (count($this->components) === 0) {
            $this->components = $this->componentsInitialaze();
        }
        if (!isset($options['startComponent'])) {
            throw new Exceptions("The startComponent key must be informed in the options array.", 404);
        }
        if ($options['startComponent']) {
            if (array_key_exists('component', $options) === false) {
                throw new Exceptions("The component key must be informed in the options array.", 404);
            }
            $component = $options['component'];
            if (!is_null($component)) {
                if ($this->name === 'Error' && in_array($component, $this->components) === false) {
                    return $this;
                }
                $this->{$component} = $this->instance->resolveClass(
                    $this->instance->theseFilesAreFromTheFramework(
                        $this->components
                    )->locateTheFileWhetherItIsInTheAppOrInTheFramework($classComponent, $component),
                    ['controller' => $this]
                );
                if (array_key_exists($component, $this->activeHelpers) && $this->activeHelpers[$component] === false) {
                    $this->activatingHelpersThroughTheComponent($component, true);
                }
                return $this;
            }
            foreach ($this->components as $component) {
                $this->{$component} = $this->instance->resolveClass(
                    $this->instance->theseFilesAreFromTheFramework(
                        $this->components
                    )->locateTheFileWhetherItIsInTheAppOrInTheFramework($classComponent, $component),
                    ['controller' => $this]
                );
                if (array_key_exists($component, $this->activeHelpers) && $this->activeHelpers[$component] === false) {
                    $this->activatingHelpersThroughTheComponent($component, true);
                }
            }
        }
        return $this;
    }

    /**
     * @return array<string>
     * @throws Exceptions
     */
    private function componentsInitialaze(): array
    {
        $components = $tracers = [];
        if ($this->name === 'Error') {
            $tracers = ['Form', 'Paginator', 'Email'];
        }
        foreach ($this->instance->read(Search_FRAMEWORK . DS . MVC[0] . DS . SUBMVC[0], 'folder')['files'] as $file) {
            $component = substr($file, 0, stripos($file, 'Component'));
            if (count($tracers) === 0 || in_array($component, $tracers) === false) {
                $components[] = $component;
            }
        }
        return $components;
    }

    /**
     * @return $this
     * @throws Exceptions
     */
    public function initializeORM(): BaseController
    {
        $this->app = $this->request->bootstrap(strtolower(Search_NAMESPACE[1]));
        if (count($this->notORM) > 0 && in_array($this->action, $this->notORM)) {
            $this->use['ORM'] = false;
        }
        if ($this->use['ORM']) {
            if ($this->request->bootstrap('database')->validNotEmpty()) {
                throw new Exceptions(
                    'One of the keys must be empty and the host, dbname, user and pass keys cannot be empty.',
                    600,
                );
            }
            $this->objectRelationalMapping = $this->instance->resolveClass(
                Search_NAMESPACE[1] . DS_REVERSE . MVC[2][strtolower(
                    Search_NAMESPACE[1],
                )] . DS_REVERSE . Search_NAMESPACE[1] . MVC[2][strtolower(Search_NAMESPACE[1])],
                [
                    'instance' => $this->instance,
                    'request' => $this->request
                ],
            );
            $this->checkAnotherDatabase = true;
        }
        return $this;
    }

    /**
     * @return BaseTable
     */
    public function objectRelationalMapping(): BaseTable
    {
        return $this->objectRelationalMapping;
    }

    /**
     * @param array $main
     * @param array $joins
     * @return object
     * @throws Exceptions
     */
    public function returnTableRegistry(array $main, array $joins = []): object
    {
        return $this->tableRegistry(
            $this->instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                ['datas' => []]
            ),
            array_merge($main, ['join' => $joins]),
            ['return' => 'repository']
        );
    }

    /**
     * @param array $keys
     * @return $this
     */
    public function keysFieldsNotValidated(array $keysFieldsNotValidated): BaseController
    {
        $this->keysFieldsNotValidated = $keysFieldsNotValidated;
        return $this;
    }

    /**
     * @param string $queryExecutionType
     * @param array $table
     * @param array $options
     * @param array $details
     * @return object
     * @throws Exceptions
     */
    public function querys(
        string $queryExecutionType,
        array $table,
        array $options = [],
        array $details = [],
    ): object {
        if (!$this->use['ORM']) {
            throw new Exceptions(
                'You did not instantiate the model. To instantiate, go to the AppController and type $this->use["ORM"] = true in the initialize method.',
                404,
            );
        }
        if (!array_key_exists('main', $table)) {
            throw new Exceptions('The table you are using cannot be an array with the main key.', 404);
        }
        $details = $this->checkDetails($queryExecutionType, $details);
        [$table, $options, $joins, $fields, $limit] = $this->validateAndAlignData(
            $queryExecutionType,
            $table,
            $options,
        );
        if ($queryExecutionType !== 'details') {
            $this->queryExecutionType($queryExecutionType)->tableRegistry(
                $options,
                array_merge($table, ['join' => $joins])
            );
            unset($options, $joins);
            $this->identifyOfCreationOrAlterationOrDeletion();
            if ($details['notDelete']) {
                $this->queryExecutionType(
                    in_array(
                        $queryExecutionType,
                        ['delete', 'remove'],
                    ) ? ($queryExecutionType === 'remove' ? 'delete' : 'update') : $queryExecutionType,
                );
            }
            if ($details['validate'] && $this->validateTableData()) {
                $this->queryExecutionType('errorValidate');
                return $this->executed(['result'], false);
            }
            if ($details['businessRules']) {
                $this->objectRelationalMapping->businessRules();
            }
            $this->assemblyQuery($limit, $details, $fields);
            return $this->executed((isset($fields) && count($fields) > 0 ? $fields : []), $details['repository']);
        }
        $this->queryExecutionType($queryExecutionType)->TableRegistry($options, $table);
        unset($options, $joins);
        $this->assemblyQuery(0, $details, []);
        return $this->executed([], false);
    }

    /**
     * @param string $table
     * @param array $options
     * @return array
     * @throws Exceptions
     */
    public function conversionDatas(string $table, array $options): array
    {
        $this->tableRegistry(
            $this->instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                ['datas' => $options]
            ),
            ['main' => [['table' => $table]]]
        );
        $newOptions = $this->objectRelationalMapping->queryExecutionType('update')->businessRules()->optionsQuery()->clone();
        foreach (array_keys($options[0]['fields']) as $key) {
            $datas[$key] = $newOptions[0]['fields'][$key];
        }
        return $datas;
    }

    /**
     * @return string
     * @throws Exceptions
     */
    public function csrf(): string
    {
        $this->oldRouteUseCsrf();
        return $this->request->bootstrap('security')->salt();
    }

    /**
     * @param object $query
     * @param int|null $limit
     * @return bool|int
     */
    public function counts(object $query, ?int $limit = null): bool|int
    {
        $counts = 0;
        foreach ($query as $key => $values) {
            if (in_array($key, ['repository', 'count']) === false) {
                if (is_object($values)) {
                    if ($this->counts($values, $limit)) {
                        $counts++;
                    }
                } else {
                    $counts++;
                }
            }
        }
        if (isset($limit)) {
            return $counts > $limit;
        }
        return $counts;
    }

    /**
     * @param string $view
     * @param string $prefix
     * @param bool $changeRequest
     * @return $this
     */
    public function renderRequestAction(string $view, string $prefix = ''): BaseController
    {
        if ($prefix !== '' && $prefix != $this->request->prefix) {
            $this->request->prefix = $prefix;
            if ($prefix === 'app') {
                $this->folder('Main');
            }
        }
        $this->render($view);
        return $this;
    }

    /**
     * @param string $class
     * @param string|array $methods
     * @param array $options
     * @param string|null $type
     * @return $this
     * @throws Exceptions
     */
    public function returnFromAbstractClassOrResult(
        string $class,
        string|array $methods,
        array $options,
        ?string $type = null
    ): BaseController {
        if (preg_match('/[A-Z]/i', substr($class, 0, 1)) === false) {
            $class = ucFirst($class);
        }
        $plugin = $this->request->bootstrap('plugins')->startClass($class);
        if ($type === 'object') {
            if (!isset($options['component'])) {
                $options['component'] = $this->instance->resolveClass(
                    Search_NAMESPACE[0] . DS_REVERSE . 'Controller' . DS_REVERSE . 'Component',
                    ['controller' => $this],
                );
            }
            $this->{$class} = isset($type) ? $plugin->treatment(
                $methods,
                $options,
                $type,
            ) : $plugin->treatments($methods, $options);
            return $this;
        }
        return isset($type) ? $plugin->treatment($methods, $options, $type) : $plugin->treatments($methods, $options);
    }

    /**
     * @param string $action
     * @return $this
     * @throws Exceptions
     */
    public function emailBuilder(string $action): BaseController
    {
        if ($action === '') {
            throw new Exceptions("This action variable can't be empty.");
        }
        $this->builder($action);
        return $this;
    }

    /**
     * @param Event $event
     * @return mixed
     */
    public function beforeFilter(Event $event): mixed
    {
        return null;
    }

    /**
     * @param Event $event
     * @param string $url
     * @param Response $response
     * @return null
     */
    public function beforeRedirect(Event $event, string $url, Response $response): mixed
    {
        return null;
    }

    /**
     * @param Event $event
     * @return null
     */
    public function afterFilter(Event $event): mixed
    {
        return null;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function keysEncryptionAuthIdentify(array $data): BaseController
    {
        $this->keysEncryptionAuth($data);
        return $this;
    }

    /**
     * @return Instances
     */
    public function instance(): Instances
    {
        return $this->instance;
    }

    /**
     * @param string $key
     * @return $this
     * @throws Exceptions
     */
    public function unsetDataView(string $key): BaseController
    {
        if (!isset($this->view[$key])) {
            throw new Exceptions("The {$key} key doesn't exist in the datas view.", 404);
        }
        $this->deletedDataView($key);
        return $this;
    }

    /**
     * @return array
     */
    protected function validationsResult(): array
    {
        if ($this->validExistErroInValidate()) {
            return $this->returnErrorValidation();
        }
        return [];
    }

    /**
     * @param array $routes
     * @return $this
     */
    protected function addRoutesNotEncryption(array $routes): BaseController
    {
        $keysEncryptionAuth = $this->keysEncryptionAuth();
        $change = false;
        if (!isset($keysEncryptionAuth[0]['routes'])) {
            $change = !$change;
            $keysEncryptionAuth[0]['routes'] = $routes;
        } else {
            $exist = 0;
            foreach ($routes as $route) {
                if (in_array($route, $keysEncryptionAuth[0]['routes'])) {
                    $exist++;
                }
            }
            if ($exist === 0) {
                $change = !$change;
            }
        }
        if ($change) {
            $this->keysEncryptionAuth($keysEncryptionAuth);
        }
        return $this;
    }
}

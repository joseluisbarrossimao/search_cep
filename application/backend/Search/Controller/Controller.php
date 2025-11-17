<?php

declare(strict_types=1);

namespace Search\Controller;

use Search\Collection\Groupings;
use Search\Container\Instances;
use Search\Controller\Component\AuthComponent;
use Search\Controller\Component\FlashComponent;
use Search\Controller\Component\PaginatorComponent;
use Search\Core\Application;
use Search\Datasource\EventDispatcherTrait;
use Search\Error\Exceptions;
use Search\Http\Request;
use Search\Http\Response;
use Search\ORM\BaseTable;
use Search\Utility\Translator;

/**
 *
 */
abstract class Controller
{
    use EventDispatcherTrait;

    /**
     * @var FlashComponent
     */
    public FlashComponent $Flash;

    /**
     * @var PaginatorComponent
     */
    public PaginatorComponent $Paginator;

    /**
     * @var AuthComponent
     */
    public AuthComponent $Auth;

    /**
     * @var string
     */
    public string $layout = 'default';

    /**
     * @var array
     */
    public array $view = [];

    /**
     * @var string
     */
    public string $name;

    /**
     * @var Request
     */
    public Request $request;

    /**
     * @var Response
     */
    public Response $response;

    /**
     * @var array
     */
    public array $activeHelpers = ['email' => false, 'paginator' => false];

    /**
     * @var string
     */
    public string $action;

    /**
     * @var bool
     */
    public bool $encrypted = false;

    /**
     * @var array
     */
    public array $unencryptedRoute;

    /**
     * @var Translator
     */
    public Translator $Translator;

    /**
     * @var string
     */
    public string $route = '';

    /**
     * @var BaseTable
     */
    protected BaseTable $objectRelationalMapping;

    /**
     * @var bool
     */
    protected bool $checkAnotherDatabase = false;

    /**
     * @var Instances
     */
    protected Instances $instance;

    /**
     * @var object
     */
    protected object $result;

    /**
     * @var array
     */
    protected array $notORM = [];

    /**
     * @var array|false[]
     */
    protected array $use = ['ORM' => false, 'validation' => false];

    /**
     * @var Application
     */
    protected Application $app;

    /**
     * @var array
     */
    protected array $keysFieldsNotValidated = [];

    /**
     * @var array
     */
    private array $errorsInValidation;

    /**
     * @return $this
     */
    public function control(): Controller
    {
        $this->name = $this->request->controller;
        $this->action = $this->request->action;
        $this->route = $this->request->route;
        if ($this->name == 'Error') {
            $this->layout = strtolower($this->request->controller);
        }
        return $this;
    }

    /**
     * @param bool $startModel
     * @param bool $startValidation
     * @return $this
     * @throws Exceptions
     */
    public function settingTrueOrFalseToUseTheModel(bool $startModel = false, bool $startValidation = true): Controller
    {
        if (!is_null($this->request->bootstrap('database'))) {
            $this->use['ORM'] = $startModel;
            if ($startModel) {
                $this->use['validation'] = $startValidation;
            }
        }
        return $this;
    }

    /**
     * @return bool
     * @throws Exceptions
     */
    public function validateTableData(): bool
    {
        $options = $this->objectRelationalMapping->optionsQuery();
        $queryExecutionType = $this->objectRelationalMapping->queryExecutionType();
        if ($this->use['validation']) {
            $biult = false;
            if ($queryExecutionType === 'query') {
                $type = substr($options[0]['query'], 0, stripos($options[0]['query'], ' '));
                if ($type === 'show') {
                    $queryExecutionType = 'select';
                }
            } elseif (
                in_array(
                    $queryExecutionType,
                    ['open', 'all', 'first', 'countRows', 'union', 'built', 'union and built', 'built and union'],
                )
            ) {
                if (in_array($queryExecutionType, ['biult', 'union and built', 'built and union'])) {
                    $biult = !$biult;
                }
                $queryExecutionType = 'select';
            }
            if ($queryExecutionType != 'select') {
                $datas = [];
                $options = $this->objectRelationalMapping->optionsQuery();
                foreach ($options->iterator() as $optionsDatas) {
                    unset($optionsDatas['table']);
                    if ($queryExecutionType === 'update') {
                        $datas = array_merge($datas, $optionsDatas['fields']);
                    }
                    $datas = $this->theQueryConditionsToBeValidated($optionsDatas['conditions'], $datas);
                }
                if ($biult) {
                    $datas = $this->theQueryConditionsToBeValidated($options['conditions'], $datas);
                }
                return $this->objectRelationalMapping->insertDatasInValidate(
                    $datas,
                    $this->keysFieldsNotValidated,
                    $this->route
                )->validation();
            }
        }
        return false;
    }

    /**
     * @param string $queryExecutionType
     * @return $this
     * @throws Exceptions
     */
    protected function queryExecutionType(string $queryExecutionType): Controller
    {
        if (
            in_array(
                $queryExecutionType,
                [
                    'open',
                    'all',
                    'first',
                    'countRows',
                    'union',
                    'built',
                    'union and built',
                    'built and union',
                    'create',
                    'update',
                    'delete',
                ],
            ) === false
        ) {
            throw new Exceptions(
                sprintf(
                    'this %s type is not accepted, they are these types: open, all, first, countRows, union, built, union and built, built and union, create, update or delete.',
                    $queryExecutionType
                ),
                404,
            );
        }
        $this->objectRelationalMapping->queryExecutionType($queryExecutionType);
        return $this;
    }

    /**
     * @param array $conditions
     * @param array $datas
     * @return array
     */
    private function theQueryConditionsToBeValidated(array $conditions, array $datas): array
    {
        $datasConditions = [];
        foreach ($conditions as $key => $value) {
            if (!isset($datas[$key])) {
                if (is_string($key)) {
                    if (stripos($key, ' ') !== false) {
                        $key = substr($key, 0, stripos($key, ' '));
                    }
                } else {
                    foreach ([' <==> ', ' !<==> ', ' () ', ' !() '] as $logicalOperator) {
                        if (stripos($value, $logicalOperator) !== false) {
                            [$key, $value] = explode($logicalOperator, $value);
                            $key .= $logicalOperator;
                        }
                    }
                }
                $datasConditions[$key] = $value;
            }
        }
        return array_merge($datas, $datasConditions);
    }

    /**
     * @return $this
     * @throws Exceptions
     */
    public function identifyOfCreationOrAlterationOrDeletion(): Controller
    {
        if(!isset($this->Auth)){
            throw new Exceptions('the Auth component not found.',404);
        }
        if(!$this->Auth->checkingAuthenticated()){
            return $this;
        }
        $this->objectRelationalMapping->identifyOfCreationOrAlterationOrDeletion($this->Auth->authenticated()['id']);
        return $this;
    }

    /**
     * @param string $event
     * @param array $data
     * @param object|null $object
     * @return mixed
     * @throws Exceptions
     */
    public function eventProcessVerification(string $event, array $data = [], ?object $object = null): mixed
    {
        $event = $this->dispatchEvent($this->instance, MVC[0] . '.' . $event, $data, $object);
        return $event->result();
    }

    /**
     * @param string $key
     * @param bool $value
     * @return $this
     * @throws Exceptions
     */
    public function activatingHelpersThroughTheComponent(string $key, bool $value = false): Controller
    {
        if (in_array($key, ['email', 'paginator']) === false) {
            throw new Exceptions('Can only activate the component mail or paginator of the framework.', 500);
        }
        if ($value) {
            $this->activeHelpers[$key] = $value;
            return $this;
        }
        $this->activeHelpers[$key] = $this->app->checkEmail();
        return $this;
    }

    /**
     * @return string
     */
    public function routeExecuting(): string
    {
        return $this->route;
    }

    /**
     * @return string
     */
    public function newAction(): string
    {
        return $this->action;
    }

    /**
     * @param string $queryExecutionType
     * @param array $table
     * @param array $options
     * @return array
     * @throws Exceptions
     */
    public function validateAndAlignData(string $queryExecutionType, array $table, array $options): array
    {
        if (isset($table['table'])) {
            $table['main'] = [$table];
        }
        $joins = $fields = [];
        $limit = 0;
        if ($queryExecutionType !== 'query') {
            $countLimit = 1;
            $count = 1;
            if (count($options) > 1) {
                $count = count($options);
            }
            if (isset($options[$countLimit - 1]['limit'])) {
                $limit = 1;
                if ($count > 1) {
                    $limit *= $count;
                }
            }
            for ($number = 0; $number < $count; $number++) {
                foreach ($table['main'] as $key => $value) {
                    $options[$number]['table'][$key] = $value;
                }
                if (isset($options[$number]['join'])) {
                    foreach ($table['main'] as $value) {
                        $joins = array_merge(
                            $joins,
                            $this->identifyTheJoinTable($options[$number]['join'], $value['table'])
                        );
                    }
                }
                foreach ($options[$number]['fields'] as $field) {
                    $fields[] = $field;
                }
            }
            if (in_array($queryExecutionType, ['union', 'built', 'union and built', 'built and union'])) {
                if (isset($options['built']['limit'])) {
                    $limit = 1;
                }
                if (isset($options['built']['fields'])) {
                    $fields = $options['built']['fields'];
                }
            }
            return [
                $table,
                $this->instance->resolveClass(
                    Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                    ['datas' => $options]
                ),
                $joins,
                $fields,
                $limit
            ];
        }
        if (stripos($options['query'], 'limit') !== false) {
            $limit = 1;
        }
        for ($number = 0; $number < 1; $number++) {
            $options[$number]['table'] = $table['main'];
        }
        return [
            $table,
            $this->instance->resolveClass(
                Search_NAMESPACE[0] . DS_REVERSE . 'Collection' . DS_REVERSE . 'Groupings',
                ['datas' => $options]
            ),
            $joins,
            $fields,
            $limit
        ];
    }

    /**
     * @param array $joins
     * @param string $table
     * @return array
     */
    protected function identifyTheJoinTable(array $joins, string $table): array
    {
        $tables = $newJoins = [];
        foreach ($joins as $join) {
            if (in_array($join['table'], $tables) === false) {
                $newJoins[$table][]['table'] = $join['table'];
                if (isset($join['alias'])) {
                    $newJoins[$table][]['alias'] = $join['alias'];
                }
                $tables[] = $join['table'];
            }
        }
        return $newJoins;
    }

    /**
     * @param array $conditions
     * @return bool
     * @throws Exceptions
     */
    public function validateConditionToLogin(array $conditions): bool
    {
        if (!$this->Auth->checkingAuthenticated()) {
            return $this->Auth->validationConditionsToFetchAuthenticationData($conditions);
        }
        return false;
    }

    /**
     * @param array $conditions
     * @param string $fieldNotValidate
     * @return bool
     * @throws Exceptions
     */
    public function validateAuth(array $conditions, string $fieldNotValidate): bool
    {
        if ($this->use['validation']) {
            if (isset($conditions[$fieldNotValidate])) {
                foreach ($this->keysFieldsNotValidated as $key => $value) {
                    if ($value === $fieldNotValidate) {
                        unset($this->keysFieldsNotValidated[$key]);
                        break;
                    }
                }
            }
            return $this->objectRelationalMapping->insertDatasInValidate(
                $conditions,
                $this->keysFieldsNotValidated,
                $this->route
            )->validation(false);
        }
        return false;
    }

    /**
     * @param Groupings $optionsDatas
     * @param array $tables
     * @param array $optionsMode
     * @return object
     * @throws Exceptions
     */
    public function tableRegistry(Groupings $optionsDatas, array $tables, array $optionsMode = []): object
    {
        $this->objectRelationalMapping->startTableRegistryInstance();
        $return = 'controller';
        if (isset($optionsMode['return'])) {
            $return = $optionsMode['return'];
            unset($optionsMode['return']);
        }
        if ($return === 'repository') {
            return $this->objectRelationalMapping->scannigTheMetadata($optionsDatas, $tables);
        }
        $this->objectRelationalMapping = $this->objectRelationalMapping->scannigTheMetadata($optionsDatas, $tables);
        return $this;
    }

    /**
     * @param string $link
     * @param string $prefix
     * @param string $urlParams
     * @param bool $notDeleteVariableParams
     * @return string
     * @throws Exceptions
     */
    protected function encryptLinks(
        string $link,
        string $prefix,
        string $urlParams = '',
        bool $notDeleteVariableParams = false
    ): string {
        if (stripos($link, DS) === false && $link !== '#') {
            $link = $this->request->routes()[strtolower($link)];
        }
        if ($prefix !== 'app') {
            $link = $prefix . DS . $link;
        }
        if ($urlParams !== '') {
            $params = stripos($urlParams, DS) !== false ? explode(DS, $urlParams) : [$urlParams];
        }
        $url = explode(DS, $link);
        $number = 0;
        foreach ($url as $key => $value) {
            if (stripos($value, '{') !== false) {
                if (isset($params[$number])) {
                    $url[$key] = $params[$number];
                } elseif (!$notDeleteVariableParams) {
                    unset($url[$key]);
                }
                $number++;
            }
        }
        $link = implode(DS, $url);
        if ($this->encrypted) {
            $cryptography = $this->request->bootstrap('security')->criptography();
            if (!$cryptography->alfanumero()) {
                $cryptography->changeConfig($cryptography->LevelEncrypt(), true);
            }
            return $cryptography->encrypt($link);
        }
        return $link;
    }

    /**
     * @param string|array $names
     * @return bool|array
     */
    protected function validKeyExist(string|array $names): bool|array
    {
        if (is_string($names)) {
            return array_key_exists($names, $this->view);
        }
        $validated = false;
        foreach (array_keys($this->view) as $key) {
            if ($key === $names) {
                $validated = !$validated;
                $name = $key;
                break;
            }
        }
        return [$name ?? '', $validated];
    }

    /**
     * @param string $name
     * @return $this
     */
    protected function deletedDataView(string $name): Controller
    {
        unset($this->view[$name]);
        return $this;
    }

    /**
     * @return bool
     */
    protected function validExistErroInValidate(): bool
    {
        $this->errorsInValidation = $this->objectRelationalMapping->returnErrorValidation();
        return count($this->errorsInValidation) > 0;
    }

    /**
     * @return array
     */
    protected function returnErrorValidation(): array
    {
        return $this->errorsInValidation;
    }

    /**
     * @param string $place
     * @return $this
     * @throws Exceptions
     */
    protected function anotherDatabase(string $place): Controller
    {
        if (!$this->Auth->checkingTheOtherData('checkAnotherDatabase')) {
            $this->checkAnotherDatabase = $this->app->changeConnectionDatabase($this->request, $place);
            $this->Auth->writingTheOtherData(
                'checkAnotherDatabase',
                [$this->Auth->authenticated()['id'] => [strtotime(date('Y-m-d H:i:s')), $this->checkAnotherDatabase]],
            );
            return $this;
        }
        if (!isset($this->Auth->checkingTheOtherData('checkAnotherDatabase')[$this->Auth->authenticated()['id']])) {
            $this->checkAnotherDatabase = $this->app->changeConnectionDatabase($this->request, $place);
            $this->Auth->writingTheOtherData(
                'checkAnotherDatabase',
                [$this->Auth->authenticated()['id'] => [strtotime(date('Y-m-d H:i:s')), $this->checkAnotherDatabase]],
            );
        }
        $checkAnotherDatabase = $this->Auth->returnTheOtherData('checkAnotherDatabase')[$this->Auth->authenticated(
        )['id']];
        if (strtotime(date('Y-m-d H:i:s') . ' -25 minutes') < $checkAnotherDatabase[0]) {
            $this->checkAnotherDatabase = $checkAnotherDatabase[1];
        } else {
            $this->checkAnotherDatabase = $this->app->changeConnectionDatabase($this->request, $place);
            $this->Auth->writingTheOtherData(
                'checkAnotherDatabase',
                [$this->Auth->authenticated()['id'] => [strtotime(date('Y-m-d H:i:s')), $this->checkAnotherDatabase]],
            );
        }
        return $this;
    }

    /**
     * @return BaseController
     * @throws Exceptions
     */
    protected function oldRouteUseCsrf(): BaseController
    {
        if ($this->Auth instanceof AuthComponent) {
            $this->Auth->writingTheOtherData('routeThatUsesCsrf', ['route' => $this->route]);
        }
        return $this;
    }

    /**
     * @param array|null $datas
     * @return mixed
     * @throws Exceptions
     */
    protected function keysEncryptionAuth(?array $datas = null): mixed
    {
        if (!is_null($datas)) {
            $this->Auth->writingTheOtherData('keysEncryption', $datas, true);
            return $this;
        }
        return $this->Auth->returnTheOtherData('keysEncryption');
    }

    /**
     * @return bool
     * @throws Exceptions
     */
    protected function validatingLoggedOrNot(): bool
    {
        $resultValidated = false;
        $checkedAuth = $this->Auth->checkingAuthenticated();
        $result = $this->Auth->checkingIfItIsAuthenticated($checkedAuth);
        if ($result['validated']) {
            $this->redirect($this->Auth->redirectsPath($result['redirect']));
            $resultValidated = !$resultValidated;
        }
        if (!$resultValidated) {
            if ($this->Auth->checkingIfTheIndicatedRouteIsDifferentFromTheRoutes($checkedAuth, $this->route)) {
                $this->redirect($this->Auth->redirectsPath('login'));
                $this->Flash->error('You have lost access, please re-login.');
                $this->Auth->destroyAuthenticated();
            }
        }
        return $resultValidated;
    }

    /**
     * @param string $url
     * @return $this
     * @throws Exceptions
     */
    public function redirect(string $url): Controller
    {
        if ($this->Auth !== null) {
            $this->Auth->writingTheOtherData('route', ['path' => $this->request->route]);
        }
        $this->route = $url;
        return $this;
    }

    /**
     * @param int $count
     * @param array $details
     * @param array $fields
     * @return $this
     * @throws Exceptions
     */
    protected function assemblyQuery(int $count, array $details, array $fields): Controller
    {
        $optionsFind = ['editLimit' => false, 'joinLimit' => $count > 0];
        if ($count > 0) {
            if ($count > 1) {
                for ($number = 0; $number < $count; $number++) {
                    $detail['deleteLimit'][$number] = $details['deleteLimit'][0];
                }
            }
            if (isset($detail)) {
                $details['deleteLimit'] = array_merge($details['deleteLimit'], $detail['deleteLimit']);
                unset($detail);
            }
            $optionsFind['editLimit'] = !$optionsFind['editLimit'];
        }
        $this->objectRelationalMapping->insertingDataToExecuteTheAssembledQuery(
            array_merge(['deleteLimit' => $details['deleteLimit']], $optionsFind),
            $details['lastId']
        );
        return $this;
    }

    /**
     * @param array $fields
     * @param bool $repository
     * @return object
     * @throws Exceptions
     */
    protected function executed(array $fields, bool $repository): object
    {
        return $this->objectRelationalMapping->insertDataIntoYourEntity($repository, in_array($this->objectRelationalMapping->queryExecutionType(), ['delete', 'details']) === false ? $fields : []);
    }

    /**
     * @param string $view
     * @return $this
     */
    protected function render(string $view): Controller
    {
        $this->action = $view;
        $this->request->action = $view;
        return $this;
    }

    /**
     * @param string $folder
     * @return $this
     */
    protected function folder(string $folder): Controller
    {
        $this->request->controller = $folder;
        $this->name = $folder;
        return $this;
    }

    /**
     * @param string $type
     * @param array $details
     * @return array
     */
    protected function checkDetails(string $type, array $details): array
    {
        $newDetails = [];
        foreach (
            [
                'repository',
                'lastId',
                'deleteLimit',
                'validate',
                'businessRules',
                'entityColumn',
                'notDelete',
                'joinLimit',
            ] as $key
        ) {
            if (!isset($details[$key])) {
                switch ($key) {
                    case 'repository':
                    case 'validate':
                    case 'entityColumn':
                    case 'joinLimit':
                        $newDetails[$key] = true;
                        break;
                    case 'lastId':
                    case 'notDelete':
                        $newDetails[$key] = false;
                        break;
                    case 'deleteLimit':
                        $newDetails[$key][0] = false;
                        break;
                    case 'businessRules':
                        $newDetails[$key] = in_array($type, ['delete', 'remove']) === false;
                        break;
                }
            }
        }
        return array_merge($details, $newDetails);
    }

    /**
     * @param string $action
     * @return $this
     * @throws Exceptions
     */
    protected function builder(string $action): Controller
    {
        $email = $this->instance->resolveClass(
            Search_NAMESPACE[0] . DS . 'Mail' . DS . 'Email' . MVC[1],
        );
        $email->run($this, $action);
        return $this;
    }
}
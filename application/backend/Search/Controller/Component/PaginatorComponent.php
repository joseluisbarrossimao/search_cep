<?php

declare(strict_types=1);

namespace Search\Controller\Component;

use Search\Controller\BaseController;
use Search\Controller\Component;
use Search\Datasource\Pagination;
use Search\Error\Exceptions;

/**
 *
 */
class PaginatorComponent extends Component
{
    /**
     * @var Pagination
     */
    private Pagination $pagination;

    /**
     * @var array
     */
    private array $params;

    /**
     * @param BaseController $controller
     * @throws Exceptions
     */
    public function __construct(BaseController $controller)
    {
        $instance = $controller->instance();
        $this->pagination = $instance->resolveClass(
            Search_NAMESPACE[0] . DS_REVERSE . 'Datasource' . DS_REVERSE . 'Pagination',
            ['page' => 1, 'limit' => 7],
        );
        parent::__construct($controller);
    }

    /**
     * @param object $resultset
     * @param array $options
     * @return Pagination
     */
    public function paginator(object $resultset, array $options): Pagination
    {
        $params['count'] = 0;
        if (isset($resultset->repository)) {
            $keys = array_keys($resultset->repository->rowsCount);
            foreach ($keys as $key) {
                if ($resultset->repository->rowsCount[$key] != 0) {
                    $params['count'] += $resultset->repository->rowsCount[$key];
                }
            }
        }
        if ($params['count'] === 0) {
            $params['count'] = 1;
        }
        $this->pages($resultset, $keys);
        $params = array_merge($params, $this->params);
        $this->pagination->paramsDefault(array_merge($options, $params))->paginator();
        $this->request->activation();
        $this->pagination->resultset($resultset);
        return $this->pagination;
    }

    /**
     * @param object $resultset
     * @param array $keys
     * @return PaginatorComponent
     */
    private function pages(object $resultset, array $keys): PaginatorComponent
    {
        $rowCounts = 0;
        $default = $this->pagination->paramsDefault();
        foreach ($keys as $key) {
            $rowCounts += $resultset->repository->rowsCount[$key];
        }
        $this->params['pages'] = $rowCounts % $default['limit'] != 0 ? ceil(
            $rowCounts / $default['limit'],
        ) : $rowCounts / $default['limit'];
        if ($this->params['pages'] === 0) {
            $this->params['pages'] = 1;
        }
        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     * @throws Exceptions
     */
    public function limitDefault(int $limit): PaginatorComponent
    {
        $default = $this->pagination->paramsDefault();
        $this->params(['limit' => $limit, 'page' => $default['page']]);
        return $this;
    }

    /**
     * @param array $params
     * @param bool $lastCalc
     * @return $this
     * @throws Exceptions
     */
    public function params(array $params, bool $lastCalc = true): PaginatorComponent
    {
        if ($params['page'] === '') {
            throw new Exceptions('Parameter cannot be empty.', 404);
        }
        $default = $this->pagination->paramsDefault();
        $this->params['initial'] = (int)($params['page'] != $default['page'] ? ($params['page'] * $params['limit']) - $params['limit'] : (isset($this->params['initial']) ? $params['limit'] : 0));
        if ($lastCalc) {
            $this->params['last'] = (int)($params['limit'] != $default['limit'] ? $params['limit'] : $default['limit']);
            if ($params['limit'] != $default['limit']) {
                $default['limit'] = (int)$params['limit'];
            }
        }
        if ($params['page'] != $default['page']) {
            $default['page'] = (int)$params['page'];
        }
        $this->params['page'] = $params['page'];
        $this->pagination->paramsDefault($default);
        return $this;
    }

    /**
     * @return array
     */
    public function limit(): array
    {
        return [$this->params['initial'], $this->params['last']];
    }
}

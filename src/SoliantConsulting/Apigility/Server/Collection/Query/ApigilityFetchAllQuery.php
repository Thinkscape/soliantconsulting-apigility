<?php

namespace SoliantConsulting\Apigility\Server\Collection\Query;

use DoctrineModule\Persistence\ObjectManagerAwareInterface;
use Zend\Paginator\Adapter\AdapterInterface;


interface ApigilityFetchAllQuery extends ObjectManagerAwareInterface
{

    /**
     * @param string $entityClass
     * @param array $parameters
     *
     * @return mixed This will return an ORM or ODM Query\Builder
     */
    public function createQuery($entityClass, array $parameters);

    /**
     * @param       $entityClass
     * @param array $parameters
     *
     * @return AdapterInterface
     */
    public function getPaginatedQuery($entityClass, array $parameters);

} 
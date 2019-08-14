<?php

use Doctrine\ORM\EntityManager;
use Monolog\Logger;

abstract class AbstractResource
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager = null;

    /**
     * @var \Monolog\Logger
     */
    protected $logger = null;

    public function __construct(EntityManager $entityManager, Logger $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }
}

<?php

namespace Tests;

// include vars
require __DIR__ . '/../config/vars.php';

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private function getTypes(): array
    {
        static $types;

        if (!$types) {
            $types = require CONFIG_DIR . '/types.php';
        }

        return $types;
    }

    protected function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        static $em;

        if (!$em) {
            foreach ($this->getTypes() as $type => $class) {
                \Doctrine\DBAL\Types\Type::addType($type, $class);
            }

            $config = \Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration(
                [SRC_DIR . '/Domain/Entities'], true, CACHE_DIR . '/proxies', null, false
            );
            $em = \Doctrine\ORM\EntityManager::create(
                ['driver' => 'pdo_sqlite', 'path' => VAR_DIR . '/database-test.sqlite'], $config
            );
            $em->getConnection()->getDatabasePlatform()->registerDoctrineTypeMapping('enum', 'string');
        }

        /*
         * Для каждого теста будем использовать пустую БД.
         * Для этого можно удалить схему и создать её заново
         */
        $schema = new \Doctrine\ORM\Tools\SchemaTool($em);
        $schema->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());

        return $em;
    }
}

<?php declare(strict_types=1);

namespace Tests;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @return array
     */
    private function getTypes(): array
    {
        static $types;

        if (!$types) {
            $types = require CONFIG_DIR . '/types.php';
        }

        return $types;
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\Tools\ToolsException
     *
     * @return \Doctrine\ORM\EntityManager
     */
    protected function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        static $em;

        if (!$em) {
            // include vars
            require_once __DIR__ . '/../config/vars.php';

            foreach ($this->getTypes() as $type => $class) {
                if (!\Doctrine\DBAL\Types\Type::hasType($type)) {
                    \Doctrine\DBAL\Types\Type::addType($type, $class);
                }
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

    /**
     * @return \Faker\Generator
     */
    protected function getFaker()
    {
        static $faker;

        if (!$faker) {
            $faker = \Faker\Factory::create();
        }

        return $faker;
    }
}

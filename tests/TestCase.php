<?php declare(strict_types=1);

namespace tests;

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

    /**
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    protected function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        static $em;

        if (!$em) {
            // default timezone
            date_default_timezone_set('UTC');

            // include vars
            require_once __DIR__ . '/../config/vars.php';

            foreach ($this->getTypes() as $type => $class) {
                if (!\Doctrine\DBAL\Types\Type::hasType($type)) {
                    \Doctrine\DBAL\Types\Type::addType($type, $class);
                } else {
                    \Doctrine\DBAL\Types\Type::overrideType($type, $class);
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
         * for each test, we will use an empty database
         * delete the scheme and create it again
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

    /**
     * @param string $method
     * @param string $path
     * @param array  $headers
     * @param array  $cookies
     * @param array  $serverParams
     *
     * @return Request
     */
    protected function createRequest(
        string $method,
        string $path,
        array $headers = ['HTTP_ACCEPT' => 'application/json'],
        array $cookies = [],
        array $serverParams = []
    ): Request
    {
        $uri = new Uri('', '', 80, $path);
        $handle = fopen('php://temp', 'w+');
        $stream = (new StreamFactory())->createStreamFromResource($handle);

        $h = new Headers();
        foreach ($headers as $name => $value) {
            $h->addHeader($name, $value);
        }

        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    }
}

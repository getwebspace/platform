<?php declare(strict_types=1);

namespace tests;

use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Slim\App;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private static App $app;

    private static ContainerInterface $container;

    protected EntityManager $em;

    /**
     * @return App
     */
    public static function setUpBeforeClass(): void
    {
        $_ENV['TEST'] = 1; // in test always true (!)

        require SRC_DIR . '/bootstrap.php';

        /**
         * from bootstrap
         *
         * @var \Slim\App     $app
         */
        static::$app = $app;
        static::$container = static::$app->getContainer();
    }

    public function setUp(): void
    {
        $this->em = $em = static::$container->get(EntityManager::class);

        /*
         * for each test, we will use an empty database
         * delete the scheme and create it again
         */
        $schema = new \Doctrine\ORM\Tools\SchemaTool($em);
        $schema->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $schema->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function getService($class): mixed
    {
        return static::$container->get($class);
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

    //    /**
    //     * @param string $method
    //     * @param string $path
    //     * @param array  $headers
    //     * @param array  $cookies
    //     * @param array  $serverParams
    //     *
    //     * @return Request
    //     */
    //    protected function createRequest(
    //        string $method,
    //        string $path,
    //        array $headers = ['HTTP_ACCEPT' => 'application/json'],
    //        array $cookies = [],
    //        array $serverParams = []
    //    ): Request
    //    {
    //        $uri = new Uri('', '', 80, $path);
    //        $handle = fopen('php://temp', 'w+');
    //        $stream = (new StreamFactory())->createStreamFromResource($handle);
    //
    //        $h = new Headers();
    //        foreach ($headers as $name => $value) {
    //            $h->addHeader($name, $value);
    //        }
    //
    //        return new SlimRequest($method, $uri, $h, $cookies, $serverParams, $stream);
    //    }
}

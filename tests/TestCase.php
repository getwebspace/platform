<?php declare(strict_types=1);

namespace tests;

use Phinx\Console\PhinxApplication;
use Psr\Container\ContainerInterface;
use Slim\App;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private static App $app;

    private static ContainerInterface $container;

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
         * @var \Slim\App $app
         */
        static::$app = $app;
        static::$container = static::$app->getContainer();

        static::ensureSecretKeys();
    }

    /**
     * The API-key and password-recovery/confirmation flows sign a JWT, which
     * needs a real key pair - normally created on first visit to /cup/system,
     * here generated once so tests don't depend on that page having run
     */
    private static function ensureSecretKeys(): void
    {
        $private = VAR_DIR . '/private.secret.key';
        $public = VAR_DIR . '/public.secret.key';

        if (file_exists($private) && file_exists($public)) {
            return;
        }

        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export_to_file($key, $private);
        file_put_contents($public, openssl_pkey_get_details($key)['key']);
    }

    public function setUp(): void
    {
        /*
         * for each test, we will use an empty database
         * delete the scheme and create it again
         */

        $phinxApp = new PhinxApplication();
        $phinxApp->setAutoExit(false);
        $phinxApp->setCatchExceptions(false);

        foreach (['rollback -t 0 -f', 'migrate'] as $command) {
            /*
             * phinx stays quiet while everything is fine,
             * but its output is needed to explain a failure
             */
            $output = new BufferedOutput();

            try {
                $code = $phinxApp->run(new StringInput($command), $output);
            } catch (\Throwable $e) {
                throw new \RuntimeException("phinx '{$command}' failed: {$e->getMessage()}\n{$output->fetch()}", 0, $e);
            }

            if ($code !== 0) {
                throw new \RuntimeException("phinx '{$command}' failed with exit code {$code}\n{$output->fetch()}");
            }
        }
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

    /**
     * Issues a bearer token the same way the API key admin screen does -
     * full access unless a narrower scope is asked for
     */
    protected function createApiKeyToken(array $scopes = [], bool $fullAccess = true): string
    {
        /** @var \App\Domain\Service\ApiKey\ApiKeyService $service */
        $service = $this->getService(\App\Domain\Service\ApiKey\ApiKeyService::class);

        $apiKey = $service->create([
            'title' => 'test key',
            'scopes' => $scopes,
            'is_full_access' => $fullAccess,
        ]);

        return $service->issueToken($apiKey);
    }

    protected function createRequest(): \GuzzleHttp\Client
    {
        static $client;

        if (!$client) {
            $client = new \GuzzleHttp\Client([
                'base_uri' => 'http://127.0.0.1:80',
                'http_errors' => false,
                // 'debug' => true,
            ]);
        }

        return $client;
    }
}

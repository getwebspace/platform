<?php declare(strict_types=1);

namespace tests;

use DI\ContainerBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class SettingsTest extends \PHPUnit\Framework\TestCase
{
    private array $env;

    private array $ini;

    public function setUp(): void
    {
        $this->env = $_ENV;
        $this->ini = [
            'display_errors' => ini_get('display_errors'),
            'display_startup_errors' => ini_get('display_startup_errors'),
            'html_errors' => ini_get('html_errors'),
            'log_errors' => ini_get('log_errors'),
        ];
        $this->ini['error_reporting'] = error_reporting();
    }

    public function tearDown(): void
    {
        $_ENV = $this->env;

        error_reporting($this->ini['error_reporting']);
        unset($this->ini['error_reporting']);

        foreach ($this->ini as $key => $value) {
            ini_set($key, $value);
        }
    }

    /**
     * Without DEBUG, warnings and deprecations must never be printed into the response
     */
    #[DataProvider('debugProvider')]
    public function testDisplayErrors(string $debug, string $expected): void
    {
        $_ENV['DEBUG'] = $debug;

        $settings = require SRC_DIR . '/settings.php';
        $settings(new ContainerBuilder());

        $this->assertEquals($expected, ini_get('display_errors'));
    }

    public static function debugProvider(): array
    {
        return [
            'production' => ['0', '0'],
            'debug' => ['1', '1'],
        ];
    }
}

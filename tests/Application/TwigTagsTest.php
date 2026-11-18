<?php declare(strict_types=1);

namespace tests\Application;

use App\Application\i18n;
use tests\TestCase;

/**
 * The {% locale %} and {% htmlcompress %} tags
 *
 * @internal
 *
 * #[CoversNothing]
 */
class TwigTagsTest extends TestCase
{
    private string $language;

    public function setUp(): void
    {
        parent::setUp();

        $this->language = i18n::$localeCode;
    }

    public function tearDown(): void
    {
        i18n::$localeCode = $this->language;

        parent::tearDown();
    }

    private function render(string $template, array $data = []): string
    {
        return trim($this->getService('view')->fetchFromString($template, $data));
    }

    public function testTheLocaleTagKeepsTheTextWhenThereIsNoTranslation(): void
    {
        $this->assertSame('Hello there', $this->render('{% locale %}Hello there{% endlocale %}'));
    }

    public function testTheLocaleTagLeavesMarkupAlone(): void
    {
        $this->assertSame('<b>Bold</b> text', $this->render('{% locale %}<b>Bold</b> text{% endlocale %}'));
    }

    public function testTheLocaleTagCanHoldPlaceholders(): void
    {
        $this->assertSame('Dear Ann, welcome', $this->render('{% locale %}Dear {{ name }}, welcome{% endlocale %}', ['name' => 'Ann']));
    }

    public function testThePluralFormFollowsTheCount(): void
    {
        $template = '{% locale %}one item{% plural n %}{{ n }} items{% endlocale %}';

        $this->assertSame('one item', $this->render($template, ['n' => 1]));
        $this->assertSame('5 items', $this->render($template, ['n' => 5]));
    }

    public function testTranslatorsCanBeLeftANote(): void
    {
        $this->assertSame('Save', $this->render('{% locale %}Save{% notes %}the button of the form{% endlocale %}'));
    }

    public function testOnlySimpleVariablesAreAllowedInsideTheTag(): void
    {
        $this->expectException(\Twig\Error\SyntaxError::class);

        $this->render('{% locale %}Hello {{ user.name|upper }}{% endlocale %}', ['user' => ['name' => 'x']]);
    }

    public function testTheCompressTagStillRendersTemplateLogic(): void
    {
        $html = $this->render('{% htmlcompress %}<p>{{ a }}</p>  <p>{% if b %}yes{% endif %}</p>{% endhtmlcompress %}', ['a' => 'x', 'b' => true]);

        $this->assertStringContainsString('<p>x</p>', $html);
        $this->assertStringContainsString('<p>yes</p>', $html);
    }

    public function testHtmlIsMinifiedOnlyOutsideTheDebugMode(): void
    {
        $extension = new \App\Application\TwigExtension($this->getService(\Psr\Container\ContainerInterface::class));
        $source = "<ul>\n    <li>one</li>\n    <li>two</li>\n</ul>";
        $loader = new \Twig\Loader\ArrayLoader();

        $debug = new \Twig\Environment($loader, ['debug' => true]);
        $production = new \Twig\Environment($loader, ['debug' => false]);

        $this->assertFalse($extension->isCompressionActive($debug));
        $this->assertSame($source, $extension->compress($debug, $source), 'left as written for the developer');

        $this->assertTrue($extension->isCompressionActive($production));
        $compressed = $extension->compress($production, $source);
        $this->assertStringContainsString('<li>one</li>', $compressed);
        $this->assertStringNotContainsString("\n    ", $compressed);
        $this->assertLessThan(strlen($source), strlen($compressed));
    }
}

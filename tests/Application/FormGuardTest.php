<?php declare(strict_types=1);

namespace tests\Application;

use App\Application\FormGuard;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class FormGuardTest extends TestCase
{
    private string $secret;

    private FormGuard $guard;

    protected function setUp(): void
    {
        $this->secret = sys_get_temp_dir() . '/form-guard-' . bin2hex(random_bytes(4)) . '.secret';
        $this->guard = new FormGuard($this->secret);
    }

    protected function tearDown(): void
    {
        @unlink($this->secret);
    }

    public function testSpamFromRealLife(): void
    {
        $verdict = $this->guard->inspect([
            'name' => '4k USD is available in your balance. GRAB => https://www.google.com/share.google?q=C4S2dACcIVWZY4S7S',
            'phone' => '592617199607',
            'email' => '8whlgwvg8rqsx0@fldubai.com',
            'comment' => '6rgctv',
        ]);

        $this->assertTrue($this->guard->isSpam($verdict));
    }

    public function testSpamWithValidTokenStillCaught(): void
    {
        $verdict = $this->guard->inspect([
            FormGuard::FIELD_TOKEN => $this->guard->token(time() - 10),
            'name' => 'GRAB => https://spam.example/x',
            'email' => 'x@fldubai.com',
        ]);

        $this->assertTrue($this->guard->isSpam($verdict));
    }

    public function testCustomerWithTokenPasses(): void
    {
        $verdict = $this->guard->inspect([
            FormGuard::FIELD_TOKEN => $this->guard->token(time() - 20),
            FormGuard::FIELD_TRAP => '',
            'name' => 'Иван Петров',
            'phone' => '+7 (999) 123-45-67',
            'email' => 'ivan@gmail.com',
            'comment' => "Здравствуйте!\nПосмотрите мой сайт https://ivan.example",
        ]);

        $this->assertFalse($this->guard->isSpam($verdict), implode(', ', $verdict['reasons']));
    }

    public function testFormFromAnotherSiteWithoutTokenPasses(): void
    {
        $verdict = $this->guard->inspect(['name' => 'Анна', 'phone' => '+79990000000']);

        $this->assertFalse($this->guard->isSpam($verdict));
    }

    public function testHoneypot(): void
    {
        $verdict = $this->guard->inspect([
            FormGuard::FIELD_TOKEN => $this->guard->token(time() - 20),
            FormGuard::FIELD_TRAP => 'http://x',
            'name' => 'Bot',
        ]);

        $this->assertTrue($this->guard->isSpam($verdict));
    }

    public function testTooFast(): void
    {
        $verdict = $this->guard->inspect([FormGuard::FIELD_TOKEN => $this->guard->token(), 'name' => 'Bot']);

        $this->assertTrue($this->guard->isSpam($verdict));
        $this->assertContains('too fast', $verdict['reasons']);
    }

    public function testForgedToken(): void
    {
        $this->assertNull($this->guard->tokenAge(time() - 20 . '.' . str_repeat('a', 64)));
        $this->assertNull($this->guard->tokenAge('garbage'));
        $this->assertNull($this->guard->tokenAge($this->guard->token(time() + 3600)));
        $this->assertSame(30, $this->guard->tokenAge($this->guard->token(time() - 30)));
    }

    public function testRateLimit(): void
    {
        $data = [FormGuard::FIELD_TOKEN => $this->guard->token(time() - 20), 'name' => 'Иван'];

        $this->assertFalse($this->guard->isSpam($this->guard->inspect($data, 9, 10)));
        $this->assertTrue($this->guard->isSpam($this->guard->inspect($data, 10, 10)));
    }

    public function testCleanRemovesServiceFields(): void
    {
        $this->assertSame(['name' => 'a'], $this->guard->clean([
            FormGuard::FIELD_TOKEN => 'x',
            FormGuard::FIELD_TRAP => '',
            'name' => 'a',
        ]));
    }
}

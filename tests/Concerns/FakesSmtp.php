<?php declare(strict_types=1);

namespace tests\Concerns;

use App\Domain\Service\Parameter\ParameterService;

/**
 * Points the application's mail settings at a throw-away SMTP server that
 * records what it receives, so a sent letter can be looked at
 */
trait FakesSmtp
{
    /** @var resource|null */
    private $smtpProcess;

    private string $smtpLog = '';

    private int $smtpPort = 0;

    protected function startSmtp(): void
    {
        $this->smtpPort = random_int(20000, 40000);
        $this->smtpLog = sys_get_temp_dir() . '/smtp-' . uniqid() . '.log';
        @unlink($this->smtpLog . '.stop');

        $this->smtpProcess = proc_open(
            [PHP_BINARY, __DIR__ . '/../Support/smtp_server.php', (string) $this->smtpPort, $this->smtpLog],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );

        // wait until it listens
        for ($i = 0; $i < 50; ++$i) {
            $socket = @fsockopen('127.0.0.1', $this->smtpPort, $errno, $error, 0.1);
            if ($socket) {
                fclose($socket);

                break;
            }
            usleep(100000);
        }

        $this->configureMail($this->smtpPort);
    }

    private mixed $debugBefore = null;

    protected function configureMail(int $port): void
    {
        // the SMTP conversation is otherwise dumped into the test output
        $this->debugBefore ??= $_ENV['DEBUG'] ?? '';
        $_ENV['DEBUG'] = 0;

        $parameters = $this->getService(ParameterService::class);

        foreach ([
            'mail_from' => 'shop@example.com',
            'mail_from_name' => 'Shop',
            'smtp_host' => '127.0.0.1',
            'smtp_port' => (string) $port,
            'smtp_login' => 'login',
            'smtp_pass' => 'secret',
        ] as $name => $value) {
            $parameters->create(['name' => $name, 'value' => $value]);
        }
    }

    protected function stopSmtp(): void
    {
        if ($this->debugBefore !== null) {
            $_ENV['DEBUG'] = $this->debugBefore;
            $this->debugBefore = null;
        }

        if ($this->smtpLog !== '') {
            touch($this->smtpLog . '.stop');
        }

        if (is_resource($this->smtpProcess)) {
            proc_close($this->smtpProcess);
            $this->smtpProcess = null;
        }

        @unlink($this->smtpLog);
        @unlink($this->smtpLog . '.stop');
    }

    /**
     * @return array<int, array{from: string, to: string[], data: string}>
     */
    protected function sentMails(): array
    {
        if (!is_file($this->smtpLog)) {
            return [];
        }

        return array_map(
            static fn (string $line): array => json_decode($line, true),
            array_filter(explode("\n", (string) file_get_contents($this->smtpLog)))
        );
    }

    /**
     * Every recipient of every letter, in the order they were handed over
     *
     * @return string[]
     */
    protected function recipients(): array
    {
        return array_merge(...array_map(static fn (array $mail): array => $mail['to'], $this->sentMails()) ?: [[]]);
    }
}

<?php declare(strict_types=1);

namespace App\Application;

/**
 * Local (no external service) protection of public forms
 *
 * Works by scoring a submission: every suspicious sign adds points, and a submission
 * that reaches THRESHOLD is saved as spam (see FormAction) instead of being mailed.
 * Nothing here is fatal on its own except the honeypot, so a form that is posted from
 * another site (no token) or a customer who pasted a link still gets through.
 *
 * The page side is `mixin/form-guard.twig`, injected automatically by HasRenderer, so
 * themes need no changes: it adds the signed token and the honeypot to every form
 * that posts to /form/{address}
 */
class FormGuard
{
    public const FIELD_TOKEN = '_fg';
    public const FIELD_TRAP = '_fgt';

    public const THRESHOLD = 3;
    public const MIN_AGE = 3;
    public const MAX_AGE = 86400;

    private const FREE_TEXT = '/(message|comment|text|body|note|msg|question|descr)/i';
    private const LINK = '~(?:https?://|www\.|=>)~iu';
    private const DISPOSABLE = [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', '10minutemail.com',
        'tempmail.com', 'temp-mail.org', 'temp-mail.io', 'yopmail.com', 'trashmail.com', 'getnada.com',
        'dispostable.com', 'maildrop.cc', 'throwawaymail.com', 'fakeinbox.com', 'mintemail.com',
        'emailondeck.com', 'mohmal.com', 'fldubai.com', 'tempail.com', 'burnermail.io',
    ];

    public function __construct(private ?string $secretFile = null) {}

    /**
     * Signed "page rendered at" stamp: `time.hmac`
     */
    public function token(?int $time = null): string
    {
        $time ??= time();

        return $time . '.' . hash_hmac('sha256', (string) $time, $this->secret());
    }

    /**
     * @param int $recent how many submissions this client has already made lately
     * @param int $limit  how many of them are normal
     *
     * @return array{score: int, reasons: string[]}
     */
    public function inspect(array $data, int $recent = 0, int $limit = 10): array
    {
        $score = 0;
        $reasons = [];
        $add = function (int $points, string $reason) use (&$score, &$reasons): void {
            $score += $points;
            $reasons[] = $reason;
        };

        // honeypot: a person never sees this field
        if (mb_trim((string) ($data[self::FIELD_TRAP] ?? '')) !== '') {
            $add(100, 'honeypot');
        }

        // token: proves the page was loaded (and some time passed) before the post
        $age = $this->tokenAge((string) ($data[self::FIELD_TOKEN] ?? ''));
        switch (true) {
            case $age === null && empty($data[self::FIELD_TOKEN]):
                $add(2, 'no token');

                break;

            case $age === null:
                $add(3, 'bad token');

                break;

            case $age < self::MIN_AGE:
                $add(3, 'too fast');

                break;

            case $age > self::MAX_AGE:
                $add(1, 'expired token');

                break;
        }

        $links = 0;
        foreach ($this->fields($data) as $key => $value) {
            $count = (int) preg_match_all(self::LINK, $value);
            $links += $count;

            // a link where a name or a phone is expected is never a customer
            if ($count > 0 && !preg_match(self::FREE_TEXT, $key) && !str_contains($value, "\n")) {
                $add(3, 'link in field ' . $key);
            }

            if (mb_stripos($key, 'mail') !== false && ($domain = $this->emailDomain($value)) !== null && $this->isDisposable($domain)) {
                $add(2, 'disposable e-mail');
            }
        }

        if ($links >= 3) {
            $add(2, 'many links');
        } elseif ($links > 0) {
            $add(1, 'link');
        }

        if ($limit > 0 && $recent >= $limit) {
            $add(3, 'too many submissions');
        }

        return ['score' => $score, 'reasons' => array_values(array_unique($reasons))];
    }

    public function isSpam(array $verdict): bool
    {
        return $verdict['score'] >= self::THRESHOLD;
    }

    /**
     * Service fields must not reach the saved data, the mail or the duplicate url
     */
    public function clean(array $data): array
    {
        unset($data[self::FIELD_TOKEN], $data[self::FIELD_TRAP]);

        return $data;
    }

    /**
     * Seconds since the token was issued, null when it is missing or forged
     */
    public function tokenAge(string $token): ?int
    {
        if (!preg_match('/^(\d{9,11})\.([0-9a-f]{64})$/', $token, $m)) {
            return null;
        }

        if (!hash_equals(hash_hmac('sha256', $m[1], $this->secret()), $m[2])) {
            return null;
        }

        $age = time() - (int) $m[1];

        return $age < 0 ? null : $age;
    }

    /**
     * @return array<string, string> flat text values, service fields skipped
     */
    private function fields(array $data): array
    {
        $result = [];
        $walk = function (mixed $value, string $key) use (&$walk, &$result): void {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, $key . '[' . $k . ']');
                }
            } elseif (is_scalar($value)) {
                $result[$key] = (string) $value;
            }
        };

        foreach ($data as $key => $value) {
            if (!in_array($key, [self::FIELD_TOKEN, self::FIELD_TRAP, 'recaptcha', 'g-recaptcha-response'], true)) {
                $walk($value, (string) $key);
            }
        }

        return $result;
    }

    private function emailDomain(string $value): ?string
    {
        $value = mb_trim($value);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return mb_strtolower(mb_substr(mb_strrchr($value, '@'), 1));
    }

    private function isDisposable(string $domain): bool
    {
        foreach (self::DISPOSABLE as $bad) {
            if ($domain === $bad || str_ends_with($domain, '.' . $bad)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Own secret, so the guard works on a fresh install where the JWT key pair
     * does not exist yet
     */
    private function secret(): string
    {
        static $cache = [];

        $file = $this->secretFile ?? VAR_DIR . '/form-guard.secret';

        if (isset($cache[$file])) {
            return $cache[$file];
        }

        $secret = is_file($file) ? mb_trim((string) file_get_contents($file)) : '';

        if (mb_strlen($secret) < 32) {
            $secret = bin2hex(random_bytes(32));
            $tmp = $file . '.' . bin2hex(random_bytes(4));
            file_put_contents($tmp, $secret);
            @chmod($tmp, 0o600);
            rename($tmp, $file);
        }

        return $cache[$file] = $secret;
    }
}

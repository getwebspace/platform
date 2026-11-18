<?php declare(strict_types=1);

namespace tests\Concerns;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\User;
use App\Domain\Service\User\UserService;
use Firebase\JWT\JWT;
use Psr\Http\Message\ResponseInterface;

/**
 * Helpers for tests that talk to the real nginx + php-fpm stack
 * as an anonymous visitor, a logged in user or an API client
 */
trait MakesAuthRequests
{
    protected const PASSWORD = 'correct horse battery';

    /**
     * Never follows redirects - the redirect itself is what most
     * of these tests are about
     */
    protected function http(string $method, string $uri, array $options = []): ResponseInterface
    {
        $options += ['allow_redirects' => false];

        return $this->createRequest()->request($method, $uri, $options);
    }

    /**
     * A readable one-liner out of an error page, for assertion messages
     */
    protected function why(ResponseInterface $response): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<style.*?<\/style>/s', '', (string) $response->getBody()))));

        return $response->getStatusCode() . ': ' . mb_substr($text, 0, 500);
    }

    protected function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function makeUser(array $data = []): User
    {
        static $counter = 0;
        ++$counter;

        return $this->getService(UserService::class)->create($data + [
            'username' => 'user' . $counter,
            'email' => 'user' . $counter . '@example.com',
            'password' => self::PASSWORD,
            'status' => UserStatus::WORK,
        ]);
    }

    /**
     * Logs in through the real endpoint, so the refresh token is bound to
     * the user agent and address the request was made from
     *
     * @return array{user: array, access_token: string, refresh_token: string}
     */
    protected function loginAs(User $user, string $password = self::PASSWORD): array
    {
        $response = $this->http('POST', '/auth/login', [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => ['identifier' => $user->username, 'password' => $password],
        ]);
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return $this->json($response);
    }

    protected function cookie(string $accessToken): array
    {
        return ['Cookie' => 'access_token=' . $accessToken];
    }

    /**
     * A token signed with the instance's own key, the way the application
     * signs its own: used to craft expired, forged or foreign-subject tokens
     */
    protected function signToken(string $sub, ?string $uuid, array $data = [], ?int $exp = null): string
    {
        $payload = ['sub' => $sub, 'uuid' => $uuid, 'data' => $data, 'iat' => time()];

        if ($exp !== null) {
            $payload['exp'] = $exp;
        }

        return JWT::encode($payload, file_get_contents(VAR_DIR . '/private.secret.key'), 'RS256');
    }

    /**
     * A well formed token that the instance never signed
     */
    protected function forgeToken(string $sub, string $uuid): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        return JWT::encode(
            ['sub' => $sub, 'uuid' => $uuid, 'data' => [], 'iat' => time(), 'exp' => time() + 600],
            $pem,
            'RS256'
        );
    }
}

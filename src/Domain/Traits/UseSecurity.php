<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Models\User;
use App\Domain\Models\UserToken;
use App\Domain\Service\User\TokenService as UserTokenService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;

trait UseSecurity
{
    private const PRIVATE_SECRET_FILE = VAR_DIR . '/private.secret.key';

    private const PUBLIC_SECRET_FILE = VAR_DIR . '/public.secret.key';

    private const MAX_COUNT_ACTIVE_SESSIONS = 5;

    private function getPrivateKey(): false|string
    {
        static $key;

        if (!$key) {
            if (file_exists(self::PRIVATE_SECRET_FILE)) {
                return file_get_contents(self::PRIVATE_SECRET_FILE);
            }
            $key = false;
        }

        return $key;
    }

    private function getPublicKey(): false|string
    {
        static $key;

        if (!$key) {
            if (file_exists(self::PUBLIC_SECRET_FILE)) {
                $key = file_get_contents(self::PUBLIC_SECRET_FILE);
            } else {
                $key = false;
            }
        }

        return $key;
    }

    public function getTokenPair(array $data = []): array
    {
        $default = [
            'user' => null,
            'user_token' => null,
            'ip' => '',
            'agent' => '',
            'comment' => '',
        ];
        $data = array_merge($default, $data);

        /** @var UserTokenService $userTokenService */
        $userTokenService = $this->container->get(UserTokenService::class);

        $access_token = $this->getAccessToken($data['user']);
        $refresh_token = $this->getRefreshToken($data['user']->uuid, $data['agent'], $data['ip']);

        if ($data['user_token'] && is_a($data['user_token'], UserToken::class)) {
            $userTokenService->update($data['user_token'], [
                'unique' => $refresh_token,
                'ip' => $this->getRequestRemoteIP(),
                'date' => 'now',
            ]);
        } else {
            // auto remove all active sessions if count of active session more than const
            $active = $userTokenService->read(['user' => $data['user']]);

            if ($active->count() >= self::MAX_COUNT_ACTIVE_SESSIONS) {
                foreach ($active as $token) {
                    $userTokenService->delete($token);
                }
            }

            $userTokenService->create([
                'user_uuid' => $data['user']->uuid,
                'unique' => $refresh_token,
                'comment' => $data['comment'],
                'ip' => $data['ip'],
                'agent' => $data['agent'],
                'date' => 'now',
            ]);
        }

        return [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
        ];
    }

    /*
     * Generate JWT
     */
    protected function getAccessToken(User $user): string
    {
        $privateKey = $this->getPrivateKey();

        if ($privateKey !== false) {
            $payload = [
                'sub' => 'user',
                'uuid' => $user->uuid,
                'data' => $user->toArray(),
                'iat' => time(),
                'exp' => time() + (\App\Domain\References\Date::MINUTE * 10),
            ];

            return JWT::encode($payload, $privateKey, 'RS256');
        }

        throw new \RuntimeException('Not exist PEM keys files');
    }

    /*
     * Generate sha1 hash
     */
    protected function getRefreshToken(string $uuid, string $ip, string $agent): string
    {
        return sha1(
            'uuid:' . $uuid . ';' .
            'ip:' . sha1($ip) . ';' .
            'agent:' . sha1($agent) . ';' .
            'microtime:' . intval(microtime(true)),
        );
    }

    /**
     * Decode JWT
     *
     * @throws ExpiredException
     * @throws SignatureInvalidException
     */
    protected function decodeJWT(string $token): array
    {
        $publicKey = $this->getPublicKey();

        if ($publicKey !== false) {
            return (array) JWT::decode($token, new Key($publicKey, 'RS256'));
        }

        throw new \RuntimeException('Not exist PEM keys files');
    }
}

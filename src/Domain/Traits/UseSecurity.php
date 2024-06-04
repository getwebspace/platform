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

    /**
     * Encode JWT
     *
     * @throws ExpiredException
     * @throws SignatureInvalidException
     */
    protected function encodeJWT(string $sub, ?string $uuid = null, array $data = []): string
    {
        $privateKey = $this->getPrivateKey();

        if ($privateKey !== false) {
            $payload = [
                'sub' => $sub,
                'uuid' => $uuid,
                'data' => $data,
                'iat' => time(),
                'exp' => time() + (\App\Domain\References\Date::MINUTE * 10),
            ];

            return JWT::encode($payload, $privateKey, 'RS256');
        }

        throw new \RuntimeException('Not exist PEM keys files');
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

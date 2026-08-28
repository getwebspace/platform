<?php declare(strict_types=1);

namespace App\Domain\Traits;

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
                $key = file_get_contents(self::PRIVATE_SECRET_FILE);
            } else {
                $key = false;
            }
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
    protected function encodeJWT(string $sub, ?string $uuid = null, array $data = [], ?int $ttl = null): string
    {
        $privateKey = $this->getPrivateKey();

        if ($privateKey !== false) {
            $payload = [
                'sub' => $sub,
                'uuid' => $uuid,
                'data' => $data,
                'iat' => time(),
            ];

            // $ttl === 0 means the token does not expire by clock - used for
            // API keys, where a revoked or deleted row invalidates it instead
            if ($ttl !== 0) {
                $payload['exp'] = time() + ($ttl ?: \App\Domain\References\Date::MINUTE * 10);
            }

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

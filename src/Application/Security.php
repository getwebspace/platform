<?php declare(strict_types=1);

namespace App\Application;

use Firebase\JWT\JWT;
use RuntimeException;

class Security
{
    public const PRIVATE_SECRET_FILE = VAR_DIR . '/private.secret.key';

    public const PUBLIC_SECRET_FILE = VAR_DIR . '/public.secret.key';

    public static function getPrivateKey(): string|false
    {
        if (file_exists(self::PRIVATE_SECRET_FILE)) {
            return file_get_contents(self::PRIVATE_SECRET_FILE);
        }

        return false;
    }

    public static function getPublicKey(): string|false
    {
        if (file_exists(self::PUBLIC_SECRET_FILE)) {
            return file_get_contents(self::PUBLIC_SECRET_FILE);
        }

        return false;
    }

    public static function genUserToken($uuid): string
    {
        if (
            ($privateKey = self::getPrivateKey()) !== false
        ) {

            $payload = [
                'sub' => 'user',
                'uuid' => $uuid,
                'iat' => time(),
                'exp' => time() + (10 * 60),
            ];

            return JWT::encode($payload, $privateKey, 'RS256');
        }

        throw new RuntimeException('Not exist PEM keys files');
    }

    public static function genAPIKeyToken($domain = '*'): string
    {
        if (
            ($privateKey = self::getPrivateKey()) !== false
        ) {

            $payload = [
                'sub' => 'domain',
                'domain' => $domain,
                'iat' => time(),
                'exp' => time() + (10 * 60),
            ];

            return JWT::encode($payload, $privateKey, 'RS256');
        }

        throw new RuntimeException('Not exist PEM keys files');
    }
}

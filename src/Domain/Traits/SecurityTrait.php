<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Entities\User;
use App\Domain\Service\User\TokenService as UserTokenService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Ramsey\Uuid\UuidInterface as Uuid;

trait SecurityTrait
{
    private const PRIVATE_SECRET_FILE = VAR_DIR . '/private.secret.key';

    private const PUBLIC_SECRET_FILE = VAR_DIR . '/public.secret.key';

    private function getPrivateKey(): string|false
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

    private function getPublicKey(): string|false
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

    public function getTokenPair(User $user, string $ip, string $agent, $comment = ''): array
    {
        /** @var UserTokenService $userTokenService */
        $userTokenService = $this->container->get(UserTokenService::class);

        $access_token = $this->getAccessToken($user);
        $refresh_token = $this->getRefreshToken($user->getUuid(), $agent, $ip);

        $userTokenService->create([
            'user' => $user,
            'unique' => $refresh_token,
            'comment' => $comment,
            'ip' => $ip,
            'agent' => $agent,
            'date' => 'now',
        ]);

        return [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
        ];
    }

    /*
     * From User/UUID/UUID-String return UUID string.
     */
    protected function getUuidString(mixed $uuid): string
    {
        switch (true) {
            case is_string($uuid) && \Ramsey\Uuid\Uuid::isValid($uuid):
            case is_object($uuid) && is_a($uuid, Uuid::class):
                $uuid = (string) $uuid;

                break;

            case is_object($uuid) && is_a($uuid, User::class):
                $uuid = (string) $uuid->getUuid();
        }

        return $uuid;
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
                'uuid' => $user->getUuid()->toString(),
                'data' => [
                    'name' => $user->getName(),
                    'username' => $user->getUsername(),
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhone(),
                    'gender' => $user->getGender(),
                    'birthdate' => $user->getBirthdate(),
                    'additional' => $user->getAdditional(),
                    'shipping' => [
                        'country' => $user->getCountry(),
                        'city' => $user->getCity(),
                        'address' => $user->getAddress(),
                        'postcode' => $user->getPostcode(),
                    ],
                    'avatar' => $user->avatar(100),
                    'external_id' => $user->getExternalId(),
                    'group' => $user->getGroup()->getUuid(),
                    'language' => $user->getLanguage(),
                ],
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
    protected function getRefreshToken(\Ramsey\Uuid\UuidInterface $uuid, string $ip, string $agent): string
    {
        return sha1(
            'uuid:' . $uuid . ';' .
            'ip:' . sha1($ip) . ';' .
            'agent:' . sha1($agent) . ';' .
            'date:' . time(),
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

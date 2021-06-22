<?php declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\AbstractOAuthProvider;
use App\Domain\Entities\User;
use App\Domain\Entities\User\Integration as UserIntegration;
use App\Domain\Service\User\Exception\IntegrationNotFoundException;

class VKOAuthProvider extends AbstractOAuthProvider
{
    public function getAuthUrl(): string
    {
        if ($this->parameter('user_auth_vk_is_enabled', 'no') === 'yes') {
            $params = [
                'client_id' => $this->parameter('user_auth_vk_app_id'),
                'redirect_uri' => $this->parameter('user_auth_vk_proxy'),
                'scope' => 'email',
                'response_type' => 'code',
                'display' => 'page',
            ];

            return 'https://oauth.vk.com/authorize?' . urldecode(http_build_query($params));
        }

        return '#';
    }

    public function getToken($data): array
    {
        if ($this->parameter('user_auth_vk_is_enabled', 'no') === 'yes') {
            $params = [
                'client_id' => $this->parameter('user_auth_vk_app_id'),
                'client_secret' => $this->parameter('user_auth_vk_app_secret'),
                'redirect_uri' => $this->parameter('user_auth_vk_proxy'),
                'code' => $data,
            ];
            $data = file_get_contents('https://oauth.vk.com/access_token?' . urldecode(http_build_query($params)));

            return $data ? json_decode($data, true) : [];
        }

        return [];
    }

    public function getInfo($data): array
    {
        if ($this->parameter('user_auth_vk_is_enabled', 'no') === 'yes') {
            $params = [
                'user_id' => $data['user_id'] ?? '',
                'access_token' => $data['access_token'] ?? '',
                'fields' => 'id,first_name,last_name,name,email,link,photo_max',
            ];
            $info = file_get_contents('https://api.vk.com/method/users.get?v=5.131&' . urldecode(http_build_query($params)));

            return $info ? json_decode($info, true)['response'][0] : [];
        }

        return [];
    }

    /**
     * @throws \App\Domain\Service\User\Exception\WrongEmailValueException
     * @throws \App\Domain\Service\User\Exception\EmailBannedException
     * @throws \App\Domain\Service\User\Exception\EmailAlreadyExistsException
     */
    public function callback(array $token, ?User $current_user = null): ?UserIntegration
    {
        if ($this->parameter('user_auth_vk_is_enabled', 'no') === 'yes' && $token) {
            $info = $this->getInfo($token);

            try {
                return $this->userIntegrationService->read([
                    'provider' => 'vk',
                    'unique' => $info['id'],
                ]);
            } catch (IntegrationNotFoundException $e) {
                if ($current_user === null) {
                    $groupUuid = $this->parameter('user_group');

                    $current_user = $this->userService->create([
                        'email' => $token['email'],
                        'password' => uniqid(),
                        'firstname' => $info['first_name'],
                        'lastname' => $info['last_name'],
                        'group' => $groupUuid ? $this->userGroupService->read(['uuid' => $groupUuid]) : null,
                    ]);
                }

                return $this->userIntegrationService->create([
                    'user' => $current_user,
                    'provider' => 'vk',
                    'unique' => $info['id'],
                ]);
            }
        }

        return null;
    }
}

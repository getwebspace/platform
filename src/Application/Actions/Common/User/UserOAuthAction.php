<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use App\Domain\Service\User\Exception\IntegrationNotFoundException;
use App\Domain\Traits\SecurityTrait;

class UserOAuthAction extends UserAction
{
    use SecurityTrait;

    protected function action(): \Slim\Psr7\Response
    {
        $provider = $this->getOAuthService()->getProvider($this->resolveArg('provider'));

        if ($this->getParams()) {
            $accessToken = $provider->getAccessTokenByRequestParameters($this->getParams());

            try {
                $integration = $this->userIntegrationService->read([
                    'provider' => $this->resolveArg('provider'),
                    'unique' => $accessToken->getUserId(),
                ]);
                $user = $integration->getUser();
            } catch (IntegrationNotFoundException $e) {
                $user = $this->request->getAttribute('user');

                if ($user === null) {
                    $identity = $provider->getIdentity($accessToken);
                    $groupUuid = $this->parameter('user_group');

                    $user = $this->userService->create([
                        'email' => $identity['email'],
                        'username' => $identity['username'] ?? '',
                        'birthdate' => $identity['bdate'] ?? '',
                        'gender' => $identity['sex'] ?? '',
                        'password' => uniqid(),
                        'firstname' => $identity['firstname'] ?? '',
                        'lastname' => $identity['lastname'] ?? '',
                        'group' => $groupUuid ? $this->userGroupService->read(['uuid' => $groupUuid]) : null,
                    ]);
                }

                $this->userIntegrationService->create([
                    'user' => $user,
                    'provider' => $this->resolveArg('provider'),
                    'unique' => $accessToken->getUserId(),
                ]);
            }

            $data = [
                'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),
            ];
            $tokens = $this->getTokenPair($user, $data['ip'], $data['agent'], 'Login via OAuth service');

            setcookie('access_token', $tokens['access_token'], time() + \App\Domain\References\Date::MONTH, '/');
            setcookie('refresh_token', $tokens['refresh_token'], time() + \App\Domain\References\Date::MONTH, '/auth');

            $this->container->get(\App\Application\PubSub::class)->publish('common:user:oauth', $user);

            return $this->respondWithRedirect('/user/profile');
        }

        return $this->respondWithRedirect($provider->makeAuthUrl());
    }
}

<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User\OAuth;

use App\Application\Actions\Common\User\UserAction;
use App\Domain\Service\User\Exception\IntegrationNotFoundException;

class CallbackAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $provider = $this->getOAuthService()->getProvider($this->resolveArg('provider'));
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
                    'password' => uniqid(),
                    'firstname' => $identity['firstname'],
                    'lastname' => $identity['lastname'],
                    'group' => $groupUuid ? $this->userGroupService->read(['uuid' => $groupUuid]) : null,
                ]);
            }

            $this->userIntegrationService->create([
                'user' => $user,
                'provider' => $this->resolveArg('provider'),
                'unique' => $accessToken->getUserId(),
            ]);
        }

        $session = $user->getSession();

        // create new session
        if ($session === null) {
            $session = $this->userSessionService->create([
                'user' => $user,
                'date' => 'now',
                'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),
            ]);
        } else {
            // update session
            $session = $this->userSessionService->update($session, [
                'date' => 'now',
                'agent' => $this->getServerParam('HTTP_USER_AGENT'),
                'ip' => $this->getRequestRemoteIP(),
            ]);
        }

        setcookie('uuid', $user->getUuid()->toString(), time() + \App\Domain\References\Date::YEAR, '/');
        setcookie('session', $session->getHash(), time() + \App\Domain\References\Date::YEAR, '/');

        $this->container->get(\App\Application\PubSub::class)->publish('common:user:oauth', $user);

        return $this->respondWithRedirect('/user/profile');
    }
}

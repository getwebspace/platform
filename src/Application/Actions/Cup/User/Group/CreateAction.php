<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Group;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\MissingTitleValueException;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongTitleValueException;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->isPost()) {
            try {
                $userGroup = $this->userGroupService->create([
                    'title' => $this->getParam('title'),
                    'description' => $this->getParam('description'),
                    'access' => $this->getParam('access', []),
                ]);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:user:group:create', $userGroup);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        return $this->respondWithRedirect('/cup/user/group');

                    default:
                        return $this->respondWithRedirect('/cup/user/group/' . $userGroup->uuid . '/edit');
                }
            } catch (MissingTitleValueException|TitleAlreadyExistsException|WrongTitleValueException $e) {
                $this->addError('title', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/user/group/form.twig', [
            'routes' => [
                'all' => $this->getRoutes()->all(),
            ],
        ]);
    }
}

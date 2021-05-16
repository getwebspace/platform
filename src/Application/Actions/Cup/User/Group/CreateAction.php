<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\User\Group;

use App\Application\Actions\Cup\User\UserAction;
use App\Domain\Service\User\Exception\MissingTitleValueException;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;

class CreateAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $userGroup = $this->userGroupService->create([
                    'title' => $this->request->getParam('title'),
                    'description' => $this->request->getParam('description'),
                    'access' => $this->request->getParam('access', []),
                ]);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/user/group');

                    default:
                        return $this->response->withRedirect('/cup/user/group/' . $userGroup->getUuid() . '/edit');
                }
            } catch (MissingTitleValueException | TitleAlreadyExistsException $e) {
                $this->addError('title', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/user/group/form.twig', [
            'routes' => [
                'all' => $this->getRoutes()->all(),
                'default' => $this->getRoutes()->filter(fn ($el) => str_start_with($el, ['api:', 'common:']))->all(),
            ],
        ]);
    }
}

<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Application\Middlewares\AccessCheckerMiddleware;
use App\Domain\AbstractAction;
use App\Domain\Entities\User;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\UserService;
use Illuminate\Support\Collection;

class SystemPageAction extends AbstractAction
{
    protected static string $lock_file = VAR_DIR . '/installer.lock';

    protected function action(): \Slim\Http\Response
    {
        /** @var User $user */
        $user = $this->request->getAttribute('user', false);
        $allow = false;

        // first install
        if (!file_exists(self::$lock_file)) {
            $allow = true;
        } else {
            // need auth user, redirect
            if ($user === false) {
                return $this->response->withRedirect('/cup/login?redirect=/cup/system');
            }
        }

        // already exist user
        if (!$allow) {
            if ($user->getGroup() !== null && in_array('cup:main', $user->getGroup()->getAccess(), true)) {
                $allow = true;
            }
        }

        // ok, allow access to page
        if ($allow) {
            if ($this->request->isPost()) {
                // database
                if ($databaseAction = $this->request->getParam('database', '')) {
                    $schema = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);

                    switch ($databaseAction) {
                        case 'create':
                            $schema->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

                            break;

                        case 'update':
                            $schema->updateSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

                            break;

                        case 'delete':
                            $schema->dropSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

                            break;
                    }
                }

                // user
                if ($userData = $this->request->getParam('user', [])) {
                    $userGroupService = UserGroupService::getWithContainer($this->container);
                    $userService = UserService::getWithContainer($this->container);

                    // create or read group
                    try {
                        $userData['group'] = $userGroupService->create([
                            'title' => 'Администраторы',
                            'access' => $this->getRoutes()->values()->all(),
                        ]);
                    } catch (TitleAlreadyExistsException $e) {
                        $userData['group'] = $userGroupService->read([
                            'title' => 'Администраторы',
                        ]);
                    }

                    // create or update database
                    if ($user !== false) {
                        $userService->update($user, $userData);
                    } else {
                        $userService->create($userData);
                    }
                }

                // write lock file
                file_put_contents(self::$lock_file, time());

                return $this->response->withRedirect('/cup/system');
            }

            return $this->respondWithTemplate('cup/system/index.twig');
        }

        return $this->response->withRedirect('/cup/login?redirect=/cup/system');
    }
}

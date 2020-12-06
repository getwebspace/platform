<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Entities\User;
use App\Domain\Service\User\UserService;

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
            if ($user !== false && $user->getLevel() === \App\Domain\Types\UserLevelType::LEVEL_ADMIN) {
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
                    $userService = UserService::getWithContainer($this->container);
                    $userData['level'] = \App\Domain\Types\UserLevelType::LEVEL_ADMIN;

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

            return $this->respondWithTemplate('cup/system/index.twig', [
                'properties' => [
                    'version' => ($_ENV['COMMIT_BRANCH'] ?? 'other') . ' (' . ($_ENV['COMMIT_SHA'] ?? 'specific') . ')',
                    'os' => @implode(' ', [php_uname('s'), php_uname('r'), php_uname('m')]),
                    'php' => PHP_VERSION,
                    'memory_limit' => ini_get('memory_limit'),
                    'disable_functions' => ini_get('disable_functions'),
                    'disable_classes' => ini_get('disable_classes'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'max_file_uploads' => ini_get('max_file_uploads'),
                ],
            ]);
        }

        return $this->response->withRedirect('/cup/login?redirect=/cup/system');
    }
}

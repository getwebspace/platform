<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Entities\User;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\GroupService as UserGroupService;
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

                return $this->response->withRedirect('/cup');
            }

            return $this->respondWithTemplate('cup/system/index.twig', [
                'step' => $this->args['step'] ?? '1',
                'health' => $this->self_check(),
            ]);
        }

        return $this->response->withRedirect('/cup/login?redirect=/cup/system');
    }

    protected function self_check(): array
    {
        $fileAccess = [
            BASE_DIR => 0755,
            BIN_DIR => 0755,
            CONFIG_DIR => 0755,
            PLUGIN_DIR => 0777,
            PUBLIC_DIR => 0755,
            RESOURCE_DIR => 0755,
            UPLOAD_DIR => 0776,
            SRC_DIR => 0755,
            SRC_LOCALE_DIR => 0755,
            VIEW_DIR => 0755,
            VIEW_ERROR_DIR => 0755,
            THEME_DIR => 0776,
            VAR_DIR => 0777,
            CACHE_DIR => 0777,
            LOG_DIR => 0777,
            VENDOR_DIR => 0755,
        ];

        foreach ($fileAccess as $folder => $value) {
            if (realpath($folder)) {
                if ($value === (@fileperms($folder) & 0777) || @chmod($folder, $value)) {
                    $fileAccess[$folder] = true;
                } else {
                    $fileAccess[$folder] = decoct($value);
                }
            }
        }

        return [
            'php' => version_compare(phpversion(), '7.4', '>='),
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                // 'pdo_mysql' => extension_loaded('pdo_mysql'),
                // 'pdo_pgsql' => extension_loaded('pdo_pgsql'),
                // 'sqlite3' => extension_loaded('sqlite3'),
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring'),
                'gd' => extension_loaded('gd'),
                'imagick' => extension_loaded('imagick'),
                'xml' => extension_loaded('xml'),
                'yaml' => extension_loaded('yaml'),
                'zip' => extension_loaded('zip'),
            ],
            'folders' => $fileAccess,
        ];
    }
}

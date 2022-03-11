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

    protected function action(): \Slim\Psr7\Response
    {
        $access = false;

        /** @var false|User $user */
        $user = $this->request->getAttribute('user', false);

        // first install
        if (!file_exists(self::$lock_file)) {
            $access = true;
        }

        // exist user
        if (!$access) {
            if ($user->getGroup() !== null && in_array('cup:main', $user->getGroup()->getAccess(), true)) {
                $access = true;
            }
        }

        // ok, allow access to page
        if ($access) {
            if ($this->isPost()) {
                $this->setup_database();
                $this->setup_user();
                $this->setup_data();

                // write lock file
                file_put_contents(self::$lock_file, time());

                return $this->respondWithRedirect('/cup');
            }

            return $this->respondWithTemplate('cup/system/index.twig', [
                'step' => $this->args['step'] ?? '1',
                'health' => $this->self_check(),
            ]);
        }

        return $this->respondWithRedirect('/cup/login?redirect=/cup/system');
    }

    protected function setup_database(): void
    {
        if ($databaseAction = $this->getParam('database', '')) {
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
    }

    protected function setup_user(): void
    {
        if ($userData = $this->getParam('user', [])) {
            $userGroupService = $this->container->get(UserGroupService::class);
            $userService = $this->container->get(UserService::class);

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

            // create user with administrator group
            $userService->create($userData);
        }
    }

    protected function setup_data(): void
    {
    }

    protected function self_check(): array
    {
        $fileAccess = [
            BASE_DIR => 0o755,
            BIN_DIR => 0o755,
            CONFIG_DIR => 0o755,
            PLUGIN_DIR => 0o777,
            PUBLIC_DIR => 0o755,
            RESOURCE_DIR => 0o755,
            UPLOAD_DIR => 0o776,
            SRC_DIR => 0o755,
            SRC_LOCALE_DIR => 0o755,
            VIEW_DIR => 0o755,
            VIEW_ERROR_DIR => 0o755,
            THEME_DIR => 0o777,
            VAR_DIR => 0o777,
            CACHE_DIR => 0o777,
            LOG_DIR => 0o777,
            VENDOR_DIR => 0o755,
        ];

        foreach ($fileAccess as $folder => $value) {
            if (realpath($folder)) {
                if ($value === (@fileperms($folder) & 0o777) || @chmod($folder, $value)) {
                    $fileAccess[$folder] = true;
                } else {
                    $fileAccess[$folder] = decoct($value);
                }
            }
        }

        return [
            'php' => version_compare(phpversion(), '8.1', '>='),
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

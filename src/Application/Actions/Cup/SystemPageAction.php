<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Models\User;
use App\Domain\Service\User\Exception\EmailAlreadyExistsException;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\MissingUniqueValueException;
use App\Domain\Service\User\Exception\TitleAlreadyExistsException;
use App\Domain\Service\User\Exception\UsernameAlreadyExistsException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongUsernameValueException;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\UserService;

class SystemPageAction extends AbstractAction
{
    private const PRIVATE_SECRET_FILE = VAR_DIR . '/private.secret.key';

    private const PUBLIC_SECRET_FILE = VAR_DIR . '/public.secret.key';

    protected function action(): \Slim\Psr7\Response
    {
        $access = false;

        if (!file_exists(LOCK_FILE)) {
            $access = true; // first install
        }

        // ok, allow access to page
        if ($access) {
            if ($this->isPost()) {
                $this->gen_openssl();
                $this->setup_user();

                if (!$this->hasError()) {
                    file_put_contents(LOCK_FILE, time()); // write lock file

                    return $this->respondWithRedirect('/cup');
                }
            }

            return $this->respondWithTemplate('cup/system/index.twig', [
                'step' => $this->args['step'] ?? '1',
                'health' => $this->self_check(),
            ]);
        }

        return $this->respondWithRedirect('/cup');
    }

    protected function gen_openssl(): void
    {
        if (!file_exists(self::PRIVATE_SECRET_FILE) || !file_exists(self::PUBLIC_SECRET_FILE)) {
            // generate private key file
            $privateKeyResource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            openssl_pkey_export_to_file($privateKeyResource, self::PRIVATE_SECRET_FILE);

            // generate public key for private key
            $privateKeyDetailsArray = openssl_pkey_get_details($privateKeyResource);

            file_put_contents(self::PUBLIC_SECRET_FILE, $privateKeyDetailsArray['key']);
        }
    }

    protected function setup_user(): void
    {
        $user = $this->request->getAttribute('user', false);

        if (!$user) {
            $userData = $this->getParam('user', []);
            $userGroupService = $this->container->get(UserGroupService::class);
            $userService = $this->container->get(UserService::class);

            // create or read group
            try {
                $group = $userGroupService->create([
                    'title' => 'Administrators',
                    'access' => $this->getRoutes()->values()->all(),
                ]);
            } catch (TitleAlreadyExistsException $e) {
                $group = $userGroupService->read(['title' => 'Administrators']);
            } finally {
                $userData['group_uuid'] = $group->uuid;
            }

            try {
                // create user with administrator group
                $userService->create($userData);
            } catch (MissingUniqueValueException $e) {
                $this->addError('user[email]', $e->getMessage());
                $this->addError('user[username]', $e->getMessage());
            } catch (UsernameAlreadyExistsException|WrongUsernameValueException $e) {
                $this->addError('user[username]', $e->getMessage());
            } catch (EmailAlreadyExistsException|EmailBannedException|WrongEmailValueException $e) {
                $this->addError('user[email]', $e->getMessage());
            }
        }
    }

    protected function self_check(): array
    {
        $fileAccess = [
            BASE_DIR => 0o755,
            BIN_DIR => 0o777,
            CONFIG_DIR => 0o755,
            PLUGIN_DIR => 0o777,
            PUBLIC_DIR => 0o755,
            RESOURCE_DIR => 0o777,
            UPLOAD_DIR => 0o777,
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

        $extensions = collect([
            'curl' => extension_loaded('curl'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd'),
            'intl' => extension_loaded('intl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'opcache' => extension_loaded('Zend OPcache'),
            'openssl' => extension_loaded('openssl'),
            'pdo' => extension_loaded('pdo'),
            'sqlite3' => extension_loaded('sqlite3'),
            'xml' => extension_loaded('xml'),
            'zip' => extension_loaded('zip'),
        ]);

        foreach (explode(' ', $_ENV['EXTRA_EXTENSIONS']) as $ext_name) {
            $extensions[$ext_name] = extension_loaded($ext_name);
        }

        return [
            'php' => version_compare(phpversion(), '8.3', '>='),
            'extensions' => $extensions->sortKeys(),
            'folders' => $fileAccess,
        ];
    }
}

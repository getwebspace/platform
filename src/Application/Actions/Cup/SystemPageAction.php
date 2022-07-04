<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Entities\User;
use App\Domain\Service\Catalog\MeasureService as CatalogMeasureService;
use App\Domain\Service\Catalog\OrderStatusService as CatalogOrderStatusService;
use App\Domain\Service\Parameter\ParameterService;
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
                    'title' => __('Администраторы'),
                    'access' => $this->getRoutes()->values()->all(),
                ]);
            } catch (TitleAlreadyExistsException $e) {
                $userData['group'] = $userGroupService->read([
                    'title' => __('Администраторы'),
                ]);
            }

            // create user with administrator group
            $userService->create($userData);
        }
    }

    protected function setup_data(): void
    {
        if ('system_default' === $this->getParam('fill', 'no')) {
            $order_status = [
                ['title' => __('Новый'), 'order' => 1],
                ['title' => __('В обработке'), 'order' => 2],
                ['title' => __('Отправлен'), 'order' => 3],
                ['title' => __('Доставлен'), 'order' => 4],
                ['title' => __('Отменён'), 'order' => 5],
            ];
            foreach ($order_status as $el) {
                $this->container->get(CatalogOrderStatusService::class)->create($el);
            }

            $product_measure = [
                ['title' => __('Килограмм'), 'contraction' => __('кг'), 'value' => 1000],
                ['title' => __('Грамм'), 'contraction' => __('г'), 'value' => 1],
                ['title' => __('Литр'), 'contraction' => __('л'), 'value' => 1000],
                ['title' => __('Миллилитр'), 'contraction' => __('мл'), 'value' => 1],
            ];
            foreach ($product_measure as $el) {
                $this->container->get(CatalogMeasureService::class)->create($el);
            }

            $this->container->get(ParameterService::class)->create('catalog_invoice', INVOICE_TEMPLATE);
        }
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

const INVOICE_TEMPLATE = <<<'EOD'
    <div class="m-5">
        <div class="row">
            <div class="col-12 text-center">
                <h3 class="font-weight-bold">{{ 'Инвойс'|locale }}</h3>
                {#<img src="/images/logo.png" style="width: 100%; max-width: 300px" />#}
            </div>
            <div class="col-6">
                {{ parameter('common_title') }}<br />
                {{ 'Заказ'|locale }}: <b>{{ order.external_id ?: order.serial }}</b><br />
                {{ 'Дата'|locale }}: <b>{{ order.date|df('d.m.Y H:i') }}</b><br />
                {{ 'Доставка'|locale }}: <b>{{ order.shipping|df('d.m.Y H:i') }}</b>
            </div>
            <div class="col-6 text-right">
                {{ qr_code(base_url() ~ '/cart/done/' ~ order.uuid, 100) }}
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-6">
                {{ order.user and order.user.getName() == order.delivery.client ? order.user.getName() : order.delivery.client }}<br />
                {{ order.user and order.user.phone == order.phone ? order.user.phone : (order.phone ? order.phone : '-') }}<br />
                {{ order.user and order.user.email == order.email ? order.user.email : (order.email ? order.email : '-') }}
            </div>
            <div class="col-6 text-right">
                {{ order.user and order.user.address == order.delivery.address ? order.user.address : order.delivery.address }}<br />
                {{ order.comment }}
            </div>
        </div>

        <div class="row py-1 mt-3 bg-grey2">
            <div class="col-8 col-md-6 text-nowrap font-weight-bold">{{ 'Позиция'|locale }}</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">{{ 'Цена'|locale }}</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">{{ 'Количество'|locale }}</div>
            <div class="col-4 col-md-2 text-right text-nowrap font-weight-bold">{{ 'Сумма'|locale }}</div>
        </div>

        {% set total = 0 %}
        {% for item in order.products %}
            <div class="row py-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
                <div class="col-8 col-md-6 overflow-hidden text-nowrap">{{ item.title }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.price|number_format(2, '.', ' ') }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.count }}</div>
                <div class="col-4 col-md-2 text-right text-nowrap">{{ (item.price * item.count)|number_format(2, '.', ' ') }}</div>
            </div>
        {% endfor %}

        <div class="row py-1">
            <div class="col-12 text-right text-nowrap font-weight-bold border-top">{{ 'Общая сумма'|locale }}: {{ order.getTotalPrice()|number_format(2, '.', ' ') }}</div>
        </div>
    </div>
    EOD;

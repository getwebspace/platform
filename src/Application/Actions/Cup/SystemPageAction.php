<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\Entities\User;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Reference\ReferenceService;
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
    private const LOCK_FILE = VAR_DIR . '/installer.lock';

    private const PRIVATE_SECRET_FILE = VAR_DIR . '/private.secret.key';

    private const PUBLIC_SECRET_FILE = VAR_DIR . '/public.secret.key';

    protected function action(): \Slim\Psr7\Response
    {
        $access = false;

        // first install
        if (!file_exists(self::LOCK_FILE)) {
            $access = true;
        }

        /** @var false|User $user */
        $user = $this->request->getAttribute('user', false);

        // exist user
        if (!$access && $user) {
            if ($user->getGroup() !== null && in_array('cup:main', $user->getGroup()->getAccess(), true)) {
                $access = true;
            }
        }

        // ok, allow access to page
        if ($access) {
            if ($this->isPost()) {
                $this->gen_openssl();
                $this->setup_database();
                $this->setup_data();
                $this->setup_user();

                if (!$this->hasError()) {
                    // write lock file
                    file_put_contents(self::LOCK_FILE, time());

                    return $this->respondWithRedirect('/cup');
                }
            }

            return $this->respondWithTemplate('cup/system/index.twig', [
                'step' => $this->args['step'] ?? '1',
                'health' => $this->self_check(),
            ]);
        }

        return $this->respondWithRedirect('/cup/login?redirect=/cup/system');
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

    protected function setup_database(): void
    {
        if ($databaseData = $this->getParam('database', [])) {
            $schema = new \Doctrine\ORM\Tools\SchemaTool($this->entityManager);

            switch ($databaseData['action']) {
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

    protected function setup_data(): void
    {
        if ($databaseData = $this->getParam('database', [])) {
            if ($databaseData['fill'] === 'default' && $databaseData['action'] !== 'delete') {
                $referenceService = $this->container->get(ReferenceService::class);

                $order_status = [
                    ['title' => __('New'),              'order' => 1],
                    ['title' => __('In processing'),    'order' => 2],
                    ['title' => __('Sent'),             'order' => 3],
                    ['title' => __('Delivered'),        'order' => 4],
                    ['title' => __('Canceled'),         'order' => 5],
                ];
                foreach ($order_status as $el) {
                    $referenceService->create(array_merge($el, [
                        'type' => \App\Domain\Types\ReferenceTypeType::TYPE_ORDER_STATUS,
                    ]));
                }

                $stock_status = [
                    ['title' => __('Pre-Order'),        'order' => 1],
                    ['title' => __('Out Of Stock'),     'order' => 2],
                    ['title' => __('In Stock'),         'order' => 3],
                    ['title' => __('2-3 Days'),         'order' => 4],
                ];
                foreach ($stock_status as $el) {
                    $referenceService->create(array_merge($el, [
                        'type' => \App\Domain\Types\ReferenceTypeType::TYPE_STOCK_STATUS,
                    ]));
                }

                $weight_class = [
                    ['title' => __('Kilogram'),         'value' => ['unit' => __('kg'), 'value' => 1000]],
                    ['title' => __('Gram'),             'value' => ['unit' => __('g'),  'value' => 1]],
                    ['title' => __('Ounce'),            'value' => ['unit' => __('oz'), 'value' => 35.2739]],
                    ['title' => __('Pound'),            'value' => ['unit' => __('lb'), 'value' => 2.2046]],
                    ['title' => __('Liter'),            'value' => ['unit' => __('l'),  'value' => 1000]],
                    ['title' => __('Milliliter'),       'value' => ['unit' => __('ml'), 'value' => 1]],
                ];
                foreach ($weight_class as $el) {
                    $referenceService->create(array_merge($el, [
                        'type' => \App\Domain\Types\ReferenceTypeType::TYPE_WEIGHT_CLASS,
                    ]));
                }

                $length_class = [
                    ['title' => __('Inch'),             'value' => ['unit' => __('kg'), 'value' => 0.3937]],
                    ['title' => __('Centimeter'),       'value' => ['unit' => __('cm'), 'value' => 1.0000]],
                    ['title' => __('Millimeter'),       'value' => ['unit' => __('mm'), 'value' => 10.0000]],
                ];
                foreach ($length_class as $el) {
                    $referenceService->create(array_merge($el, [
                        'type' => \App\Domain\Types\ReferenceTypeType::TYPE_LENGTH_CLASS,
                    ]));
                }

                $tax_rates = [
                    ['title' => __('VAT 20'),           'value' => ['rate' => 20.0000]],
                    ['title' => __('VAT 10'),           'value' => ['rate' => 10.0000]],
                ];
                foreach ($tax_rates as $el) {
                    $referenceService->create(array_merge($el, [
                        'type' => \App\Domain\Types\ReferenceTypeType::TYPE_TAX_RATE,
                    ]));
                }

                $social_networks = [
                    ['title' => __('Facebook'),         'value' => ['url' => '#']],
                    ['title' => __('Instagram'),        'value' => ['url' => '#']],
                    ['title' => __('VK'),               'value' => ['url' => '#']],
                    ['title' => __('Telegram'),         'value' => ['url' => '#']],
                    ['title' => __('WhatsApp'),         'value' => ['url' => '#']],
                ];
                foreach ($social_networks as $el) {
                    $referenceService->create(array_merge($el, [
                        'type' => \App\Domain\Types\ReferenceTypeType::TYPE_SOCIAL_NETWORKS,
                    ]));
                }

                $this->container->get(ParameterService::class)->create([
                    'key' => 'catalog_invoice',
                    'value' => INVOICE_TEMPLATE,
                ]);
                $this->container->get(ParameterService::class)->create([
                    'key' => 'catalog_shipping',
                    'value' => SHIPPING_TEMPLATE,
                ]);
            }
        }
    }

    protected function setup_user(): void
    {
        if ($databaseData = $this->getParam('database', [])) {
            $userData = $this->getParam('user', []);

            if ($userData && $databaseData['action'] !== 'delete') {
                $userGroupService = $this->container->get(UserGroupService::class);
                $userService = $this->container->get(UserService::class);

                // create or read group
                try {
                    $userData['group'] = $userGroupService->create([
                        'title' => __('Administrators'),
                        'access' => $this->getRoutes()->values()->all(),
                    ]);
                } catch (TitleAlreadyExistsException $e) {
                    $userData['group'] = $userGroupService->read([
                        'title' => __('Administrators'),
                    ]);
                }

                try {
                    // create user with administrator group
                    $userService->create($userData);
                } catch (MissingUniqueValueException $e) {
                    $this->addError('user[email]', $e->getMessage());
                    $this->addError('user[username]', $e->getMessage());
                } catch (WrongUsernameValueException|UsernameAlreadyExistsException $e) {
                    $this->addError('user[username]', $e->getMessage());
                } catch (WrongEmailValueException|EmailAlreadyExistsException|EmailBannedException $e) {
                    $this->addError('user[email]', $e->getMessage());
                }
            }
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

        $extensions = [
            'pdo' => extension_loaded('pdo'),
            'sqlite3' => extension_loaded('sqlite3'),
            'curl' => extension_loaded('curl'),
            'json' => extension_loaded('json'),
            'mbstring' => extension_loaded('mbstring'),
            'gd' => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
            'xml' => extension_loaded('xml'),
            'yaml' => extension_loaded('yaml'),
            'zip' => extension_loaded('zip'),
        ];
        $extra_extensions = [];

        foreach (explode(' ', $_ENV['EXTRA_EXTENSIONS']) as $ext_name) {
            $extra_extensions[$ext_name] = extension_loaded($ext_name);
        }

        return [
            'php' => version_compare(phpversion(), '8.2', '>='),
            'extensions' => collect($extensions)->merge($extra_extensions)->sortKeys(),
            'folders' => $fileAccess,
        ];
    }
}

const INVOICE_TEMPLATE = <<<'EOD'
    <div class="m-5">
        <div class="row">
            <div class="col-12 text-center">
                <h3 class="font-weight-bold">{{ 'Invoice'|locale }}</h3>
                {#<img src="/images/logo.png" style="width: 100%; max-width: 300px" />#}
            </div>
            <div class="col-6">
                {{ parameter('common_title') }}<br />
                {{ 'Order'|locale }}: <b>{{ order.external_id ?: order.serial }}</b><br />
                {{ 'Date'|locale }}: <b>{{ order.date|df('d.m.Y H:i') }}</b><br />
                {{ 'Shipping'|locale }}: <b>{{ order.shipping|df('d.m.Y H:i') }}</b>
            </div>
            <div class="col-6 text-right">
                {{ qr_code(base_url() ~ '/cart/done/' ~ order.uuid, 100) }}
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-6">
                {{ order.delivery.client }}<br />
                {{ order.phone ? order.phone : '-' }}<br />
                {{ order.email ? order.email : '-' }}
            </div>
            <div class="col-6 text-right">
                {{ order.delivery.address }}<br />
                {{ order.comment }}
            </div>
        </div>

        <div class="row py-1 mt-3 bg-grey2">
            <div class="col-8 col-md-6 text-nowrap font-weight-bold">{{ 'Item'|locale }}</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">{{ 'Price'|locale }}</div>
            <div class="d-none d-md-block col-md-2 text-right text-nowrap font-weight-bold">{{ 'Quantity'|locale }}</div>
            <div class="col-4 col-md-2 text-right text-nowrap font-weight-bold">{{ 'Sum'|locale }}</div>
        </div>

        {% set total = 0 %}
        {% for item in order.getProducts().where('type', 'product') %}
            <div class="row py-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
                <div class="col-8 col-md-6 overflow-hidden text-nowrap">{{ item.title }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.price|number_format(2, '.', ' ') }}</div>
                <div class="d-none d-md-block col-md-2 text-right text-nowrap">{{ item.count }}</div>
                <div class="col-4 col-md-2 text-right text-nowrap">{{ (item.price * item.count)|number_format(2, '.', ' ') }}</div>
            </div>
        {% endfor %}

        <div class="row py-1">
            <div class="col-12 text-right text-nowrap font-weight-bold border-top">{{ 'Total price'|locale }}: {{ order.getTotalPrice()|number_format(2, '.', ' ') }}</div>
        </div>
    </div>
EOD;

const SHIPPING_TEMPLATE = <<<'EOD'
    <div class="m-5">
        <div class="row">
            <div class="col-12 text-center">
                <h3 class="font-weight-bold">{{ 'Shipping note'|locale }}</h3>
                {#<img src="/images/logo.png" style="width: 100%; max-width: 300px" />#}
            </div>
            <div class="col-6">
                {{ parameter('common_title') }}<br />
                {{ 'Order'|locale }}: <b>{{ order.external_id ?: order.serial }}</b><br />
                {{ 'Date'|locale }}: <b>{{ order.date|df('d.m.Y H:i') }}</b><br />
                {{ 'Shipping'|locale }}: <b>{{ order.shipping|df('d.m.Y H:i') }}</b><br />
                {{ 'Total price'|locale }}: <b>{{ order.getTotalPrice()|number_format(2, '.', ' ') }}</b>
            </div>
            <div class="col-6 text-right">
                {{ order.delivery.client }}<br />
                {{ order.phone ? order.phone : '-' }}<br />
                {{ order.email ? order.email : '-' }}<br />
                {{ order.delivery.address }}<br />
                {{ order.comment }}
            </div>
        </div>
    
        <div class="row py-1 mt-3 bg-grey2">
            <div class="col-6 text-nowrap font-weight-bold">{{ 'Item'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Volumetric weight'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Weight'|locale }}</div>
            <div class="col-2 text-right text-nowrap font-weight-bold">{{ 'Quantity'|locale }}</div>
        </div>
    
        {% set total = 0 %}
        {% for item in order.getProducts().where('type', 'product') %}
            <div class="row py-1 {{ loop.last ?: 'border-bottom' }} {{ loop.index0 % 2 ? 'bg-grey1' }}">
                <div class="col-6 text-nowrap font-weight-bold">{{ item.title }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ catalog_product_dimensional_weight(item) }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ item.getWeightWithClass() }}</div>
                <div class="col-2 text-right text-nowrap font-weight-bold">{{ item.count }}</div>
            </div>
        {% endfor %}
    </div>
EOD;

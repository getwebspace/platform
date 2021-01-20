<?php declare(strict_types=1);

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @var \Slim\App                         $app
 * @var \Psr\Container\ContainerInterface $container
 */

// API section
$app
    ->group('/api', function (App $app): void {
        // users
        $app->group('/user', function (App $app): void {
            $app->map(['get', 'post'], '/info', \App\Application\Actions\Api\User\Info::class)
                ->setName('api:user:info');

            // users subscribers
            $app->group('/newsletter', function (App $app): void {
                $app->map(['get', 'post'], '/subscribe', \App\Application\Actions\Api\User\Subscriber\SubscribeAction::class)
                    ->setName('api:user:newsletter:subscribe');
                $app->map(['get', 'post'], '/{uuid}/unsubscribe', \App\Application\Actions\Api\User\Subscriber\UnsubscribeAction::class)
                    ->setName('api:user:newsletter:unsubscribe');
            });
        });

        // files
        $app->get('/file', \App\Application\Actions\Api\File\File::class)
            ->setName('api:file');

        // publications
        $app->group('/publication', function (App $app): void {
            $app->get('', \App\Application\Actions\Api\Publication\Publication::class)
                ->setName('api:publication');
            $app->get('/category', \App\Application\Actions\Api\Publication\Category::class)
                ->setName('api:publication:category');
        });

        // catalog
        $app->group('/catalog', function (App $app): void {
            $app
                ->get('/category', \App\Application\Actions\Api\Catalog\Category::class)
                ->setName('api:catalog:category')
                ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

            $app
                ->get('/product', \App\Application\Actions\Api\Catalog\Product::class)
                ->setName('api:catalog:product')
                ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);
        });
    })
    ->add(new \Slim\HttpCache\Cache('public', 0));

// CUP section
$app
    ->group('/cup', function (App $app): void {
        // login cup
        $app->map(['get', 'post'], '/login', \App\Application\Actions\Cup\LoginPageAction::class)
            ->setName('cup:login');

        // cup forbidden
        $app->map(['get', 'post'], '/forbidden', \App\Application\Actions\Cup\ForbiddenPageAction::class)
            ->setName('cup:forbidden');

        // installer
        $app->map(['get', 'post'], '/system', \App\Application\Actions\Cup\SystemPageAction::class)
            ->setName('cup:system');

        $app
            ->group('', function (App $app): void {
                // main page
                $app->get('', \App\Application\Actions\Cup\MainPageAction::class)
                    ->setName('cup:main');

                // settings
                $app->map(['get', 'post'], '/parameters', \App\Application\Actions\Cup\ParametersPageAction::class)
                    ->setName('cup:parameters');

                // refresh
                $app->map(['post', 'get'], '/refresh', \App\Application\Actions\Cup\RefreshAction::class)
                    ->setName('cup:refresh');

                // users
                $app->group('/user', function (App $app): void {
                    // users group
                    $app->group('/group', function (App $app): void {
                        $app->get('', \App\Application\Actions\Cup\User\Group\ListAction::class)
                            ->setName('cup:user:group:list');
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\User\Group\CreateAction::class)
                            ->setName('cup:user:group:add');
                        $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\User\Group\UpdateAction::class)
                            ->setName('cup:user:group:edit');
                        $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Group\DeleteAction::class)
                            ->setName('cup:user:group:delete');
                    });

                    // users subscribers
                    $app->group('/subscriber', function (App $app): void {
                        $app->get('', \App\Application\Actions\Cup\User\Subscriber\ListAction::class)
                            ->setName('cup:user:subscriber:list');
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\User\Subscriber\CreateAction::class)
                            ->setName('cup:user:subscriber:add');
                        $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Subscriber\DeleteAction::class)
                            ->setName('cup:user:subscriber:delete');
                    });
                    $app->map(['get', 'post'], '/newsletter', \App\Application\Actions\Cup\User\NewsLetter\CreateAction::class)
                        ->setName('cup:user:newsletter:list');

                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\User\UserListAction::class)
                        ->setName('cup:user:list');
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\User\UserCreateAction::class)
                        ->setName('cup:user:add');
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\User\UserUpdateAction::class)
                        ->setName('cup:user:edit');
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\User\UserDeleteAction::class)
                        ->setName('cup:user:delete');
                });

                // static pages
                $app->group('/page', function (App $app): void {
                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Page\PageListAction::class)
                        ->setName('cup:page:list');
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Page\PageCreateAction::class)
                        ->setName('cup:page:add');
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Page\PageUpdateAction::class)
                        ->setName('cup:page:edit');
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Page\PageDeleteAction::class)
                        ->setName('cup:page:delete');
                });

                // publications
                $app->group('/publication', function (App $app): void {
                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Publication\PublicationListAction::class)
                        ->setName('cup:publication:list');
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Publication\PublicationCreateAction::class)
                        ->setName('cup:publication:add');
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\PublicationUpdateAction::class)
                        ->setName('cup:publication:edit');
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\PublicationDeleteAction::class)
                        ->setName('cup:publication:delete');
                    $app->map(['get', 'post'], '/preview', \App\Application\Actions\Cup\Publication\PublicationPreviewAction::class)
                        ->setName('cup:publication:preview');

                    // category
                    $app->group('/category', function (App $app): void {
                        $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Publication\Category\CategoryListAction::class)
                            ->setName('cup:publication:category:list');
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Publication\Category\CategoryCreateAction::class)
                            ->setName('cup:publication:category:add');
                        $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\Category\CategoryUpdateAction::class)
                            ->setName('cup:publication:category:edit');
                        $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\Category\CategoryDeleteAction::class)
                            ->setName('cup:publication:category:delete');
                    });
                });

                // forms
                $app->group('/form', function (App $app): void {
                    $app->get('', \App\Application\Actions\Cup\Form\FormListAction::class)
                        ->setName('cup:form:list');
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Form\FormCreateAction::class)
                        ->setName('cup:form:add');
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Form\FormUpdateAction::class)
                        ->setName('cup:form:edit');
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Form\FormDeleteAction::class)
                        ->setName('cup:form:delete');

                    // forms data
                    $app->group('/{uuid}/view', function (App $app): void {
                        $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Form\Data\DataListAction::class)
                            ->setName('cup:form:view:list');
                        $app->map(['get', 'post'], '/{data}', \App\Application\Actions\Cup\Form\Data\DataViewAction::class)
                            ->setName('cup:form:view:data');
                        $app->map(['get', 'post'], '/{data}/delete', \App\Application\Actions\Cup\Form\Data\DataDeleteAction::class)
                            ->setName('cup:form:view:delete');
                    });
                });

                // catalog
                $app->group('/catalog', function (App $app): void {
                    // categories
                    $app->group('/category', function (App $app): void {
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Catalog\Category\CategoryCreateAction::class)
                            ->setName('cup:catalog:category:add');
                        $app->map(['get', 'post'], '/{category}/edit', \App\Application\Actions\Cup\Catalog\Category\CategoryUpdateAction::class)
                            ->setName('cup:catalog:category:edit');
                        $app->map(['get', 'post'], '/{category}/delete', \App\Application\Actions\Cup\Catalog\Category\CategoryDeleteAction::class)
                            ->setName('cup:catalog:category:delete');
                        $app->get('[/{parent}]', \App\Application\Actions\Cup\Catalog\Category\CategoryListAction::class)
                            ->setName('cup:catalog:category:list');
                    });

                    // products
                    $app->group('/product', function (App $app): void {
                        $app
                            ->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Catalog\Product\ProductCreateAction::class)
                            ->setName('cup:catalog:product:add');
                        $app->map(['get', 'post'], '/{product}/edit', \App\Application\Actions\Cup\Catalog\Product\ProductUpdateAction::class)
                            ->setName('cup:catalog:product:edit');
                        $app->map(['get', 'post'], '/{product}/delete', \App\Application\Actions\Cup\Catalog\Product\ProductDeleteAction::class)
                            ->setName('cup:catalog:product:delete');
                        $app->get('[/{category}]', \App\Application\Actions\Cup\Catalog\Product\ProductListAction::class)
                            ->setName('cup:catalog:product:list');
                    });

                    // attribute
                    $app->group('/attribute', function (App $app): void {
                        $app->get('', \App\Application\Actions\Cup\Catalog\Attribute\AttributeListAction::class)
                            ->setName('cup:attribute:order:list');
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Catalog\Attribute\AttributeCreateAction::class)
                            ->setName('cup:attribute:order:add');
                        $app->map(['get', 'post'], '/{attribute}/edit', \App\Application\Actions\Cup\Catalog\Attribute\AttributeUpdateAction::class)
                            ->setName('cup:attribute:order:edit');
                        $app->map(['get', 'post'], '/{attribute}/delete', \App\Application\Actions\Cup\Catalog\Attribute\AttributeDeleteAction::class)
                            ->setName('cup:attribute:order:delete');
                    });

                    // order
                    $app->group('/order', function (App $app): void {
                        $app->get('', \App\Application\Actions\Cup\Catalog\Order\OrderListAction::class)
                            ->setName('cup:catalog:order:list');
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Catalog\Order\OrderCreateAction::class)
                            ->setName('cup:catalog:order:add');
                        $app->map(['get', 'post'], '/{order}/edit', \App\Application\Actions\Cup\Catalog\Order\OrderUpdateAction::class)
                            ->setName('cup:catalog:order:edit');
                        $app->map(['get', 'post'], '/{order}/delete', \App\Application\Actions\Cup\Catalog\Order\OrderDeleteAction::class)
                            ->setName('cup:catalog:order:delete');
                    });

                    // import export
                    $app->group('/data', function (App $app): void {
                        $app
                            ->get('/export', \App\Application\Actions\Cup\Catalog\CatalogExportAction::class)
                            ->setName('cup:catalog:data:export');
                        $app
                            ->post('/import', \App\Application\Actions\Cup\Catalog\CatalogImportAction::class)
                            ->setName('cup:catalog:data:import');
                    });
                });

                // guestbook
                $app->group('/guestbook', function (App $app): void {
                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\GuestBook\GuestBookListAction::class)
                        ->setName('cup:guestbook:list');
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\GuestBook\GuestBookUpdateAction::class)
                        ->setName('cup:guestbook:edit');
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\GuestBook\GuestBookDeleteAction::class)
                        ->setName('cup:guestbook:delete');
                });

                // files
                $app->group('/file', function (App $app): void {
                    // small text-editor api
                    $app->group('/image', function (App $app): void {
                        $app->get('', \App\Application\Actions\Cup\File\Image\GetAction::class)
                            ->setName('cup:file:image:get');
                        $app->post('/delete', \App\Application\Actions\Cup\File\Image\DeleteAction::class)
                            ->setName('cup:file:image:delete');
                    });

                    $app->get('', \App\Application\Actions\Cup\File\FileListAction::class)
                        ->setName('cup:file:list');
                    $app->any('/{uuid}/delete', \App\Application\Actions\Cup\File\FileDeleteAction::class)
                        ->setName('cup:file:delete');
                });

                // template editor
                $app->group('/editor', function (App $app): void {
                    $app->map(['get', 'post'], '[/{file:.*}]', \App\Application\Actions\Cup\EditorPageAction::class)
                        ->setName('cup:editor');
                });

                // task add to queue
                $app->post('/task/run', \App\Application\Actions\Cup\Task\TaskRunAction::class)
                    ->setName('cup:task:run');

                // dev console
                $app->post('/console', '\RunTracy\Controllers\RunTracyConsole:index')
                    ->setName('cup:console');
            });
    })
    ->add(new \Slim\HttpCache\Cache('private', 0, true));

// COMMON section
// main path
$app
    ->get('/', \App\Application\Actions\Common\MainPageAction::class)
    ->setName('common:main')
    ->add(
        ($_ENV['DEBUG'] ?? false) ?
            new \Slim\HttpCache\Cache('private', 0, false) :
            new \Slim\HttpCache\Cache('public', 60, true)
    );

// user
$app
    ->group('/user', function (App $app): void {
        $app
            ->map(['get', 'post'], '/login', \App\Application\Actions\Common\User\UserLoginAction::class)
            ->setName('common:user:login')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

        $app
            ->map(['get', 'post'], '/register', \App\Application\Actions\Common\User\UserRegisterAction::class)
            ->setName('common:user:register')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

        $app->map(['get', 'post'], '/logout', \App\Application\Actions\Common\User\UserLogoutAction::class)
            ->setName('common:user:logout');

        $app
            ->map(['get', 'post'], '/profile', \App\Application\Actions\Common\User\UserProfileAction::class)
            ->setName('common:user:profile')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class)
            ->add(function (Request $request, Response $response, $next) {
                $user = $request->getAttribute('user', false);

                if ($user === false) {
                    return $response->withHeader('Location', '/user/login')->withStatus(301);
                }

                return $next($request, $response);
            });
    })
    ->add(new \Slim\HttpCache\Cache('private', 0, true));

// other
$app
    ->group('', function (App $app) use ($container): void {
        // forbidden
        $app->map(['get', 'post'], '/forbidden', \App\Application\Actions\Common\ForbiddenPageAction::class)
            ->setName('forbidden');

        // file
        $app
            ->group('/file', function (App $app): void {
                $app->get('/get/{salt}/{hash}', \App\Application\Actions\Common\File\FileGetAction::class)
                    ->setName('common:file:get');
                $app
                    ->post('/upload', \App\Application\Actions\Common\File\FileUploadAction::class)
                    ->setName('common:file:upload')
                    ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);
            });

        // form
        $app
            ->post('/form/{unique}', \App\Application\Actions\Common\FormAction::class)
            ->setName('common:form')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

        // catalog
        $app
            ->group('', function (App $app) use ($container): void {
                $paramService = \App\Domain\Service\Parameter\ParameterService::getWithContainer($container);
                $pathCatalog = $paramService->read(['key' => 'catalog_address'], 'catalog')->getValue();

                // view categories and products
                $app
                    ->get("/{$pathCatalog}[/{args:.*}]", \App\Application\Actions\Common\Catalog\ListAction::class)
                    ->setName('common:catalog:list')
                    ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

                // view cart
                $app
                    ->map(['get', 'post'], '/cart', \App\Application\Actions\Common\Catalog\CartAction::class)
                    ->setName('common:catalog:cart')
                    ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

                // view order confirm
                $app
                    ->get('/cart/done/{order}', \App\Application\Actions\Common\Catalog\CartDoneAction::class)
                    ->setName('common:catalog:cart:done')
                    ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);
            });

        // guest book
        $app
            ->map(['get', 'post'], '/guestbook[/{page:[0-9]+}}]', \App\Application\Actions\Common\GuestBookAction::class)
            ->setName('common:guestbook')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

        // xml files
        $app->get('/xml/{name}', \App\Application\Actions\Common\XMLFileAction::class)
            ->setName('common:xml');

        // publication rss
        $app->get('/rss/{channel:.*}', \App\Application\Actions\Common\PublicationRSS::class)
            ->setName('common:rss');

        // dynamic path handler
        $app->get('/{args:.*}', \App\Application\Actions\Common\DynamicPageAction::class)
            ->setName('common:dynamic');
    })
    ->add(
        ($_ENV['DEBUG'] ?? false) ?
            new \Slim\HttpCache\Cache('private', 0, false) :
            new \Slim\HttpCache\Cache('public', 60, true)
    );

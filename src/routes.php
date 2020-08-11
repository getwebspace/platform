<?php declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

/*
 * @var App                $app
 * @var ContainerInterface $container
 */

// API section
$app
    ->group('/api', function (App $app): void {
        // users
        $app->group('/user', function (App $app): void {
            // users subscribers
            $app->group('/newsletter', function (App $app): void {
                $app->map(['get', 'post'], '/subscribe', \App\Application\Actions\Api\User\Subscriber\SubscribeAction::class)
                    ->setName('user:newsletter:subscribe:api');
                $app->map(['get', 'post'], '/{uuid}/unsubscribe', \App\Application\Actions\Api\User\Subscriber\UnsubscribeAction::class)
                    ->setName('user:newsletter:unsubscribe:api');
            });
        });

        // files
        $app->get('/file', \App\Application\Actions\Api\File\File::class)
            ->setName('file:api');

        // publications
        $app->group('/publication', function (App $app): void {
            $app->get('', \App\Application\Actions\Api\Publication\Publication::class)
                ->setName('publication:api');
            $app->get('/category', \App\Application\Actions\Api\Publication\Category::class)
                ->setName('publication:category:api');
        });

        // catalog
        $app->group('/catalog', function (App $app): void {
            $app
                ->get('/category', \App\Application\Actions\Api\Catalog\Category::class)
                ->setName('catalog:category:api')
                ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

            $app
                ->get('/product', \App\Application\Actions\Api\Catalog\Product::class)
                ->setName('catalog:product:api')
                ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);
        });
    });

// CUP section
$app
    ->group('/cup', function (App $app): void {
        $app->map(['get', 'post'], '/login', \App\Application\Actions\Cup\LoginPageAction::class);

        $app
            ->group('', function (App $app): void {
                // main page
                $app->get('', \App\Application\Actions\Cup\MainPageAction::class)
                    ->setName('cup:main');

                // settings
                $app->map(['get', 'post'], '/parameters', \App\Application\Actions\Cup\ParametersPageAction::class)
                    ->setName('cup:parameters');

                // refresh
                $app->map(['post'], '/refresh', \App\Application\Actions\Cup\RefreshAction::class)
                    ->setName('cup:refresh');

                // users
                $app->group('/user', function (App $app): void {
                    // users subscribers
                    $app->group('/subscriber', function (App $app): void {
                        $app->get('', \App\Application\Actions\Cup\User\Subscriber\ListAction::class);
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\User\Subscriber\CreateAction::class);
                        $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Subscriber\DeleteAction::class);
                    });
                    $app->map(['get', 'post'], '/newsletter', \App\Application\Actions\Cup\User\NewsLetter\CreateAction::class);

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
                        $app->get('', \App\Application\Actions\Cup\File\Image\GetAction::class);
                        $app->post('/delete', \App\Application\Actions\Cup\File\Image\DeleteAction::class);
                    });

                    $app->get('', \App\Application\Actions\Cup\File\FileListAction::class)
                        ->setName('cup:file:list');
                    $app->any('/{uuid}/delete', \App\Application\Actions\Cup\File\FileDeleteAction::class)
                        ->setName('cup:file:delete');
                });

                // редактор шаблонов
                $app->group('/editor', function (App $app): void {
                    $app->map(['get', 'post'], '[/{file:.*}]', \App\Application\Actions\Cup\EditorPageAction::class)
                        ->setName('cup:editor');
                });

                // task add to queue
                $app->post('/task/run', \App\Application\Actions\Cup\Task\TaskRunAction::class);

                // dev console
                $app->post('/console', '\RunTracy\Controllers\RunTracyConsole:index');
            })
            ->add(new \App\Application\Middlewares\CupMiddleware($app->getContainer()));
    });

// COMMON section
// main path
$app
    ->get('/', \App\Application\Actions\Common\MainPageAction::class)
    ->setName('main');

// file
$app->group('/file', function (App $app): void {
    $app->get('/get/{salt}/{hash}', \App\Application\Actions\Common\File\FileGetAction::class)
        ->setName('file:get');
    $app
        ->post('/upload', \App\Application\Actions\Common\File\FileUploadAction::class)
        ->setName('file:upload')
        ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);
});

// user
$app->group('/user', function (App $app): void {
    $app
        ->map(['get', 'post'], '/login', \App\Application\Actions\Common\User\UserLoginAction::class)
        ->setName('user:login')
        ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

    $app
        ->map(['get', 'post'], '/register', \App\Application\Actions\Common\User\UserRegisterAction::class)
        ->setName('user:register')
        ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

    $app->map(['get', 'post'], '/logout', \App\Application\Actions\Common\User\UserLogoutAction::class)
        ->setName('user:logout');

    $app
        ->map(['get', 'post'], '/profile', \App\Application\Actions\Common\User\UserProfileAction::class)
        ->setName('user:profile')
        ->add(\App\Application\Middlewares\IsEnabledMiddleware::class)
        ->add(function (Request $request, Response $response, $next) {
            $user = $request->getAttribute('user', false);

            if ($user === false) {
                return $response->withHeader('Location', '/user/login')->withStatus(301);
            }

            return $next($request, $response);
        });
});

// form
$app
    ->post('/form/{unique}', \App\Application\Actions\Common\FormAction::class)
    ->setName('form')
    ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

// catalog
$app
    ->group('', function (App $app) use ($container): void {
        $pathCatalog = \App\Domain\Service\Parameter\ParameterService::getWithContainer($container)
                ->read(['key' => 'catalog_address'])->getValue() ?? 'catalog';

        // view categories and products
        $app
            ->get("/{$pathCatalog}[/{args:.*}]", \App\Application\Actions\Common\Catalog\ListAction::class)
            ->setName('catalog:list')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

        // view cart
        $app
            ->map(['get', 'post'], '/cart', \App\Application\Actions\Common\Catalog\CartAction::class)
            ->setName('catalog:cart')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

        // view order confirm
        $app
            ->get('/cart/done/{order}', \App\Application\Actions\Common\Catalog\CartCompleteAction::class)
            ->setName('catalog:cart:done')
            ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);
    });

// guest book
$app
    ->map(['get', 'post'], '/guestbook[/{page:[0-9]+}}]', \App\Application\Actions\Common\GuestBookAction::class)
    ->setName('guestbook')
    ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);

// xml files
$app->get('/xml/{name}', \App\Application\Actions\Common\XMLFileAction::class)
    ->setName('xml');

// publication rss
$app->get('/rss/{channel:.*}', \App\Application\Actions\Common\PublicationRSS::class)
    ->setName('rss');

// dynamic path handler
$app->get('/{args:.*}', \App\Application\Actions\Common\DynamicPageAction::class)
    ->setName('dynamic');

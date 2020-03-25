<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app
    ->group('/api', function (App $app) {
        // users
        $app->group('/user', function (App $app) {
            // users subscribers
            $app->group('/newsletter', function (App $app) {
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
        $app->group('/publication', function (App $app) {
            $app->get('', \App\Application\Actions\Api\Publication\Publication::class)
                ->setName('publication:api');
            $app->get('/category', \App\Application\Actions\Api\Publication\Category::class)
                ->setName('publication:category:api');
        });

        // catalog
        $app->group('/catalog', function (App $app) {
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

$app
    ->group('/cup', function (App $app) {
        $app->map(['get', 'post'], '/login', \App\Application\Actions\Cup\LoginPageAction::class);

        // catalog scan
        $app->get('/catalog/product/scan/{channel}', \App\Application\Actions\Cup\Catalog\CatalogScanAction::class);

        $app
            ->group('', function (App $app) {
                // main page
                $app->get('', \App\Application\Actions\Cup\MainPageAction::class);

                // settings
                $app->map(['get', 'post'], '/parameters', \App\Application\Actions\Cup\ParametersPageAction::class);

                // users
                $app->group('/user', function (App $app) {
                    // users subscribers
                    $app->group('/subscriber', function (App $app) {
                        $app->get('', \App\Application\Actions\Cup\User\Subscriber\ListAction::class);
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\User\Subscriber\CreateAction::class);
                        $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Subscriber\DeleteAction::class);
                    });
                    $app->map(['get', 'post'], '/newsletter', \App\Application\Actions\Cup\User\NewsLetter\CreateAction::class);

                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\User\UserListAction::class);
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\User\UserCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\User\UserUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\User\UserDeleteAction::class);
                });

                // static pages
                $app->group('/page', function (App $app) {
                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Page\PageListAction::class);
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Page\PageCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Page\PageUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Page\PageDeleteAction::class);
                });

                // publications
                $app->group('/publication', function (App $app) {
                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Publication\PublicationListAction::class);
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Publication\PublicationCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\PublicationUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\PublicationDeleteAction::class);
                    $app->map(['get', 'post'], '/preview', \App\Application\Actions\Cup\Publication\PublicationPreviewAction::class);

                    // category
                    $app->group('/category', function (App $app) {
                        $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Publication\Category\CategoryListAction::class);
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Publication\Category\CategoryCreateAction::class);
                        $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\Category\CategoryUpdateAction::class);
                        $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\Category\CategoryDeleteAction::class);
                    });
                });

                // forms
                $app->group('/form', function (App $app) {
                    $app->get('', \App\Application\Actions\Cup\Form\FormListAction::class);
                    $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Form\FormCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\Form\FormUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\Form\FormDeleteAction::class);

                    // forms data
                    $app->group('/{uuid}/view', function (App $app) {
                        $app->map(['get', 'post'], '', \App\Application\Actions\Cup\Form\Data\DataListAction::class);
                        $app->map(['get', 'post'], '/{data}', \App\Application\Actions\Cup\Form\Data\DataViewAction::class);
                        $app->map(['get', 'post'], '/{data}/delete', \App\Application\Actions\Cup\Form\Data\DataDeleteAction::class);
                    });
                });

                // catalog
                $app->group('/catalog', function (App $app) {
                    // categories
                    $app->group('/category', function (App $app) {
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Catalog\Category\CategoryCreateAction::class);
                        $app->map(['get', 'post'], '/{category}/edit', \App\Application\Actions\Cup\Catalog\Category\CategoryUpdateAction::class);
                        $app->map(['get', 'post'], '/{category}/delete', \App\Application\Actions\Cup\Catalog\Category\CategoryDeleteAction::class);
                        $app->get('[/{parent}]', \App\Application\Actions\Cup\Catalog\Category\CategoryListAction::class);
                    });

                    // products
                    $app->group('/product', function (App $app) {
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Catalog\Product\ProductCreateAction::class);
                        $app->map(['get', 'post'], '/{product}/edit', \App\Application\Actions\Cup\Catalog\Product\ProductUpdateAction::class);
                        $app->map(['get', 'post'], '/{product}/delete', \App\Application\Actions\Cup\Catalog\Product\ProductDeleteAction::class);
                        $app->get('[/{category}]', \App\Application\Actions\Cup\Catalog\Product\ProductListAction::class);
                        $app->get('/{category}/export', \App\Application\Actions\Cup\Catalog\Product\ProductExportAction::class);
                    });

                    // order
                    $app->group('/order', function (App $app) {
                        $app->get('', \App\Application\Actions\Cup\Catalog\Order\OrderListAction::class);
                        $app->map(['get', 'post'], '/add', \App\Application\Actions\Cup\Catalog\Order\OrderCreateAction::class);
                        $app->map(['get', 'post'], '/{order}/edit', \App\Application\Actions\Cup\Catalog\Order\OrderUpdateAction::class);
                        $app->map(['get', 'post'], '/{order}/delete', \App\Application\Actions\Cup\Catalog\Order\OrderDeleteAction::class);
                    });
                });

                // guestbook
                $app->group('/guestbook', function (App $app) {
                    $app->map(['get', 'post'], '', \App\Application\Actions\Cup\GuestBook\GuestBookListAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \App\Application\Actions\Cup\GuestBook\GuestBookUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \App\Application\Actions\Cup\GuestBook\GuestBookDeleteAction::class);
                });

                // files
                $app->group('/file', function (App $app) {
                    $app->group('/image', function (App $app) {
                        $app->get('', \App\Application\Actions\Cup\File\Image\GetAction::class);
                        $app->post('/delete', \App\Application\Actions\Cup\File\Image\DeleteAction::class);
                    });

                    $app->get('', \App\Application\Actions\Cup\File\FileListAction::class);
                    $app->any('/{uuid}/delete', \App\Application\Actions\Cup\File\FileDeleteAction::class);
                });

                // редактор шаблонов
                $app->group('/editor', function (App $app) {
                    $app->map(['get', 'post'], '[/{file:.*}]', \App\Application\Actions\Cup\EditorPageAction::class);
                });

                // docs
                $app->get('/docs', \App\Application\Actions\Cup\DocsPageAction::class);

                // task add to queue
                $app->post('/task/run', \App\Application\Actions\Cup\Task\TaskRunAction::class);

                // dev console
                $app->post('/console', '\RunTracy\Controllers\RunTracyConsole:index');
            })
            ->add(function (Request $request, Response $response, $next) {
                $user = $request->getAttribute('user', false);

                if ($user === false || !in_array($user->level, \App\Domain\Types\UserLevelType::CUP_ACCESS)) {
                    return $response->withHeader('Location', '/cup/login?redirect=' . $request->getUri()->getPath())->withStatus(301);
                }
                if ($request->isPost() && $user && $user->level === \App\Domain\Types\UserLevelType::LEVEL_DEMO) {
                    return $response->withHeader('Location', $request->getUri()->getPath())->withStatus(301);
                }

                return $next($request, $response);
            });
    });

// main path
$app
    ->get('/', \App\Application\Actions\Common\MainPageAction::class)
    ->setName('main');

// file
$app->group('/file', function (App $app) {
    $app->get('/get/{salt}/{hash}', \App\Application\Actions\Common\File\FileGetAction::class)
        ->setName('file:get');
    $app
        ->post('/upload', \App\Application\Actions\Common\File\FileUploadAction::class)
        ->setName('file:upload')
        ->add(\App\Application\Middlewares\IsEnabledMiddleware::class);
});

// user
$app->group('/user', function (App $app) {
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
    ->group('', function (App $app) use ($container) {
        $pathCatalog = $container->get('parameter')->get('catalog_address', 'catalog');

        // view categories and products
        $app
            ->get("/{$pathCatalog}[/{args:.*}]", \App\Application\Actions\Common\Catalog\ListAction::class)
            ->setName('catalog')
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

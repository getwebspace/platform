<?php declare(strict_types=1);

use DI\Container;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

return function (App $app, Container $container): void {
    $_DEBUG = (bool) ($_ENV['DEBUG'] ?? false);

    // API section
    $app
        ->group('/api', function (Group $group): void {
            // Entity getter
            $group
                ->map(['GET', 'POST'], '/{args:.*}', \App\Application\Actions\Api\EntityAction::class)
                ->setName('api:entity')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
        })
        ->add(new \Slim\HttpCache\Cache('public', 0));

    // CUP section
    $app
        ->group('/cup', function (Group $group): void {
            // login cup
            $group->map(['GET', 'POST'], '/login', \App\Application\Actions\Cup\LoginPageAction::class)
                ->setName('cup:login');

            // cup forbidden
            $group->map(['GET', 'POST'], '/forbidden', \App\Application\Actions\Cup\ForbiddenPageAction::class)
                ->setName('cup:forbidden');

            // installer
            $group->map(['GET', 'POST'], '/system[/{step:.*}]', \App\Application\Actions\Cup\SystemPageAction::class)
                ->setName('cup:system');

            $group
                ->group('', function (Group $group): void {
                    // main page
                    $group->get('', \App\Application\Actions\Cup\MainPageAction::class)
                        ->setName('cup:main');

                    // settings
                    $group->map(['GET', 'POST'], '/parameters', \App\Application\Actions\Cup\ParametersPageAction::class)
                        ->setName('cup:parameters');

                    // refresh
                    $group->map(['GET', 'POST'], '/refresh', \App\Application\Actions\Cup\RefreshAction::class)
                        ->setName('cup:refresh');

                    // users
                    $group->group('/user', function (Group $group): void {
                        // users group
                        $group->group('/group', function (Group $group): void {
                            $group->get('', \App\Application\Actions\Cup\User\Group\ListAction::class)
                                ->setName('cup:user:group:list');
                            $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\User\Group\CreateAction::class)
                                ->setName('cup:user:group:add');
                            $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\User\Group\UpdateAction::class)
                                ->setName('cup:user:group:edit');
                            $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Group\DeleteAction::class)
                                ->setName('cup:user:group:delete');
                        });

                        // users subscribers
                        $group->group('/subscriber', function (Group $group): void {
                            $group->get('', \App\Application\Actions\Cup\User\Subscriber\ListAction::class)
                                ->setName('cup:user:subscriber:list');
                            $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\User\Subscriber\CreateAction::class)
                                ->setName('cup:user:subscriber:add');
                            $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Subscriber\DeleteAction::class)
                                ->setName('cup:user:subscriber:delete');
                        });
                        $group->map(['GET', 'POST'], '/newsletter', \App\Application\Actions\Cup\User\NewsLetter\CreateAction::class)
                            ->setName('cup:user:newsletter:list');

                        $group->map(['GET', 'POST'], '', \App\Application\Actions\Cup\User\UserListAction::class)
                            ->setName('cup:user:list');
                        $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\User\UserCreateAction::class)
                            ->setName('cup:user:add');
                        $group->map(['GET', 'POST'], '/{uuid}/view', \App\Application\Actions\Cup\User\UserViewAction::class)
                            ->setName('cup:user:view');
                        $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\User\UserUpdateAction::class)
                            ->setName('cup:user:edit');
                        $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\User\UserDeleteAction::class)
                            ->setName('cup:user:delete');
                    });

                    // static pages
                    $group->group('/page', function (Group $group): void {
                        $group->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Page\PageListAction::class)
                            ->setName('cup:page:list');
                        $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Page\PageCreateAction::class)
                            ->setName('cup:page:add');
                        $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Page\PageUpdateAction::class)
                            ->setName('cup:page:edit');
                        $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Page\PageDeleteAction::class)
                            ->setName('cup:page:delete');
                    });

                    // publications
                    $group->group('/publication', function (Group $group): void {
                        $group->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Publication\PublicationListAction::class)
                            ->setName('cup:publication:list');
                        $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Publication\PublicationCreateAction::class)
                            ->setName('cup:publication:add');
                        $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\PublicationUpdateAction::class)
                            ->setName('cup:publication:edit');
                        $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\PublicationDeleteAction::class)
                            ->setName('cup:publication:delete');
                        $group->map(['GET', 'POST'], '/preview', \App\Application\Actions\Cup\Publication\PublicationPreviewAction::class)
                            ->setName('cup:publication:preview');

                        // category
                        $group->group('/category', function (Group $group): void {
                            $group->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Publication\Category\CategoryListAction::class)
                                ->setName('cup:publication:category:list');
                            $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Publication\Category\CategoryCreateAction::class)
                                ->setName('cup:publication:category:add');
                            $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\Category\CategoryUpdateAction::class)
                                ->setName('cup:publication:category:edit');
                            $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\Category\CategoryDeleteAction::class)
                                ->setName('cup:publication:category:delete');
                        });
                    });

                    // forms
                    $group->group('/form', function (Group $group): void {
                        $group->get('', \App\Application\Actions\Cup\Form\FormListAction::class)
                            ->setName('cup:form:list');
                        $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Form\FormCreateAction::class)
                            ->setName('cup:form:add');
                        $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Form\FormUpdateAction::class)
                            ->setName('cup:form:edit');
                        $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Form\FormDeleteAction::class)
                            ->setName('cup:form:delete');

                        // forms data
                        $group->group('/{uuid}/view', function (Group $group): void {
                            $group->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Form\Data\DataListAction::class)
                                ->setName('cup:form:view:list');
                            $group->map(['GET', 'POST'], '/{data}', \App\Application\Actions\Cup\Form\Data\DataViewAction::class)
                                ->setName('cup:form:view:data');
                            $group->map(['GET', 'POST'], '/{data}/preview', \App\Application\Actions\Cup\Form\Data\DataPreviewAction::class)
                                ->setName('cup:form:view:preview');
                            $group->map(['GET', 'POST'], '/{data}/delete', \App\Application\Actions\Cup\Form\Data\DataDeleteAction::class)
                                ->setName('cup:form:view:delete');
                        });
                    });

                    // catalog
                    $group->group('/catalog', function (Group $group): void {
                        // categories
                        $group->group('/category', function (Group $group): void {
                            $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Category\CategoryCreateAction::class)
                                ->setName('cup:catalog:category:add');
                            $group->map(['GET', 'POST'], '/{category}/edit', \App\Application\Actions\Cup\Catalog\Category\CategoryUpdateAction::class)
                                ->setName('cup:catalog:category:edit');
                            $group->map(['GET', 'POST'], '/{category}/delete', \App\Application\Actions\Cup\Catalog\Category\CategoryDeleteAction::class)
                                ->setName('cup:catalog:category:delete');
                            $group->get('[/{parent}]', \App\Application\Actions\Cup\Catalog\Category\CategoryListAction::class)
                                ->setName('cup:catalog:category:list');
                        });

                        // products
                        $group->group('/product', function (Group $group): void {
                            $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Product\ProductCreateAction::class)
                                ->setName('cup:catalog:product:add');
                            $group->map(['GET', 'POST'], '/{product}/edit', \App\Application\Actions\Cup\Catalog\Product\ProductUpdateAction::class)
                                ->setName('cup:catalog:product:edit');
                            $group->map(['GET', 'POST'], '/{product}/delete', \App\Application\Actions\Cup\Catalog\Product\ProductDeleteAction::class)
                                ->setName('cup:catalog:product:delete');
                            $group->get('[/{category}]', \App\Application\Actions\Cup\Catalog\Product\ProductListAction::class)
                                ->setName('cup:catalog:product:list');
                        });

                        // attribute
                        $group->group('/attribute', function (Group $group): void {
                            $group->get('', \App\Application\Actions\Cup\Catalog\Attribute\AttributeListAction::class)
                                ->setName('cup:catalog:attribute:list');
                            $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Attribute\AttributeCreateAction::class)
                                ->setName('cup:catalog:attribute:add');
                            $group->map(['GET', 'POST'], '/{attribute}/edit', \App\Application\Actions\Cup\Catalog\Attribute\AttributeUpdateAction::class)
                                ->setName('cup:catalog:attribute:edit');
                            $group->map(['GET', 'POST'], '/{attribute}/delete', \App\Application\Actions\Cup\Catalog\Attribute\AttributeDeleteAction::class)
                                ->setName('cup:catalog:attribute:delete');
                        });

                        // order
                        $group->group('/order', function (Group $group): void {
                            $group->get('/invoice', \App\Application\Actions\Cup\Catalog\Order\Invoice\OrderInviceEditorAction::class)
                                ->setName('cup:catalog:order:invoice:editor');

                            // order status
                            $group->group('/status', function (Group $group): void {
                                $group->get('', \App\Application\Actions\Cup\Catalog\Order\Status\OrderStatusListAction::class)
                                    ->setName('cup:catalog:order:status:list');
                                $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Order\Status\OrderStatusCreateAction::class)
                                    ->setName('cup:catalog:order:status:add');
                                $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Catalog\Order\Status\OrderStatusUpdateAction::class)
                                    ->setName('cup:catalog:order:status:edit');
                                $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Catalog\Order\Status\OrderStatusDeleteAction::class)
                                    ->setName('cup:catalog:order:status:delete');
                            });

                            $group->get('', \App\Application\Actions\Cup\Catalog\Order\OrderListAction::class)
                                ->setName('cup:catalog:order:list');
                            $group->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Order\OrderCreateAction::class)
                                ->setName('cup:catalog:order:add');
                            $group->map(['GET', 'POST'], '/{order}/edit', \App\Application\Actions\Cup\Catalog\Order\OrderUpdateAction::class)
                                ->setName('cup:catalog:order:edit');
                            $group->map(['GET', 'POST'], '/{order}/delete', \App\Application\Actions\Cup\Catalog\Order\OrderDeleteAction::class)
                                ->setName('cup:catalog:order:delete');
                            $group->map(['GET', 'POST'], '/{order}/invoice', \App\Application\Actions\Cup\Catalog\Order\OrderInvoiceAction::class)
                                ->setName('cup:catalog:order:invoice');
                        });

                        // import export
                        $group->group('/data', function (Group $group): void {
                            $group
                                ->get('/export', \App\Application\Actions\Cup\Catalog\CatalogExportAction::class)
                                ->setName('cup:catalog:data:export');
                            $group
                                ->post('/import', \App\Application\Actions\Cup\Catalog\CatalogImportAction::class)
                                ->setName('cup:catalog:data:import');
                        });
                    });

                    // guestbook
                    $group->group('/guestbook', function (Group $group): void {
                        $group->map(['GET', 'POST'], '', \App\Application\Actions\Cup\GuestBook\GuestBookListAction::class)
                            ->setName('cup:guestbook:list');
                        $group->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\GuestBook\GuestBookUpdateAction::class)
                            ->setName('cup:guestbook:edit');
                        $group->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\GuestBook\GuestBookDeleteAction::class)
                            ->setName('cup:guestbook:delete');
                    });

                    // files
                    $group->group('/file', function (Group $group): void {
                        // small text-editor api
                        $group->group('/image', function (Group $group): void {
                            $group->get('', \App\Application\Actions\Cup\File\Image\GetAction::class)
                                ->setName('cup:file:image:get');
                            $group->post('/delete', \App\Application\Actions\Cup\File\Image\DeleteAction::class)
                                ->setName('cup:file:image:delete');
                        });

                        $group->get('', \App\Application\Actions\Cup\File\FileListAction::class)
                            ->setName('cup:file:list');
                        $group->any('/{uuid}/delete', \App\Application\Actions\Cup\File\FileDeleteAction::class)
                            ->setName('cup:file:delete');
                    });

                    // template editor
                    $group->group('/editor', function (Group $group): void {
                        $group->map(['GET', 'POST'], '[/{file:.*}]', \App\Application\Actions\Cup\EditorPageAction::class)
                            ->setName('cup:editor');
                    });

                    // log viewer
                    $group->get('/logs', \App\Application\Actions\Cup\LogPageAction::class)
                        ->setName('cup:logs');

                    // task add to queue
                    $group->post('/task/run', \App\Application\Actions\Cup\Task\TaskRunAction::class)
                        ->setName('cup:task:run');
                });
        })
        ->add(new \Slim\HttpCache\Cache('private', 0, true));

    // COMMON section
    // main path
    $app
        ->get('/', \App\Application\Actions\Common\MainPageAction::class)
        ->setName('common:main')
        ->add(
            $_DEBUG ?
                new \Slim\HttpCache\Cache('private', 0, false) :
                new \Slim\HttpCache\Cache('public', 60, true)
        );

    // user
    $app
        ->group('/user', function (Group $group): void {
            $group
                ->map(['GET', 'POST'], '/login', \App\Application\Actions\Common\User\UserLoginAction::class)
                ->setName('common:user:login')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

            $group
                ->map(['GET', 'POST'], '/oauth/{provider}', \App\Application\Actions\Common\User\UserOAuthAction::class)
                ->setName('common:user:oauth')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

            $group
                ->map(['GET', 'POST'], '/register', \App\Application\Actions\Common\User\UserRegisterAction::class)
                ->setName('common:user:register')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

            $group->map(['GET', 'POST'], '/logout', \App\Application\Actions\Common\User\UserLogoutAction::class)
                ->setName('common:user:logout');

            $group
                ->map(['GET', 'POST'], '/profile', \App\Application\Actions\Common\User\UserProfileAction::class)
                ->setName('common:user:profile')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class)
                ->add(function (Request $request, RequestHandlerInterface $handler) {
                    $user = $request->getAttribute('user', false);

                    if ($user === false) {
                        return (new Response())
                            ->withAddedHeader('Location', '/user/login')
                            ->withStatus(301);
                    }

                    return $handler->handle($request);
                });

            $group
                ->map(['GET', 'POST'], '/subscriber', \App\Application\Actions\Common\User\UserSubscribeAction::class)
                ->setName('common:user:subscriber')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
        })
        ->add(new \Slim\HttpCache\Cache('private', 0, true));

    // other PRIVATE section
    $app
        ->group('', function (Group $group): void {
            $group
                ->map(['GET', 'POST'], '/search', \App\Application\Actions\Common\SearchAction::class)
                ->setName('common:search')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

            $group
                ->map(['GET', 'POST'], '/cart', \App\Application\Actions\Common\Catalog\CartAction::class)
                ->setName('common:catalog:cart')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

            // view order confirm
            $group
                ->get('/cart/done/{order}', \App\Application\Actions\Common\Catalog\CartDoneAction::class)
                ->setName('common:catalog:cart:done')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
        })
        ->add(new \Slim\HttpCache\Cache('private', 0, true));

    // other PUBLIC section
    $app
        ->group('', function (Group $group) use ($container): void {
            // forbidden
            $group->map(['GET', 'POST'], '/forbidden', \App\Application\Actions\Common\ForbiddenPageAction::class)
                ->setName('forbidden');

            // publication
            $group
                ->group('', function (Group $group) use ($container): void {
                    $publicationCategoryService = $container->get(\App\Domain\Service\Publication\CategoryService::class);

                    if (($categories = $publicationCategoryService->read()) !== null) {
                        $categoryPath = $categories->pluck('address')->implode('|');

                        // view categories and products
                        $group
                            ->get("/{category:{$categoryPath}}[/{args:.*}]", \App\Application\Actions\Common\Publication\ListAction::class)
                            ->setName('common:publication:list')
                            ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                    }
                });

            // file
            $group
                ->group('/file', function (Group $group): void {
                    $group->get('/get/{salt}/{hash}', \App\Application\Actions\Common\File\FileGetAction::class)
                        ->setName('common:file:get');
                    $group->get('/view/{salt}/{hash}', \App\Application\Actions\Common\File\FileViewAction::class)
                        ->setName('common:file:view');
                    $group->post('/upload', \App\Application\Actions\Common\File\FileUploadAction::class)
                        ->setName('common:file:upload')
                        ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                });

            // form
            $group
                ->post('/form/{unique}', \App\Application\Actions\Common\FormAction::class)
                ->setName('common:form')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

            // catalog
            $group
                ->group('', function (Group $group) use ($container): void {
                    $paramService = $container->get(\App\Domain\Service\Parameter\ParameterService::class);
                    $pathCatalog = $paramService->read(['key' => 'catalog_address'], 'catalog')->getValue();

                    // view categories and products
                    $group
                        ->get("/{$pathCatalog}[/{args:.*}]", \App\Application\Actions\Common\Catalog\ListAction::class)
                        ->setName('common:catalog:list')
                        ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                });

            // guest book
            $group
                ->map(['GET', 'POST'], '/guestbook[/{page:[0-9]+}}]', \App\Application\Actions\Common\GuestBookAction::class)
                ->setName('common:guestbook')
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

            // xml files
            $group->get('/xml/{name}', \App\Application\Actions\Common\XMLFileAction::class)
                ->setName('common:xml');

            // publication rss
            $group->get('/rss/{channel:.*}', \App\Application\Actions\Common\PublicationRSS::class)
                ->setName('common:rss');

            // page
            $group->get('/{args:.*}', \App\Application\Actions\Common\PageAction::class)
                ->setName('common:page');
        })
        ->add(
            $_DEBUG ?
                new \Slim\HttpCache\Cache('private', 0, false) :
                new \Slim\HttpCache\Cache('public', 60, true)
        );
};

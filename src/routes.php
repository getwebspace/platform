<?php declare(strict_types=1);

use DI\Container;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

return function (App $app, Container $container): void {
    // --------------------------------------------------- //
    // Section without check access rights
    //          ^^ ^^
    // --------------------------------------------------- //

    $app
        ->group('', function (Group $proxy): void {
            // API section
            $proxy
                ->group('/api', function (Group $proxy): void {
                    // search
                    $proxy
                        ->map(['GET'], '/v1/search', \App\Application\Actions\Api\v1\SearchAction::class)
                        ->setName('api:v1:search');

                    // entity getter/setter
                    $proxy
                        ->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/v1/{args:.*}', \App\Application\Actions\Api\v1\EntityAction::class)
                        ->setName('api:v1:entity')
                        ->add(\Slim\Middleware\BodyParsingMiddleware::class);
                })
                ->add(\App\Application\Middlewares\AuthorizationAPIMiddleware::class)
                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class)
                ->add(\App\Application\Middlewares\CORSMiddleware::class);

            // Auth section
            $proxy
                ->group('/auth', function (Group $proxy): void {
                    // login
                    $proxy
                        ->map(['GET', 'POST'], '/login', \App\Application\Actions\Auth\LoginAction::class)
                        ->setName('auth:login');

                    // refresh
                    $proxy
                        ->map(['GET', 'POST'], '/refresh-token', \App\Application\Actions\Auth\RefreshTokenAction::class)
                        ->setName('auth:refresh-token');

                    // revoke
                    $proxy
                        ->map(['GET', 'POST'], '/revoke', \App\Application\Actions\Auth\RevokeTokenAction::class)
                        ->setName('auth:revoke-token')
                        ->add(\App\Application\Middlewares\AuthorizationMiddleware::class);

                    // logout
                    $proxy
                        ->map(['GET', 'POST'], '/logout', \App\Application\Actions\Auth\LogoutAction::class)
                        ->setName('auth:logout');
                });

            // COMMON forbidden
            $proxy->map(['GET', 'POST'], '/forbidden', \App\Application\Actions\Common\ForbiddenPageAction::class)
                ->setName('common:forbidden');

            // CUP section
            $proxy
                ->group('/cup', function (Group $proxy): void {
                    // login cup
                    $proxy->map(['GET', 'POST'], '/login', \App\Application\Actions\Cup\LoginPageAction::class)
                        ->setName('cup:login');

                    // installer
                    $proxy->map(['GET', 'POST'], '/system[/{step:.*}]', \App\Application\Actions\Cup\SystemPageAction::class)
                        ->setName('cup:system')
                        ->add(\App\Application\Middlewares\AuthorizationMiddleware::class);

                    // cup forbidden
                    $proxy->map(['GET', 'POST'], '/forbidden', \App\Application\Actions\Cup\ForbiddenPageAction::class)
                        ->setName('cup:forbidden');
                })
                ->add(\App\Application\Middlewares\LocaleMiddleware::class);
        })
        ->add(new \Slim\HttpCache\Cache('public', 0));

    // --------------------------------------------------- //
    // Section with check access rights
    //          ^^
    // --------------------------------------------------- //

    $app
        ->group('', function (Group $proxy) use ($container): void {
            // CUP section
            $proxy
                ->group('/cup', function (Group $proxy): void {
                    // main page
                    $proxy->get('', \App\Application\Actions\Cup\MainPageAction::class)
                        ->setName('cup:main');

                    // parameters
                    $proxy->map(['GET', 'POST'], '/parameters', \App\Application\Actions\Cup\ParametersPageAction::class)
                        ->setName('cup:parameters');

                    // refresh
                    $proxy->map(['GET', 'POST'], '/refresh', \App\Application\Actions\Cup\RefreshAction::class)
                        ->setName('cup:refresh');

                    // users
                    $proxy->group('/user', function (Group $proxy): void {
                        // users group
                        $proxy->group('/group', function (Group $proxy): void {
                            $proxy->get('', \App\Application\Actions\Cup\User\Group\ListAction::class)
                                ->setName('cup:user:group:list');
                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\User\Group\CreateAction::class)
                                ->setName('cup:user:group:add');
                            $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\User\Group\UpdateAction::class)
                                ->setName('cup:user:group:edit');
                            $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Group\DeleteAction::class)
                                ->setName('cup:user:group:delete');
                        });

                        // users subscribers
                        $proxy->group('/subscriber', function (Group $proxy): void {
                            $proxy->get('', \App\Application\Actions\Cup\User\Subscriber\ListAction::class)
                                ->setName('cup:user:subscriber:list');
                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\User\Subscriber\CreateAction::class)
                                ->setName('cup:user:subscriber:add');
                            $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\User\Subscriber\DeleteAction::class)
                                ->setName('cup:user:subscriber:delete');
                        });
                        $proxy->map(['GET', 'POST'], '/newsletter', \App\Application\Actions\Cup\User\NewsLetter\CreateAction::class)
                            ->setName('cup:user:newsletter:list');

                        $proxy->map(['GET', 'POST'], '', \App\Application\Actions\Cup\User\UserListAction::class)
                            ->setName('cup:user:list');
                        $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\User\UserCreateAction::class)
                            ->setName('cup:user:add');
                        $proxy->map(['GET', 'POST'], '/{uuid}/view', \App\Application\Actions\Cup\User\UserViewAction::class)
                            ->setName('cup:user:view');
                        $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\User\UserUpdateAction::class)
                            ->setName('cup:user:edit');
                        $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\User\UserDeleteAction::class)
                            ->setName('cup:user:delete');
                    });

                    // static pages
                    $proxy->group('/page', function (Group $proxy): void {
                        $proxy->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Page\PageListAction::class)
                            ->setName('cup:page:list');
                        $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Page\PageCreateAction::class)
                            ->setName('cup:page:add');
                        $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Page\PageUpdateAction::class)
                            ->setName('cup:page:edit');
                        $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Page\PageDeleteAction::class)
                            ->setName('cup:page:delete');
                    });

                    // publications
                    $proxy->group('/publication', function (Group $proxy): void {
                        $proxy->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Publication\PublicationListAction::class)
                            ->setName('cup:publication:list');
                        $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Publication\PublicationCreateAction::class)
                            ->setName('cup:publication:add');
                        $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\PublicationUpdateAction::class)
                            ->setName('cup:publication:edit');
                        $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\PublicationDeleteAction::class)
                            ->setName('cup:publication:delete');
                        $proxy->map(['GET', 'POST'], '/preview', \App\Application\Actions\Cup\Publication\PublicationPreviewAction::class)
                            ->setName('cup:publication:preview');

                        // category
                        $proxy->group('/category', function (Group $proxy): void {
                            $proxy->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Publication\Category\CategoryListAction::class)
                                ->setName('cup:publication:category:list');
                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Publication\Category\CategoryCreateAction::class)
                                ->setName('cup:publication:category:add');
                            $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Publication\Category\CategoryUpdateAction::class)
                                ->setName('cup:publication:category:edit');
                            $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Publication\Category\CategoryDeleteAction::class)
                                ->setName('cup:publication:category:delete');
                        });
                    });

                    // forms
                    $proxy->group('/form', function (Group $proxy): void {
                        $proxy->get('', \App\Application\Actions\Cup\Form\FormListAction::class)
                            ->setName('cup:form:list');
                        $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Form\FormCreateAction::class)
                            ->setName('cup:form:add');
                        $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Form\FormUpdateAction::class)
                            ->setName('cup:form:edit');
                        $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\Form\FormDeleteAction::class)
                            ->setName('cup:form:delete');

                        // forms data
                        $proxy->group('/{uuid}/view', function (Group $proxy): void {
                            $proxy->map(['GET', 'POST'], '', \App\Application\Actions\Cup\Form\Data\DataListAction::class)
                                ->setName('cup:form:view:list');
                            $proxy->map(['GET', 'POST'], '/{data}', \App\Application\Actions\Cup\Form\Data\DataViewAction::class)
                                ->setName('cup:form:view:data');
                            $proxy->map(['GET', 'POST'], '/{data}/preview', \App\Application\Actions\Cup\Form\Data\DataPreviewAction::class)
                                ->setName('cup:form:view:preview');
                            $proxy->map(['GET', 'POST'], '/{data}/delete', \App\Application\Actions\Cup\Form\Data\DataDeleteAction::class)
                                ->setName('cup:form:view:delete');
                        });
                    });

                    // catalog
                    $proxy->group('/catalog', function (Group $proxy): void {
                        // statistic
                        $proxy->get('/statistic', \App\Application\Actions\Cup\Catalog\CategoryStatisticAction::class)
                            ->setName('cup:catalog:statistic');

                        // categories
                        $proxy->group('/category', function (Group $proxy): void {
                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Category\CategoryCreateAction::class)
                                ->setName('cup:catalog:category:add');
                            $proxy->map(['GET', 'POST'], '/{category}/edit', \App\Application\Actions\Cup\Catalog\Category\CategoryUpdateAction::class)
                                ->setName('cup:catalog:category:edit');
                            $proxy->map(['GET', 'POST'], '/{category}/delete', \App\Application\Actions\Cup\Catalog\Category\CategoryDeleteAction::class)
                                ->setName('cup:catalog:category:delete');
                            $proxy->get('[/{parent}]', \App\Application\Actions\Cup\Catalog\Category\CategoryListAction::class)
                                ->setName('cup:catalog:category:list');
                        });

                        // products
                        $proxy->group('/product', function (Group $proxy): void {
                            $proxy->get('/export', \App\Application\Actions\Cup\Catalog\Product\ProductExportAction::class)
                                ->setName('cup:catalog:product:export');
                            $proxy->post('/import', \App\Application\Actions\Cup\Catalog\Product\ProductImportAction::class)
                                ->setName('cup:catalog:product:import');

                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Product\ProductCreateAction::class)
                                ->setName('cup:catalog:product:add');
                            $proxy->map(['GET', 'POST'], '/{product}/edit', \App\Application\Actions\Cup\Catalog\Product\ProductUpdateAction::class)
                                ->setName('cup:catalog:product:edit');
                            $proxy->map(['GET', 'POST'], '/{product}/delete', \App\Application\Actions\Cup\Catalog\Product\ProductDeleteAction::class)
                                ->setName('cup:catalog:product:delete');
                            $proxy->get('[/{category}]', \App\Application\Actions\Cup\Catalog\Product\ProductListAction::class)
                                ->setName('cup:catalog:product:list');
                        });

                        // attribute
                        $proxy->group('/attribute', function (Group $proxy): void {
                            $proxy->get('', \App\Application\Actions\Cup\Catalog\Attribute\AttributeListAction::class)
                                ->setName('cup:catalog:attribute:list');
                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Attribute\AttributeCreateAction::class)
                                ->setName('cup:catalog:attribute:add');
                            $proxy->map(['GET', 'POST'], '/{attribute}/edit', \App\Application\Actions\Cup\Catalog\Attribute\AttributeUpdateAction::class)
                                ->setName('cup:catalog:attribute:edit');
                            $proxy->map(['GET', 'POST'], '/{attribute}/delete', \App\Application\Actions\Cup\Catalog\Attribute\AttributeDeleteAction::class)
                                ->setName('cup:catalog:attribute:delete');
                        });

                        // order
                        $proxy->group('/order', function (Group $proxy): void {
                            $proxy->get('/export', \App\Application\Actions\Cup\Catalog\Order\OrderExportAction::class)
                                ->setName('cup:catalog:order:export');

                            $proxy->get('', \App\Application\Actions\Cup\Catalog\Order\OrderListAction::class)
                                ->setName('cup:catalog:order:list');
                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Catalog\Order\OrderCreateAction::class)
                                ->setName('cup:catalog:order:add');
                            $proxy->map(['GET', 'POST'], '/{order}/edit', \App\Application\Actions\Cup\Catalog\Order\OrderUpdateAction::class)
                                ->setName('cup:catalog:order:edit');
                            $proxy->map(['GET', 'POST'], '/{order}/delete', \App\Application\Actions\Cup\Catalog\Order\OrderDeleteAction::class)
                                ->setName('cup:catalog:order:delete');
                            $proxy->map(['GET', 'POST'], '/{order}/invoice', \App\Application\Actions\Cup\Catalog\Order\OrderInvoiceAction::class)
                                ->setName('cup:catalog:order:invoice');
                            $proxy->map(['GET', 'POST'], '/{order}/dispatch', \App\Application\Actions\Cup\Catalog\Order\OrderDispatchAction::class)
                                ->setName('cup:catalog:order:dispatch');
                        });
                    });

                    // guestbook
                    $proxy->group('/guestbook', function (Group $proxy): void {
                        $proxy->map(['GET', 'POST'], '', \App\Application\Actions\Cup\GuestBook\GuestBookListAction::class)
                            ->setName('cup:guestbook:list');
                        $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\GuestBook\GuestBookUpdateAction::class)
                            ->setName('cup:guestbook:edit');
                        $proxy->map(['GET', 'POST'], '/{uuid}/delete', \App\Application\Actions\Cup\GuestBook\GuestBookDeleteAction::class)
                            ->setName('cup:guestbook:delete');
                    });

                    // files
                    $proxy->group('/file', function (Group $proxy): void {
                        // small text-editor api
                        $proxy->group('/image', function (Group $proxy): void {
                            $proxy->get('', \App\Application\Actions\Cup\File\Image\GetAction::class)
                                ->setName('cup:file:image:get');
                            $proxy->post('/delete', \App\Application\Actions\Cup\File\Image\DeleteAction::class)
                                ->setName('cup:file:image:delete');
                        });

                        $proxy->get('', \App\Application\Actions\Cup\File\FileListAction::class)
                            ->setName('cup:file:list');
                        $proxy->any('/{uuid}/delete', \App\Application\Actions\Cup\File\FileDeleteAction::class)
                            ->setName('cup:file:delete');
                    });

                    // reference
                    $proxy->group('/reference', function (Group $proxy): void {
                        $proxy->group('/{entity}', function (Group $proxy): void {
                            $proxy->get('', \App\Application\Actions\Cup\Reference\ReferenceListAction::class)
                                ->setName('cup:reference:list');
                            $proxy->map(['GET', 'POST'], '/add', \App\Application\Actions\Cup\Reference\ReferenceCreateAction::class)
                                ->setName('cup:reference:add');
                            $proxy->map(['GET', 'POST'], '/{uuid}/edit', \App\Application\Actions\Cup\Reference\ReferenceUpdateAction::class)
                                ->setName('cup:reference:edit');
                        });

                        $proxy->post('/{uuid}/delete', \App\Application\Actions\Cup\Reference\ReferenceDeleteAction::class)
                            ->setName('cup:reference:delete');
                    });

                    // editor
                    $proxy->group('/editor', function (Group $proxy): void {
                        $proxy->map(['GET', 'POST'], '[/{file:.*}]', \App\Application\Actions\Cup\EditorPageAction::class)
                            ->setName('cup:editor');
                    });

                    // log viewer
                    $proxy->get('/logs', \App\Application\Actions\Cup\LogPageAction::class)
                        ->setName('cup:logs');

                    // task add to queue
                    $proxy->post('/task/run', \App\Application\Actions\Cup\TaskRunAction::class)
                        ->setName('cup:task:run');

                    // cup entity getter/setter
                    $proxy
                        ->map(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/api/v1/{args:.*}', \App\Application\Actions\Api\v1\EntityAction::class)
                        ->setName('cup:api:v1:entity')
                        ->add(\Slim\Middleware\BodyParsingMiddleware::class);
                })
                ->add(new \Slim\HttpCache\Cache('private', 0, true));

            // COMMON section
            $proxy
                ->group('', function (Group $proxy) use ($container): void {
                    // main path
                    $proxy
                        ->get('/', \App\Application\Actions\Common\MainPageAction::class)
                        ->setName('common:main')
                        ->add(new \Slim\HttpCache\Cache('public', 60 * 60));

                    // user
                    $proxy
                        ->group('/user', function (Group $proxy): void {
                            $proxy
                                ->map(['GET', 'POST'], '/login', \App\Application\Actions\Common\User\UserLoginAction::class)
                                ->setName('common:user:login')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

                            $proxy
                                ->map(['GET', 'POST'], '/register', \App\Application\Actions\Common\User\UserRegisterAction::class)
                                ->setName('common:user:register')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

                            $proxy->map(['GET', 'POST'], '/revoke', \App\Application\Actions\Common\User\UserRevokeTokenAction::class)
                                ->setName('common:user:revoke')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

                            $proxy->map(['GET', 'POST'], '/logout', \App\Application\Actions\Common\User\UserLogoutAction::class)
                                ->setName('common:user:logout');

                            $proxy
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

                            $proxy
                                ->map(['GET', 'POST'], '/subscriber', \App\Application\Actions\Common\User\UserSubscribeAction::class)
                                ->setName('common:user:subscriber')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                        })
                        ->add(new \Slim\HttpCache\Cache('private', 0, true));

                    // other PRIVATE section
                    $proxy
                        ->group('', function (Group $proxy): void {
                            $proxy
                                ->map(['GET', 'POST'], '/search', \App\Application\Actions\Common\SearchAction::class)
                                ->setName('common:search')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

                            $proxy
                                ->map(['GET', 'POST'], '/cart', \App\Application\Actions\Common\Catalog\CartAction::class)
                                ->setName('common:catalog:cart')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

                            // view order confirm
                            $proxy
                                ->get('/cart/done/{order}', \App\Application\Actions\Common\Catalog\CartDoneAction::class)
                                ->setName('common:catalog:cart:done')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                        })
                        ->add(new \Slim\HttpCache\Cache('private', 0, true));

                    // other PUBLIC section
                    $proxy
                        ->group('', function (Group $proxy) use ($container): void {
                            // publication
                            $proxy
                                ->group('', function (Group $proxy) use ($container): void {
                                    $publicationCategoryService = $container->get(\App\Domain\Service\Publication\CategoryService::class);

                                    if (($categories = $publicationCategoryService->read()) !== null) {
                                        $categoryPath = $categories->pluck('address')->implode('|');

                                        // view categories and products
                                        $proxy
                                            ->get("/{category:{$categoryPath}}[/{args:.*}]", \App\Application\Actions\Common\Publication\ListAction::class)
                                            ->setName('common:publication:list')
                                            ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                                    }
                                });

                            // file
                            $proxy
                                ->group('/file', function (Group $proxy): void {
                                    $proxy->get('/get/{salt}/{hash}', \App\Application\Actions\Common\File\FileGetAction::class)
                                        ->setName('common:file:get');

                                    $proxy->get('/view/{salt}/{hash}', \App\Application\Actions\Common\File\FileViewAction::class)
                                        ->setName('common:file:view');

                                    $proxy->post('/upload', \App\Application\Actions\Common\File\FileUploadAction::class)
                                        ->setName('common:file:upload')
                                        ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                                });

                            // form
                            $proxy
                                ->post('/form/{unique}', \App\Application\Actions\Common\FormAction::class)
                                ->setName('common:form')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

                            // catalog
                            $proxy
                                ->group('', function (Group $proxy): void {
                                    // view categories and products
                                    $proxy
                                        ->get('/catalog[/{args:.*}]', \App\Application\Actions\Common\Catalog\ListAction::class)
                                        ->setName('common:catalog:list')
                                        ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);
                                });

                            // guest book
                            $proxy
                                ->map(['GET', 'POST'], '/guestbook[/{page:[0-9]+}}]', \App\Application\Actions\Common\GuestBookAction::class)
                                ->setName('common:guestbook')
                                ->add(\App\Application\Middlewares\IsRouteEnabledMiddleware::class);

                            // xml files
                            $proxy->get('/xml/{name}', \App\Application\Actions\Common\XMLFileAction::class)
                                ->setName('common:xml');

                            // page
                            $proxy->get('/{args:.*}', \App\Application\Actions\Common\PageAction::class)
                                ->setName('common:page');
                        })
                        ->add(new \Slim\HttpCache\Cache('public', 60 * 60));
                })
                ->add(\App\Application\Middlewares\IsSiteEnabledMiddleware::class);
        })
        ->add(\App\Application\Middlewares\LocaleMiddleware::class)
        ->add(\App\Application\Middlewares\AccessCheckerMiddleware::class)
        ->add(\App\Application\Middlewares\AuthorizationMiddleware::class);
};

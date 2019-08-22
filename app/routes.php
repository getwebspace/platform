<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app
    ->group('/cup', function (App $app) {
        $app->map(['get', 'post'], '/login', \Application\Actions\Cup\LoginPageAction::class);

        $app
            ->group('', function (App $app) {
                // main page
                $app->get('', \Application\Actions\Cup\MainPageAction::class);

                // settings
                $app->map(['get', 'post'], '/parameters', \Application\Actions\Cup\ParametersPageAction::class);

                // users
                $app->group('/user', function (App $app) {
                    $app->map(['get', 'post'], '', \Application\Actions\Cup\User\UserListAction::class);
                    $app->map(['get', 'post'], '/add', \Application\Actions\Cup\User\UserCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \Application\Actions\Cup\User\UserUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \Application\Actions\Cup\User\UserDeleteAction::class);
                });

                // static pages
                $app->group('/page', function (App $app) {
                    $app->map(['get', 'post'], '', \Application\Actions\Cup\Page\PageListAction::class);
                    $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Page\PageCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \Application\Actions\Cup\Page\PageUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \Application\Actions\Cup\Page\PageDeleteAction::class);
                });

                // publications
                $app->group('/publication', function (App $app) {
                    $app->map(['get', 'post'], '', \Application\Actions\Cup\Publication\PublicationListAction::class);
                    $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Publication\PublicationCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \Application\Actions\Cup\Publication\PublicationUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \Application\Actions\Cup\Publication\PublicationDeleteAction::class);
                    $app->map(['get', 'post'], '/preview', \Application\Actions\Cup\Publication\PublicationPreviewAction::class);

                    // category
                    $app->group('/category', function (App $app) {
                        $app->map(['get', 'post'], '', \Application\Actions\Cup\Publication\Category\CategoryListAction::class);
                        $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Publication\Category\CategoryCreateAction::class);
                        $app->map(['get', 'post'], '/{uuid}/edit', \Application\Actions\Cup\Publication\Category\CategoryUpdateAction::class);
                        $app->map(['get', 'post'], '/{uuid}/delete', \Application\Actions\Cup\Publication\Category\CategoryDeleteAction::class);
                    });
                });

                // forms
                $app->group('/form', function (App $app) {
                    $app->get('', \Application\Actions\Cup\Form\FormListAction::class);
                    $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Form\FormCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \Application\Actions\Cup\Form\FormUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \Application\Actions\Cup\Form\FormDeleteAction::class);

                    // forms data
                    $app->group('/{uuid}/view', function (App $app) {
                        $app->map(['get', 'post'], '', \Application\Actions\Cup\Form\Data\DataListAction::class);
                        $app->map(['get', 'post'], '/{data}', \Application\Actions\Cup\Form\Data\DataViewAction::class);
                        $app->map(['get', 'post'], '/{data}/delete', \Application\Actions\Cup\Form\Data\DataDeleteAction::class);
                    });
                });

                // catalog
                $app->group('/catalog', function (App $app) {
                    // categories
                    $app->group('/category', function (App $app) {
                        $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Catalog\Category\CategoryCreateAction::class);
                        $app->map(['get', 'post'], '/{category}/edit', \Application\Actions\Cup\Catalog\Category\CategoryUpdateAction::class);
                        $app->map(['get', 'post'], '/{category}/delete', \Application\Actions\Cup\Catalog\Category\CategoryDeleteAction::class);
                        $app->get('[/{parent}]', \Application\Actions\Cup\Catalog\Category\CategoryListAction::class);
                    });

                    // products
                    $app->group('/product', function (App $app) {
                        $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Catalog\Product\ProductCreateAction::class);
                        $app->map(['get', 'post'], '/{product}/edit', \Application\Actions\Cup\Catalog\Product\ProductUpdateAction::class);
                        $app->map(['get', 'post'], '/{product}/delete', \Application\Actions\Cup\Catalog\Product\ProductDeleteAction::class);
                        $app->get('[/{category}]', \Application\Actions\Cup\Catalog\Product\ProductListAction::class);
                    });

                    // order
                    $app->group('/order', function (App $app) {
                        $app->get('', \Application\Actions\Cup\Catalog\Order\OrderListAction::class);
                        $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Catalog\Order\OrderCreateAction::class);
                        $app->map(['get', 'post'], '/{order}/edit', \Application\Actions\Cup\Catalog\Order\OrderUpdateAction::class);
                        $app->map(['get', 'post'], '/{order}/delete', \Application\Actions\Cup\Catalog\Order\OrderDeleteAction::class);
                    });
                });

                // guestbook
                $app->group('/guestbook', function (App $app) {
                    $app->map(['get', 'post'], '', \Application\Actions\Cup\GuestBook\GuestBookListAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \Application\Actions\Cup\GuestBook\GuestBookUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \Application\Actions\Cup\GuestBook\GuestBookDeleteAction::class);
                });

                // files
                $app->group('/file', function (App $app) {
                    $app->get('', \Application\Actions\Cup\File\FileListAction::class);
                    $app->any('/{uuid}/delete', \Application\Actions\Cup\File\FileDeleteAction::class);
                });

                // docs
                $app->get('/docs', \Application\Actions\Cup\DocsPageAction::class);

                // dev console
                $app->post('/console', '\RunTracy\Controllers\RunTracyConsole:index');
            })
            ->add(function (Request $request, Response $response, $next) {
                $user = $request->getAttribute('user', false);

                if ($user === false || $user->level !== \Domain\Types\UserLevelType::LEVEL_ADMIN) {
                    return $response->withHeader('Location', '/cup/login?redirect=' . $request->getUri()->getPath());
                }

                return $next($request, $response);
            });
    });

// main path
$app->get('/', \Application\Actions\Common\MainPageAction::class);

// file worker
$app->group('/file', function (App $app) {
    $app->get('/get/{salt}/{hash}', \Application\Actions\Common\File\FileGetAction::class);
    $app->post('/upload', \Application\Actions\Common\File\FileUploadAction::class);
});

// form worker
$app->post('/form/{unique}', \Application\Actions\Common\FormAction::class);

// catalog worker
$app->get('/catalog[/{args:.*}]', \Application\Actions\Common\CatalogAction::class);

// guestbook worker
$app->map(['get', 'post'], '/guestbook[/{page:[0-9]+}}]', \Application\Actions\Common\GuestBookAction::class);

// dynamic path handler
$app->get('/{args:.*}', \Application\Actions\Common\DynamicPageAction::class);

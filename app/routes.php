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
                    $app->get('', \Application\Actions\Cup\Catalog\Category\CategoryListAction::class);
                    $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Catalog\Category\CategoryCreateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/edit', \Application\Actions\Cup\Catalog\Category\CategoryUpdateAction::class);
                    $app->map(['get', 'post'], '/{uuid}/delete', \Application\Actions\Cup\Catalog\Category\CategoryDeleteAction::class);

                    // catalog products
                    $app->group('/{uuid}/product', function (App $app) {
                        $app->get('', \Application\Actions\Cup\Catalog\Product\ProductListAction::class);
                        $app->map(['get', 'post'], '/add', \Application\Actions\Cup\Catalog\Product\ProductCreateAction::class);
                        $app->map(['get', 'post'], '/{product}/edit', \Application\Actions\Cup\Catalog\Product\ProductUpdateAction::class);

                        // delete
                        $app->map(['get', 'post'], '/{product}/delete', function (Request $request, Response $response, $args = []) {
                            if (
                                $args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid']) &&
                                $args['product'] && Ramsey\Uuid\Uuid::isValid($args['product'])
                            ) {
                                /** @var \Entity\Catalog\Category $item */
                                $item = $this->get(\Resource\Catalog\Product::class)->fetchOne(['uuid' => $args['uuid'], 'category' => $args['uuid']]);

                                if (!$item->isEmpty() && $request->isPost()) {
                                    $this->get(\Resource\Catalog\Category::class)->remove([
                                        'uuid' => $item->uuid,
                                    ]);
                                }
                            }

                            return $response->withAddedHeader('Location', '/cup/catalog');
                        });
                    });
                });

                // guestbook
                $app->group('/guestbook', function (App $app) {
                    // list
                    $app->map(['get', 'post'], '', function (Request $request, Response $response) {
                        $list = $this->get(\Resource\GuestBook::class)->fetch();

                        return $this->template->render($response, 'cup/guestbook/index.twig', ['list' => $list]);
                    });

                    // edit
                    $app->map(['get', 'post'], '/{uuid}/edit', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\GuestBook $item */
                            $item = $this->get(\Resource\GuestBook::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty()) {
                                if ($request->isPost()) {
                                    $data = [
                                        'uuid' => $item->uuid,
                                        'message' => $request->getParam('message'),
                                        'date' => $request->getParam('date'),
                                        //'status' => $request->getParam('status'),
                                    ];

                                    $check = \Filter\GuestBook::check($data);

                                    if ($check === true) { // todo потом узнать как работает
                                        try {
                                            $this->get(\Resource\GuestBook::class)->flush($data);

                                            return $response->withAddedHeader('Location', '/cup/guestbook');
                                        } catch (Exception $e) {
                                            // todo nothing
                                        }
                                    }
                                }

                                return $this->template->render($response, 'cup/guestbook/form.twig', ['item' => $item]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/guestbook');
                    });

                    // delete
                    $app->map(['get', 'post'], '/{uuid}/delete', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\GuestBook $item */
                            $item = $this->get(\Resource\GuestBook::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty() && $request->isPost()) {
                                $this->get(\Resource\GuestBook::class)->remove([
                                    'uuid' => $item->uuid
                                ]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/guestbook');
                    });
                });

                // docs
                $app->get('/docs', \Application\Actions\Cup\DocsPageAction::class);
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
$app->any('/', \Application\Actions\Common\MainPageAction::class);

// file worker
$app->group('/file', function (App $app) {
    $app->get('/get/{salt}/{hash}', \Application\Actions\Common\FileGetAction::class);
    $app->post('/upload', \Application\Actions\Common\FileUploadAction::class);
});

// form worker
$app->any('/form/{unique}', \Application\Actions\Common\FormAddDataAction::class);

// dynamic path handler
$app->any('/{args:.*}', \Application\Actions\Common\DynamicPageAction::class);

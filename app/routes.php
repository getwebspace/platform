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
                    // list
                    $app->get('', function (Request $request, Response $response) {
                        $category = $this->get(\Resource\Catalog\Category::class)->fetch();

                        return $this->template->render($response, 'cup/catalog/category/index.twig', [
                            'category' => $category,
                        ]);
                    });

                    // add
                    $app->map(['get', 'post'], '/add', function (Request $request, Response $response) {
                        if ($request->isPost()) {
                            $data = [
                                'parent' => $request->getParam('parent'),
                                'title' => $request->getParam('title'),
                                'description' => $request->getParam('description'),
                                'address' => $request->getParam('address'),
                                'field1' => $request->getParam('field1'),
                                'field2' => $request->getParam('field2'),
                                'field3' => $request->getParam('field3'),
                                'order' => $request->getParam('order'),
                                'meta' => $request->getParam('meta'),
                                'template' => $request->getParam('template'),
                            ];

                            $check = \Filter\Catalog\Category::check($data);

                            if ($check === true) {
                                try {
                                    $this->get(\Resource\Catalog\Category::class)->flush($data);

                                    return $response->withAddedHeader('Location', '/cup/catalog');
                                } catch (Exception $e) {
                                    // todo nothing
                                }
                            }
                        }

                        $category = $this->get(\Resource\Catalog\Category::class)->fetch();

                        return $this->template->render($response, 'cup/catalog/category/form.twig', [
                            'category' => $category,
                        ]);
                    });

                    // edit
                    $app->map(['get', 'post'], '/{uuid}/edit', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\Catalog\Category $item */
                            $item = $this->get(\Resource\Catalog\Category::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty()) {
                                if ($request->isPost()) {
                                    $data = [
                                        'uuid' => $item->uuid,
                                        'parent' => $request->getParam('parent'),
                                        'title' => $request->getParam('title'),
                                        'description' => $request->getParam('description'),
                                        'address' => $request->getParam('address'),
                                        'field1' => $request->getParam('field1'),
                                        'field2' => $request->getParam('field2'),
                                        'field3' => $request->getParam('field3'),
                                        'order' => $request->getParam('order'),
                                        'meta' => $request->getParam('meta'),
                                        'template' => $request->getParam('template'),
                                    ];

                                    $check = \Filter\Catalog\Category::check($data);

                                    if ($check === true) {
                                        try {
                                            $this->get(\Resource\Catalog\Category::class)->flush($data);

                                            return $response->withAddedHeader('Location', '/cup/catalog');
                                        } catch (Exception $e) {
                                            pre($e->getMessage());
                                            exit;
                                            // todo nothing
                                        }
                                    }
                                }

                                $category = $this->get(\Resource\Catalog\Category::class)->fetch();

                                return $this->template->render($response, 'cup/catalog/category/form.twig', ['category' => $category, 'item' => $item]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/catalog');
                    });

                    // delete
                    $app->map(['get', 'post'], '/{uuid}/delete', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\Catalog\Category $item */
                            $item = $this->get(\Resource\Catalog\Category::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty() && $request->isPost()) {
                                $this->get(\Resource\Catalog\Category::class)->remove([
                                    'uuid' => $item->uuid,
                                ]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/catalog');
                    });

                    // products
                    $app->group('/{uuid}/product', function (App $app) {
                        // list
                        $app->get('', function (Request $request, Response $response, $args = []) {
                            if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                                /** @var \Entity\Catalog\Category $category */
                                $category = $this->get(\Resource\Catalog\Category::class)->fetchOne(['uuid' => $args['uuid']]);

                                if (!$category->isEmpty()) {
                                    /** @var \Entity\Catalog\Product $product */
                                    $product = $this->get(\Resource\Catalog\Product::class)->fetch(['category' => $args['uuid']]);

                                    return $this->template->render($response, 'cup/catalog/product/index.twig', ['category' => $category, 'product' => $product]);
                                }
                            }

                            return $response->withAddedHeader('Location', '/cup/catalog');
                        });

                        // add
                        $app->map(['get', 'post'], '/add', function (Request $request, Response $response, $args = []) {
                            if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                                /** @var \Entity\Catalog\Category $category */
                                $category = $this->get(\Resource\Catalog\Category::class)->fetchOne(['uuid' => $args['uuid']]);

                                if (!$category->isEmpty()) {
                                    if ($request->isPost()) {
                                        $data = [
                                            'category' => $request->getParam('category'),
                                            'title' => $request->getParam('title'),
                                            'description' => $request->getParam('description'),
                                            'extra' => $request->getParam('extra'),
                                            'address' => $request->getParam('address'),
                                            'vendorcode' => $request->getParam('vendorcode'),
                                            'barcode' => $request->getParam('barcode'),
                                            'priceFirst' => $request->getParam('priceFirst'),
                                            'price' => $request->getParam('price'),
                                            'priceWholesale' => $request->getParam('priceWholesale'),
                                            'volume' => $request->getParam('volume'),
                                            'unit' => $request->getParam('unit'),
                                            'stock' => $request->getParam('stock'),
                                            'field1' => $request->getParam('field1'),
                                            'field2' => $request->getParam('field2'),
                                            'field3' => $request->getParam('field3'),
                                            'field4' => $request->getParam('field4'),
                                            'field5' => $request->getParam('field5'),
                                            'country' => $request->getParam('country'),
                                            'manufacturer' => $request->getParam('manufacturer'),
                                            'order' => $request->getParam('order'),
                                            'meta' => $request->getParam('meta'),
                                        ];

                                        $check = \Filter\Catalog\Product::check($data);

                                        if ($check === true) {
                                            try {
                                                $this->get(\Resource\Catalog\Product::class)->flush($data);

                                                return $response->withAddedHeader('Location', '/cup/catalog/' . $category->uuid . '/product' );
                                            } catch (Exception $e) {
                                                // todo nothing
                                            }
                                        }
                                    }

                                    return $this->template->render($response, 'cup/catalog/product/form.twig', ['category' => $category]);
                                }
                            }

                            return $response->withAddedHeader('Location', '/cup/catalog');
                        });

                        // edit
                        $app->map(['get', 'post'], '/{product}/edit', function (Request $request, Response $response, $args = []) {
                            if (
                                $args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid']) &&
                                $args['product'] && Ramsey\Uuid\Uuid::isValid($args['product'])
                            ) {
                                /** @var \Entity\Catalog\Category $category */
                                $category = $this->get(\Resource\Catalog\Category::class)->fetchOne(['uuid' => $args['uuid']]);
                                /** @var \Entity\Catalog\Product $product */
                                $product = $this->get(\Resource\Catalog\Product::class)->fetchOne(['uuid' => $args['product'], 'category' => $args['uuid']]);

                                if (!$category->isEmpty() && !$product->isEmpty()) {
                                    if ($request->isPost()) {
                                        $data = [
                                            'uuid' => $product->uuid,
                                            'category' => $request->getParam('category'),
                                            'title' => $request->getParam('title'),
                                            'description' => $request->getParam('description'),
                                            'extra' => $request->getParam('extra'),
                                            'address' => '', //$request->getParam('address'),
                                            'vendorcode' => $request->getParam('vendorcode'),
                                            'barcode' => $request->getParam('barcode'),
                                            'priceFirst' => $request->getParam('priceFirst'),
                                            'price' => $request->getParam('price'),
                                            'priceWholesale' => $request->getParam('priceWholesale'),
                                            'volume' => $request->getParam('volume'),
                                            'unit' => $request->getParam('unit'),
                                            'stock' => $request->getParam('stock'),
                                            'field1' => $request->getParam('field1'),
                                            'field2' => $request->getParam('field2'),
                                            'field3' => $request->getParam('field3'),
                                            'field4' => $request->getParam('field4'),
                                            'field5' => $request->getParam('field5'),
                                            'country' => $request->getParam('country'),
                                            'manufacturer' => $request->getParam('manufacturer'),
                                            'order' => $request->getParam('order'),
                                            'meta' => $request->getParam('meta'),
                                        ];

                                        $check = \Filter\Catalog\Product::check($data);

                                        if ($check === true) {
                                            try {
                                                $this->get(\Resource\Catalog\Product::class)->flush($data);

                                                return $response->withAddedHeader('Location', '/cup/catalog/' . $category->uuid . '/product' );
                                            } catch (Exception $e) {
                                                // todo nothing
                                            }
                                        }
                                    }

                                    return $this->template->render($response, 'cup/catalog/product/form.twig', ['category' => $category, 'item' => $product]);
                                }
                            }

                            return $response->withAddedHeader('Location', '/cup/catalog');
                        });

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

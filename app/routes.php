<?php

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app
    ->group('/cup', function (App $app) {
        $app->map(['get', 'post'], '/login', \Application\Actions\Cup\LoginPageAction::class);

        $app
            ->group('', function (App $app) {
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

//                $app->group('/page', function (App $app) {
//                    // list
//                    $app->map(['get', 'post'], '', function (Request $request, Response $response) {
//                        $list = $this->get(\Resource\Page::class)->fetch();
//
//                        return $this->template->render($response, 'cup/page/index.twig', ['list' => $list]);
//                    });
//
//                    // add
//                    $app->map(['get', 'post'], '/add', function (Request $request, Response $response, $args = []) {
//                        if ($request->isPost()) {
//                            $data = [
//                                'title' => $request->getParam('title'),
//                                'address' => $request->getParam('address'),
//                                'date' => $request->getParam('date'),
//                                'content' => $request->getParam('content'),
//                                'type' => $request->getParam('type'),
//                                'meta' => $request->getParam('meta'),
//                                'template' => $request->getParam('template'),
//                            ];
//
//                            $check = \Filter\Page::check($data);
//
//                            if ($check === true) {
//                                try {
//                                    $this->get(\Resource\Page::class)->flush($data);
//
//                                    return $response->withAddedHeader('Location', '/cup/page');
//                                } catch (Exception $e) {
//                                    // todo nothing
//                                }
//                            }
//                        }
//
//                        return $this->template->render($response, 'cup/page/form.twig');
//                    });
//
//                    // edit
//                    $app->map(['get', 'post'], '/{uuid}/edit', function (Request $request, Response $response, $args = []) {
//                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
//                            /** @var \Entity\User $item */
//                            $item = $this->get(\Resource\Page::class)->fetchOne(['uuid' => $args['uuid']]);
//
//                            if (!$item->isEmpty()) {
//                                if ($request->isPost()) {
//                                    $data = [
//                                        'uuid' => $item->uuid,
//                                        'title' => $request->getParam('title'),
//                                        'address' => $request->getParam('address'),
//                                        'date' => $request->getParam('date'),
//                                        'content' => $request->getParam('content'),
//                                        'type' => $request->getParam('type'),
//                                        'meta' => $request->getParam('meta'),
//                                        'template' => $request->getParam('template'),
//                                    ];
//
//                                    $check = \Filter\Page::check($data);
//
//                                    if ($check === true) {
//                                        try {
//                                            $this->get(\Resource\Page::class)->flush($data);
//
//                                            return $response->withAddedHeader('Location', '/cup/page');
//                                        } catch (Exception $e) {
//                                            // todo nothing
//                                        }
//                                    }
//                                }
//
//                                return $this->template->render($response, 'cup/page/form.twig', ['item' => $item]);
//                            }
//                        }
//
//                        return $response->withAddedHeader('Location', '/cup/page');
//                    });
//
//                    // delete
//                    $app->map(['get', 'post'], '/{uuid}/delete', function (Request $request, Response $response, $args = []) {
//                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
//                            /** @var \Entity\User $item */
//                            $item = $this->get(\Resource\Page::class)->fetchOne(['uuid' => $args['uuid']]);
//
//                            if (!$item->isEmpty() && $request->isPost()) {
//                                $this->get(\Resource\Page::class)->remove([
//                                    'uuid' => $item->uuid,
//                                ]);
//                            }
//                        }
//
//                        return $response->withAddedHeader('Location', '/cup/page');
//                    });
//                });

                // publications
                $app->group('/publication', function (App $app) {
                    // list
                    $app->get('', function (Request $request, Response $response) {
                        $categories = $this->get(\Resource\Publication\Category::class)->fetch();
                        $publications = $this->get(\Resource\Publication::class)->fetch();

                        return $this->template->render($response, 'cup/publication/index.twig', [
                            'categories' => $categories,
                            'publications' => $publications,
                        ]);
                    });

                    // add
                    $app->map(['get', 'post'], '/add', function (Request $request, Response $response, $args = []) {
                        if ($request->isPost()) {
                            $data = [
                                'title' => $request->getParam('title'),
                                'address' => $request->getParam('address'),
                                'date' => $request->getParam('date'),
                                'category' => $request->getParam('category'),
                                'content' => $request->getParam('content'),
                                'poll' => $request->getParam('poll'),
                                'meta' => $request->getParam('meta')
                            ];

                            $check = \Filter\Publication::check($data);

                            if ($check === true) {
                                try {
                                    $this->get(\Resource\Publication::class)->flush($data);

                                    return $response->withAddedHeader('Location', '/cup/publication');
                                } catch (Exception $e) {
                                    // todo nothing
                                }
                            }
                        }

                        $list = $this->get(\Resource\Publication\Category::class)->fetch();

                        return $this->template->render($response, 'cup/publication/form.twig', ['list' => $list]);
                    });

                    // edit
                    $app->map(['get', 'post'], '/{uuid}/edit', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\Publication $item */
                            $item = $this->get(\Resource\Publication::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty()) {
                                if ($request->isPost()) {
                                    $data = [
                                        'uuid' => $item->uuid,
                                        'title' => $request->getParam('title'),
                                        'address' => $request->getParam('address'),
                                        'date' => $request->getParam('date'),
                                        'category' => $request->getParam('category'),
                                        'content' => $request->getParam('content'),
                                        'poll' => $request->getParam('poll'),
                                        'meta' => $request->getParam('meta')
                                    ];

                                    $check = \Filter\Publication::check($data);

                                    if ($check === true) {
                                        try {
                                            $this->get(\Resource\Publication::class)->flush($data);

                                            return $response->withAddedHeader('Location', '/cup/publication');
                                        } catch (Exception $e) {
                                            // todo nothing
                                        }
                                    }
                                }

                                $list = $this->get(\Resource\Publication\Category::class)->fetch();

                                return $this->template->render($response, 'cup/publication/form.twig', ['list' => $list, 'item' => $item]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/publication');
                    });

                    // delete
                    $app->map(['get', 'post'], '/{uuid}/delete', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\Publication $item */
                            $item = $this->get(\Resource\Publication::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty() && $request->isPost()) {
                                $this->get(\Resource\Publication::class)->remove([
                                    'uuid' => $item->uuid,
                                ]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/publication');
                    });

                    // preview
                    $app->map(['get', 'post'], '/preview', function (Request $request, Response $response) {
                        return $this->template->render($response, 'cup/publication/preview.twig');
                    });

                    // publications category
                    $app->group('/category', function (App $app) {
                        // list
                        $app->map(['get', 'post'], '', function (Request $request, Response $response) {
                            $list = $this->get(\Resource\Publication\Category::class)->fetch();

                            return $this->template->render($response, 'cup/publication/category/index.twig', ['list' => $list]);
                        });

                        // add
                        $app->map(['get', 'post'], '/add', function (Request $request, Response $response, $args = []) {
                            if ($request->isPost()) {
                                $data = [
                                    'title' => $request->getParam('title'),
                                    'address' => $request->getParam('address'),
                                    'description' => $request->getParam('description'),
                                    'parent' => $request->getParam('parent'),
                                    'pagination' => $request->getParam('pagination'),
                                    'sort' => $request->getParam('sort'),
                                    'meta' => $request->getParam('meta'),
                                    'template' => $request->getParam('template'),
                                ];

                                $check = \Filter\Publication\Category::check($data);

                                if ($check === true) {
                                    try {
                                        $this->get(\Resource\Publication\Category::class)->flush($data);

                                        return $response->withAddedHeader('Location', '/cup/publication/category');
                                    } catch (Exception $e) {
                                        // todo nothing
                                    }
                                }
                            }

                            $list = $this->get(\Resource\Publication\Category::class)->fetch();

                            return $this->template->render($response, 'cup/publication/category/form.twig', ['list' => $list]);
                        });

                        // edit
                        $app->map(['get', 'post'], '/{uuid}/edit', function (Request $request, Response $response, $args = []) {
                            if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                                /** @var \Entity\Publication\Category $item */
                                $item = $this->get(\Resource\Publication\Category::class)->fetchOne(['uuid' => $args['uuid']]);

                                if (!$item->isEmpty()) {
                                    if ($request->isPost()) {
                                        $data = [
                                            'uuid' => $item->uuid,
                                            'title' => $request->getParam('title'),
                                            'address' => $request->getParam('address'),
                                            'description' => $request->getParam('description'),
                                            'parent' => $request->getParam('parent'),
                                            'pagination' => $request->getParam('pagination'),
                                            'sort' => $request->getParam('sort'),
                                            'meta' => $request->getParam('meta'),
                                            'template' => $request->getParam('template'),
                                        ];

                                        $check = \Filter\Publication\Category::check($data);

                                        if ($check === true) {
                                            try {
                                                $this->get(\Resource\Publication\Category::class)->flush($data);

                                                return $response->withAddedHeader('Location', '/cup/publication/category');
                                            } catch (Exception $e) {
                                                // todo nothing
                                            }
                                        }
                                    }

                                    $list = $this->get(\Resource\Publication\Category::class)->fetch();

                                    return $this->template->render($response, 'cup/publication/category/form.twig', ['list' => $list, 'item' => $item]);
                                }
                            }

                            return $response->withAddedHeader('Location', '/cup/publication/category');
                        });

                        // delete
                        $app->map(['get', 'post'], '/{uuid}/delete', function (Request $request, Response $response, $args = []) {
                            if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                                /** @var \Entity\Publication\Category $item */
                                $item = $this->get(\Resource\Publication\Category::class)->fetchOne(['uuid' => $args['uuid']]);

                                if (!$item->isEmpty() && $request->isPost()) {
                                    $this->get(\Resource\Publication\Category::class)->remove([
                                        'uuid' => $item->uuid,
                                    ]);
                                }
                            }

                            return $response->withAddedHeader('Location', '/cup/publication/category');
                        });
                    });
                });

                // forms
                $app->group('/form', function (App $app) {
                    // list
                    $app->get('', function (Request $request, Response $response) {
                        $list = $this->get(\Resource\Form::class)->fetch();

                        return $this->template->render($response, 'cup/form/index.twig', [
                            'list' => $list,
                        ]);
                    });

                    // add
                    $app->map(['get', 'post'], '/add', function (Request $request, Response $response, $args = []) {
                        if ($request->isPost()) {
                            $data = [
                                'title' => $request->getParam('title'),
                                'address' => $request->getParam('address'),
                                'template' => $request->getParam('template'),
                                'mailto' => $request->getParam('mailto'),
                                'origin' => $request->getParam('origin'),
                            ];

                            $check = \Filter\Form::check($data);

                            if ($check === true) {
                                try {
                                    $this->get(\Resource\Form::class)->flush($data);

                                    return $response->withAddedHeader('Location', '/cup/form');
                                } catch (Exception $e) {
                                    // todo nothing
                                }
                            }
                        }

                        return $this->template->render($response, 'cup/form/form.twig');
                    });

                    // edit
                    $app->map(['get', 'post'], '/{uuid}/edit', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\Form $item */
                            $item = $this->get(\Resource\Form::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty()) {
                                if ($request->isPost()) {
                                    $data = [
                                        'uuid' => $item->uuid,
                                        'title' => $request->getParam('title'),
                                        'address' => $request->getParam('address'),
                                        'template' => $request->getParam('template'),
                                        'mailto' => $request->getParam('mailto'),
                                        'origin' => $request->getParam('origin'),
                                    ];

                                    $check = \Filter\Form::check($data);

                                    if ($check === true) {
                                        try {
                                            $this->get(\Resource\Form::class)->flush($data);

                                            return $response->withAddedHeader('Location', '/cup/form');
                                        } catch (Exception $e) {
                                            // todo nothing
                                        }
                                    }
                                }

                                return $this->template->render($response, 'cup/form/form.twig', ['item' => $item]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/form');
                    });

                    // delete
                    $app->map(['get', 'post'], '/{uuid}/delete', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\Form $item */
                            $item = $this->get(\Resource\Form::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty() && $request->isPost()) {
                                $this->get(\Resource\Form::class)->remove([
                                    'uuid' => $item->uuid,
                                ]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/form');
                    });

                    // form data view list
                    $app->map(['get', 'post'], '/{uuid}/view', function (Request $request, Response $response, $args = []) {
                        if ($args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
                            /** @var \Entity\Form $item */
                            $item = $this->get(\Resource\Form::class)->fetchOne(['uuid' => $args['uuid']]);

                            if (!$item->isEmpty()) {
                                $list = $this->get(\Resource\Form\Data::class)->fetch(['form_uuid' => $args['uuid']]);

                                return $this->template->render($response, 'cup/form/view/list.twig', [
                                    'form' => $item,
                                    'list' => $list,
                                ]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/form');
                    });

                    // form data view detail
                    $app->map(['get', 'post'], '/{uuid}/view/{data}', function (Request $request, Response $response, $args = []) {
                        if (
                            $args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid']) &&
                            $args['data'] && Ramsey\Uuid\Uuid::isValid($args['data'])
                        ) {
                            /** @var \Entity\Form\Data $item */
                            $item = $this->get(\Resource\Form\Data::class)->fetchOne([
                                'form_uuid' => $args['uuid'],
                                'uuid' => $args['data'],
                            ]);

                            if (!$item->isEmpty()) {
                                $files = $this->get(\Resource\File::class)->fetch([
                                    'item' => \Reference\File::ITEM_FORM_DATA,
                                    'item_uuid' => $args['data'],
                                ]);

                                return $this->template->render($response, 'cup/form/view/detail.twig', [
                                    'item' => $item,
                                    'files' => $files,
                                ]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/form');
                    });

                    // form data detail delete
                    $app->map(['get', 'post'], '/{uuid}/view/{data}/delete', function (Request $request, Response $response, $args = []) {
                        if (
                            $args['uuid'] && Ramsey\Uuid\Uuid::isValid($args['uuid']) &&
                            $args['data'] && Ramsey\Uuid\Uuid::isValid($args['data'])
                        ) {
                            /** @var \Entity\Form\Data $item */
                            $item = $this->get(\Resource\Form\Data::class)->fetchOne([
                                'form_uuid' => $args['uuid'],
                                'uuid' => $args['data'],
                            ]);

                            if (!$item->isEmpty() && $request->isPost()) {
                                $this->get(\Resource\Form\Data::class)->remove([
                                    'form_uuid' => $args['uuid'],
                                    'uuid' => $args['data'],
                                ]);
                            }
                        }

                        return $response->withAddedHeader('Location', '/cup/form/' . $args['uuid'] . '/view');
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

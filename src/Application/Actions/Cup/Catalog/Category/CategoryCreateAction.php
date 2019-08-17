<?php

namespace Application\Actions\Cup\Catalog\Category;

use AEngine\Support\Str;
use Application\Actions\Cup\Catalog\CatalogAction;
use Exception;

class CategoryCreateAction extends CatalogAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'parent' => $this->request->getParam('parent'),
                'title' => $this->request->getParam('title'),
                'description' => $this->request->getParam('description'),
                'address' => $this->request->getParam('address'),
                'field1' => $this->request->getParam('field1'),
                'field2' => $this->request->getParam('field2'),
                'field3' => $this->request->getParam('field3'),
                'product' => $this->request->getParam('product'),
                'order' => $this->request->getParam('order'),
                'meta' => $this->request->getParam('meta'),
                'template' => $this->request->getParam('template'),
                'external_id' => $this->request->getParam('external_id'),
            ];

            $check = \Domain\Filters\Catalog\Category::check($data);

            if ($check === true) {
                try {
                    $model = new \Domain\Entities\Catalog\Category($data);
                    $this->entityManager->persist($model);

                    /** @var \Psr\Http\Message\UploadedFileInterface[] $files */
                    $files = $this->request->getUploadedFiles()['files'] ?? false;

                    foreach ($files as $file) {
                        if ($file->getSize() && !$file->getError()) {
                            $salt = uniqid();
                            $name = Str::translate(strtolower($file->getClientFilename()));
                            $path = UPLOAD_DIR . '/' . $salt;

                            if (!file_exists($path)) {
                                mkdir($path);
                            }

                            // create model
                            $fileModel = new \Domain\Entities\File([
                                'name' => $name,
                                'type' => $file->getClientMediaType(),
                                'size' => (int)$file->getSize(),
                                'salt' => $salt,
                                'date' => new \DateTime(),
                                'item' => \Domain\Types\FileItemType::ITEM_CATALOG_CATEGORY,
                                'item_uuid' => $model->uuid,
                            ]);

                            $file->moveTo($path . '/' . $name);
                            $fileModel->set('hash', sha1_file($path . '/' . $name));

                            // save model
                            $this->entityManager->persist($fileModel);
                        }
                    }

                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/cup/catalog');
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        $category = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/catalog/category/form.twig', [
            'category' => $category,
            'fields' => $this->getParameter(['catalog_category_field_1', 'catalog_category_field_2', 'catalog_category_field_3']),
        ]);
    }
}

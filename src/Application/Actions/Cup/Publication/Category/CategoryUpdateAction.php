<?php

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use Exception;

class CategoryUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            /** @var \App\Domain\Entities\Publication\Category $item */
            $item = $this->categoryRepository->findOneBy(['uuid' => $this->resolveArg('uuid')]);

            if (!$item->isEmpty()) {
                if ($this->request->isPost()) {
                    $data = [
                        'uuid' => $item->uuid,
                        'title' => $this->request->getParam('title'),
                        'address' => $this->request->getParam('address'),
                        'description' => $this->request->getParam('description'),
                        'parent' => $this->request->getParam('parent'),
                        'children' => $this->request->getParam('children'),
                        'pagination' => $this->request->getParam('pagination'),
                        'sort' => $this->request->getParam('sort'),
                        'meta' => $this->request->getParam('meta'),
                        'template' => $this->request->getParam('template'),
                    ];

                    $check = \App\Domain\Filters\Publication\Category::check($data);

                    if ($check === true) {
                        try {
                            $item->replace($data);
                            $this->entityManager->persist($item);
                            $this->entityManager->flush();

                            return $this->response->withAddedHeader('Location', '/cup/publication/category');
                        } catch (Exception $e) {
                            // todo nothing
                        }
                    }
                }

                $list = collect($this->categoryRepository->findAll());

                return $this->respondRender('cup/publication/category/form.twig', ['list' => $list, 'item' => $item]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category');
    }
}

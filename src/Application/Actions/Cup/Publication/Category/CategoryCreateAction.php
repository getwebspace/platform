<?php

namespace Application\Actions\Cup\Publication\Category;

use Application\Actions\Cup\Publication\PublicationAction;
use Exception;

class CategoryCreateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'title' => $this->request->getParam('title'),
                'address' => $this->request->getParam('address'),
                'description' => $this->request->getParam('description'),
                'parent' => $this->request->getParam('parent'),
                'pagination' => $this->request->getParam('pagination'),
                'sort' => $this->request->getParam('sort'),
                'meta' => $this->request->getParam('meta'),
                'template' => $this->request->getParam('template'),
            ];

            $check = \Domain\Filters\Publication\Category::check($data);

            if ($check === true) {
                try {
                    $model = new \Domain\Entities\Publication\Category($data);
                    $this->entityManager->persist($model);
                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/cup/publication/category');
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        $list = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/publication/category/form.twig', ['list' => $list]);
    }
}

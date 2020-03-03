<?php

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
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
                'public' => $this->request->getParam('public'),
                'children' => $this->request->getParam('children'),
                'pagination' => $this->request->getParam('pagination'),
                'sort' => $this->request->getParam('sort'),
                'meta' => $this->request->getParam('meta'),
                'template' => $this->request->getParam('template'),
            ];

            $check = \App\Domain\Filters\Publication\Category::check($data);

            if ($check === true) {
                $model = new \App\Domain\Entities\Publication\Category($data);
                $model->removeFiles($this->handlerFileRemove());
                $model->addFiles($this->handlerFileUpload());

                $this->entityManager->persist($model);
                $this->entityManager->flush();

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
                    default:
                        return $this->response->withAddedHeader('Location', '/cup/publication/category/' . $model->uuid . '/edit')->withStatus(301);
                }
            }
        }

        $list = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/publication/category/form.twig', ['list' => $list]);
    }
}

<?php

namespace App\Application\Actions\Cup\Publication;

use Exception;

class PublicationCreateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'title' => $this->request->getParam('title'),
                'address' => $this->request->getParam('address'),
                'date' => $this->request->getParam('date'),
                'category' => $this->request->getParam('category'),
                'content' => $this->request->getParam('content'),
                'poll' => $this->request->getParam('poll'),
                'meta' => $this->request->getParam('meta'),
            ];

            $check = \App\Domain\Filters\Publication::check($data);

            if ($check === true) {
                try {
                    $model = new \App\Domain\Entities\Publication($data);
                    $this->entityManager->persist($model);
                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/cup/publication');
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        $list = collect($this->categoryRepository->findAll());

        return $this->respondRender('cup/publication/form.twig', ['list' => $list]);
    }
}

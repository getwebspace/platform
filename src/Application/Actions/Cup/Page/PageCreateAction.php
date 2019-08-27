<?php

namespace App\Application\Actions\Cup\Page;

use Exception;

class PageCreateAction extends PageAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'title' => $this->request->getParam('title'),
                'address' => $this->request->getParam('address'),
                'date' => $this->request->getParam('date'),
                'content' => $this->request->getParam('content'),
                'type' => $this->request->getParam('type'),
                'meta' => $this->request->getParam('meta'),
                'template' => $this->request->getParam('template'),
            ];

            $check = \App\Domain\Filters\Page::check($data);

            if ($check === true) {
                try {
                    $model = new \App\Domain\Entities\Page($data);
                    $this->entityManager->persist($model);
                    $this->entityManager->flush();

                    switch (true) {
                        case $this->request->getParam('save', 'exit') === 'exit':
                            return $this->response->withAddedHeader('Location', '/cup/page');
                        default:
                            return $this->response->withAddedHeader('Location', '/cup/page/' . $model->uuid . '/edit');
                    }
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        return $this->respondRender('cup/page/form.twig');
    }
}

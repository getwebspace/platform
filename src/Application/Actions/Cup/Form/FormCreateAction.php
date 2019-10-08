<?php

namespace App\Application\Actions\Cup\Form;

use Exception;

class FormCreateAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'title' => $this->request->getParam('title'),
                'address' => $this->request->getParam('address'),
                'template' => $this->request->getParam('template'),
                'mailto' => $this->request->getParam('mailto'),
                'origin' => $this->request->getParam('origin'),
            ];

            $check = \App\Domain\Filters\Form::check($data);

            if ($check === true) {
                try {
                    $model = new \App\Domain\Entities\Form($data);
                    $this->entityManager->persist($model);
                    $this->entityManager->flush();

                    switch (true) {
                        case $this->request->getParam('save', 'exit') === 'exit':
                            return $this->response->withAddedHeader('Location', '/cup/form')->withStatus(301);
                        default:
                            return $this->response->withAddedHeader('Location', '/cup/form/' . $model->uuid . '/edit')->withStatus(301);
                    }
                } catch (Exception $e) {
                    // todo nothing
                }
            }
        }

        return $this->respondRender('cup/form/form.twig');
    }
}

<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

class FormCreateAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'title' => $this->request->getParam('title'),
                'address' => $this->request->getParam('address'),
                'template' => $this->request->getParam('template'),
                'save_data' => $this->request->getParam('save_data'),
                'recaptcha' => $this->request->getParam('recaptcha'),
                'origin' => $this->request->getParam('origin'),
                'mailto' => $this->request->getParam('mailto'),
            ];

            $check = \App\Domain\Filters\Form::check($data);

            if ($check === true) {
                $model = new \App\Domain\Entities\Form($data);
                $this->entityManager->persist($model);
                $this->entityManager->flush();

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withAddedHeader('Location', '/cup/form')->withStatus(301);
                    default:
                        return $this->response->withAddedHeader('Location', '/cup/form/' . $model->uuid . '/edit')->withStatus(301);
                }
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        return $this->respondWithTemplate('cup/form/form.twig');
    }
}

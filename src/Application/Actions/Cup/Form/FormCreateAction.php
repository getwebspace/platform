<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

use App\Domain\Service\Form\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Form\Exception\MissingTitleValueException;
use App\Domain\Service\Form\Exception\TitleAlreadyExistsException;

class FormCreateAction extends FormAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $form = $this->formService->create([
                    'title' => $this->request->getParam('title'),
                    'address' => $this->request->getParam('address'),
                    'template' => $this->request->getParam('template'),
                    'recaptcha' => $this->request->getParam('recaptcha'),
                    'origin' => $this->request->getParam('origin'),
                    'mailto' => $this->request->getParam('mailto'),
                ]);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withRedirect('/cup/form');
                    default:
                        return $this->response->withRedirect('/cup/form/' . $form->getUuid() . '/edit');
                }
            } catch (MissingTitleValueException|TitleAlreadyExistsException $e) {
                $this->addError('title', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/form/form.twig');
    }
}

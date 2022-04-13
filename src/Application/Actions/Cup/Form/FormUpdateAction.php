<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

use App\Domain\Service\Form\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Form\Exception\TitleAlreadyExistsException;

class FormUpdateAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $form = $this->formService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($form) {
                if ($this->isPost()) {
                    try {
                        $form = $this->formService->update($form, [
                            'title' => $this->getParam('title'),
                            'address' => $this->getParam('address'),
                            'template' => $this->getParam('template'),
                            'templateFile' => $this->getParam('templateFile'),
                            'authorSend' => $this->getParam('authorSend'),
                            'recaptcha' => $this->getParam('recaptcha'),
                            'origin' => $this->getParam('origin'),
                            'mailto' => $this->getParam('mailto'),
                            'duplicate' => $this->getParam('duplicate'),
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:form:edit', $form);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect('/cup/form');

                            default:
                                return $this->respondWithRedirect('/cup/form/' . $form->getUuid() . '/edit');
                        }
                    } catch (TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/form/form.twig', ['item' => $form]);
            }
        }

        return $this->respondWithRedirect('/cup/form');
    }
}

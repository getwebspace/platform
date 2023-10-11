<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

class FormDeleteAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $form = $this->formService->read([
                'uuid' => $this->resolveArg('uuid'),
            ]);

            if ($form) {
                foreach ($this->formDataService->read(['form_uuid' => $this->resolveArg('uuid')]) as $item) {
                    $this->formDataService->delete($item);
                }

                $this->formService->delete($form);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:form:delete', $form);
            }
        }

        return $this->respondWithRedirect('/cup/form');
    }
}

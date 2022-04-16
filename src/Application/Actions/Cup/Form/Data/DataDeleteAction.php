<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;

class DataDeleteAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))
            && $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            $data = $this->formDataService->read([
                'uuid' => $this->resolveArg('data'),
            ]);

            if ($data) {
                $this->formDataService->delete($data);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:form:data:delete', $data);
            }
        }

        return $this->respondWithRedirect('/cup/form/' . $this->resolveArg('uuid') . '/view');
    }
}

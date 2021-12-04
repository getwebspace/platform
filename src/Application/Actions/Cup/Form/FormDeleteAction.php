<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form;

class FormDeleteAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $this->formService->delete($this->resolveArg('uuid'));
        }

        return $this->respondWithRedirect('/cup/form');
    }
}

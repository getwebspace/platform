<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;
use App\Domain\Service\Form\Exception\FormDataNotFoundException;

/**
 * "Not spam": the entry goes back to the normal list
 */
class DataRestoreAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if (
            $this->isPost()
            && $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))
            && $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            try {
                $this->formDataService->update($this->formDataService->read(['uuid' => $this->resolveArg('data')]), [
                    'is_spam' => false,
                    'spam_reason' => '',
                ]);
            } catch (FormDataNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect('/cup/form/' . $this->resolveArg('uuid') . '/view?spam=1');
    }
}

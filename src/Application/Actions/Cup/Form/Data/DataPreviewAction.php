<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Form\Data;

use App\Application\Actions\Cup\Form\FormAction;

class DataPreviewAction extends FormAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if (
            $this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))
            && $this->resolveArg('data') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('data'))
        ) {
            $application = $this->formDataService->read([
                'form_uuid' => $this->resolveArg('uuid'),
                'uuid' => $this->resolveArg('data'),
            ]);

            if ($application) {
                if (($message = $application->message) !== '') {
                    // handle field message
                    $this->response->getBody()->write($message);
                } else {
                    $form = $this->formService->read([
                        'uuid' => $application->form_uuid,
                    ]);

                    // prepare preview
                    if ($form->templateFile) {
                        $body = $this->render($form->templateFile, $application->data);
                    } elseif ($form->template) {
                        $body = $this->renderFromString($form->template, $application->data);
                    } else {
                        // json
                        $body = json_encode($application->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }

                    $this->response->getBody()->write($body);
                }

                return $this->response;
            }
        }

        return $this->response->withStatus(404);
    }
}

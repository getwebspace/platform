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
                'uuid' => $this->resolveArg('data'),
            ]);

            if ($application) {
                if (($message = $application->getMessage()) !== '') {
                    // handle field message
                    $this->response->getBody()->write($message);
                } else {
                    $form = $this->formService->read([
                        'uuid' => $application->getFormUuid()
                    ]);

                    // prepare preview
                    if ($form->getTemplateFile()) {
                        $body = $this->render($form->getTemplateFile(), $application->getData());
                    } elseif ($form->getTemplate()) {
                        $body = $this->renderFromString($form->getTemplate(), $application->getData());
                    } else {
                        // json
                        $body = json_encode($application->getData(), JSON_UNESCAPED_UNICODE);
                    }

                    $this->response->getBody()->write($body);
                }

                return $this->response;
            }
        }

        return $this->response->withStatus(404);
    }
}

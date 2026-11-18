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
            try {
                $application = $this->formDataService->read([
                    'form_uuid' => $this->resolveArg('uuid'),
                    'uuid' => $this->resolveArg('data'),
                ]);

                // what a visitor wrote is shown as text, never as markup of the panel
                if (($message = $application->message) !== '') {
                    $this->response->getBody()->write(nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE)));
                } else {
                    $form = $this->formService->read([
                        'uuid' => $application->form_uuid,
                    ]);

                    // prepare preview: the template of the form is the administrator's own, the values in it are escaped by Twig
                    if ($form->templateFile) {
                        $body = $this->render($form->templateFile, $application->data);
                    } elseif ($form->template) {
                        $body = $this->renderFromString($form->template, $application->data);
                    } else {
                        // json
                        $body = '<pre>' . htmlspecialchars((string) json_encode($application->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES | ENT_SUBSTITUTE) . '</pre>';
                    }

                    $this->response->getBody()->write($body);
                }

                return $this->response->withHeader('Content-Type', 'text/html; charset=utf-8');
            } catch (\App\Domain\Service\Form\Exception\FormDataNotFoundException|\App\Domain\Service\Form\Exception\FormNotFoundException $e) {
                // nothing
            }
        }

        return $this->response->withStatus(404);
    }
}

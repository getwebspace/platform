<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Entities\FileRelation;
use App\Domain\Entities\Form;
use App\Domain\Entities\Form\Data as FromData;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\Exception\FormNotFoundException;
use App\Domain\Service\Form\FormService;

class FormAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $formService = $this->container->get(FormService::class);
        $formDataService = $this->container->get(FormDataService::class);
        $notificationService = $this->container->get(NotificationService::class);

        try {
            /** @var Form $form */
            $form = $formService->read(['address' => $this->resolveArg('unique')]);

            if (
                (
                    empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest'
                ) && !empty($_SERVER['HTTP_REFERER'])
            ) {
                $this->response = $this->respondWithRedirect($_SERVER['HTTP_REFERER']);
            }

            if (!$form->getRecaptcha() || $this->isRecaptchaChecked()) {
                $remote = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? false;
                $data = $this->getParams();

                // CORS header sets
                foreach ($form->getOrigin() as $origin) {
                    if ($remote && mb_strpos($origin, $remote) >= 0) {
                        $this->response = $this->response->withHeader('Access-Control-Allow-Origin', $remote);

                        break;
                    }
                    if ($origin === '*') {
                        $this->response = $this->response->withHeader('Access-Control-Allow-Origin', '*');

                        break;
                    }
                }

                if ($this->response->hasHeader('Access-Control-Allow-Origin')) {
                    $this->response = $this->response->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
                }

                // prepare mailto
                $mailto = [];
                foreach (array_map('trim', $form->getMailto()) as $value) {
                    $buf = array_map('trim', explode(':', $value));

                    if (count($buf) === 2) {
                        $mailto[$buf[0]] = $buf[1];
                    } else {
                        $mailto[] = $buf[0];
                    }
                }

                // form data
                $formData = $formDataService->create([
                    'form_uuid' => $form->getUuid(),
                    'data' => $data,
                    'message' => $this->getParam('body', ''),
                ]);

                // prepare attachments
                $attachments = [];
                $json = [];
                if ($this->parameter('file_is_enabled', 'yes') === 'yes') {
                    /** @var FromData $formData */
                    $formData = $this->processEntityFiles($formData);

                    foreach ($formData->getFiles() as $file) {
                        /** @var FileRelation $file */
                        $attachments[$file->getFileName()] = $file->getPublicPath();
                        $json[] = [
                            'uuid' => $file->getUuid()->toString(),
                            'name' => $file->getFileName(),
                            'order' => $file->getOrder(),
                            'comment' => $file->getComment(),
                            'internal' => $file->getInternalPath(),
                            'public' => $file->getPublicPath(),
                        ];
                    }
                }

                // check if duplication is enabled
                if (($duplicate = $form->getDuplicate()) !== '') {
                    // send json task
                    $task = new \App\Domain\Tasks\SendJSONTask($this->container);
                    $task->execute([
                        'url' => $duplicate,
                        'data' => $data,
                        'files' => $json,
                    ]);

                    // run worker
                    \App\Domain\AbstractTask::worker($task);
                }

                // prepare mail params
                $params = [
                    'to' => $mailto,
                    'cc' => $form->getAuthorSend() && !empty($data['email']) ? $data['email'] : '',
                    'subject' => $form->getTitle(),
                    'body' => '',
                    'template' => '',
                    'data' => [],
                    'attachments' => $attachments,
                ];

                if (($buf = $this->getParam('body', false)) !== false) {
                    $params['body'] = $buf;
                } else {
                    $params['template'] = $form->getTemplateFile() ?: $form->getTemplate();
                    $params['data'] = $data;
                }

                // send mail task
                $task = new \App\Domain\Tasks\SendMailTask($this->container);
                $task->execute($params);

                // run worker
                \App\Domain\AbstractTask::worker($task);

                $this->container->get(\App\Application\PubSub::class)->publish('common:form:create', $params);

                return $this->respondWithJson(['status' => 'ok']);
            }

            $this->addError('grecaptcha', 'EXCEPTION_WRONG_GRECAPTCHA');
        } catch (FormNotFoundException $e) {
            // 404
            return $this->respond('p404.twig')->withStatus(404);
        }

        return $this->response->withStatus(500);
    }
}

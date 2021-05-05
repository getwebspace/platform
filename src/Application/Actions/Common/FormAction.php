<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Domain\AbstractAction;
use App\Domain\Exceptions\HttpNotFoundException;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use DateTime;
use Slim\Http\UploadedFile;

class FormAction extends AbstractAction
{
    protected function action(): \Slim\Http\Response
    {
        $fileService = FileService::getWithContainer($this->container);
        $formService = FormService::getWithContainer($this->container);
        $formDataService = FormDataService::getWithContainer($this->container);
        $form = $formService->read(['address' => $this->resolveArg('unique')]);

        if ($form) {
            if (
                (
                    empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest'
                ) && !empty($_SERVER['HTTP_REFERER'])
            ) {
                $this->response = $this->response->withRedirect($_SERVER['HTTP_REFERER']);
            }

            if (!$form->getRecaptcha() || $this->isRecaptchaChecked()) {
                $remote = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? false;
                $data = $this->request->getParams();

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
                foreach (array_map('trim', $form->getMailto()) as $key => $value) {
                    $buf = array_map('trim', explode(':', $value));

                    if (count($buf) === 2) {
                        $mailto[$buf[0]] = $buf[1];
                    } else {
                        $mailto[] = $buf[0];
                    }
                }

                $isHtml = true;

                // prepare mail body
                if (
                    $form->getTemplate()
                    && $form->getTemplate() !== '<p><br></p>'
                ) {
                    $body = $this->renderer->fetchFromString($form->getTemplate(), $data);
                } else {
                    // no template, check post data for mail body
                    if (($buf = $this->request->getParam('body', false)) !== false) {
                        $body = $buf;
                    } else {
                        // json in mail
                        $body = json_encode(str_escape($data), JSON_UNESCAPED_UNICODE);
                        $isHtml = false;
                    }
                }

                // prepare form data
                $formData = $formDataService->create([
                    'form_uuid' => $form->getUuid(),
                    'message' => $body,
                    'date' => new DateTime(),
                ]);

                // prepare attachments
                $attachments = [];
                if ($this->parameter('file_is_enabled', 'no') === 'yes') {
                    foreach ($this->request->getUploadedFiles() as $field => $files) {
                        if (!is_array($files)) {
                            $files = [$files];
                        }

                        /**
                         * @var UploadedFile $el
                         */
                        foreach ($files as $el) {
                            if (!$el->getError()) {
                                $model = $fileService->createFromPath($el->file, $el->getClientFilename());
                                $formData->addFile($model);
                                $attachments[$model->getName()] = $model->getInternalPath();
                            }
                        }
                    }
                }

                // send mail task
                $task = new \App\Domain\Tasks\SendMailTask($this->container);
                $task->execute([
                    'to' => $mailto,
                    'subject' => $form->getTitle(),
                    'body' => $body,
                    'isHtml' => $isHtml,
                    'attachments' => $attachments,
                ]);

                // run worker
                \App\Domain\AbstractTask::worker($task);

                return $this->respondWithJson(['status' => 'ok']);
            }

            $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
        } else {
            throw new HttpNotFoundException();
        }

        return $this->response->withStatus(500);
    }
}

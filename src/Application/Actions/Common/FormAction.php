<?php declare(strict_types=1);

namespace App\Application\Actions\Common;

use App\Application\FormGuard;
use App\Domain\AbstractAction;
use App\Domain\Models\File;
use App\Domain\Models\Form;
use App\Domain\Models\FormData;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\Exception\FormNotFoundException;
use App\Domain\Service\Form\FormService;

class FormAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $formService = $this->container->get(FormService::class);
        $formDataService = $this->container->get(FormDataService::class);

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

            if (!$form->recaptcha || $this->isRecaptchaChecked()) {
                $remote = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? false;
                $data = $this->getParams();

                // spam protection (local, see FormGuard)
                $verdict = $this->guardVerdict($form, $data);
                $data = (new FormGuard())->clean($data);

                // CORS header sets
                foreach ($form->origin as $origin) {
                    if ($remote && $this->isSameOrigin($origin, $remote)) {
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
                foreach (array_map('trim', $form->mailto) as $value) {
                    $buf = array_map('trim', explode(':', $value));

                    if (count($buf) === 2) {
                        $mailto[$buf[0]] = $buf[1];
                    } else {
                        $mailto[] = $buf[0];
                    }
                }

                // form data
                $isSpam = $verdict !== null && (new FormGuard())->isSpam($verdict);
                $formData = $formDataService->create([
                    'form_uuid' => $form->uuid,
                    'data' => $data,
                    'message' => $this->getParam('body', ''),
                    'is_spam' => $isSpam,
                    'spam_reason' => $isSpam ? mb_substr(implode(', ', $verdict['reasons']), 0, 255) : '',
                ]);

                if ($isSpam) {
                    $this->logger->info('Form spam', ['form' => $form->address, 'score' => $verdict['score'], 'reasons' => $verdict['reasons']]);

                    // the same answer as for a real one: a bot has nothing to adjust to
                    return $this->respondWithJson(['status' => 'ok']);
                }

                // prepare attachments
                $attachments = [];
                $json = [];
                if ($this->parameter('file_is_enabled', 'yes') === 'yes') {
                    /** @var FormData $formData */
                    $formData = $this->processEntityFiles($formData);

                    foreach ($formData->files as $file) {
                        /** @var File $file */
                        $attachments[$file->filename()] = $file->public_path();
                        $json[] = [
                            'uuid' => $file->uuid,
                            'name' => $file->filename(),
                            'order' => $file->order(),
                            'comment' => $file->comment(),
                            'internal' => $file->internal_path(),
                            'public' => $file->public_path(),
                        ];
                    }
                }

                // check if duplication is enabled
                if (($duplicate = $form->duplicate) !== '') {
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
                    'cc' => $form->authorSend && !empty($data['email']) ? $data['email'] : '',
                    'subject' => $form->title,
                    'body' => '',
                    'template' => '',
                    'data' => [],
                    'attachments' => $attachments,
                ];

                if (($buf = $this->getParam('body', false)) !== false) {
                    $params['body'] = $buf;
                } else {
                    $params['template'] = $form->templateFile ?: $form->template;
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

    /**
     * Score of the submission, null when the protection is turned off
     *
     * @return null|array{score: int, reasons: string[]}
     */
    private function guardVerdict(Form $form, array $data): ?array
    {
        if ($this->parameter('form_guard_mode', 'on') === 'off') {
            return null;
        }

        // every attempt counts, not only the rejected ones: a flood is a flood
        $key = 'form-guard-' . sha1($form->uuid . '|' . $this->getRequestRemoteIP());
        $recent = (int) $this->fileCache->get($key);
        $this->fileCache->put($key, $recent + 1, \App\Domain\References\Date::HOUR);

        return (new FormGuard())->inspect($data, $recent, (int) $this->parameter('form_guard_limit', 10));
    }

    /**
     * Does the caller (Origin or Referer, so possibly with a path) belong to the allowed origin
     * from the form settings, written either as `https://site.tld` or just `site.tld`
     */
    private function isSameOrigin(string $allowed, string $remote): bool
    {
        $allowed = mb_rtrim(mb_trim($allowed), '/');
        $parts = parse_url($remote);

        if ($allowed === '' || !is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $host = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');

        return strcasecmp($allowed, $parts['scheme'] . '://' . $host) === 0 || strcasecmp($allowed, $host) === 0;
    }
}

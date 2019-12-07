<?php

namespace App\Application\Actions\Common;

use App\Application\Actions\Action;
use App\Domain\Exceptions\HttpNotFoundException;
use DateTime;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Slim\Http\UploadedFile;

class FormAction extends Action
{
    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $formRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $formDataRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $fileRepository;

    /**
     * @inheritDoc
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->formRepository = $this->entityManager->getRepository(\App\Domain\Entities\Form::class);
        $this->formDataRepository = $this->entityManager->getRepository(\App\Domain\Entities\Form\Data::class);
        $this->fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);
    }

    protected function action(): \Slim\Http\Response
    {
        /** @var \App\Domain\Entities\Form $item */
        $item = $this->formRepository->findOneBy([
            'address' => $this->resolveArg('unique'),
        ]);

        if ($item) {
            if ($item->recaptcha === false || $this->isRecaptchaChecked()) {
                $remote = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? false;
                $data = $this->request->getParams();

                // CORS header sets
                foreach ($item->origin as $origin) {
                    if ($remote && strpos($origin, $remote) >= 0) {
                        $this->response = $this->response->withHeader('Access-Control-Allow-Origin', $remote);
                        break;
                    } else {
                        if ($origin === '*') {
                            $this->response = $this->response->withHeader('Access-Control-Allow-Origin', '*');
                            break;
                        }
                    }
                }

                if ($this->response->hasHeader('Access-Control-Allow-Origin')) {
                    $this->response = $this->response->withHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
                }

                // mailto field prepare
                $mailto = [];
                foreach (array_map('trim', $item->mailto) as $key => $value) {
                    $buf = array_map('trim', explode(':', $value));

                    if (count($buf) == 2) {
                        $mailto[$buf[0]] = $buf[1];
                    } else {
                        $mailto[] = $buf[0];
                    }
                }

                $isHtml = true;

                // mail body prepare
                if ($item->template && $item->template != '<p><br></p>') {
                    $filter = new class($data) extends \Alksily\Validator\Filter
                    {
                        use \Alksily\Validator\Traits\FilterRules;
                    };
                    $filter->addGlobalRule($filter->leadEscape());
                    $filter->addGlobalRule($filter->leadTrim());
                    $check = $filter->run();

                    if ($check === true) {
                        $body = $this->renderer->fetchFromString($item->template, $data);
                    } else {
                        throw new InvalidArgumentException('Error in POST data');
                    }
                } else {
                    // no template, check post data for mail body
                    if ($buf = $this->request->getParam('body', false)) {
                        $body = $buf;
                    } else {
                        // json in mail
                        $body = json_encode(str_escape($data), JSON_UNESCAPED_UNICODE);
                        $isHtml = false;
                    }
                }

                if ($item->save_data === true) {
                    /**
                     * save request
                     * @var \App\Domain\Entities\Form\Data $bid
                     */
                    $bid = new \App\Domain\Entities\Form\Data([
                        'form_uuid' => $item->uuid,
                        'message' => $body,
                        'date' => new DateTime(),
                    ]);
                    $this->entityManager->persist($bid);
                }

                // prepare mail attachments
                $attachments = [];
                foreach ($this->request->getUploadedFiles() as $field => $files) {
                    if (!is_array($files)) $files = [$files];

                    /* @var UploadedFile $file */
                    foreach ($files as $file) {
                        if (!$file->getError()) {
                            $file_model = \App\Domain\Entities\File::getFromPath($file->file, $file->getClientFilename());

                            if ($file_model) {
                                if ($item->save_data === true) {
                                    $file_model->replace([
                                        'item' => \App\Domain\Types\FileItemType::ITEM_FORM_DATA,
                                        'item_uuid' => $bid->uuid,
                                    ]);
                                    $this->entityManager->persist($file_model);
                                }

                                // add to attachments
                                $attachments[$file_model->getName()] = $file_model->getInternalPath();
                            }
                        }
                    }
                }

                // send mail
                $mail = $this->send_mail([
                    'subject' => $item->title,
                    'to' => $mailto,
                    'body' => $body,
                    'isHtml' => $isHtml,
                    'attachments' => $attachments,
                ]);

                // create notify
                $notify = new \App\Domain\Entities\Notification([
                    'title' => 'Ответ на форму: ' . $item->title,
                    'message' => 'Была заполнена форма, проверьте список ответов и/или почту',
                    'date' => new DateTime(),
                ]);
                $this->entityManager->persist($notify);

                // send push stream
                $this->container->get('pushstream')->send([
                    'group' => \App\Domain\Types\UserLevelType::LEVEL_ADMIN,
                    'content' => $notify,
                ]);

                $this->entityManager->flush();

                if (
                    (
                        empty($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest'
                    ) && !empty($_SERVER['HTTP_REFERER'])
                ) {
                    $this->response = $this->response->withHeader('Location', $_SERVER['HTTP_REFERER'])->withStatus(301);
                }

                if ($mail !== false) {
                    if (!$mail->isError()) {
                        $this->logger->info('Form sended: ' . $item->title, ['mailto' => $item->mailto]);
                    } else {
                        $this->logger->warn('Form will not sended: fail', ['mailto' => $item->mailto, 'error' => $mail->ErrorInfo]);
                    }

                    return $this->respondWithData(['status' => !$mail->isError() ? 'ok' : 'fail', 'error' => $mail->ErrorInfo]);
                }

                return $this->respondWithData(['status' => 'ok']);
            } else {
                $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
            }
        }

        throw new HttpNotFoundException($this->request);
    }
}

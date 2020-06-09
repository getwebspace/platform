<?php declare(strict_types=1);

namespace App\Domain;

use App\Application\Mail;
use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Exceptions\HttpForbiddenException;
use App\Domain\Exceptions\HttpMethodNotAllowedException;
use App\Domain\Exceptions\HttpNotFoundException;
use App\Domain\Exceptions\HttpNotImplementedException;
use App\Domain\Service\File\FileService;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

abstract class AbstractAction extends AbstractComponent
{
    // 40X
    protected const BAD_REQUEST = 'BAD_REQUEST';
    protected const NOT_ALLOWED = 'NOT_ALLOWED';
    protected const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    protected const SERVER_ERROR = 'SERVER_ERROR';
    protected const UNAUTHENTICATED = 'UNAUTHENTICATED';

    // 50X
    protected const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';

    /**
     * @var Twig
     */
    protected $renderer;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $args;

    /**
     * @var array
     */
    private $error = [];

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->renderer = $container->get('view');
    }

    /**
     * @param string $field
     * @param string $reason
     */
    protected function addError($field, $reason = ''): void
    {
        $this->error[$field] = $reason;
    }

    /**
     * @param array $check
     */
    protected function addErrorFromCheck(array $check): void
    {
        $this->error = array_merge($this->error, $check);
    }

    /**
     * Производит отправку письма
     *
     * @param array $data
     *
     * @throws \PHPMailer\PHPMailer\Exception
     *
     * @return bool|\PHPMailer\PHPMailer\PHPMailer
     */
    protected function send_mail(array $data = [])
    {
        $data = array_merge(
            $this->getParameter(
                [
                    'smtp_from', 'smtp_from_name',
                    'smtp_login', 'smtp_pass',
                    'smtp_host', 'smtp_port',
                    'smtp_secure',
                    'subject',
                ]
            ),
            $data
        );

        if ($data['smtp_host'] && $data['smtp_login'] && $data['smtp_pass']) {
            return Mail::send($data);
        }

        return false;
    }

    /**
     * @param Request  $request
     * @param Response $response
     * @param array    $args
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, $args): Response
    {
        \RunTracy\Helpers\Profiler\Profiler::start('route');

        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        $result = null;

        try {
            $result = $this->action();
        } catch (AbstractHttpException $exception) {
            $error = [
                'code' => $exception->getCode(),
                'error' => [
                    'type' => self::SERVER_ERROR,
                    'error' => $exception->getDescription(),
                    'message' => $exception->getMessage(),
                ],
            ];

            if ($exception instanceof HttpNotFoundException) {
                $error['error']['type'] = self::RESOURCE_NOT_FOUND;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $error['error']['type'] = self::NOT_ALLOWED;
            } elseif ($exception instanceof HttpForbiddenException) {
                $error['error']['type'] = self::UNAUTHENTICATED;
            } elseif ($exception instanceof HttpBadRequestException) {
                $error['error']['type'] = self::BAD_REQUEST;
            } elseif ($exception instanceof HttpNotImplementedException) {
                $error['error']['type'] = self::NOT_IMPLEMENTED;
            }

            $result = new Response($error['code']);
            $result->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));
            $result = $result->withHeader('Content-Type', 'application/json');
        }

        \RunTracy\Helpers\Profiler\Profiler::finish('route');

        return $result;
    }

    /**
     * @return Response
     */
    abstract protected function action(): \Slim\Http\Response;

    /**
     * @param string $name
     *
     * @throws HttpBadRequestException
     *
     * @return mixed
     */
    protected function resolveArg(string $name)
    {
        if (!isset($this->args[$name])) {
            throw new HttpBadRequestException("Could not resolve argument `{$name}`.");
        }

        return $this->args[$name];
    }

    /**
     * @param AbstractEntity $entity
     * @param string[]       $fields
     *
     * @return AbstractEntity
     */
    protected function handlerEntityFiles(AbstractEntity $entity, array $fields = []): AbstractEntity
    {
        if (
            $this->getParameter('file_is_enabled', 'no') === 'yes' &&
            method_exists($entity, 'addFile') && method_exists($entity, 'removeFile')
        ) {
            $default = [
                'upload' => 'files',
                'delete' => 'delete-file',
            ];
            $fields = array_merge($default, $fields);
            $fileService = FileService::getWithContainer($this->container);

            // upload files
            /** @var \Psr\Http\Message\UploadedFileInterface[] $files */
            if (($files = $this->request->getUploadedFiles()[$fields['upload']]) !== null) {
                if (!is_array($files)) {
                    $files = [$files]; // allow upload one file
                }

                $uuids = [];
                foreach ($files as $el) {
                    if (!$el->getError()) {
                        $model = $fileService->createFromPath($el->file, $el->getClientFilename());
                        $entity->addFile($model);

                        // is image
                        if (str_starts_with('image/', $model->getType())) {
                            $uuids[] = $model->getUuid();
                        }
                    }
                }

                if ($uuids) {
                    // add task convert
                    $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                    $task->execute(['uuid' => $uuids]);

                    // run worker
                    \App\Domain\AbstractTask::worker();
                }
            }

            // remove files
            if (($files = $this->request->getParam($fields['delete'])) !== null) {
                if (!is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $uuid) {
                    $fileService->delete($uuid);
                }
            }

            // flush data
            $this->entityManager->flush();
        }

        return $entity;
    }

    /**
     * Upload image files
     *
     * @param string $field
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @deprecated
     *
     * @return array
     */
    protected function handlerFileUpload(string $field = 'files')
    {
        return [];
    }

    /**
     * Upload image files
     *
     * @param string $field
     *
     * @deprecated
     *
     * @return array
     */
    protected function handlerFileRemove(string $field = 'delete-file')
    {
        return [];
    }

    /**
     * Return recaptcha status if is enabled
     *
     * @return bool
     */
    protected function isRecaptchaChecked(): bool
    {
        if ($this->request->isPost() && $this->getParameter('integration_recaptcha', 'off') === 'on') {
            \RunTracy\Helpers\Profiler\Profiler::start('recaptcha');

            $query = http_build_query([
                'secret' => $this->getParameter('integration_recaptcha_private'),
                'response' => $this->request->getParam('recaptcha', ''),
                'remoteip' => $this->request->getServerParam('REMOTE_ADDR'),
            ]);
            $verify = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
                        'Content-Length: ' . mb_strlen($query) . "\r\n",
                    'content' => $query,
                    'timeout' => 10,
                ],
            ])));

            $this->logger->info('Check reCAPTCHA', ['status' => $verify->success]);

            \RunTracy\Helpers\Profiler\Profiler::finish('recaptcha');

            return $verify->success;
        }

        return true;
    }

    /**
     * @param string $template
     * @param array  $data
     *
     * @throws HttpBadRequestException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return string
     */
    protected function render($template, array $data = [])
    {
        try {
            \RunTracy\Helpers\Profiler\Profiler::start('render (%s)', $template);

            $data = array_merge(
                [
                    'NIL' => \Ramsey\Uuid\Uuid::NIL,
                    '_request' => &$_REQUEST,
                    '_error' => \Alksily\Support\Form::$globalError = $this->error,
                    'user' => $this->request->getAttribute('user', false),
                    'plugins' => $this->container->get('plugin')->get(),
                ],
                $data
            );
            if (($path = realpath(THEME_DIR . '/' . $this->getParameter('common_theme', 'default'))) !== false) {
                $this->renderer->getLoader()->addPath($path);
            }
            $rendered = $this->renderer->fetch($template, $data);

            \RunTracy\Helpers\Profiler\Profiler::finish('render (%s)', $template);

            return $rendered;
        } catch (\Twig\Error\LoaderError $exception) {
            throw new HttpBadRequestException($exception->getMessage());
        }
    }

    /**
     * @param string $template
     * @param array  $data
     *
     * @throws HttpBadRequestException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     *
     * @return Response
     */
    protected function respondWithTemplate($template, array $data = [])
    {
        $this->response->getBody()->write(
            $this->render($template, $data)
        );

        return $this->response;
    }

    /**
     * @param array $array
     *
     * @return Response
     */
    protected function respondWithJson(array $array = []): Response
    {
        $json = json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->response->getBody()->write($json);

        return $this->response->withHeader('Content-Type', 'application/json');
    }
}

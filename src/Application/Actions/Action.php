<?php

namespace App\Application\Actions;

use Alksily\Entity\Model;
use App\Application\Mail;
use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Exceptions\HttpException;
use App\Domain\Exceptions\HttpNotFoundException;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Twig;

abstract class Action
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    protected $entityManager;

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
        $this->container = $container;
        $this->logger = $container->get('monolog');
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        $this->renderer = $container->get('view');
    }

    /**
     * Возвращает значение параметра по переданному ключу
     * Если передан массив ключей, возвращает массив найденных ключей и их значения
     *
     * @param string|string[] $key
     * @param mixed           $default
     *
     * @return array|string|mixed
     */
    protected function getParameter($key = null, $default = null)
    {
        return $this->container->get('parameter')->get($key, $default);
    }

    /**
     * @param string $field
     * @param string $reason
     */
    protected function addError($field, $reason)
    {
        $this->error[$field] = $reason ?? \App\Domain\References\Errors\Common::WRONG_COMMON;
    }

    /**
     * @param array $check
     */
    protected function addErrorFromCheck(array $check)
    {
        $this->error = array_merge($this->error, $check);
    }

    /**
     * Производит отправку письма
     *
     * @param array $data
     *
     * @return bool|\PHPMailer\PHPMailer\PHPMailer
     * @throws \PHPMailer\PHPMailer\Exception
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
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

        \RunTracy\Helpers\Profiler\Profiler::start('route');

        try {
            $result = $this->action();

            \RunTracy\Helpers\Profiler\Profiler::finish('route');

            return $result;
        } catch (HttpException $exception) {
            $error = new ActionError(ActionError::SERVER_ERROR, 'An internal error has occurred while processing your request.');
            $error->setDescription($exception->getMessage());

            // todo add handles
            if ($exception instanceof HttpNotFoundException) {
                $error->setType(ActionError::RESOURCE_NOT_FOUND);
            } else if ($exception instanceof HttpBadRequestException) {
                $error->setType(ActionError::BAD_REQUEST);
            }

            $payload = new ActionPayload($exception->getCode(), null, $error);
            $encodedPayload = json_encode($payload, JSON_PRETTY_PRINT);

            $response = new Response($exception->getCode());
            $response->getBody()->write($encodedPayload);

            $response = $response->withHeader('Content-Type', 'application/json');

            \RunTracy\Helpers\Profiler\Profiler::finish('route');

            return $response;
        }
    }

    /**
     * @return Response
     */
    abstract protected function action(): \Slim\Http\Response;

    /**
     * @param string $name
     *
     * @return mixed
     * @throws HttpBadRequestException
     */
    protected function resolveArg(string $name)
    {
        if (!isset($this->args[$name])) {
            throw new HttpBadRequestException($this->request, "Could not resolve argument `{$name}`.");
        }

        return $this->args[$name];
    }

    /**
     * Upload image files
     *
     * @param string $field
     *
     * @return array
     * @throws \Doctrine\ORM\ORMException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     */
    protected function handlerFileUpload(string $field = 'files')
    {
        $result = [];

        if ($this->getParameter('file_is_enabled', 'no') === 'yes') {
            /** @var \Psr\Http\Message\UploadedFileInterface[] $files */
            $files = $this->request->getUploadedFiles()[$field] ?? [];

            if (!is_array($files)) {
                $files = [$files]; // allow upload one file
            }

            foreach ($files as $file) {
                if (!$file->getError()) {
                    if (($model = \App\Domain\Entities\File::getFromPath($file->file, $file->getClientFilename())) !== null) {
                        $result[] = $model;
                        $this->entityManager->persist($model);

                        // is image
                        if (\Alksily\Support\Str::start('image/', $model->type)) {
                            // add task convert
                            $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                            $task->execute(['uuid' => $model->uuid]);

                            // run worker
                            \App\Domain\Tasks\Task::worker();
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Upload image files
     *
     * @param string $field
     *
     * @return array
     */
    protected function handlerFileRemove(string $field = 'delete-file')
    {
        $result = [];

        if (($files = $this->request->getParam($field)) !== null) {
            $fileRepository = $this->entityManager->getRepository(\App\Domain\Entities\File::class);

            if (!is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $uuid) {
                /** @var \App\Domain\Entities\File $file */

                if (
                    \Ramsey\Uuid\Uuid::isValid($uuid) &&
                    ($file = $fileRepository->findOneBy(['uuid' => $uuid])) !== null
                ) {
                    $result[] = $file;
                }
            }
        }

        return $result;
    }

    /**
     * @param string $template
     * @param array  $data
     *
     * @return Response
     * @throws HttpBadRequestException
     * @throws \RunTracy\Helpers\Profiler\Exception\ProfilerException
     */
    protected function respondRender($template, array $data = [])
    {
        try {
            \RunTracy\Helpers\Profiler\Profiler::start('render (%s)', $template);

            $data = array_merge(
                [
                    '_error' => \Alksily\Support\Form::$globalError = $this->error,
                    'user' => $this->request->getAttribute('user', false),
                    'trademaster' => $this->getParameter('integration_trademaster_enable', 'off'),
                ],
                $data
            );
            $this->renderer->getLoader()->addPath(THEME_DIR . '/' . $this->getParameter('common_theme', 'default'));
            $this->response->getBody()->write($this->renderer->fetch($template, $data));

            \RunTracy\Helpers\Profiler\Profiler::finish('render (%s)', $template);

            return $this->response;
        } catch (\Twig\Error\LoaderError $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }
    }

    /**
     * Return recaptcha status if is enabled
     *
     * @return bool
     */
    protected function isRecaptchaChecked(): bool
    {
        if ($this->request->isPost() && $this->getParameter('integration_recaptcha', 'off') == 'on') {
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
                                "Content-Length: " . strlen($query) . "\r\n",
                    'content' => $query,
                    'timeout' => 10,
                ],
            ])));

            \RunTracy\Helpers\Profiler\Profiler::finish('recaptcha');

            $this->logger->info('Check reCAPTCHA', ['status' => $verify->success]);

            return $verify->success;
        }

        return true;
    }

    /**
     * @param array $payload
     *
     * @return Response
     */
    protected function respondWithJson(array $payload = null): Response
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->response->getBody()->write($json);

        return $this->response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param array|object|null $data
     *
     * @return Response
     */
    protected function respondWithData($data = null): Response
    {
        $payload = new ActionPayload(200, $data);

        return $this->respond($payload);
    }

    /**
     * @param ActionPayload $payload
     *
     * @return Response
     */
    protected function respond(ActionPayload $payload): Response
    {
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $this->response->getBody()->write($json);

        return $this->response->withHeader('Content-Type', 'application/json');
    }
}

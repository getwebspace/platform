<?php declare(strict_types=1);

namespace App\Domain;

use App\Application\Mail;
use App\Domain\Entities\File;
use App\Domain\Exceptions\HttpBadRequestException;
use App\Domain\Exceptions\HttpForbiddenException;
use App\Domain\Exceptions\HttpMethodNotAllowedException;
use App\Domain\Exceptions\HttpNotFoundException;
use App\Domain\Exceptions\HttpNotImplementedException;
use App\Domain\Service\File\FileRelationService;
use App\Domain\Service\File\FileService;
use App\Domain\Traits\FileTrait;
use App\Domain\Traits\ParameterTrait;
use App\Domain\Traits\StorageTrait;
use Doctrine\ORM\EntityManager;
use Illuminate\Support\Collection;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Slim\Interfaces\RouteCollectorInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Slim\Views\Twig;
use function PHPUnit\Framework\isNull;

abstract class AbstractAction
{
    use ParameterTrait;
    use StorageTrait;

    // 40X
    private const BAD_REQUEST = 'BAD_REQUEST';
    private const NOT_ALLOWED = 'NOT_ALLOWED';
    private const RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
    private const SERVER_ERROR = 'SERVER_ERROR';
    private const UNAUTHENTICATED = 'UNAUTHENTICATED';

    // 50X
    private const NOT_IMPLEMENTED = 'NOT_IMPLEMENTED';

    protected ContainerInterface $container;

    protected LoggerInterface $logger;

    protected EntityManager $entityManager;

    protected RouteCollectorInterface $routeCollector;

    protected Twig $renderer;

    protected Request $request;

    protected Response $response;

    protected array $args;

    private array $error = [];

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(LoggerInterface::class);
        $this->entityManager = $container->get(EntityManager::class);
        $this->routeCollector = $container->get(RouteCollectorInterface::class);
        $this->renderer = $container->get('view');
    }

    protected function isGet(): bool
    {
        return $this->request->getMethod() === 'GET';
    }

    protected function isPost(): bool
    {
        return $this->request->getMethod() === 'POST';
    }

    protected function getParams(): array
    {
        return array_merge(
            $this->request->getQueryParams(),
            ($this->request->getParsedBody() ?? [])
        );
    }

    protected function getParam(string $key, mixed $default = null): mixed
    {
        return $this->getParams()[$key] ?? $default;
    }

    protected function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->request->getQueryParams()[$key] ?? $default;
    }

    protected function getBodyParam(string $key, mixed $default = null): mixed
    {
        return ($this->request->getParsedBody() ?? [])[$key] ?? $default;
    }

    protected function getServerParam(string $key, mixed $default = null): mixed
    {
        return $this->request->getServerParams()[$key] ?? $default;
    }

    protected function getRoutes(): Collection
    {
        static $routes;

        if (!$routes) {
            $routes = collect($this->routeCollector->getRoutes())
                ->flatten()
                ->map(fn ($item) => $item->getName())
                ->filter(fn ($item) => !str_start_with($item, \App\Application\Middlewares\AccessCheckerMiddleware::PUBLIC));
        }

        return $routes->combine($routes);
    }

    protected function addError(string $field, string $reason = ''): void
    {
        $this->error[$field] = $reason;
    }

    protected function addErrorFromCheck(array $check): void
    {
        $this->error = array_merge($this->error, $check);
    }

    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;

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

        return $result;
    }

    /**
     * @throws AbstractHttpException
     */
    abstract protected function action(): \Slim\Psr7\Response;

    /**
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

    protected function getRequestRemoteIP(): string
    {
        return $this->getServerParam(
            'HTTP_X_REAL_IP',
            $this->getServerParam(
                'HTTP_X_FORWARDED_FOR',
                $this->getServerParam('REMOTE_ADDR', '')
            )
        );
    }

    /**
     * For add or remove files for AbstractEntity with files
     */
    protected function processEntityFiles(AbstractEntity $entity, string $field = 'files'): AbstractEntity
    {
        if (in_array(FileTrait::class, class_uses($entity), true)) {
            $fileRelationService = $this->container->get(FileRelationService::class);

            // new
            if (($uploaded = $this->getUploadedFiles($field)) !== []) {
                $index = $entity->hasFiles();

                foreach ($uploaded as $name => $files) {
                    if (is_numeric($name)) {
                        $name = '';
                    }

                    foreach ($files as $file) {
                        $fileRelation = $fileRelationService->create([
                            'entity' => $entity,
                            'file' => $file,
                            'comment' => $name,
                            'order' => ++$index,
                        ]);

                        // link file to entity
                        if ($fileRelation) {
                            $entity->addFile($fileRelation);
                        }
                    }
                }
            }

            // update
            if (($files = $this->getParam($field)) !== null && is_array($files)) {
                foreach ($files as $uuid => $data) {
                    $default = [
                        'order' => null,
                        'comment' => null,
                        'delete' => null,
                    ];
                    $data = array_merge($default, $data);

                    $relation = $entity->getFiles()->firstWhere('uuid', $uuid);

                    if ($relation) {
                        if ($data['delete'] !== null) {
                            $fileRelationService->delete($relation);

                            continue;
                        }

                        $fileRelationService->update($relation, $data);
                    }
                }
            }
        }

        return $entity;
    }

    /**
     * For uploaded files without entity
     *
     * @return File[]
     */
    protected function getUploadedFiles(string $field = 'files', mixed $return = null): array
    {
        $uploaded = [];

        if ($this->parameter('file_is_enabled', 'yes') === 'yes') {
            $fileService = $this->container->get(FileService::class);

            /** @var \Psr\Http\Message\UploadedFileInterface[] $files */
            $files = $this->request->getUploadedFiles()[$field] ?? [];

            if (!is_array($files)) {
                $files = [$files]; // allow upload one file
            }

            $image_uuids = [];

            foreach ($files as $name => $file) {
                if (!is_array($file)) {
                    $file = [$file];
                }

                foreach ($file as $index => $item) {
                    if (!$item->getError()) {
                        if (($model = $fileService->createFromPath($item->getFilePath(), $item->getClientFilename())) !== null) {
                            $uploaded[$name][$index] = $model;

                            // is image
                            if (str_start_with($model->getType(), 'image/')) {
                                $image_uuids[] = $model->getUuid();
                            }
                        }
                    }
                }
            }

            if ($image_uuids) {
                // add task convert
                $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                $task->execute(['uuid' => $image_uuids]);

                // run worker
                \App\Domain\AbstractTask::worker($task);
            }
        }

        return $return === null ? $uploaded : $uploaded[$return];
    }

    /**
     * For upload file from POST body
     */
    protected function getFileFromBody(string $filename = ''): ?File
    {
        $uploaded = null;
        $tmp_path = UPLOAD_DIR . '/' . uniqid();

        if ($filename && file_put_contents($tmp_path, $this->request->getBody()->getContents()) !== false) {
            $fileService = $this->container->get(FileService::class);

            if (($model = $fileService->createFromPath($tmp_path, $filename)) !== null) {
                $uploaded = $model;

                // is image
                if (str_start_with($model->getType(), 'image/')) {
                    // add task convert
                    $task = new \App\Domain\Tasks\ConvertImageTask($this->container);
                    $task->execute(['uuid' => [$model->getUuid()]]);

                    // run worker
                    \App\Domain\AbstractTask::worker($task);
                }
            }
        }

        return $uploaded;
    }

    /**
     * Return recaptcha status if is enabled
     */
    protected function isRecaptchaChecked(): bool
    {
        if ($this->isPost() && $this->parameter('integration_recaptcha', 'off') === 'on') {
            $query = http_build_query([
                'secret' => $this->parameter('integration_recaptcha_private'),
                'response' => $this->getParam('recaptcha', ''),
                'remoteip' => $this->getRequestRemoteIP(),
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

            return $verify->success;
        }

        return true;
    }

    /**
     * @throws HttpBadRequestException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    protected function render(string $template, array $data = []): string
    {
        try {
            $data = array_merge(
                [
                    '_request' => &$_REQUEST,
                    '_error' => \Alksily\Support\Form::$globalError = $this->error,
                    '_lang' => \App\Application\i18n::$localeCode ?? 'ru',
                    'sha' => mb_substr($_ENV['COMMIT_SHA'] ?? 'specific', 0, 7),
                    'NIL' => \Ramsey\Uuid\Uuid::NIL,
                    'plugins' => $this->container->get('plugin')->get(),
                    'user' => $this->request->getAttribute('user', false),
                ],
                $data
            );
            if (($path = realpath(THEME_DIR . '/' . $this->parameter('common_theme', 'default'))) !== false) {
                $this->renderer->getLoader()->addPath($path);
            }

            // add default errors pages
            $this->renderer->getLoader()->addPath(VIEW_ERROR_DIR);

            return $this->renderer->fetch($template, $data);
        } catch (\Twig\Error\LoaderError $exception) {
            throw new HttpBadRequestException($exception->getMessage());
        }
    }

    /**
     * @throws HttpBadRequestException
     */
    protected function respond(string $template, array $data = []): Response
    {
        $format = $this->request->getQueryParams()['format'] ?? 'html';
        $accept = explode(',', $this->request->getHeaderLine('HTTP_ACCEPT'));

        switch (true) {
            case $format === 'json':
            case in_array('application/json', $accept, true):
                return $this->respondWithJson([
                    'params' => $this->request->getQueryParams(),
                    'data' => $data,
                ]);

            case $format === 'text':
            case in_array('text/plain', $accept, true):
                return $this->respondWithText($data);

            case $format === 'html':
            case in_array('text/html', $accept, true):
            default:
                return $this->respondWithTemplate($template, $data);
        }
    }

    /**
     * @throws HttpBadRequestException
     */
    protected function respondWithTemplate(string $template, array $data = []): Response
    {
        try {
            $this->response->getBody()->write($this->render($template, $data));
        } catch (\Exception $e) {
            return $this->respondWithTemplate('p400.twig', ['exception' => $e])->withStatus(400);
        }

        return $this->response;
    }

    protected function respondWithJson(array $array = []): Response
    {
        $this->response->getBody()->write(
            json_encode(array_serialize($array), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $this->response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    protected function respondWithText(array|string $output = ''): Response
    {
        if (is_array($output) || is_a($output, Collection::class)) {
            $output = json_encode(array_serialize($output), JSON_UNESCAPED_UNICODE);
        }
        $this->response->getBody()->write($output);

        return $this->response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    protected function respondWithRedirect(string $location = '/'): Response
    {
        return $this->response->withAddedHeader('Location', $location)->withStatus(301);
    }
}

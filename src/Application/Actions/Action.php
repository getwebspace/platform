<?php

namespace Application\Actions;

use Application\Mail;
use Doctrine\ORM\EntityManager;
use Domain\Exceptions\HttpBadRequestException;
use Domain\Exceptions\HttpException;
use Domain\Exceptions\HttpNotFoundException;
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
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    private $parametersRepository;

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
    protected $error = [];

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get(\Monolog\Logger::class);
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);
        $this->renderer = $container->get(\Slim\Views\Twig::class);

        $this->parametersRepository = $this->entityManager->getRepository(\Domain\Entities\Parameter::class);
    }

    /**
     * Возвращает значение параметра по переданному ключу
     * Если передан массив ключей, возвращает массив найденных ключей и их значения
     *
     * @param string|string[] $key
     * @param mixed           $default
     *
     * @return |null
     */
    protected function getParameter($key = null, $default = null)
    {
        if ($key === null) {
            return collect($this->parametersRepository->findAll())->mapWithKeys(function ($item) {
                list($group, $key) = explode('_', $item->key, 2);
                return [$group . '[' . $key . ']' => $item];
            });
        }
        if (is_string($key)) {
            return $this->parametersRepository->findOneBy(['key' => $key])->value ?? $default;
        }

        return collect($this->parametersRepository->findBy(['key' => $key]))->pluck('value', 'key')->all() ?? $default;
    }

    /**
     * @param string $field
     * @param string $reason
     */
    protected function addError($field, $reason)
    {
        $this->error[$field] = $reason;
    }

    /**
     * Производит отправку письма
     *
     * @param array $data
     *
     * @return \PHPMailer\PHPMailer\PHPMailer
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

        return Mail::send($data);
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

        try {
            return $this->action();
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

            return $response->withHeader('Content-Type', 'application/json');
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
     * @param string $template
     * @param array  $data
     *
     * @return Response
     * @throws HttpBadRequestException
     */
    protected function respondRender($template, array $data = [])
    {
        try {
            $data = array_merge(
                [
                    'parameter' => $this->getParameter(),
                    'user' => $this->request->getAttribute('user', null),
                    '_error' => array_merge($this->error, \AEngine\Support\Form::$globalError),
                ],
                $data
            );
            $this->response->getBody()->write($this->renderer->fetch($template, $data));

            return $this->response;
        } catch (\Twig\Error\LoaderError $exception) {
            throw new HttpBadRequestException($this->request, $exception->getMessage());
        }
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
        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $this->response->getBody()->write($json);

        return $this->response->withHeader('Content-Type', 'application/json');
    }
}

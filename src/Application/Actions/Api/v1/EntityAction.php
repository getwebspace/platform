<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\AbstractException;
use App\Domain\AbstractNotFoundException;
use App\Domain\AbstractService;
use App\Domain\Models\ApiKey;
use App\Domain\References\ApiEntity;
use Illuminate\Support\Collection;
use Psr\Container\ContainerExceptionInterface;

class EntityAction extends ActionApi
{
    protected function action(): \Slim\Psr7\Response
    {
        $status = 200;
        $result = [];

        try {
            $entity = ltrim($this->resolveArg('args'), '/');
            $params = $this->getParamsQuery();

            // the cup panel's own AJAX calls this same endpoint under a
            // cup-only session, already gated by AuthorizationMiddleware
            // above it in routes.php - full CRUD, and a few settings-only
            // entities besides, never reachable from the public /api/v1
            $isCup = str_starts_with($this->request->getUri()->getPath(), '/cup/api');

            $service = $this->getService($entity, $isCup);
            [$canRead, $canWrite] = $this->resolvePermissions($entity, $isCup);

            if ($service !== null && $canRead) {
                switch ($this->request->getMethod()) {
                    case 'GET':
                        try {
                            $result = $service->read($params);
                        } catch (AbstractNotFoundException|\Exception $e) {
                            $status = 404;
                        }

                        break;

                    case 'POST':
                    case 'PUT':
                        if ($canWrite) {
                            $result = $service->create($this->getParamsBody());
                            $result = $this->processEntityFiles($result);

                            $status = 201;

                            $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $entity) . ':create', $result);
                            $this->logger->notice('Create entity via API', $params);
                        } else {
                            $status = 405;
                        }

                        break;

                    case 'PATCH':
                        if ($canWrite) {
                            try {
                                $result = $service->read($params);

                                if ($result) {
                                    if (!is_array($result) && !is_a($result, Collection::class)) {
                                        $result = [$result];
                                    }

                                    foreach ($result as &$item) {
                                        $item = $service->update($item, $this->getParamsBody());
                                        $item = $this->processEntityFiles($item);
                                    }

                                    $status = 202;

                                    $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $entity) . ':edit', $result);
                                    $this->logger->notice('Update entity via API', $params);
                                } else {
                                    $status = 409;
                                }
                            } catch (AbstractNotFoundException|\Exception $e) {
                                $status = 404;
                            }
                        } else {
                            $status = 405;
                        }

                        break;

                    case 'DELETE':
                        if ($canWrite) {
                            try {
                                $result = $service->read($params);

                                if ($result) {
                                    if (!is_array($result) && !is_a($result, Collection::class)) {
                                        $result = [$result];
                                    }

                                    foreach ($result as &$item) {
                                        $item = $service->delete($item);
                                    }

                                    $status = 410;

                                    $this->container->get(\App\Application\PubSub::class)->publish('api:' . str_replace('/', ':', $entity) . ':delete', $result);
                                    $this->logger->notice('Delete entity via API', $params);
                                } else {
                                    $status = 409;
                                }
                            } catch (AbstractNotFoundException|\Exception $e) {
                                $status = 404;
                            }
                        } else {
                            $status = 405;
                        }

                        break;
                }
            } elseif ($service !== null) {
                // exists, but this caller's key has no read scope for it
                $status = 403;
            } else {
                $status = 404;
            }
        } catch (AbstractException|ContainerExceptionInterface $exception) {
            $status = 503;
            $result = $exception->getTitle();
        }

        return $this
            ->respondWithJson([
                'status' => $status,
                'data' => is_a($result, Collection::class) ? $result->toArray() : $result,
            ])
            ->withStatus($status);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    private function getService(string $entity, bool $isCup): ?AbstractService
    {
        $map = ApiEntity::MAP;

        if ($isCup) {
            $map += ApiEntity::CUP_ONLY_MAP;
        }

        return isset($map[$entity]) ? $this->container->get($map[$entity]) : null;
    }

    /**
     * Whether the current caller may read / write the given entity
     *
     * A cup session gets both, unconditionally - it authenticated with a
     * password, not a key, and is already scoped by the admin's own account.
     * An API key is checked against its own scopes. Anything else (a plain
     * customer session, or an unauthenticated request let in by an "all"
     * access policy) keeps the platform's long-standing behaviour: read is
     * open, write always requires a key
     *
     * @return array{0: bool, 1: bool} [$canRead, $canWrite]
     */
    private function resolvePermissions(string $entity, bool $isCup): array
    {
        if ($isCup) {
            return [true, true];
        }

        $apiKey = $this->request->getAttribute('apikey');

        if ($apiKey instanceof ApiKey) {
            return [$apiKey->can($entity, 'read'), $apiKey->can($entity, 'write')];
        }

        return [true, false];
    }

    private function getParamsQuery(): array
    {
        $default = [
            'status' => 'work',
            'order' => [],
            'limit' => 1000,
            'offset' => 0,
        ];
        $params = $this->request->getQueryParams();

        // fix nullable values
        foreach ($params as &$value) {
            if ($value === 'null') {
                $value = null;
            }
        }
        unset($value, $params['with']);

        // `with` picks which relations get preloaded and serialized - it is an
        // internal read() knob, not a caller-facing filter. Left open to the
        // query string it lets anyone attach an unrelated relation (e.g.
        // `with[]=tokens` on the user entity) and read it back in the response

        return array_merge($default, $params);
    }

    private function getParamsBody(): array
    {
        return (array) ($this->request->getParsedBody() ?? []);
    }
}

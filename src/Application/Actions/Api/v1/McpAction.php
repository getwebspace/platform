<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\AbstractException;
use App\Domain\AbstractMcpTool;
use App\Domain\Models\ApiKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * MCP server (Model Context Protocol), Streamable HTTP transport without SSE
 * Tools are registered in container 'mcp', plugins add own tools via addMcpTool()
 */
class McpAction extends ActionApi
{
    public const SERVER_NAME = 'webspace-platform';

    /**
     * Supported protocol versions, first is preferred
     */
    public const PROTOCOL_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    // JSON-RPC errors
    private const PARSE_ERROR = -32700;
    private const INVALID_REQUEST = -32600;
    private const METHOD_NOT_FOUND = -32601;
    private const INVALID_PARAMS = -32602;

    protected function action(): \Slim\Psr7\Response
    {
        // server does not open SSE stream
        if (!$this->isPost()) {
            return $this->response->withHeader('Allow', 'POST')->withStatus(405);
        }

        $message = json_decode((string) $this->request->getBody(), true);

        if (!is_array($message) || !$message) {
            return $this->respondWithJson($this->error(null, self::PARSE_ERROR, 'Parse error'))->withStatus(400);
        }

        // batch (protocol 2025-03-26)
        if (array_is_list($message)) {
            $result = array_values(array_filter(array_map(fn ($item) => $this->handle($item), $message)));
        } else {
            $result = $this->handle($message);
        }

        // only notifications or responses were received
        if (!$result) {
            return $this->response->withStatus(202);
        }

        return $this->respondWithJson($result);
    }

    private function handle(mixed $message): ?array
    {
        if (!is_array($message) || ($message['jsonrpc'] ?? null) !== '2.0' || !is_string($message['method'] ?? null)) {
            // response from client to server request, nothing to do
            if (is_array($message) && (isset($message['result']) || isset($message['error']))) {
                return null;
            }

            return $this->error($message['id'] ?? null, self::INVALID_REQUEST, 'Invalid Request');
        }

        // notification: no response
        if (!array_key_exists('id', $message)) {
            return null;
        }

        $id = $message['id'];
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];

        return match ($message['method']) {
            'initialize' => $this->result($id, $this->initialize($params)),
            'ping' => $this->result($id, new \stdClass()),
            'tools/list' => $this->result($id, ['tools' => $this->getTools()->map(fn (AbstractMcpTool $tool) => $tool->getDefinition())->values()->all()]),
            'tools/call' => $this->callTool($id, $params),
            default => $this->error($id, self::METHOD_NOT_FOUND, 'Method not found: ' . $message['method']),
        };
    }

    private function initialize(array $params): array
    {
        $version = $params['protocolVersion'] ?? null;

        return [
            'protocolVersion' => in_array($version, self::PROTOCOL_VERSIONS, true) ? $version : self::PROTOCOL_VERSIONS[0],
            'capabilities' => [
                'tools' => ['listChanged' => false],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'title' => $this->parameter('common_title', 'WebSpace Platform'),
                'version' => !empty($_ENV['COMMIT_SHA']) ? mb_substr($_ENV['COMMIT_SHA'], 0, 7) : '1.0.0',
            ],
            'instructions' => implode(' ', array_filter([
                'Tools of online store: ' . $this->parameter('common_homepage', ''),
                $this->parameter('common_description', ''),
            ])),
        ];
    }

    /**
     * Tools available for current request
     *
     * @return Collection<string, AbstractMcpTool>
     */
    private function getTools(): Collection
    {
        $apiKey = $this->getApiKey();

        return $this->container->get('mcp')->get()->filter(fn (AbstractMcpTool $tool) => $tool->isAllowed($apiKey));
    }

    private function getApiKey(): ?ApiKey
    {
        $apiKey = $this->request->getAttribute('apikey');

        return $apiKey instanceof ApiKey ? $apiKey : null;
    }

    private function callTool(mixed $id, array $params): array
    {
        $name = $params['name'] ?? null;
        $args = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        /** @var null|AbstractMcpTool $tool */
        $tool = is_string($name) ? $this->getTools()->get($name) : null;

        if ($tool === null) {
            return $this->error($id, self::INVALID_PARAMS, 'Unknown tool: ' . (is_string($name) ? $name : ''));
        }

        $tool->setApiKey($this->getApiKey());

        try {
            $result = $tool->execute($args);

            $this->logger->info('MCP tool call', ['tool' => $name]);

            return $this->result($id, [
                'content' => [
                    ['type' => 'text', 'text' => $this->encode($result)],
                ],
                'isError' => false,
            ]);
        } catch (AbstractException $e) {
            $text = $e->getDescription() ?: $e->getTitle();
        } catch (\DomainException|\InvalidArgumentException $e) {
            $text = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->error('MCP tool call failed', ['tool' => $name, 'exception' => $e->getMessage()]);

            $text = 'Internal error';
        }

        // error of the tool is a result for the model, not protocol error
        return $this->result($id, [
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
            'isError' => true,
        ]);
    }

    private function encode(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }
        if (is_array($result) || is_a($result, Collection::class) || is_a($result, Model::class)) {
            $result = array_serialize($result);
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function result(mixed $id, mixed $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }
}

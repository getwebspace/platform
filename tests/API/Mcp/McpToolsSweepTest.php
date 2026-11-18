<?php declare(strict_types=1);

namespace tests\API\Mcp;

/**
 * Every tool of the server, one by one: how it describes itself and what it says to a call with nothing in it
 *
 * @internal
 *
 * #[CoversNothing]
 */
class McpToolsSweepTest extends McpTestCase
{
    private function definitions(): array
    {
        return array_column($this->call($this->createApiKeyToken(), 'tools/list')['result']['tools'], null, 'name');
    }

    public function testEveryToolDescribesItselfWithAValidSchema(): void
    {
        $definitions = $this->definitions();

        $this->assertGreaterThan(50, count($definitions));
        foreach ($definitions as $name => $definition) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $name);
            $this->assertNotSame('', trim($definition['description']), $name);
            $this->assertSame('object', $definition['inputSchema']['type'], $name);
            $this->assertArrayHasKey('properties', $definition['inputSchema'], $name);
            $this->assertIsBool($definition['annotations']['readOnlyHint'] ?? false, $name);

            foreach ($definition['inputSchema']['required'] ?? [] as $required) {
                $this->assertArrayHasKey($required, (array) $definition['inputSchema']['properties'], "{$name}: required {$required} is described");
            }
            foreach ((array) $definition['inputSchema']['properties'] as $field => $schema) {
                $this->assertArrayHasKey('type', $schema + ['type' => $schema['oneOf'] ?? $schema['anyOf'] ?? null], "{$name}.{$field}");
            }
        }
    }

    public function testEveryToolAnswersACallWithNothingInItWithoutBreaking(): void
    {
        $token = $this->createApiKeyToken();
        $skip = ['site_reindex'];

        foreach (array_keys($this->definitions()) as $name) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            $result = $this->call($token, 'tools/call', ['name' => $name, 'arguments' => new \stdClass()]);

            $this->assertArrayHasKey('result', $result, "{$name}: " . json_encode($result));
            $this->assertIsBool($result['result']['isError'], $name);
            $this->assertNotSame('', $result['result']['content'][0]['text'], $name);
        }
    }

    public function testEveryToolRefusesWrongTypesOfArgumentsWithoutBreaking(): void
    {
        $token = $this->createApiKeyToken();
        $skip = ['site_reindex'];
        $broken = [];

        foreach (array_keys($this->definitions()) as $name) {
            if (in_array($name, $skip, true)) {
                continue;
            }

            foreach ([['uuid' => ['x'], 'limit' => 'many', 'offset' => -5], ['uuid' => 'not-a-uuid', 'serial' => 123, 'address' => false, 'status' => [], 'search' => ['a']]] as $args) {
                $response = $this->rpc($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $args]]);
                $text = (string) $response->getBody();
                $body = json_decode($text, true);
                if (!is_array($body) || !isset($body['result'])) {
                    $broken[] = $name . ' ' . json_encode($args) . ': ' . substr(strip_tags($text), 0, 160);
                }
            }
        }

        $this->assertSame([], $broken);
    }

    public function testABatchIsAnsweredWithAListAndANotificationWithNothing(): void
    {
        $token = $this->createApiKeyToken();

        $batch = $this->rpc($token, [
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'prompts/list'],
        ]);
        $this->assertSame(200, $batch->getStatusCode());
        $answers = json_decode((string) $batch->getBody(), true);
        $this->assertSame([1, 2], array_column($answers, 'id'), 'the notification has no answer');

        $only = $this->rpc($token, [['jsonrpc' => '2.0', 'method' => 'notifications/initialized']]);
        $this->assertSame(202, $only->getStatusCode());
        $this->assertSame('', (string) $only->getBody());
    }

    public function testWhatTheClientAnswersToTheServerNeedsNoReply(): void
    {
        $response = $this->rpc($this->createApiKeyToken(), ['jsonrpc' => '2.0', 'id' => 7, 'result' => new \stdClass()]);

        $this->assertSame(202, $response->getStatusCode());
    }

    public function testAMessageThatIsNotJsonRpcIsInvalid(): void
    {
        $token = $this->createApiKeyToken();

        $answer = json_decode((string) $this->rpc($token, ['hello' => 'world', 'id' => 3])->getBody(), true);
        $this->assertSame(-32600, $answer['error']['code']);
        $this->assertSame(3, $answer['id']);

        $this->assertSame(-32600, json_decode((string) $this->rpc($token, ['jsonrpc' => '1.0', 'id' => 1, 'method' => 'ping'])->getBody(), true)['error']['code']);
        $this->assertSame(400, $this->rpc($token, 'not json at all')->getStatusCode());
        $this->assertSame(-32601, json_decode((string) $this->rpc($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'nothing/here'])->getBody(), true)['error']['code']);
    }
}

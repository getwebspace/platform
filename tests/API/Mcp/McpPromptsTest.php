<?php declare(strict_types=1);

namespace tests\API\Mcp;

/**
 * The scenarios as the model gets them: through the endpoint, for a key
 *
 * @internal
 *
 * #[CoversNothing]
 */
class McpPromptsTest extends McpTestCase
{
    public function testAFullAccessKeyIsOfferedEveryScenarioAndEachOfThemRenders(): void
    {
        $token = $this->createApiKeyToken();
        $listed = $this->prompts($token);

        $this->assertCount(10, $listed);
        foreach ($listed as $prompt) {
            $args = [];
            foreach ($prompt['arguments'] ?? [] as $argument) {
                if ($argument['required']) {
                    $args[$argument['name']] = $argument['name'] === 'change' ? '+5%' : 'something';
                }
            }

            $result = $this->call($token, 'prompts/get', ['name' => $prompt['name'], 'arguments' => $args ?: new \stdClass()]);

            $this->assertArrayNotHasKey('error', $result, $prompt['name'] . ' ' . json_encode($result));
            $this->assertSame('user', $result['result']['messages'][0]['role'], $prompt['name']);
            $this->assertStringContainsString('Rules:', $result['result']['messages'][0]['content']['text'], $prompt['name']);
            $this->assertNotSame('', $result['result']['description'] ?? $prompt['description'], $prompt['name']);
        }
    }

    public function testAKeyWithNothingToDoWithTheShopIsOfferedNoShopScenarios(): void
    {
        $token = $this->createApiKeyToken(['read' => ['page'], 'write' => []], false);

        $names = array_column($this->prompts($token), 'name');

        foreach (['handle_new_orders', 'change_prices', 'sales_analysis', 'catalog_check', 'moderate_content', 'edit_template'] as $shop) {
            $this->assertNotContains($shop, $names, $shop);
        }
    }

    public function testAScenarioThatIsNotOfferedCannotBeAskedFor(): void
    {
        $token = $this->createApiKeyToken(['read' => ['page'], 'write' => []], false);

        $result = $this->call($token, 'prompts/get', ['name' => 'change_prices', 'arguments' => ['category' => 'Jackets', 'change' => '+5%']]);

        $this->assertEquals(-32602, $result['error']['code']);
    }

    public function testBadArgumentsAreAnErrorOfTheCallNotACrash(): void
    {
        $token = $this->createApiKeyToken();

        foreach ([
            ['name' => 'change_prices', 'arguments' => ['category' => 'Jackets', 'change' => 'plenty']],
            ['name' => 'change_prices', 'arguments' => new \stdClass()],
            ['name' => 'write_publication', 'arguments' => ['topic' => '   ']],
            ['name' => 'no_such_scenario'],
            ['arguments' => []],
        ] as $params) {
            $result = $this->call($token, 'prompts/get', $params);

            $this->assertEquals(-32602, $result['error']['code'], json_encode($params));
        }
    }
}

<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\HasParameters;
use Psr\Container\ContainerInterface;

/**
 * MCP prompt: a ready scenario the operator picks in the AI client ("handle new orders"),
 * it expands into instructions for the model. Prompts do nothing by themselves - the model calls tools
 */
abstract class AbstractMcpPrompt
{
    use HasParameters;

    /**
     * Unique name, ex: handle_new_orders
     */
    public const NAME = '';

    public const TITLE = '';

    public const DESCRIPTION = '';

    /**
     * Names of the tools the scenario needs: the prompt is offered only to a caller who may use all of them
     *
     * @var string[]
     */
    public const TOOLS = [];

    protected ContainerInterface $container;

    protected \Illuminate\Cache\ArrayStore $arrayCache;

    public function __construct(ContainerInterface $container)
    {
        if (empty(static::NAME)) {
            throw new \RuntimeException('MCP prompt name is empty');
        }

        $this->container = $container;
        $this->arrayCache = $container->get(\Illuminate\Cache\ArrayStore::class);
    }

    /**
     * @return array<int, array{name: string, description: string, required: bool}>
     */
    public function getArguments(): array
    {
        return [];
    }

    /**
     * @param string[] $toolNames tools available to the caller
     */
    public function isAvailable(array $toolNames): bool
    {
        return array_diff(static::TOOLS, $toolNames) === [];
    }

    public function getDefinition(): array
    {
        return array_filter([
            'name' => static::NAME,
            'title' => static::TITLE,
            'description' => static::DESCRIPTION,
            'arguments' => $this->getArguments(),
        ]);
    }

    /**
     * Text of the instruction for the model
     *
     * @throws \InvalidArgumentException when an argument is wrong
     */
    abstract public function render(array $args = []): string;

    /**
     * Rules every scenario ends with: the model must not act on its own
     */
    protected function rules(): string
    {
        return "\n\nRules:\n"
            . "- Answer in the language the operator writes in.\n"
            . "- Never change or publish anything without showing the operator what exactly will change and getting an explicit yes.\n"
            . "- Do not delete anything and do not send messages to customers: if a reply is needed, write a draft text for the operator.\n"
            . '- If a tool returns an error, say what it is and stop, do not retry with guessed values.';
    }
}

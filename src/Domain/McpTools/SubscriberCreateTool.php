<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Service\User\SubscriberService;

class SubscriberCreateTool extends AbstractMcpTool
{
    public const NAME = 'subscriber_create';
    public const TITLE = 'Add subscriber';
    public const DESCRIPTION = 'Adds an e-mail to the newsletter subscribers. Only for an address the owner asked to subscribe - never add people on your own. Nothing is sent to the address.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = '';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['email' => ['type' => 'string']],
            'required' => ['email'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        $email = mb_strtolower($this->textArgument($args, 'email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Field email is not a valid e-mail address');
        }

        $subscriber = $this->container->get(SubscriberService::class)->create(['email' => $email]);

        $this->logger->notice('Subscriber created via MCP', ['uuid' => (string) $subscriber->uuid]);

        return ['uuid' => (string) $subscriber->uuid, 'email' => $subscriber->email];
    }
}

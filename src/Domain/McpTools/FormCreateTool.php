<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\AbstractMcpTool;
use App\Domain\McpTools\Concerns\BuildsAddress;
use App\Domain\Models\Form;
use App\Domain\Service\Form\FormService;

class FormCreateTool extends AbstractMcpTool
{
    use BuildsAddress;

    public const NAME = 'form_create';
    public const TITLE = 'Create form';
    public const DESCRIPTION = 'Creates a feedback form (title, address, who receives submissions, allowed origins). Submissions of a form are mailed to "recipients": add only addresses the operator named. '
        . 'The mail template, the template file and the "duplicate" URL are NOT handled here (they are code or send data to other hosts) - set them in the admin panel. Requires a full access API key.';
    public const READ_ONLY = false;
    public const PRIVATE = true;
    public const SCOPE = '';

    public function getInputSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'title' => $string,
                'address' => $string + ['description' => 'Latin letters, digits and dashes, made of the title by default'],
                'recipients' => ['type' => 'array', 'items' => $string, 'description' => 'E-mail addresses that get the submissions'],
                'origins' => ['type' => 'array', 'items' => $string, 'description' => 'Sites allowed to send the form, e.g. https://example.com (empty - any)'],
                'recaptcha' => ['type' => 'boolean', 'default' => true],
                'send_copy_to_author' => ['type' => 'boolean', 'default' => false],
            ],
            'required' => ['title'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (blank($title = $this->textArgument($args, 'title'))) {
            throw new \InvalidArgumentException('Field title is required');
        }

        $form = $this->container->get(FormService::class)->create([
            'title' => $title,
            'address' => $this->buildAddress('', $title, $args['address'] ?? null),
            'recaptcha' => (bool) ($args['recaptcha'] ?? true),
            'authorSend' => (bool) ($args['send_copy_to_author'] ?? false),
            'mailto' => $this->lines($args, 'recipients', fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) !== false, 'an e-mail address'),
            'origin' => $this->lines($args, 'origins', fn (string $value) => preg_match('#^https?://[\w.\-]+(:\d{1,5})?$#i', $value) === 1, 'an origin like https://example.com'),
        ]);

        $this->container->get(PubSub::class)->publish('api:form:create', $form);
        $this->logger->notice('Form created via MCP', ['uuid' => (string) $form->uuid]);

        return $this->describe($form);
    }

    /**
     * A list of validated values in the one-per-line form the model stores
     */
    protected function lines(array $args, string $key, callable $valid, string $expected): string
    {
        $values = $args[$key] ?? [];

        if (!is_array($values)) {
            throw new \InvalidArgumentException("Field {$key} must be a list");
        }

        $values = array_values(array_unique(array_map(fn ($value) => trim((string) $value), $values)));

        foreach ($values as $value) {
            if (!$valid($value)) {
                throw new \InvalidArgumentException("Field {$key}: \"{$value}\" is not {$expected}");
            }
        }

        return implode(PHP_EOL, $values);
    }

    protected function describe(Form $form): array
    {
        return [
            'uuid' => (string) $form->uuid,
            'title' => $form->title,
            'address' => $form->address,
            'recipients' => array_values(array_filter((array) $form->mailto)),
            'origins' => array_values(array_filter((array) $form->origin)),
            'recaptcha' => (bool) $form->recaptcha,
            'send_copy_to_author' => (bool) $form->authorSend,
        ];
    }
}

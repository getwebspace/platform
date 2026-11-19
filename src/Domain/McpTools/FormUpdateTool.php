<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Application\PubSub;
use App\Domain\Service\Form\FormService;

class FormUpdateTool extends FormCreateTool
{
    public const NAME = 'form_update';
    public const TITLE = 'Update form';
    public const DESCRIPTION = 'Changes a feedback form (uuid from form_list): title, recipients (replaces the list), origins (replaces the list), recaptcha, send_copy_to_author. Pass only the fields to change. '
        . 'The address is kept so the site links keep working. Template, template file and "duplicate" are not handled here. Requires a full access API key.';

    public function getInputSchema(): array
    {
        $schema = parent::getInputSchema();
        unset($schema['properties']['address']);
        $schema['properties'] = ['uuid' => ['type' => 'string']] + $schema['properties'];
        $schema['required'] = ['uuid'];

        return $schema;
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        /** @var FormService $service */
        $service = $this->container->get(FormService::class);
        $form = $service->read(['uuid' => $args['uuid']]);
        $data = [];

        if (array_key_exists('title', $args)) {
            if (blank($title = $this->textArgument($args, 'title'))) {
                throw new \InvalidArgumentException('Field title cannot be empty');
            }
            $data['title'] = $title;
        }
        if (array_key_exists('recipients', $args)) {
            $data['mailto'] = $this->lines($args, 'recipients', fn (string $value) => filter_var($value, FILTER_VALIDATE_EMAIL) !== false, 'an e-mail address');
        }
        if (array_key_exists('origins', $args)) {
            $data['origin'] = $this->lines($args, 'origins', fn (string $value) => preg_match('#^https?://[\w.\-]+(:\d{1,5})?$#i', $value) === 1, 'an origin like https://example.com');
        }
        if (array_key_exists('recaptcha', $args)) {
            $data['recaptcha'] = (bool) $args['recaptcha'];
        }
        if (array_key_exists('send_copy_to_author', $args)) {
            $data['authorSend'] = (bool) $args['send_copy_to_author'];
        }
        if (!$data) {
            throw new \InvalidArgumentException('Nothing to change');
        }

        $changed = array_keys($data);
        // address generation on update would move the form to another URL
        $form = $service->update($form, $data + ['address' => $form->address]);

        $this->container->get(PubSub::class)->publish('api:form:edit', $form);
        $this->logger->notice('Form updated via MCP', ['uuid' => (string) $form->uuid, 'fields' => $changed]);

        return $this->describe($form) + ['changed_fields' => $changed];
    }
}

<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\File;
use App\Domain\Service\Form\DataService;

class FormDataGetTool extends AbstractMcpTool
{
    public const NAME = 'form_data_get';
    public const TITLE = 'Get form submission';
    public const DESCRIPTION = 'Returns a form submission: all fields, message and attached files. Requires a full access API key.';
    public const PRIVATE = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uuid' => ['type' => 'string', 'description' => 'Submission uuid, see form_data_list'],
            ],
            'required' => ['uuid'],
        ];
    }

    public function execute(array $args = []): mixed
    {
        if (!is_string($args['uuid'] ?? null) || !\Ramsey\Uuid\Uuid::isValid($args['uuid'])) {
            throw new \InvalidArgumentException('Field uuid is required');
        }

        $data = $this->container->get(DataService::class)->read(['uuid' => $args['uuid']]);
        $homepage = rtrim((string) $this->parameter('common_homepage', ''), '/');

        return [
            'uuid' => (string) $data->uuid,
            'form' => $data->form ? ['address' => $data->form->address, 'title' => $data->form->title] : null,
            'date' => $data->date?->format('Y-m-d H:i'),
            'fields' => collect((array) $data->data)
                ->reject(fn ($value, $key) => in_array($key, ['recaptcha', 'g-recaptcha-response', 'token'], true))
                ->all(),
            'message' => $data->message,
            'files' => $data->files->map(fn (File $file) => [
                'name' => $file->filename(),
                'type' => $file->type,
                'url' => $homepage . $file->public_path(),
            ])->values()->all(),
        ];
    }
}

<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\Form;
use App\Domain\Models\FormData;
use App\Domain\Service\Form\DataService;
use App\Domain\Service\Form\FormService;

class FormDataListTool extends AbstractMcpTool
{
    public const NAME = 'form_data_list';
    public const TITLE = 'Form submissions';
    public const DESCRIPTION = 'Submissions of site forms (requests, questionnaires, feedback), newest first, with a short preview of the fields. '
        . 'Without "form" returns submissions of all forms and the list of forms. Use form_data_get for the full submission. Requires a full access API key.';
    public const PRIVATE = true;

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'form' => ['type' => 'string', 'description' => 'Form address or uuid'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        /** @var FormService $formService */
        $formService = $this->container->get(FormService::class);
        /** @var DataService $dataService */
        $dataService = $this->container->get(DataService::class);

        $forms = $formService->read()->keyBy(fn (Form $form) => (string) $form->uuid);
        $filter = [];

        if (!blank($args['form'] ?? null)) {
            $form = $forms->first(fn (Form $form) => (string) $form->uuid === $args['form'] || $form->address === $args['form']);

            if ($form === null) {
                throw new \InvalidArgumentException('Unknown form, allowed: ' . $forms->pluck('address')->implode(', '));
            }

            $filter['form_uuid'] = [(string) $form->uuid];
        }

        $result = $this->paginate(
            $args,
            fn (int $limit, int $offset) => $dataService->read($filter + [
                'order' => ['date' => 'desc'],
                'limit' => $limit,
                'offset' => $offset,
            ]),
            fn (FormData $data) => [
                'uuid' => (string) $data->uuid,
                'form' => $forms->get((string) $data->form_uuid)?->title,
                'date' => $data->date?->format('Y-m-d H:i'),
                'preview' => collect((array) $data->data)
                    ->reject(fn ($value, $key) => in_array($key, ['recaptcha', 'g-recaptcha-response', 'token'], true))
                    ->map(fn ($value) => str_truncate(is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE), 80))
                    ->take(6)
                    ->all(),
                'files' => $data->files->count(),
            ]
        );

        if (!isset($filter['form_uuid'])) {
            $result['forms'] = $forms->map(fn (Form $form) => ['address' => $form->address, 'title' => $form->title])->values()->all();
        }

        return $result;
    }
}

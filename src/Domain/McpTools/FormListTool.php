<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\Form;

class FormListTool extends AbstractMcpTool
{
    public const NAME = 'form_list';
    public const TITLE = 'Site forms';
    public const DESCRIPTION = 'The forms of the site (feedback, requests, questionnaires): address, where the submissions go (recipients), whether a captcha is on and how many submissions there are, with the date of the last one. '
        . 'The submissions themselves: form_data_list. Requires a full access API key.';
    public const PRIVATE = true;

    public function execute(array $args = []): mixed
    {
        return Form::all()
            ->sortBy('title')
            ->map(fn (Form $form) => [
                'uuid' => (string) $form->uuid,
                'title' => $form->title,
                'address' => $form->address,
                'recipients' => array_values((array) $form->mailto),
                'recaptcha' => (bool) $form->recaptcha,
                'send_copy_to_author' => (bool) $form->authorSend,
                'submissions' => $form->entries()->count(),
                'last_submission' => ($last = $form->entries()->max('date')) ? datetime($last)->format('Y-m-d H:i') : null,
            ])
            ->values()
            ->all();
    }
}

<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\FormData;
use App\Domain\Service\Form\DataService;
use App\Domain\Service\Form\FormService;

/**
 * What visitors sent through the forms of the site, and what the administrator does with it
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupFormDataTest extends CupTestCase
{
    private function form(string $title = 'Contact', string $address = 'contact'): \App\Domain\Models\Form
    {
        $this->admin();

        return $this->getService(FormService::class)->create(['title' => $title, 'address' => $address, 'mailto' => 'boss@example.com']);
    }

    private function sent(\App\Domain\Models\Form $form, array $data = ['email' => 'visitor@example.com'], string $message = 'Hello', bool $spam = false): FormData
    {
        return $this->getService(DataService::class)->create([
            'form_uuid' => $form->uuid, 'data' => $data, 'message' => $message, 'is_spam' => $spam, 'spam_reason' => $spam ? 'looks like spam' : '',
        ]);
    }

    // --------------------------------------------------------------------- lists

    public function testTheListOfFormsOpens(): void
    {
        $this->form('Callback request', 'callback');

        $this->assertStringContainsString('Callback request', (string) $this->cup('GET', '/cup/form')->getBody());
    }

    public function testTheSubmissionsOfAFormAreListedWithoutTheSpam(): void
    {
        $form = $this->form();
        $real = $this->sent($form, ['name' => 'Honest Ann'], 'real message');
        $junk = $this->sent($form, ['name' => 'Bot Bob'], 'cheap pills', true);

        $response = $this->cup('GET', "/cup/form/{$form->uuid}/view");
        $body = (string) $response->getBody();

        $this->assertRenders($response);
        $this->assertStringContainsString("/view/{$real->uuid}", $body);
        $this->assertStringNotContainsString($junk->uuid, $body);
    }

    public function testTheSpamHasItsOwnListWithTheReasonAndTheCountIsShown(): void
    {
        $form = $this->form();
        $real = $this->sent($form, ['name' => 'Honest Ann'], 'real message');
        $first = $this->sent($form, ['name' => 'Bot Bob'], 'cheap pills', true);
        $second = $this->sent($form, ['name' => 'Bot Eve'], 'more pills', true);

        $body = (string) $this->cup('GET', "/cup/form/{$form->uuid}/view?spam=1")->getBody();

        $this->assertStringContainsString($first->uuid, $body);
        $this->assertStringContainsString($second->uuid, $body);
        $this->assertStringNotContainsString($real->uuid, $body);
        $this->assertStringContainsString('looks like spam', $body);
        $this->assertStringContainsString('/restore', $body, 'with the button that takes it out of the spam');
        $this->assertStringContainsString('(2)', (string) $this->cup('GET', "/cup/form/{$form->uuid}/view")->getBody(), 'the count of the spam is on the other tab');
    }

    public function testTheNewestSubmissionIsFirst(): void
    {
        $form = $this->form();
        $old = $this->sent($form, ['name' => 'Old one'], 'the older message');
        FormData::where('uuid', $old->uuid)->update(['date' => date('Y-m-d H:i:s', strtotime('-3 days'))]);
        $new = $this->sent($form, ['name' => 'New one'], 'the newer message');

        $body = (string) $this->cup('GET', "/cup/form/{$form->uuid}/view")->getBody();

        $this->assertLessThan(strpos($body, $old->uuid), strpos($body, $new->uuid));
    }

    public function testTheSubmissionsOfAnotherFormAreNotInTheList(): void
    {
        $form = $this->form();
        $other = $this->form('Order', 'order');
        $foreign = $this->sent($other, ['name' => 'Someone'], 'message to the other form');

        $this->assertStringNotContainsString($foreign->uuid, (string) $this->cup('GET', "/cup/form/{$form->uuid}/view")->getBody());
    }

    public function testTheListOfAnUnknownFormGoesBackToTheForms(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('GET', "/cup/form/{$uuid}/view"), '/cup/form', $uuid);
        }
    }

    // --------------------------------------------------------------------- detail

    public function testASubmissionIsShownWithItsPreviewAndTheWayToDeleteIt(): void
    {
        $form = $this->form();
        $data = $this->sent($form, ['name' => 'Ann Lee', 'email' => 'ann@example.com', 'topic' => 'Delivery'], 'Where is my order?');

        $response = $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}");
        $body = (string) $response->getBody();

        $this->assertRenders($response);
        $this->assertStringContainsString("/cup/form/{$form->uuid}/view/{$data->uuid}/preview", $body);
        $this->assertStringContainsString("/cup/form/{$form->uuid}/view/{$data->uuid}/delete", $body);

        $preview = (string) $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}/preview")->getBody();
        $this->assertStringContainsString('Where is my order?', $preview);
    }

    public function testTheFieldsOfASubmissionAreShownWhenThereIsNoMessage(): void
    {
        $form = $this->form();
        $data = $this->sent($form, ['name' => 'Ann Lee', 'email' => 'ann@example.com'], '');

        $preview = (string) $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}/preview")->getBody();

        foreach (['Ann Lee', 'ann@example.com'] as $part) {
            $this->assertStringContainsString($part, $preview);
        }
    }

    public function testASubmissionIsNotShownUnderAnotherForm(): void
    {
        $form = $this->form();
        $other = $this->form('Order', 'order');
        $data = $this->sent($other, ['name' => 'Someone'], 'private to the other form');

        $response = $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}");

        $this->assertRedirectsTo($response, '/cup/form');
        $this->assertStringNotContainsString('private to the other form', (string) $response->getBody());
    }

    public function testAnUnknownSubmissionGoesBackToTheForms(): void
    {
        $form = $this->form();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('GET', "/cup/form/{$form->uuid}/view/{$uuid}"), '/cup/form', $uuid);
        }
    }

    public function testWhatAVisitorSentIsEscaped(): void
    {
        $form = $this->form();
        $hostile = '<script>alert("v")</script>';
        $data = $this->sent($form, ['name' => $hostile, $hostile => 'key too'], $hostile);

        $other = $this->sent($form, [$hostile => $hostile], '');
        foreach (["/cup/form/{$form->uuid}/view", "/cup/form/{$form->uuid}/view/{$data->uuid}", "/cup/form/{$form->uuid}/view/{$data->uuid}/preview", "/cup/form/{$form->uuid}/view/{$other->uuid}/preview"] as $path) {
            $this->assertStringNotContainsString($hostile, (string) $this->cup('GET', $path)->getBody(), $path);
        }
    }

    // ------------------------------------------------------------------- delete

    public function testASubmissionIsDeleted(): void
    {
        $form = $this->form();
        $data = $this->sent($form);
        $keep = $this->sent($form, ['email' => 'second@example.com']);

        $response = $this->cup('POST', "/cup/form/{$form->uuid}/view/{$data->uuid}/delete");

        $this->assertRedirectsTo($response, "/cup/form/{$form->uuid}/view");
        $this->assertNull(FormData::find($data->uuid));
        $this->assertNotNull(FormData::find($keep->uuid));
    }

    public function testASubmissionIsNotDeletedThroughAnotherForm(): void
    {
        $form = $this->form();
        $other = $this->form('Order', 'order');
        $data = $this->sent($other);

        $this->cup('POST', "/cup/form/{$form->uuid}/view/{$data->uuid}/delete");

        $this->assertNotNull(FormData::find($data->uuid), 'the address names a form, and the submission is not of it');
    }

    public function testDeletingAnUnknownSubmissionChangesNothing(): void
    {
        $form = $this->form();
        $data = $this->sent($form);

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertSame(302, $this->cup('POST', "/cup/form/{$form->uuid}/view/{$uuid}/delete")->getStatusCode(), $uuid);
        }

        $this->assertNotNull(FormData::find($data->uuid));
    }

    public function testTheWayBackAfterADeleteIsTheListOfThatFormAndNothingElse(): void
    {
        $this->admin();

        foreach (['not-a-uuid', '%2f%2fevil.example', 'x%0d%0aSet-Cookie:a=b'] as $uuid) {
            $response = $this->cup('POST', "/cup/form/{$uuid}/view/not-a-uuid/delete");

            $location = $response->getHeaderLine('Location');
            if ($location !== '') {
                $this->assertStringStartsWith('/cup/form', $location, $uuid);
            }
            $this->assertStringNotContainsString("\n", $location, $uuid);
            $this->assertFalse($response->hasHeader('Set-Cookie') && str_contains($response->getHeaderLine('Set-Cookie'), 'a=b'), $uuid);
        }
    }

    // ------------------------------------------------------------------- restore

    public function testASubmissionTakenForSpamIsRestored(): void
    {
        $form = $this->form();
        $data = $this->sent($form, ['name' => 'Real person'], 'wrongly caught', true);

        $response = $this->cup('POST', "/cup/form/{$form->uuid}/view/{$data->uuid}/restore");

        $this->assertRedirectsTo($response, "/cup/form/{$form->uuid}/view?spam=1");
        $fresh = FormData::find($data->uuid);
        $this->assertFalse((bool) $fresh->is_spam);
        $this->assertSame('', $fresh->spam_reason);
        $this->assertStringContainsString($data->uuid, (string) $this->cup('GET', "/cup/form/{$form->uuid}/view")->getBody());
    }

    public function testASubmissionIsNotRestoredThroughAnotherForm(): void
    {
        $form = $this->form();
        $other = $this->form('Order', 'order');
        $data = $this->sent($other, ['a' => 'b'], 'spam of the other form', true);

        $this->cup('POST', "/cup/form/{$form->uuid}/view/{$data->uuid}/restore");

        $this->assertTrue((bool) FormData::find($data->uuid)->is_spam);
    }

    public function testRestoringNeedsAPostAndAKnownSubmission(): void
    {
        $form = $this->form();
        $data = $this->sent($form, ['a' => 'b'], 'caught', true);

        $this->assertNotSame(200, $this->cup('GET', "/cup/form/{$form->uuid}/view/{$data->uuid}/restore")->getStatusCode());
        $this->assertTrue((bool) FormData::find($data->uuid)->is_spam);

        $unknown = $this->cup('POST', "/cup/form/{$form->uuid}/view/" . \Ramsey\Uuid\Uuid::uuid4() . '/restore');
        $this->assertRedirectsTo($unknown, "/cup/form/{$form->uuid}/view?spam=1");
    }

    // ------------------------------------------------- from the site to the panel

    public function testASubmissionOfTheSiteArrivesInTheList(): void
    {
        $form = $this->form();

        $this->http('POST', '/form/contact', [
            'headers' => ['X-Requested-With' => 'XMLHttpRequest'],
            'form_params' => ['email' => 'site-visitor@example.com', 'body' => 'a message from the site'],
        ]);

        $stored = FormData::where('form_uuid', $form->uuid)->first();
        $this->assertNotNull($stored);
        $this->assertStringContainsString($stored->uuid, (string) $this->cup('GET', "/cup/form/{$form->uuid}/view")->getBody());
        $this->assertStringContainsString('a message from the site', (string) $this->cup('GET', "/cup/form/{$form->uuid}/view/{$stored->uuid}/preview")->getBody());
    }
}

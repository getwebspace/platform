<?php declare(strict_types=1);

namespace tests\Domain\Tasks;

use App\Application\PubSub;
use App\Domain\Casts\Task\Status;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Service\User\SubscriberService;
use App\Domain\Service\User\UserService;
use App\Domain\Tasks\SendMailTask;
use App\Domain\Tasks\SendNewsLetterMailTask;
use tests\Concerns\FakesSmtp;
use tests\Concerns\MakesAuthRequests;
use tests\Concerns\RunsTasks;
use tests\TestCase;

/**
 * Letters, against a throw-away SMTP server that keeps what it is given
 *
 * @internal
 *
 * #[CoversNothing]
 */
class MailTasksTest extends TestCase
{
    use FakesSmtp;
    use MakesAuthRequests;
    use RunsTasks;

    public function tearDown(): void
    {
        $this->stopSmtp();

        parent::tearDown();
    }

    private function decoded(array $mail): string
    {
        $body = $mail['data'];

        // quoted-printable / base64 bodies are readable only once decoded
        if (preg_match('/Content-Transfer-Encoding: base64\r?\n\r?\n(.*)$/s', $body, $m)) {
            return base64_decode(preg_replace('/\s+/', '', $m[1]), true) ?: $body;
        }

        return quoted_printable_decode($body);
    }

    // --------------------------------------------------------------- one letter

    public function testALetterIsSentToTheRecipient(): void
    {
        $this->startSmtp();

        $task = $this->runTask(SendMailTask::class, ['subject' => 'Your order', 'to' => 'buyer@example.com', 'body' => 'Thank you']);

        $this->assertEquals(Status::DONE, $task->status, $task->output);
        $this->assertSame('sent to buyer@example.com', $task->output);

        $mails = $this->sentMails();
        $this->assertCount(1, $mails);
        $this->assertSame('shop@example.com', $mails[0]['from']);
        $this->assertSame(['buyer@example.com'], $mails[0]['to']);
        $this->assertStringContainsString('Subject: Your order', $mails[0]['data']);
        $this->assertStringContainsString('Thank you', $this->decoded($mails[0]));
    }

    public function testEveryKindOfRecipientGetsTheLetter(): void
    {
        $this->startSmtp();

        $this->runTask(SendMailTask::class, [
            'to' => ['one@example.com' => 'One', 'two@example.com'],
            'cc' => 'copy@example.com',
            'bcc' => ['hidden@example.com'],
            'body' => 'x',
        ]);

        $this->assertEqualsCanonicalizing(
            ['one@example.com', 'two@example.com', 'copy@example.com', 'hidden@example.com'],
            $this->recipients()
        );
        $this->assertStringNotContainsString('hidden@example.com', $this->sentMails()[0]['data'], 'a blind copy stays out of the headers');
    }

    public function testTheSubjectFallsBackToTheConfiguredOne(): void
    {
        $this->startSmtp();
        $this->getService(\App\Domain\Service\Parameter\ParameterService::class)->create(['name' => 'mail_subject', 'value' => 'Default subject of the shop']);

        $this->runTask(SendMailTask::class, ['to' => 'buyer@example.com', 'body' => 'x']);

        $this->assertStringContainsString('Subject: Default subject of the shop', $this->sentMails()[0]['data']);
    }

    public function testTheBodyCanBeATemplateStringWithData(): void
    {
        $this->startSmtp();

        $this->runTask(SendMailTask::class, ['to' => 'buyer@example.com', 'template' => '<p>Hello {{ name }}</p>', 'data' => ['name' => 'Ann']]);

        $mail = $this->sentMails()[0];
        $this->assertStringContainsString('Content-Type: text/html', $mail['data']);
        $this->assertStringContainsString('<p>Hello Ann</p>', $this->decoded($mail));
    }

    public function testDataWithoutATemplateIsSentAsEscapedText(): void
    {
        $this->startSmtp();

        $this->runTask(SendMailTask::class, ['to' => 'buyer@example.com', 'data' => ['note' => '<b>bold</b>']]);

        $mail = $this->sentMails()[0];
        $this->assertStringContainsString('Content-Type: text/plain', $mail['data']);
        $this->assertStringNotContainsString('<b>bold</b>', $this->decoded($mail), 'markup is escaped, not delivered');
    }

    public function testAnAttachmentTravelsWithTheLetter(): void
    {
        $this->startSmtp();
        $file = sys_get_temp_dir() . '/invoice-' . uniqid() . '.txt';
        file_put_contents($file, 'invoice');

        try {
            $this->runTask(SendMailTask::class, ['to' => 'buyer@example.com', 'body' => 'x', 'attachments' => ['invoice.txt' => $file]]);
        } finally {
            @unlink($file);
        }

        $this->assertStringContainsString('filename=invoice.txt', $this->sentMails()[0]['data']);
    }

    public function testAnUnreachableServerFailsTheTaskWithTheReason(): void
    {
        $this->configureMail(1); // nothing listens there

        $announced = 0;
        $this->getService(PubSub::class)->subscribe('task:mail:send', function () use (&$announced): void {
            ++$announced;
        });

        $task = $this->runTask(SendMailTask::class, ['to' => 'buyer@example.com', 'body' => 'x']);

        $this->assertEquals(Status::FAIL, $task->status);
        $this->assertNotSame('', $task->output, 'a failed send must say why');
        $this->assertSame(1, $announced);
    }

    public function testABadRecipientFailsTheTaskInsteadOfPretendingToSend(): void
    {
        $this->startSmtp();

        $task = $this->runTask(SendMailTask::class, ['to' => 'not an address', 'body' => 'x']);

        $this->assertEquals(Status::FAIL, $task->status);
        $this->assertSame([], $this->sentMails());
    }

    // --------------------------------------------------------------- newsletter

    private function usersWithMail(int $count): array
    {
        $emails = [];
        foreach (range(1, $count) as $i) {
            $emails[] = $this->makeUser(['email' => "reader{$i}@example.com", 'username' => "reader{$i}"])->email;
        }

        return $emails;
    }

    public function testTheNewsletterReachesEveryRecipientExactlyOnce(): void
    {
        $this->startSmtp();
        // more than one batch of five, so the paging is exercised
        $emails = $this->usersWithMail(7);

        $task = $this->runTask(SendNewsLetterMailTask::class, ['subject' => 'News', 'body' => 'Hello', 'type' => 'users']);

        $this->assertEquals(Status::DONE, $task->status, $task->output);
        $this->assertSame('sent 7, failed 0', $task->output);
        $received = $this->recipients();
        sort($received);
        sort($emails);
        $this->assertSame($emails, $received);
    }

    public function testTheNewsletterTypePicksTheAudience(): void
    {
        $this->startSmtp();
        $this->usersWithMail(2);
        $this->getService(SubscriberService::class)->create(['email' => 'subscriber@example.com']);

        $this->runTask(SendNewsLetterMailTask::class, ['type' => 'subscribers', 'body' => 'Hello']);
        $this->assertSame(['subscriber@example.com'], $this->recipients());
    }

    public function testEveryoneIsMailedOnceEvenIfBothAUserAndASubscriber(): void
    {
        $this->startSmtp();
        $this->usersWithMail(2);
        $this->getService(SubscriberService::class)->create(['email' => 'reader1@example.com']);
        $this->getService(SubscriberService::class)->create(['email' => 'only-subscriber@example.com']);

        $this->runTask(SendNewsLetterMailTask::class, ['type' => 'all', 'body' => 'Hello']);

        $received = $this->recipients();
        sort($received);
        $this->assertSame(['only-subscriber@example.com', 'reader1@example.com', 'reader2@example.com'], $received);
    }

    public function testTheNewsletterSkipsBlockedUsersAndThoseWhoDeclined(): void
    {
        $this->startSmtp();
        $this->makeUser(['username' => 'ok', 'email' => 'ok@example.com']);
        $this->makeUser(['username' => 'blocked', 'email' => 'blocked@example.com', 'status' => UserStatus::BLOCK]);
        $this->makeUser(['username' => 'declined', 'email' => 'declined@example.com', 'is_allow_mail' => false]);

        $task = $this->runTask(SendNewsLetterMailTask::class, ['type' => 'users', 'body' => 'Hello']);

        $this->assertSame(['ok@example.com'], $this->recipients(), $task->status . ': ' . $task->output);
    }

    public function testOneBadAddressDoesNotStopTheNewsletter(): void
    {
        $this->startSmtp();
        $this->getService(SubscriberService::class)->create(['email' => 'good@example.com']);
        // the subscriber service validates, so the broken one goes in directly
        \App\Domain\Models\UserSubscriber::create(['email' => 'broken address']);

        $task = $this->runTask(SendNewsLetterMailTask::class, ['type' => 'subscribers', 'body' => 'Hello']);

        $this->assertEquals(Status::DONE, $task->status);
        $this->assertStringStartsWith('sent 1, failed 1 (last error:', $task->output);
        $this->assertSame(['good@example.com'], $this->recipients());
    }

    public function testTheNewsletterReportsFailuresAndStillFinishes(): void
    {
        $this->configureMail(1);
        $this->usersWithMail(2);

        $task = $this->runTask(SendNewsLetterMailTask::class, ['type' => 'users', 'body' => 'Hello']);

        $this->assertEquals(Status::DONE, $task->status);
        $this->assertStringStartsWith('sent 0, failed 2', $task->output);
    }

    public function testAnUnknownAudienceSendsNothing(): void
    {
        $this->startSmtp();
        $this->usersWithMail(2);

        $this->runTask(SendNewsLetterMailTask::class, ['type' => 'nobody', 'body' => 'Hello']);

        $this->assertSame([], $this->sentMails());
    }
}

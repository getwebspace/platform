<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Models\Task;
use App\Domain\Models\User;
use App\Domain\Models\UserGroup;
use App\Domain\Models\UserSubscriber;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\User\GroupService;
use App\Domain\Service\User\SubscriberService;
use App\Domain\Tasks\SendNewsLetterMailTask;

/**
 * The people of the site: accounts, subscribers and the newsletter
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupUsersTest extends CupTestCase
{
    private function newUser(array $extra = []): array
    {
        return $extra + ['username' => 'newbie', 'email' => 'newbie@example.com', 'password' => 'first password', 'save' => 'exit'];
    }

    private function signIn(string $identifier, string $password): int
    {
        return $this->http('POST', '/auth/login', [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => ['identifier' => $identifier, 'password' => $password],
        ])->getStatusCode();
    }

    private function add(array $extra = []): User
    {
        $response = $this->cup('POST', '/cup/user/add', ['form_params' => $this->newUser($extra)]);
        $this->assertSame(302, $response->getStatusCode(), $this->why($response));

        return User::where('username', $extra['username'] ?? 'newbie')->firstOrFail();
    }

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    // -------------------------------------------------------------------- list

    public function testTheListShowsWorkingAccountsFirstAndOnly(): void
    {
        $this->admin();
        $this->makeUser(['username' => 'worker', 'email' => 'worker@example.com']);
        $this->makeUser(['username' => 'banned', 'email' => 'banned@example.com', 'status' => UserStatus::BLOCK]);
        $this->makeUser(['username' => 'waiting', 'email' => 'waiting@example.com', 'status' => UserStatus::CONFIRMATION]);
        $this->makeUser(['username' => 'removed', 'email' => 'removed@example.com', 'status' => UserStatus::DELETE]);

        $default = (string) $this->cup('GET', '/cup/user')->getBody();
        $this->assertStringContainsString('worker@example.com', $default);
        foreach (['banned', 'waiting', 'removed'] as $other) {
            $this->assertStringNotContainsString($other . '@example.com', $default, $other);
        }

        foreach (['block' => 'banned', 'confirmation' => 'waiting', 'delete' => 'removed'] as $state => $who) {
            $body = (string) $this->cup('GET', '/cup/user?state=' . $state)->getBody();

            $this->assertStringContainsString($who . '@example.com', $body, $state);
            $this->assertStringNotContainsString('worker@example.com', $body, $state);
        }

        $all = (string) $this->cup('GET', '/cup/user?state=all')->getBody();
        foreach (['worker', 'banned', 'waiting', 'removed'] as $who) {
            $this->assertStringContainsString($who . '@example.com', $all, $who);
        }
    }

    public function testAnUnknownStateIsTheDefaultOne(): void
    {
        $this->admin();
        $this->makeUser(['username' => 'banned', 'email' => 'banned@example.com', 'status' => UserStatus::BLOCK]);

        $body = (string) $this->cup('GET', '/cup/user?state=' . urlencode("'; DROP TABLE user; --"))->getBody();

        $this->assertStringNotContainsString('banned@example.com', $body);
        $this->assertGreaterThan(0, User::count());
    }

    public function testTheListCanBeSearchedByAnyPartOfTheNameOrAddress(): void
    {
        $this->admin();
        $this->makeUser(['username' => 'ann', 'email' => 'ann.smith@example.com', 'firstname' => 'Анна', 'lastname' => 'Іваненко', 'phone' => '+380501234567']);
        $this->makeUser(['username' => 'bob', 'email' => 'bob@example.org', 'firstname' => 'Bob']);

        foreach (['ann.smith', 'ANN.SMITH', 'анна', 'ІВАНЕНКО', '380501234567'] as $term) {
            $body = (string) $this->cup('GET', '/cup/user?q=' . urlencode($term))->getBody();

            $this->assertStringContainsString('ann.smith@example.com', $body, $term);
            $this->assertStringNotContainsString('bob@example.org', $body, $term);
        }

        $this->assertStringNotContainsString('ann.smith@example.com', (string) $this->cup('GET', '/cup/user?q=nobody-by-this-name')->getBody());
    }

    public function testTheListCanBeLimitedToAGroup(): void
    {
        $this->admin();
        $staff = $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => []]);
        $this->makeUser(['username' => 'inside', 'email' => 'inside@example.com', 'group_uuid' => $staff->uuid]);
        $this->makeUser(['username' => 'outside', 'email' => 'outside@example.com']);

        $body = (string) $this->cup('GET', '/cup/user?group_uuid=' . $staff->uuid)->getBody();

        $this->assertStringContainsString('inside@example.com', $body);
        $this->assertStringNotContainsString('outside@example.com', $body);
    }

    // --------------------------------------------------------------------- add

    public function testAnAccountIsAddedWithItsPasswordHashed(): void
    {
        $this->admin();

        $user = $this->add();

        $this->assertSame('newbie@example.com', $user->email);
        $this->assertSame(UserStatus::WORK, $user->status);
        $this->assertNotSame('first password', $user->getAttributes()['password']);
        $this->assertSame(200, $this->signIn('newbie', 'first password'));
        $this->assertSame(400, $this->signIn('newbie', 'another password'));
    }

    public function testSaveAndContinueOpensTheNewAccount(): void
    {
        $this->admin();

        $response = $this->cup('POST', '/cup/user/add', ['form_params' => $this->newUser(['save' => 'continue'])]);

        $this->assertRedirectsTo($response, '/cup/user/' . User::where('username', 'newbie')->value('uuid') . '/edit');
    }

    public function testAnAccountIsPlacedInTheChosenGroup(): void
    {
        $this->admin();
        $group = $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => ['cup:main']]);

        $user = $this->add(['group_uuid' => $group->uuid]);

        $this->assertSame($group->uuid, $user->group_uuid);
    }

    public function testAnAccountWithNothingToSignInWithIsRefused(): void
    {
        $this->admin();
        $before = User::count();

        $response = $this->cup('POST', '/cup/user/add', ['form_params' => ['password' => 'x', 'firstname' => 'Nobody']]);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertSame($before, User::count());
    }

    public function testATakenOrMalformedValueIsReportedAndNothingIsAdded(): void
    {
        $this->admin();
        $this->makeUser(['username' => 'taken', 'email' => 'taken@example.com', 'phone' => '+380501234567']);
        $before = User::count();

        $cases = [
            'username' => ['username' => 'taken'],
            'e-mail' => ['email' => 'taken@example.com'],
            'phone' => ['phone' => '+380501234567'],
            'malformed e-mail' => ['email' => 'not-an-address'],
            'malformed phone' => ['phone' => 'abc'],
        ];

        foreach ($cases as $case => $values) {
            $response = $this->cup('POST', '/cup/user/add', ['form_params' => $values + $this->newUser(['username' => 'fresh', 'email' => 'fresh@example.com'])]);

            $this->assertSame(200, $response->getStatusCode(), $case . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $case);
        }

        $this->assertSame($before, User::count());
    }

    public function testABannedMailDomainCannotBeAdded(): void
    {
        $this->admin();
        $this->setParameter('user_email_list', 'spam.example');
        $before = User::count();

        $response = $this->cup('POST', '/cup/user/add', ['form_params' => $this->newUser(['email' => 'who@spam.example'])]);

        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertSame($before, User::count());
    }

    public function testAnAccountMadeWithoutAPasswordCannotSignIn(): void
    {
        $this->admin();
        $this->add(['password' => '']);

        foreach (['', 'anything', '0'] as $password) {
            $this->assertNotSame(200, $this->signIn('newbie', $password), "'{$password}'");
        }
    }

    // -------------------------------------------------------------------- edit

    public function testTheEditFormShowsTheAccountButNotItsPassword(): void
    {
        $this->admin();
        $user = $this->add(['firstname' => 'Neo']);

        $body = (string) $this->cup('GET', "/cup/user/{$user->uuid}/edit")->getBody();

        $this->assertStringContainsString('newbie@example.com', $body);
        $this->assertStringContainsString('Neo', $body);
        $this->assertStringNotContainsString($user->getAttributes()['password'], $body, 'the hash is not for the page');
        $this->assertStringNotContainsString('first password', $body);
    }

    public function testEditingChangesTheProfileAndKeepsThePasswordWhenTheFieldIsEmpty(): void
    {
        $this->admin();
        $user = $this->add();

        $response = $this->cup('POST', "/cup/user/{$user->uuid}/edit", ['form_params' => [
            'username' => 'newbie', 'email' => 'newbie@example.com', 'firstname' => 'Thomas', 'city' => 'Kyiv', 'password' => '', 'status' => 'work',
        ]]);

        $this->assertRedirectsTo($response, '/cup/user');
        $fresh = User::find($user->uuid);
        $this->assertSame('Thomas', $fresh->firstname);
        $this->assertSame('Kyiv', $fresh->city);
        $this->assertSame(200, $this->signIn('newbie', 'first password'), 'the password is not wiped by an empty field');
    }

    public function testEditingCanSetANewPassword(): void
    {
        $this->admin();
        $user = $this->add();

        $this->cup('POST', "/cup/user/{$user->uuid}/edit", ['form_params' => [
            'username' => 'newbie', 'email' => 'newbie@example.com', 'password' => 'second password', 'status' => 'work',
        ]]);

        $this->assertSame(200, $this->signIn('newbie', 'second password'));
        $this->assertSame(400, $this->signIn('newbie', 'first password'));
    }

    public function testBlockingAnAccountEndsItsSessionAtOnce(): void
    {
        $this->admin();
        $user = $this->add();
        $session = $this->loginAs($user, 'first password');

        $this->cup('POST', "/cup/user/{$user->uuid}/edit", ['form_params' => [
            'username' => 'newbie', 'email' => 'newbie@example.com', 'status' => 'block',
        ]]);

        $this->assertSame(UserStatus::BLOCK, User::find($user->uuid)->status);
        $this->assertNotSame(200, $this->signIn('newbie', 'first password'));
        $profile = $this->http('GET', '/user/profile?format=json', ['headers' => $this->cookie($session['access_token'])]);
        $this->assertSame(307, $profile->getStatusCode(), 'the token that was already issued is not honoured any more');
    }

    public function testMovingAnAccountToAnotherGroupChangesWhatItMayOpen(): void
    {
        $this->fixtures();
        $editors = $this->getService(GroupService::class)->create(['title' => 'Editors', 'access' => ['cup:main', 'cup:page:list']]);
        $user = $this->add();
        $cookie = $this->cookie($this->loginAs($user, 'first password')['access_token']);
        $this->assertSame(307, $this->http('GET', '/cup/page', ['headers' => $cookie])->getStatusCode(), 'no group, no panel');

        $this->cup('POST', "/cup/user/{$user->uuid}/edit", ['form_params' => [
            'username' => 'newbie', 'email' => 'newbie@example.com', 'group_uuid' => $editors->uuid, 'status' => 'work',
        ]]);

        $this->assertSame(200, $this->http('GET', '/cup/page', ['headers' => $cookie])->getStatusCode());
    }

    public function testAValueOfSomeoneElseIsReportedOnEdit(): void
    {
        $this->admin();
        $this->makeUser(['username' => 'other', 'email' => 'other@example.com', 'phone' => '+380509999999']);
        $user = $this->add();

        foreach (['username' => 'other', 'email' => 'other@example.com', 'phone' => '+380509999999'] as $field => $value) {
            $response = $this->cup('POST', "/cup/user/{$user->uuid}/edit", ['form_params' => [$field => $value, 'status' => 'work'] + ['username' => 'newbie', 'email' => 'newbie@example.com']]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }

        $fresh = User::find($user->uuid);
        $this->assertSame('newbie', $fresh->username);
        $this->assertSame('newbie@example.com', $fresh->email);
    }

    public function testAnAccountCannotBeLeftWithoutAnyWayToSignIn(): void
    {
        $this->admin();
        $user = $this->add();

        $response = $this->cup('POST', "/cup/user/{$user->uuid}/edit", ['form_params' => ['username' => '', 'email' => '', 'phone' => '', 'status' => 'work']]);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertSame('newbie', User::find($user->uuid)->username);
    }

    public function testEditingAnUnknownAccountGoesBackToTheList(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            foreach (['GET', 'POST'] as $method) {
                $this->assertRedirectsTo($this->cup($method, "/cup/user/{$uuid}/edit", ['form_params' => ['username' => 'x']]), '/cup/user', "{$method} {$uuid}");
            }
        }
    }

    // -------------------------------------------------------------------- view

    public function testTheViewPageShowsTheAccount(): void
    {
        $this->admin();
        $user = $this->add(['firstname' => 'Neo', 'city' => 'Zion']);

        $body = (string) $this->cup('GET', "/cup/user/{$user->uuid}/view")->getBody();

        $this->assertStringContainsString('newbie@example.com', $body);
        $this->assertStringContainsString('Zion', $body);
        $this->assertStringNotContainsString($user->getAttributes()['password'], $body);
    }

    public function testViewingAnUnknownAccountGoesBackToTheList(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('GET', "/cup/user/{$uuid}/view"), '/cup/user', $uuid);
        }
    }

    public function testWhatWasTypedIntoAProfileIsEscapedEverywhere(): void
    {
        $this->admin();
        $hostile = '<img src=x onerror=alert(1)>';
        $user = $this->add(['firstname' => $hostile, 'lastname' => $hostile, 'city' => $hostile, 'additional' => $hostile]);

        foreach (['/cup/user?q=newbie', "/cup/user/{$user->uuid}/view", "/cup/user/{$user->uuid}/edit"] as $path) {
            $this->assertStringNotContainsString($hostile, (string) $this->cup('GET', $path)->getBody(), $path);
        }
    }

    // ------------------------------------------------------------------ delete

    public function testDeletingMarksTheAccountDeletedAndKeepsTheRow(): void
    {
        $this->admin();
        $user = $this->add();
        $session = $this->loginAs($user, 'first password');

        $response = $this->cup('POST', "/cup/user/{$user->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/user');
        $this->assertSame(UserStatus::DELETE, User::find($user->uuid)->status);
        $this->assertStringNotContainsString('newbie@example.com', (string) $this->cup('GET', '/cup/user')->getBody());
        $this->assertStringContainsString('newbie@example.com', (string) $this->cup('GET', '/cup/user?state=delete')->getBody());
        $this->assertNotSame(200, $this->signIn('newbie', 'first password'));
        $this->assertSame(307, $this->http('GET', '/user/profile?format=json', ['headers' => $this->cookie($session['access_token'])])->getStatusCode());
    }

    public function testDeletingAnUnknownAccountChangesNothing(): void
    {
        $this->admin();
        $this->add();
        $before = User::where('status', UserStatus::WORK)->count();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('POST', "/cup/user/{$uuid}/delete"), '/cup/user', $uuid);
        }

        $this->assertSame($before, User::where('status', UserStatus::WORK)->count());
    }

    public function testAnAdministratorCannotDeleteTheAccountHeIsSignedInWith(): void
    {
        $this->admin();
        $me = User::where('username', 'admin')->firstOrFail();

        $response = $this->cup('POST', "/cup/user/{$me->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/user');
        $this->assertSame(UserStatus::WORK, User::find($me->uuid)->status);
        $this->assertSame(200, $this->cup('GET', '/cup/user')->getStatusCode(), 'the panel stays open to him');
    }

    // ------------------------------------------------------------- subscribers

    public function testTheSubscribersAreListed(): void
    {
        $this->fixtures();

        $this->assertStringContainsString('reader@example.com', (string) $this->cup('GET', '/cup/user/subscriber')->getBody());
    }

    public function testASubscriberIsAddedFromTheList(): void
    {
        $this->admin();

        $response = $this->cup('POST', '/cup/user/subscriber/add', ['form_params' => ['email' => 'fresh@example.com']]);

        $this->assertRedirectsTo($response, '/cup/user/subscriber');
        $this->assertSame(1, UserSubscriber::where('email', 'fresh@example.com')->count());
    }

    public function testAnAddressThatCannotBeAddedIsReportedOnTheList(): void
    {
        $this->fixtures();
        $before = UserSubscriber::count();

        foreach (['' => 'empty', 'not-an-address' => 'malformed', 'reader@example.com' => 'twice'] as $email => $case) {
            $response = $this->cup('POST', '/cup/user/subscriber/add', ['form_params' => ['email' => $email]]);

            $this->assertSame(200, $response->getStatusCode(), $case . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $case);
            $this->assertStringContainsString('reader@example.com', (string) $response->getBody(), 'the list is shown again');
        }

        $this->assertSame($before, UserSubscriber::count());
    }

    public function testASubscriberIsDeleted(): void
    {
        $this->admin();
        $subscriber = $this->getService(SubscriberService::class)->create(['email' => 'leaving@example.com']);

        $response = $this->cup('POST', "/cup/user/subscriber/{$subscriber->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/user/subscriber');
        $this->assertSame(0, UserSubscriber::count());
    }

    public function testDeletingAnUnknownSubscriberChangesNothing(): void
    {
        $this->fixtures();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('POST', "/cup/user/subscriber/{$uuid}/delete"), '/cup/user/subscriber', $uuid);
        }

        $this->assertSame(1, UserSubscriber::count());
    }

    // -------------------------------------------------------------- newsletter

    public function testTheNewsletterFormIsShown(): void
    {
        $this->admin();

        $body = (string) $this->cup('GET', '/cup/user/newsletter')->getBody();

        $this->assertStringContainsString('name="subject"', $body);
        $this->assertStringContainsString('name="type"', $body);
        $this->assertSame(0, Task::count(), 'opening the page sends nothing');
    }

    public function testALetterIsQueuedForTheChosenAudience(): void
    {
        $this->admin();

        foreach (['all', 'users', 'subscribers'] as $type) {
            $response = $this->cup('POST', '/cup/user/newsletter', ['form_params' => ['subject' => 'News ' . $type, 'body' => '<p>Hello</p>', 'type' => $type]]);

            $this->assertRedirectsTo($response, '/cup/user/newsletter', $type);
        }

        $tasks = Task::where('action', SendNewsLetterMailTask::class)->orderBy('title')->get();
        $this->assertCount(3, $tasks);
        $this->assertEqualsCanonicalizing(['all', 'users', 'subscribers'], $tasks->map(fn (Task $task) => $task->params['type'])->all());
        $first = Task::where('action', SendNewsLetterMailTask::class)->get()->first(fn (Task $task) => $task->params['type'] === 'users');
        $this->assertSame('News users', $first->params['subject']);
        $this->assertSame('<p>Hello</p>', $first->params['body']);
        $this->assertSame('queue', $first->status);
    }

    public function testALetterWithoutASubjectATextOrAKnownAudienceIsNotQueued(): void
    {
        $this->admin();
        $valid = ['subject' => 'News', 'body' => 'Hello', 'type' => 'all'];

        foreach (['subject' => '', 'body' => '   ', 'type' => 'everyone'] as $field => $value) {
            $response = $this->cup('POST', '/cup/user/newsletter', ['form_params' => [$field => $value] + $valid]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }

        $this->assertSame(0, Task::count());
    }

    // ------------------------------------------------------------------ groups

    public function testAGroupWithMembersIsNotDeleted(): void
    {
        $this->admin();
        $group = $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => ['cup:main']]);
        $this->add(['group_uuid' => $group->uuid]);

        $response = $this->cup('POST', "/cup/user/group/{$group->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/user/group');
        $this->assertNotNull(UserGroup::find($group->uuid));
        $this->assertSame($group->uuid, User::where('username', 'newbie')->value('group_uuid'));
    }

    public function testAGroupWithBlockedOrWaitingMembersIsNotDeletedEither(): void
    {
        $this->admin();
        $group = $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => []]);
        $this->makeUser(['username' => 'banned', 'email' => 'banned@example.com', 'group_uuid' => $group->uuid, 'status' => UserStatus::BLOCK]);

        $this->cup('POST', "/cup/user/group/{$group->uuid}/delete");

        $this->assertNotNull(UserGroup::find($group->uuid));
    }

    public function testAGroupCanBeDeletedOnceItsMembersAreGone(): void
    {
        $this->admin();
        $group = $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => []]);
        $member = $this->makeUser(['username' => 'leaver', 'email' => 'leaver@example.com', 'group_uuid' => $group->uuid]);

        // removed accounts do not hold a group
        $this->cup('POST', "/cup/user/{$member->uuid}/delete");
        $this->cup('POST', "/cup/user/group/{$group->uuid}/delete");

        $this->assertNull(UserGroup::find($group->uuid));
    }

    public function testTheGroupListCountsEveryoneWhoHoldsTheGroup(): void
    {
        $this->admin();
        $group = $this->getService(GroupService::class)->create(['title' => 'Staff', 'access' => []]);
        $this->makeUser(['username' => 'banned', 'email' => 'banned@example.com', 'group_uuid' => $group->uuid, 'status' => UserStatus::BLOCK]);

        $body = (string) $this->cup('GET', '/cup/user/group')->getBody();

        $this->assertStringContainsString("You can't delete a group while it has users", html_entity_decode($body), 'the button says what the server will do');
    }
}

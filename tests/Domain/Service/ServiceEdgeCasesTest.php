<?php declare(strict_types=1);

namespace tests\Domain\Service;

use App\Domain\Casts\ApiKey\Status as ApiKeyStatus;
use App\Domain\Casts\User\Status as UserStatus;
use App\Domain\Service\ApiKey\ApiKeyService;
use App\Domain\Service\ApiKey\Exception\ApiKeyNotFoundException;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\File\Exception\FileAlreadyExistsException;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Page\Exception\AddressAlreadyExistsException as PageAddressAlreadyExists;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException as PageTitleAlreadyExists;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\MissingCategoryValueException as PublicationMissingCategory;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\User\Exception\EmailBannedException;
use App\Domain\Service\User\Exception\PasswordsNotMatchException;
use App\Domain\Service\User\Exception\WrongEmailValueException;
use App\Domain\Service\User\Exception\WrongPhoneValueException;
use App\Domain\Service\User\SubscriberService;
use App\Domain\Service\User\UserService;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use tests\Concerns\MakesAuthRequests;
use tests\TestCase;

/**
 * The refusals and the odd inputs of the services: what they do with what they must not accept
 *
 * @internal
 *
 * #[CoversNothing]
 */
class ServiceEdgeCasesTest extends TestCase
{
    use MakesAuthRequests;

    private function setParameter(string $name, string $value): void
    {
        $this->getService(ParameterService::class)->create(['name' => $name, 'value' => $value]);
        $this->getService(\Illuminate\Cache\ArrayStore::class)->flush();
    }

    // -------------------------------------------------------------------- api keys

    public function testAnApiKeyNeedsATitle(): void
    {
        $this->expectException(\App\Domain\Service\ApiKey\Exception\MissingTitleValueException::class);

        $this->getService(ApiKeyService::class)->create(['title' => '', 'is_full_access' => true]);
    }

    public function testApiKeyScopesKeepOnlyRealEntities(): void
    {
        $key = $this->getService(ApiKeyService::class)->create([
            'title' => 'partner',
            'scopes' => ['read' => ['page', 'no-such-entity', 'user'], 'write' => ['parameter', 'page']],
        ]);

        $this->assertSame(['page', 'user'], $key->scopes['read']);
        $this->assertSame(['page'], $key->scopes['write'], 'the settings are not an entity of the public API');
    }

    public function testWhatAKeyMayDo(): void
    {
        $service = $this->getService(ApiKeyService::class);
        $scoped = $service->create(['title' => 'scoped', 'scopes' => ['read' => ['page'], 'write' => []]]);
        $full = $service->create(['title' => 'full', 'is_full_access' => true]);

        $this->assertTrue($scoped->can('page', 'read'));
        $this->assertFalse($scoped->can('page', 'write'));
        $this->assertFalse($scoped->can('user', 'read'));
        $this->assertTrue($full->can('anything', 'write'));

        $service->update($full, ['status' => ApiKeyStatus::REVOKE]);
        $service->update($scoped, ['status' => ApiKeyStatus::REVOKE]);
        $this->assertFalse($full->can('page', 'read'), 'a revoked key can do nothing, full access included');
        $this->assertFalse($scoped->can('page', 'read'));
    }

    public function testAnApiKeyCanBeFoundChangedAndDeleted(): void
    {
        $service = $this->getService(ApiKeyService::class);
        $key = $service->create(['title' => 'one', 'is_full_access' => true]);
        $service->create(['title' => 'two', 'is_full_access' => true]);

        $this->assertCount(2, $service->read());
        $this->assertSame('one', $service->read(['uuid' => $key->uuid])->title);
        $this->assertSame('renamed', $service->update($key->uuid, ['title' => 'renamed'])->title, 'by uuid as well as by object');

        $this->assertTrue($service->delete($key->uuid));
        $this->expectException(ApiKeyNotFoundException::class);
        $service->read(['uuid' => $key->uuid]);
    }

    public function testAnApiKeyCannotBeRenamedToNothing(): void
    {
        $service = $this->getService(ApiKeyService::class);
        $key = $service->create(['title' => 'one', 'is_full_access' => true]);

        $this->expectException(\App\Domain\Service\ApiKey\Exception\MissingTitleValueException::class);
        $service->update($key, ['title' => '']);
    }

    public function testAnUnknownApiKeyIsNotFoundOnUpdateAndDelete(): void
    {
        $service = $this->getService(ApiKeyService::class);
        $missing = \Ramsey\Uuid\Uuid::uuid4()->toString();

        foreach (['update' => [$missing, ['title' => 'x']], 'delete' => [$missing]] as $method => $args) {
            try {
                $service->{$method}(...$args);
                $this->fail($method . ' of a missing key must fail');
            } catch (ApiKeyNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testTheKeyTokenNamesTheKeyAndNeverExpires(): void
    {
        $service = $this->getService(ApiKeyService::class);
        $key = $service->create(['title' => 'one', 'is_full_access' => true]);

        $payload = (array) JWT::decode($service->issueToken($key), new Key(file_get_contents(VAR_DIR . '/public.secret.key'), 'RS256'));

        $this->assertSame('api-key', $payload['sub']);
        $this->assertSame($key->uuid, $payload['uuid']);
        $this->assertArrayNotHasKey('exp', $payload);
        $this->assertArrayNotHasKey('scopes', $payload, 'what a key may do is read fresh, never taken from the token');
    }

    // ---------------------------------------------------------- required relations

    public function testAProductNeedsACategory(): void
    {
        $this->expectException(\App\Domain\Service\Catalog\Exception\MissingCategoryValueException::class);

        $this->getService(ProductService::class)->create(['title' => 'Orphan', 'address' => 'orphan']);
    }

    public function testAPublicationNeedsACategory(): void
    {
        $this->expectException(PublicationMissingCategory::class);

        $this->getService(PublicationService::class)->create(['title' => 'Orphan', 'address' => 'orphan']);
    }

    public function testAProductMayNotShareAnAddressInOneCategoryButMayInAnother(): void
    {
        $categories = $this->getService(CatalogCategoryService::class);
        $one = $categories->create(['title' => 'One', 'address' => 'one']);
        $two = $categories->create(['title' => 'Two', 'address' => 'two']);
        $products = $this->getService(ProductService::class);
        $make = fn ($category) => $products->create(['title' => 'Same', 'address' => 'same', 'category_uuid' => $category->uuid, 'type' => 'product']);

        $make($one);
        $make($two);

        $this->expectException(\App\Domain\Service\Catalog\Exception\AddressAlreadyExistsException::class);
        $make($one);
    }

    // ------------------------------------------------------------------- pages

    public function testAPageCannotTakeTheTitleOrAddressOfAnother(): void
    {
        $pages = $this->getService(PageService::class);
        $pages->create(['title' => 'First', 'address' => 'first', 'content' => 'x']);
        $second = $pages->create(['title' => 'Second', 'address' => 'second', 'content' => 'x']);

        try {
            $pages->update($second, ['title' => 'First']);
            $this->fail('the title is taken');
        } catch (PageTitleAlreadyExists) {
            $this->addToAssertionCount(1);
        }

        $second = $pages->read(['uuid' => $second->uuid]);

        try {
            $pages->update($second, ['address' => 'first']);
            $this->fail('the address is taken');
        } catch (PageAddressAlreadyExists) {
            $this->addToAssertionCount(1);
        }
    }

    public function testAPageMayBeSavedAgainWithItsOwnTitleAndAddress(): void
    {
        $pages = $this->getService(PageService::class);
        $page = $pages->create(['title' => 'First', 'address' => 'first', 'content' => 'x']);

        $saved = $pages->update($page, ['title' => 'First', 'address' => 'first', 'content' => 'changed']);

        $this->assertSame('changed', $saved->content);
    }

    public function testNonLatinAndLongTitlesAreKept(): void
    {
        $pages = $this->getService(PageService::class);
        $long = str_repeat('Довга назва ', 20);

        $page = $pages->create(['title' => 'Привіт, світе! 你好', 'address' => 'privit', 'content' => $long]);

        $fresh = $pages->read(['uuid' => $page->uuid]);
        $this->assertSame('Привіт, світе! 你好', $fresh->title);
        $this->assertSame($long, $fresh->content);
    }

    public function testAnAddressNeverStartsWithASlash(): void
    {
        $page = $this->getService(PageService::class)->create(['title' => 'Slashes', 'address' => '/about/team', 'content' => 'x']);

        $this->assertSame('about/team', $page->address);
    }

    public function testASearchTermIsNotAnSqlFragment(): void
    {
        $pages = $this->getService(PageService::class);
        $pages->create(['title' => "O'Reilly 100%", 'address' => 'oreilly', 'content' => 'x']);
        $pages->create(['title' => 'Plain', 'address' => 'plain', 'content' => 'x']);

        $this->assertCount(1, $pages->read(['search' => "O'Reilly"]));
        $this->assertCount(1, $pages->read(['search' => '100%']));
        $this->assertCount(0, $pages->read(['search' => "' OR 1=1 --"]));
        $this->assertCount(2, $pages->read(['search' => '']), 'an empty search is no search');
    }

    // -------------------------------------------------------------------- files

    public function testTheSameFileHashIsStoredOnlyOnce(): void
    {
        $files = $this->getService(FileService::class);
        $hash = sha1('same content');
        $data = ['name' => 'a', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 4, 'salt' => 'salt1', 'hash' => $hash];

        $files->create($data);

        $this->expectException(FileAlreadyExistsException::class);
        $files->create(['name' => 'b', 'salt' => 'salt2'] + $data);
    }

    // ------------------------------------------------------------------- users

    public function testTheTwoPasswordsMustAgree(): void
    {
        $this->expectException(PasswordsNotMatchException::class);

        $this->getService(UserService::class)->create(['username' => 'ann', 'password' => 'one', 'password_again' => 'two']);
    }

    public function testBlockedMailDomainsCannotRegister(): void
    {
        $this->setParameter('user_email_list', "spam.example\nbad.example");
        $users = $this->getService(UserService::class);

        $users->create(['username' => 'fine', 'email' => 'fine@good.example']);

        $this->expectException(EmailBannedException::class);
        $users->create(['username' => 'bad', 'email' => 'someone@bad.example']);
    }

    public function testABlankLineInTheBlockListBlocksNobody(): void
    {
        // the setting is a textarea: an empty line is easy to leave, and "" is the end of every address
        $this->setParameter('user_email_list', "spam.example\n\nbad.example\n");

        $user = $this->getService(UserService::class)->create(['username' => 'fine', 'email' => 'fine@good.example']);

        $this->assertSame('fine@good.example', $user->email);
    }

    public function testOnlyWhitelistedMailDomainsCanRegisterInWhitelistMode(): void
    {
        $this->setParameter('user_email_list_mode', 'whitelist');
        $this->setParameter('user_email_list', 'company.example');
        $users = $this->getService(UserService::class);

        $users->create(['username' => 'staff', 'email' => 'staff@company.example']);

        $this->expectException(EmailBannedException::class);
        $users->create(['username' => 'outsider', 'email' => 'outsider@other.example']);
    }

    public function testAMalformedEmailOrPhoneIsRefused(): void
    {
        $users = $this->getService(UserService::class);

        try {
            $users->create(['username' => 'a', 'email' => 'not-an-email']);
            $this->fail('malformed e-mail');
        } catch (WrongEmailValueException) {
            $this->addToAssertionCount(1);
        }

        try {
            $users->create(['username' => 'b', 'phone' => 'abc']);
            $this->fail('malformed phone');
        } catch (WrongPhoneValueException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, \App\Domain\Models\User::count());
    }

    public function testAnEmailIsMatchedWithoutRegardToCase(): void
    {
        $users = $this->getService(UserService::class);
        $user = $users->create(['username' => 'ann', 'email' => 'Ann@Example.com', 'password' => 'secret']);

        $this->assertSame($user->uuid, $users->read(['email' => 'ann@example.com'])->uuid);
        $this->assertSame($user->uuid, $users->read(['identifier' => 'ANN@EXAMPLE.COM'])->uuid);
        $this->assertSame($user->uuid, $users->read(['username' => 'ANN'])->uuid);
    }

    public function testPasswordsAreStoredHashedAndNeverReadBack(): void
    {
        $user = $this->getService(UserService::class)->create(['username' => 'ann', 'password' => 'my secret']);

        $raw = $user->getAttributes()['password'];
        $this->assertNotSame('my secret', $raw);
        $this->assertTrue(password_verify('my secret', $raw));
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function testUserStatusNeverComesFromTheSettingOfAnUnknownValue(): void
    {
        $users = $this->getService(UserService::class);
        $users->create(['username' => 'ann', 'status' => UserStatus::WORK]);

        // an unknown status is not a filter at all, instead of matching nothing or everything by accident
        $this->assertCount(1, $users->read(['status' => 'made-up']));
    }

    // -------------------------------------------------------------- subscribers

    public function testASubscriberNeedsAValidAddress(): void
    {
        $subscribers = $this->getService(SubscriberService::class);

        foreach (['', 'nonsense', 'a@', '@b.example'] as $email) {
            try {
                $subscribers->create(['email' => $email]);
                $this->fail("'{$email}' must be refused");
            } catch (\App\Domain\Service\User\Exception\MissingUniqueValueException|WrongEmailValueException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, \App\Domain\Models\UserSubscriber::count());
    }

    public function testAPublicationCategoryMayNotRepeatATitle(): void
    {
        $categories = $this->getService(PublicationCategoryService::class);
        $categories->create(['title' => 'Blog', 'address' => 'blog']);

        $this->expectException(\App\Domain\Service\Publication\Exception\TitleAlreadyExistsException::class);
        $categories->create(['title' => 'Blog', 'address' => 'other']);
    }
}

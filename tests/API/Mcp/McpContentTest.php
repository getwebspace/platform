<?php declare(strict_types=1);

namespace tests\API\Mcp;

use App\Domain\Casts\Review\Status as ReviewStatus;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Service\Catalog\CategoryService as CatalogCategoryService;
use App\Domain\Service\Catalog\OrderService as CatalogOrderService;
use App\Domain\Service\Catalog\ProductService as CatalogProductService;
use App\Domain\Casts\Task\Status as TaskStatus;
use App\Domain\Service\Form\DataService as FormDataService;
use App\Domain\Service\Form\FormService;
use App\Domain\Service\GuestBook\GuestBookService;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\Review\ReviewService;
use App\Domain\Service\Task\TaskService;
use App\Domain\Service\User\GroupService as UserGroupService;
use App\Domain\Service\User\SubscriberService;
use App\Domain\Service\User\UserService;
use tests\TestCase;

/**
 * Pages, publications, users, moderation, tasks, forms, search and settings
 *
 * @internal
 *
 * #[CoversNothing]
 */
class McpContentTest extends McpTestCase
{
    public function testReviewModeration(): void
    {
        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Телефоны', 'address' => 'phones']);
        $product = $this->getService(CatalogProductService::class)->create(['title' => 'Смартфон', 'address' => 'phone', 'category_uuid' => $category->uuid]);
        $reviews = $this->getService(ReviewService::class);
        $entry = fn (string $message, string $status) => $reviews->create([
            'type' => 'review', 'entity_type' => 'catalog_product', 'entity_uuid' => $product->uuid, 'rating' => 5, 'message' => $message, 'status' => $status,
        ]);
        $waiting = $entry('Отличный товар', ReviewStatus::MODERATE);
        $entry('Уже на сайте', ReviewStatus::WORK);

        $token = $this->createApiKeyToken(['read' => ['review'], 'write' => ['review']], false);

        [, $result] = $this->tool($token, 'review_list');
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Отличный товар', $result['items'][0]['message']);
        $this->assertEquals('Смартфон', $result['items'][0]['about']['title']);
        $this->assertNull($result['items'][0]['reply']);

        [, $result] = $this->tool($token, 'review_list', ['status' => 'all']);
        $this->assertCount(2, $result['items']);

        [, $result] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'decision' => 'publish', 'response' => 'Спасибо за отзыв!']);
        $this->assertEquals('work', $result['status']);
        $this->assertEquals('Спасибо за отзыв!', $result['reply']);

        // the answer is a child row, not a separate top-level entry
        [, $result] = $this->tool($token, 'review_list', ['status' => 'work']);
        $this->assertCount(2, $result['items']);
        $this->assertContains('Спасибо за отзыв!', array_column($result['items'], 'reply'));

        // change the answer, then remove it
        [, $result] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'response' => 'Благодарим!']);
        $this->assertEquals('Благодарим!', $result['reply']);
        [, $result] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'decision' => 'hide', 'response' => '']);
        $this->assertEquals('moderate', $result['status']);
        $this->assertNull($result['reply']);

        [$error] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid]);
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'review_moderate', ['uuid' => (string) $waiting->uuid, 'decision' => 'delete']);
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'review_list', ['status' => 'bad']);
        $this->assertTrue($error);
    }

    public function testGuestBookModeration(): void
    {
        $guestBook = $this->getService(GuestBookService::class);
        $entry = $guestBook->create(['name' => 'Иван', 'email' => 'ivan@example.com', 'message' => 'Хороший сайт', 'status' => 'moderate']);

        $token = $this->createApiKeyToken(['read' => ['guestbook'], 'write' => ['guestbook']], false);

        [, $result] = $this->tool($token, 'guestbook_list');
        $this->assertEquals(['Хороший сайт'], array_column($result['items'], 'message'));

        [, $result] = $this->tool($token, 'guestbook_moderate', ['uuid' => (string) $entry->uuid, 'decision' => 'publish', 'response' => 'Спасибо!']);
        $this->assertEquals('work', $result['status']);
        $this->assertEquals('Спасибо!', $result['response']);

        [, $result] = $this->tool($token, 'guestbook_list');
        $this->assertCount(0, $result['items']);
        [, $result] = $this->tool($token, 'guestbook_list', ['status' => 'work', 'search' => 'ИВАН']);
        $this->assertCount(1, $result['items']);

        [$error] = $this->tool($token, 'guestbook_moderate', ['uuid' => (string) $entry->uuid]);
        $this->assertTrue($error);
    }

    public function testTaskListAndRetry(): void
    {
        $tasks = $this->getService(TaskService::class);
        $failed = $tasks->create(['title' => 'Упавшая', 'action' => \App\Domain\Tasks\ConvertImageTask::class, 'params' => ['uuid' => []], 'status' => TaskStatus::FAIL, 'output' => 'Файл не найден']);
        $mail = $tasks->create(['title' => 'Письмо', 'action' => \App\Domain\Tasks\SendMailTask::class, 'status' => TaskStatus::FAIL]);
        $done = $tasks->create(['title' => 'Готово', 'action' => \App\Domain\Tasks\ConvertImageTask::class, 'status' => TaskStatus::DONE]);

        $token = $this->createApiKeyToken(['read' => ['task'], 'write' => ['task']], false);

        [, $result] = $this->tool($token, 'task_list');
        $this->assertEqualsCanonicalizing(['Упавшая', 'Письмо'], array_column($result['items'], 'title'));
        $this->assertContains('Файл не найден', array_column($result['items'], 'output'));

        [, $result] = $this->tool($token, 'task_list', ['status' => 'all']);
        $this->assertCount(3, $result['items']);

        [$error, $text] = $this->tool($token, 'task_retry', ['uuid' => (string) $mail->uuid]);
        $this->assertTrue($error);
        $this->assertStringContainsString('e-mails', $text);

        [$error] = $this->tool($token, 'task_retry', ['uuid' => (string) $done->uuid]);
        $this->assertTrue($error);

        [$error, $result] = $this->tool($token, 'task_retry', ['uuid' => (string) $failed->uuid]);
        $this->assertFalse($error);
        $this->assertEquals('queue', $result['status']);

        // the background worker may already be done with it, but it is not failed with the old output
        $task = $tasks->read(['uuid' => (string) $failed->uuid]);
        $this->assertNotEquals('Файл не найден', $task->output);
    }

    // wave 3: content

    public function testPublications(): void
    {
        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Новости', 'address' => 'news']);
        $token = $this->createApiKeyToken(['read' => ['publication'], 'write' => ['publication']], false);

        // a draft by default
        [$error, $created] = $this->tool($token, 'publication_create', [
            'title' => 'Открытие магазина',
            'address' => 'news/otkrytie-magazina',
            'category_uuid' => (string) $category->uuid,
            'short' => '<p>Коротко</p>',
            'full' => '<p>Полный <i>текст</i></p><script>alert(1)</script><a href="javascript:x()">link</a>',
            'meta_title' => 'SEO',
        ]);
        $this->assertFalse($error, json_encode($created));
        $this->assertEquals('draft', $created['state']);
        $this->assertEquals('<p>Полный <i>текст</i></p><a>link</a>', $created['full']);
        $this->assertEquals('SEO', $created['meta']['title']);
        $this->assertMatchesRegularExpression('#^news/[a-z0-9-]+$#', $created['address']);
        $uuid = $created['uuid'];

        // not shown on the public site, visible to the tools
        $this->assertCount(0, $this->getService(\App\Domain\Service\Publication\PublicationService::class)->read(['date_to' => datetime()->format('Y-m-d H:i:s')]));
        [, $list] = $this->tool($token, 'publication_list', ['state' => 'draft']);
        $this->assertEquals(['Открытие магазина'], array_column($list['items'], 'title'));
        [, $list] = $this->tool($token, 'publication_list', ['state' => 'published']);
        $this->assertCount(0, $list['items']);

        // edit the text, the address stays
        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'title' => 'Открытие нового магазина', 'full' => '<p>Новый текст</p>']);
        $this->assertEquals($created['address'], $result['address']);
        $this->assertEquals('<p>Новый текст</p>', $result['full']);
        $this->assertEquals('<p>Коротко</p>', $result['short']);
        $this->assertEquals('draft', $result['state']);

        // publish now, then back to a draft
        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'publish' => true]);
        $this->assertEquals('published', $result['state']);
        [, $list] = $this->tool($token, 'publication_list', ['state' => 'published', 'search' => 'НОВОГО']);
        $this->assertCount(1, $list['items']);
        $this->assertCount(1, $this->getService(\App\Domain\Service\Publication\PublicationService::class)->read(['date_to' => datetime()->addMinute()->format('Y-m-d H:i:s')]));

        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'unpublish' => true]);
        $this->assertEquals('draft', $result['state']);

        // scheduled
        [, $result] = $this->tool($token, 'publication_update', ['uuid' => $uuid, 'publish_at' => '2031-05-01 10:00']);
        $this->assertEquals('scheduled', $result['state']);
        $this->assertEquals('2031-05-01 10:00', $result['date']);

        [, $result] = $this->tool($token, 'publication_get', ['address' => $created['address']]);
        $this->assertEquals('Открытие нового магазина', $result['title']);

        foreach ([
            ['tool' => 'publication_create', 'args' => ['title' => 'Без категории']],
            ['tool' => 'publication_create', 'args' => ['title' => 'Плохая категория', 'category_uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString()]],
            ['tool' => 'publication_create', 'args' => ['title' => 'Дата', 'category_uuid' => (string) $category->uuid, 'publish_at' => '01.05.2031']],
            ['tool' => 'publication_create', 'args' => ['title' => 'Открытие нового магазина', 'address' => 'news/another', 'category_uuid' => (string) $category->uuid]],
            ['tool' => 'publication_update', 'args' => ['uuid' => $uuid]],
            ['tool' => 'publication_update', 'args' => ['uuid' => $uuid, 'publish' => true, 'unpublish' => true]],
            ['tool' => 'publication_update', 'args' => ['uuid' => $uuid, 'title' => '']],
            ['tool' => 'publication_get', 'args' => []],
            ['tool' => 'publication_list', 'args' => ['state' => 'later']],
        ] as $case) {
            [$error] = $this->tool($token, $case['tool'], $case['args']);
            $this->assertTrue($error, json_encode($case));
        }
    }

    public function testPages(): void
    {
        $pages = $this->getService(PageService::class);
        $page = $pages->create(['title' => 'О компании', 'address' => 'about', 'content' => '<p>Старый текст</p>']);
        $pages->create(['title' => 'Контакты', 'address' => 'contacts', 'content' => '<p>Адрес</p>']);

        $token = $this->createApiKeyToken(['read' => ['page'], 'write' => ['page']], false);

        [, $list] = $this->tool($token, 'page_list');
        $this->assertEquals(['Контакты', 'О компании'], array_column($list['items'], 'title'));
        [, $list] = $this->tool($token, 'page_list', ['search' => 'КОМПАНИИ']);
        $this->assertEquals(['about'], array_column($list['items'], 'address'));

        [, $result] = $this->tool($token, 'page_get', ['address' => 'about']);
        $this->assertEquals('<p>Старый текст</p>', $result['content']);

        [$error, $result] = $this->tool($token, 'page_update', [
            'uuid' => (string) $page->uuid,
            'title' => 'О нас',
            'content' => '<h2>О нас</h2><p onmouseover="x()">Новый текст</p><script>alert(1)</script>',
            'meta_title' => 'SEO',
        ]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('about', $result['address']);
        $this->assertEqualsCanonicalizing(['title', 'content', 'meta'], $result['changed_fields']);

        [, $result] = $this->tool($token, 'page_get', ['uuid' => (string) $page->uuid]);
        $this->assertEquals('О нас', $result['title']);
        $this->assertEquals('<h2>О нас</h2><p>Новый текст</p>', $result['content']);
        $this->assertEquals('SEO', $result['meta']['title']);

        foreach ([
            ['tool' => 'page_update', 'args' => ['uuid' => (string) $page->uuid]],
            ['tool' => 'page_update', 'args' => ['uuid' => (string) $page->uuid, 'title' => 'Контакты']],
            ['tool' => 'page_update', 'args' => ['uuid' => 'nope', 'title' => 'x']],
            ['tool' => 'page_get', 'args' => ['address' => 'missing']],
            ['tool' => 'page_get', 'args' => []],
        ] as $case) {
            [$error] = $this->tool($token, $case['tool'], $case['args']);
            $this->assertTrue($error, json_encode($case));
        }
    }

    // wave 4: users and the rest

    public function testUsers(): void
    {
        $groups = $this->getService(UserGroupService::class);
        $customers = $groups->create(['title' => 'Покупатели']);
        $vips = $groups->create(['title' => 'VIP']);
        $staff = $groups->create(['title' => 'Администраторы', 'access' => ['cup:main']]);

        $users = $this->getService(UserService::class);
        $ivan = $users->create(['username' => 'ivan', 'email' => 'ivan@example.com', 'phone' => '+79000000001', 'firstname' => 'Иван', 'lastname' => 'Петров', 'group_uuid' => $customers->uuid, 'password' => 'secret-pass']);
        $admin = $users->create(['username' => 'boss', 'email' => 'boss@example.com', 'firstname' => 'Босс', 'group_uuid' => $staff->uuid, 'password' => 'secret-pass']);
        $users->create(['username' => 'lonely', 'email' => 'lonely@example.com', 'firstname' => 'Без группы']);
        $blocked = $users->create(['username' => 'blocked', 'email' => 'blocked@example.com', 'status' => 'block']);

        $token = $this->createApiKeyToken(['read' => ['user', 'user/group'], 'write' => ['user']], false);

        // list: active by default, search ignores case, filters
        [, $result] = $this->tool($token, 'user_list');
        $this->assertCount(3, $result['items']);
        $this->assertStringNotContainsString('secret-pass', json_encode($result));
        [, $result] = $this->tool($token, 'user_list', ['search' => 'ПЕТРОВ']);
        $this->assertEquals(['ivan'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['group' => 'покупатели']);
        $this->assertEquals(['ivan'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['without_group' => true]);
        $this->assertEquals(['lonely'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['status' => 'block']);
        $this->assertEquals(['blocked'], array_column($result['items'], 'username'));
        [, $result] = $this->tool($token, 'user_list', ['status' => 'all']);
        $this->assertCount(4, $result['items']);
        [$error, $text] = $this->tool($token, 'user_list', ['group' => 'нет такой']);
        $this->assertTrue($error);
        $this->assertStringContainsString('Покупатели', $text);
        [$error] = $this->tool($token, 'user_list', ['status' => 'gone']);
        $this->assertTrue($error);

        // get: by any identifier, no secrets
        [, $result] = $this->tool($token, 'user_get', ['email' => 'IVAN@example.com']);
        $this->assertEquals('Иван', $result['firstname']);
        $this->assertEquals('Покупатели', $result['group']);
        $this->assertFalse($result['staff']);
        $this->assertArrayNotHasKey('password', $result);
        $this->assertArrayNotHasKey('tokens', $result);
        [, $result] = $this->tool($token, 'user_get', ['uuid' => (string) $blocked->uuid]);
        $this->assertEquals('block', $result['status']);
        [, $result] = $this->tool($token, 'user_get', ['uuid' => (string) $admin->uuid]);
        $this->assertTrue($result['staff']);
        [$error] = $this->tool($token, 'user_get');
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'user_get', ['email' => 'nobody@example.com']);
        $this->assertTrue($error);

        // groups
        [, $result] = $this->tool($token, 'user_group_list');
        $byTitle = array_column($result, null, 'title');
        $this->assertEquals(1, $byTitle['Покупатели']['members']);
        $this->assertFalse($byTitle['Покупатели']['staff']);
        $this->assertTrue($byTitle['Администраторы']['staff']);
        $this->assertArrayNotHasKey('access', $byTitle['Администраторы']);

        // update: partial json merge, group, block
        [$error, $result] = $this->tool($token, 'user_update', [
            'uuid' => (string) $ivan->uuid,
            'city' => 'Уфа',
            'birthdate' => '1990-05-17',
            'about' => 'Постоянный клиент',
            'company' => ['position' => 'Директор'],
            'messenger' => ['telegram' => '@ivan'],
            'allow_mail' => false,
            'group' => 'vip',
        ]);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('Уфа', $result['city']);
        $this->assertEquals('1990-05-17', $result['birthdate']);
        $this->assertEquals('VIP', $result['group']);
        $this->assertEquals('Директор', $result['company']['position']);
        $this->assertEquals('@ivan', $result['messenger']['telegram']);
        $this->assertFalse($result['allow_mail']);
        $this->assertEqualsCanonicalizing(['city', 'birthdate', 'additional', 'company', 'messenger', 'is_allow_mail', 'group_uuid'], $result['changed_fields']);

        [, $result] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'company' => ['title' => 'ООО Иван']]);
        $this->assertEquals(['title' => 'ООО Иван', 'position' => 'Директор'], $result['company']);

        [, $result] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'status' => 'block']);
        $this->assertEquals('block', $result['status']);
        [, $result] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'status' => 'work', 'birthdate' => '']);
        $this->assertEquals('work', $result['status']);
        $this->assertNull($result['birthdate']);

        // email taken is reported, nothing else changes
        [$error] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'email' => 'boss@example.com']);
        $this->assertTrue($error);

        // staff is out of reach in both directions, nothing is deleted or escalated
        [$error, $text] = $this->tool($token, 'user_update', ['uuid' => (string) $admin->uuid, 'city' => 'Москва']);
        $this->assertTrue($error);
        $this->assertStringContainsString('staff', $text);
        [$error] = $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'group' => 'Администраторы']);
        $this->assertTrue($error);
        $this->assertEquals('VIP', $this->tool($token, 'user_get', ['uuid' => (string) $ivan->uuid])[1]['group']);

        foreach ([
            ['uuid' => (string) $ivan->uuid],
            ['uuid' => (string) $ivan->uuid, 'status' => 'delete'],
            ['uuid' => (string) $ivan->uuid, 'status' => 'confirmation'],
            ['uuid' => (string) $ivan->uuid, 'birthdate' => '17.05.1990'],
            ['uuid' => (string) $ivan->uuid, 'group' => 'нет такой'],
            ['uuid' => 'nope', 'city' => 'x'],
            ['uuid' => \Ramsey\Uuid\Uuid::uuid4()->toString(), 'city' => 'x'],
        ] as $args) {
            [$error] = $this->tool($token, 'user_update', $args);
            $this->assertTrue($error, json_encode($args));
        }

        // password and username are not tools' business
        $before = $this->getService(UserService::class)->read(['uuid' => (string) $ivan->uuid]);
        $this->tool($token, 'user_update', ['uuid' => (string) $ivan->uuid, 'password' => 'hacked', 'username' => 'hacked', 'city' => 'Казань']);
        $after = $this->getService(UserService::class)->read(['uuid' => (string) $ivan->uuid]);
        $this->assertEquals($before->password, $after->password);
        $this->assertEquals('ivan', $after->username);
        $this->assertEquals('Казань', $after->city);
    }

    public function testAttributesFilesAndSubscribers(): void
    {
        $attributes = $this->getService(AttributeService::class);
        $attributes->create(['title' => 'Цвет', 'address' => 'color', 'type' => 'string', 'group' => 'Основные', 'is_filter' => true]);
        $attributes->create(['title' => 'Вес', 'address' => 'weight', 'type' => 'float', 'is_filter' => false]);

        $token = $this->createApiKeyToken();

        [, $result] = $this->tool($token, 'catalog_attribute_list');
        $this->assertCount(2, $result['items']);
        [, $result] = $this->tool($token, 'catalog_attribute_list', ['search' => 'ЦВЕТ']);
        $this->assertEquals(['color'], array_column($result['items'], 'address'));
        [, $result] = $this->tool($token, 'catalog_attribute_list', ['is_filter' => true]);
        $this->assertEquals(['color'], array_column($result['items'], 'address'));
        [, $result] = $this->tool($token, 'catalog_attribute_list', ['group' => 'Основные']);
        $this->assertEquals(['color'], array_column($result['items'], 'address'));

        // files
        $files = $this->getService(FileService::class);
        foreach ([['photo-one.jpg', 'image/jpeg'], ['photo-two.png', 'image/png'], ['price.pdf', 'application/pdf']] as [$name, $type]) {
            $path = CACHE_DIR . '/' . $name;
            $image = imagecreatetruecolor(20, 20);
            imagefill($image, 0, 0, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
            imagestring($image, 2, 1, 1, uniqid(), imagecolorallocate($image, 255, 255, 255));
            imagejpeg($image, $path);
            $file = $files->createFromPath($path, $name);
            $this->assertNotNull($file);
            $files->update($file, ['type' => $type]);
        }

        [, $result] = $this->tool($token, 'file_list');
        $this->assertCount(3, $result['items']);
        $this->assertStringStartsWith('/uploads/', parse_url($result['items'][0]['url'], PHP_URL_PATH));
        [, $result] = $this->tool($token, 'file_list', ['kind' => 'image']);
        $this->assertCount(2, $result['items']);
        [, $result] = $this->tool($token, 'file_list', ['kind' => 'document']);
        $this->assertEquals(['price.pdf'], array_column($result['items'], 'name'));
        [, $result] = $this->tool($token, 'file_list', ['kind' => 'image', 'limit' => 1]);
        $this->assertCount(1, $result['items']);
        $this->assertTrue($result['has_more']);
        [, $result] = $this->tool($token, 'file_list', ['search' => 'PHOTO']);
        $this->assertCount(2, $result['items']);
        [$error] = $this->tool($token, 'file_list', ['kind' => 'archive']);
        $this->assertTrue($error);

        // subscribers
        $subscribers = $this->getService(SubscriberService::class);
        $subscribers->create(['email' => 'one@example.com']);
        $subscribers->create(['email' => 'two@example.org']);

        [, $result] = $this->tool($token, 'subscriber_list');
        $this->assertCount(2, $result['items']);
        [, $result] = $this->tool($token, 'subscriber_list', ['search' => 'EXAMPLE.ORG']);
        $this->assertEquals(['two@example.org'], array_column($result['items'], 'email'));
    }

    // prompts and resources

    public function testPageAndPublicationListsAreEnoughForSeoAudit(): void
    {
        $pages = $this->getService(PageService::class);
        $pages->create(['title' => 'О компании', 'address' => 'about', 'content' => 'x', 'meta' => ['title' => 'О нас', 'description' => 'Описание', 'keywords' => 'a, b']]);
        $pages->create(['title' => 'Контакты', 'address' => 'contacts', 'content' => 'x']);
        $pages->create(['title' => 'Условия', 'address' => 'terms', 'content' => 'x']);

        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Новости', 'address' => 'news']);
        $publications = $this->getService(\App\Domain\Service\Publication\PublicationService::class);
        foreach ([['Первая', 'first', '2026-01-10 10:00:00'], ['Вторая', 'second', '2026-02-10 10:00:00'], ['Черновик', 'draft', '2099-12-31 00:00:00']] as [$title, $slug, $date]) {
            $publications->create(['title' => $title, 'address' => 'news/' . $slug, 'category_uuid' => $category->uuid, 'content' => ['short' => '', 'full' => ''], 'meta' => ['title' => 'SEO ' . $title], 'date' => $date]);
        }

        $token = $this->createApiKeyToken();

        [, $result] = $this->tool($token, 'page_list', ['limit' => 2]);
        $this->assertEquals(3, $result['total']);
        $this->assertCount(2, $result['items']);
        $this->assertTrue($result['has_more']);
        $about = array_column($result['items'], null, 'address')['about'] ?? null;
        $this->assertNotNull($about);
        $this->assertEquals(['title' => 'О нас', 'description' => 'Описание', 'keywords' => 'a, b'], $about['meta']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $about['date']);

        // a page without SEO fields shows empty ones - this is what an audit looks for
        [, $result] = $this->tool($token, 'page_list', ['search' => 'контакты']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(['title' => '', 'description' => '', 'keywords' => ''], $result['items'][0]['meta']);

        [, $result] = $this->tool($token, 'publication_list');
        $this->assertEquals(3, $result['total']);
        [, $result] = $this->tool($token, 'publication_list', ['state' => 'published', 'limit' => 1]);
        $this->assertEquals(2, $result['total'], 'total is of all matching, not of the page');
        $this->assertCount(1, $result['items']);
        $this->assertStringStartsWith('SEO ', $result['items'][0]['meta']['title']);
        [, $result] = $this->tool($token, 'publication_list', ['state' => 'draft']);
        $this->assertEquals(1, $result['total']);
        [, $result] = $this->tool($token, 'publication_list', ['search' => 'Втор']);
        $this->assertEquals(1, $result['total']);
    }

    public function testSeoAudit(): void
    {
        $pages = $this->getService(PageService::class);
        $pages->create(['title' => 'Хорошая', 'address' => 'good', 'content' => 'x', 'meta' => ['title' => 'Заголовок страницы', 'description' => 'Описание страницы']]);
        $pages->create(['title' => 'Пустая', 'address' => 'empty', 'content' => 'x']);
        $pages->create(['title' => 'Длинная', 'address' => 'long', 'content' => 'x', 'meta' => ['title' => str_repeat('я', 71), 'description' => str_repeat('я', 161)]]);
        $pages->create(['title' => 'Копия 1', 'address' => 'copy-1', 'content' => 'x', 'meta' => ['title' => 'Одинаковый', 'description' => 'д1']]);
        $pages->create(['title' => 'Копия 2', 'address' => 'copy-2', 'content' => 'x', 'meta' => ['title' => 'одинаковый', 'description' => 'д2']]);

        $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Каталог', 'address' => 'catalog']);
        $this->makeProduct('Товар без SEO', (string) $category->uuid);
        $this->makeProduct('Скрытый товар', (string) $category->uuid, ['status' => 'delete']);

        $publicationCategory = $this->getService(PublicationCategoryService::class)->create(['title' => 'Новости', 'address' => 'news']);
        $publications = $this->getService(\App\Domain\Service\Publication\PublicationService::class);
        $publications->create(['title' => 'Вышла', 'address' => 'news/out', 'category_uuid' => $publicationCategory->uuid, 'content' => ['short' => '', 'full' => ''], 'date' => '2026-01-01 10:00:00']);
        $publications->create(['title' => 'Черновик', 'address' => 'news/draft', 'category_uuid' => $publicationCategory->uuid, 'content' => ['short' => '', 'full' => ''], 'date' => '2099-12-31 00:00:00']);

        $token = $this->createApiKeyToken();

        [$error, $result] = $this->tool($token, 'seo_audit');
        $this->assertFalse($error, json_encode($result));
        $this->assertEqualsCanonicalizing(['pages', 'publications', 'products', 'categories'], array_keys($result));

        $pagesResult = $result['pages'];
        $this->assertEquals(5, $pagesResult['total']);
        $this->assertEquals(1, $pagesResult['problems']['empty_title']['count']);
        $this->assertEquals('Пустая', $pagesResult['problems']['empty_title']['examples'][0]['title']);
        $this->assertEquals(1, $pagesResult['problems']['long_title']['count']);
        $this->assertEquals(71, $pagesResult['problems']['long_title']['examples'][0]['length']);
        $this->assertEquals(1, $pagesResult['problems']['long_description']['count']);
        $this->assertEquals(2, $pagesResult['problems']['repeated_title']['count'], 'both records with the same title, whatever the letter case');
        $this->assertEquals(0, $pagesResult['problems']['repeated_description']['count']);

        $this->assertEquals(1, $result['publications']['total'], 'a draft is not audited');
        $this->assertEquals(1, $result['products']['total'], 'a hidden product is not audited');
        $this->assertEquals(1, $result['products']['problems']['empty_title']['count']);

        [, $result] = $this->tool($token, 'seo_audit', ['entity' => 'page', 'examples' => 0]);
        $this->assertEquals(['pages'], array_keys($result));
        $this->assertSame([], $result['pages']['problems']['empty_title']['examples']);
        $this->assertEquals(1, $result['pages']['problems']['empty_title']['count']);

        // a key sees what it can read
        [, $result] = $this->tool($this->createApiKeyToken(['read' => ['page']], false), 'seo_audit');
        $this->assertEquals(['pages'], array_keys($result));
        [$error] = $this->tool($this->createApiKeyToken(['read' => ['user']], false), 'seo_audit');
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'seo_audit', ['entity' => 'users']);
        $this->assertTrue($error);
    }

    public function testPublicationCategoriesAndAddress(): void
    {
        $categories = $this->getService(PublicationCategoryService::class);
        $news = $categories->create(['title' => 'Новости', 'address' => 'news']);
        $categories->create(['title' => 'Статьи', 'address' => 'articles']);
        $this->getService(\App\Domain\Service\Publication\PublicationService::class)->create(['title' => 'Первая', 'address' => 'news/first', 'category_uuid' => $news->uuid, 'content' => ['short' => '', 'full' => ''], 'date' => '2026-01-01 10:00:00']);

        $token = $this->createApiKeyToken(['read' => ['publication/category'], 'write' => ['publication']], false);

        [, $result] = $this->tool($token, 'publication_category_list');
        $this->assertEquals(['Новости', 'Статьи'], array_column($result, 'title'));
        $this->assertEquals([1, 0], array_column($result, 'publications'));

        // a title in a language that cannot be transliterated gives no address - ask for one instead of a broken page
        [$error, $text] = $this->tool($this->createApiKeyToken(), 'publication_create', ['title' => 'Без адреса', 'category_uuid' => (string) $news->uuid]);
        $this->assertTrue($error);
        $this->assertStringContainsString('address', $text);

        [$error, $result] = $this->tool($this->createApiKeyToken(), 'publication_create', ['title' => 'Без адреса', 'category_uuid' => (string) $news->uuid, 'address' => 'news/bez-adresa']);
        $this->assertFalse($error, json_encode($result));
        $this->assertEquals('news/bez-adresa', $result['address']);
    }

    public function testSiteSearch(): void
    {
        $this->withSearchIndexAside(function (): void {
            $container = $this->getService(\Psr\Container\ContainerInterface::class);
            $category = $this->getService(CatalogCategoryService::class)->create(['title' => 'Goods', 'address' => 'goods']);
            $this->getService(CatalogCategoryService::class)->create(['title' => 'Jackets and coats', 'address' => 'jackets']);
            $this->getService(CatalogCategoryService::class)->create(['title' => 'Hidden jackets', 'address' => 'hidden-jackets', 'is_hidden' => true]);
            $this->makeProduct('Waterproof jacket', (string) $category->uuid, ['description' => 'Warm']);
            $this->makeProduct('Hidden jacket', (string) $category->uuid, ['status' => 'delete']);
            $this->getService(PageService::class)->create(['title' => 'About jackets', 'address' => 'about-jackets', 'content' => 'We sell jackets']);

            $publicationCategory = $this->getService(PublicationCategoryService::class)->create(['title' => 'News', 'address' => 'news']);
            $publications = $this->getService(\App\Domain\Service\Publication\PublicationService::class);
            $publications->create(['title' => 'Jacket sale', 'address' => 'news/sale', 'category_uuid' => $publicationCategory->uuid, 'content' => ['short' => 'jackets', 'full' => 'jackets'], 'date' => '2026-01-01 10:00:00']);
            $publications->create(['title' => 'Jacket secret draft', 'address' => 'news/draft', 'category_uuid' => $publicationCategory->uuid, 'content' => ['short' => 'jackets', 'full' => 'jackets'], 'date' => '2099-12-31 00:00:00']);

            $token = $this->createApiKeyToken();

            // no index yet: nothing is found and it is said which kinds have none
            [$error, $result] = $this->tool($token, 'site_search', ['query' => 'jacket']);
            $this->assertFalse($error, json_encode($result));
            $this->assertSame(0, $result['count']);
            $this->assertEqualsCanonicalizing(['catalog_category', 'catalog_product', 'publication_category', 'publication', 'page'], $result['no_index_for']);

            // the index is built the way the worker does it
            $model = (new \App\Domain\Tasks\SearchIndexTask($container))->execute();
            (new \App\Domain\Tasks\SearchIndexTask($container, $model))->run();
            foreach (glob(CACHE_DIR . '/*.index') as $file) {
                chmod($file, 0o666); // the web server is another user
            }

            [, $result] = $this->tool($token, 'site_search', ['query' => 'jacket']);
            $this->assertArrayNotHasKey('no_index_for', $result);
            $titles = array_column($result['items'], 'title', 'kind');
            $this->assertEquals('Waterproof jacket', $titles['catalog_product']);
            $this->assertEquals('About jackets', $titles['page']);
            $this->assertEquals('Jacket sale', $titles['publication']);
            $this->assertEquals('Jackets and coats', $titles['catalog_category']);
            $this->assertStringNotContainsString('Hidden jackets', json_encode($result), 'a hidden category is not found');
            $this->assertStringNotContainsString('secret draft', json_encode($result), 'a draft is not on the site yet');
            $this->assertStringNotContainsString('Hidden jacket', json_encode($result), 'a hidden product is not found');
            $this->assertStringContainsString('/catalog/', array_column($result['items'], 'url', 'kind')['catalog_product']);

            [, $result] = $this->tool($token, 'site_search', ['query' => 'jacket', 'kind' => 'page']);
            $this->assertEquals(['page'], array_unique(array_column($result['items'], 'kind')));
            [, $result] = $this->tool($token, 'site_search', ['query' => 'nothing-like-it-zzz']);
            $this->assertSame(0, $result['count']);
            $this->assertSame([], $result['items']);

            // a key finds only what it may read
            [, $result] = $this->tool($this->createApiKeyToken(['read' => ['page']], false), 'site_search', ['query' => 'jacket']);
            $this->assertEquals(['page'], array_unique(array_column($result['items'], 'kind')));
        });
    }

    public function testSearchLogsFormsAndHealth(): void
    {
        $form = $this->getService(FormService::class)->create(['title' => 'Feedback', 'address' => 'feedback', 'mailto' => 'boss@example.com']);
        $this->getService(FormDataService::class)->create(['form_uuid' => $form->uuid, 'data' => ['name' => 'Иван'], 'message' => 'x']);

        $token = $this->createApiKeyToken();

        // forms
        [, $result] = $this->tool($token, 'form_list');
        $this->assertEquals('feedback', $result[0]['address']);
        $this->assertEquals(1, $result[0]['submissions']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $result[0]['last_submission']);
        $this->assertContains('boss@example.com', $result[0]['recipients']);

        // the index is rebuilt by a background task: here it is only started (no worker in the tests)
        [, $result] = $this->tool($token, 'site_reindex');
        $this->assertTrue(\Ramsey\Uuid\Uuid::isValid($result['task']), 'the id of the queued task');
        $this->assertEquals('queue', $result['status']);
        $this->assertEquals(\App\Domain\Tasks\SearchIndexTask::class, $this->getService(\App\Domain\Service\Task\TaskService::class)->read(['uuid' => $result['task']])->action);
        [$error] = $this->tool($token, 'site_search', ['query' => ' ']);
        $this->assertTrue($error);
        [$error] = $this->tool($token, 'site_search', ['query' => 'x', 'kind' => 'users']);
        $this->assertTrue($error);

        // health
        [, $result] = $this->tool($token, 'site_health');
        $this->assertSame(PHP_VERSION, $result['php']);
        $this->assertEquals('sqlite', $result['database']['driver']);
        $this->assertArrayHasKey('queued', $result['tasks']);
        $this->assertArrayHasKey('errors_in_the_last_file', $result['logs']);

        // logs: a record we made, tokens hidden, filters
        $marker = 'Лог ' . uniqid();
        $this->getService(\Psr\Log\LoggerInterface::class)->error('Проба ' . $marker . ' Bearer abc.def.ghi ' . 'eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiJ4In0.c2lnbmF0dXJlMTIz', ['password' => 'secret-value']);
        $this->getService(\Psr\Log\LoggerInterface::class)->info('Инфо ' . $marker);

        [, $result] = $this->tool($token, 'logs_tail', ['contains' => $marker]);
        $this->assertStringStartsWith('app-', $result['file']);
        $this->assertCount(2, $result['records']);
        $text = implode("\n", $result['records']);
        $this->assertStringNotContainsString('secret-value', $text);
        $this->assertStringNotContainsString('eyJhbGciOiJSUzI1NiJ9', $text);
        $this->assertStringContainsString('[token]', $text);
        $this->assertStringContainsString('Bearer [hidden]', $text);

        [, $result] = $this->tool($token, 'logs_tail', ['contains' => $marker, 'level' => 'ERROR']);
        $this->assertCount(1, $result['records']);
        [, $result] = $this->tool($token, 'logs_tail', ['contains' => $marker, 'lines' => 1]);
        $this->assertCount(1, $result['records']);
        [, $result] = $this->tool($token, 'logs_tail', ['date' => '2001-01-01']);
        $this->assertNull($result['file']);

        foreach ([['level' => 'LOUD'], ['date' => 'yesterday'], ['lines' => 501]] as $args) {
            [$error] = $this->tool($token, 'logs_tail', $args);
            $this->assertTrue($error, json_encode($args));
        }

        // these are for full access keys only
        $limited = $this->tools($this->createApiKeyToken(['read' => ['catalog/product', 'catalog/order', 'page', 'publication', 'catalog/category']], false));
        foreach (['logs_tail', 'site_health', 'form_list', 'site_reindex'] as $name) {
            $this->assertNotContains($name, $limited, $name);
        }
        $this->assertContains('seo_audit', $limited);
        $this->assertContains('site_search', $limited);
    }

    public function testSettingsAreShownWithoutSecrets(): void
    {
        $parameters = $this->getService(ParameterService::class);
        $secretToken = 'ZZ' . bin2hex(random_bytes(10));

        foreach ([
            'common_title' => 'Мой магазин',
            'smtp_host' => 'smtp.example.com',
            'smtp_pass' => 'hunter2-' . $secretToken,
            'smtp_login' => 'robot@example.com',
            'recaptcha_private' => 'rc-' . $secretToken,
            'recaptcha_public' => 'public-site-key',
            'PaykeeperPlugin_secret' => 'pk-' . $secretToken,
            'Form2TelegramPlugin_token' => '123456789:' . $secretToken . 'ABCDEFGH',
            'catalog_import_key' => 'k',
            'user_login_template' => 'user.login.twig',
            'notepad_boss' => 'private note ' . $secretToken,
            // a secret under a harmless name is recognised by its look
            'weird_value' => bin2hex(random_bytes(20)),
            'weird_jwt' => 'eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiJ4In0.c2lnbmF0dXJlMTIzNDU',
            'weird_empty_pass' => '',
            'long_text' => str_repeat('абв ', 400),
        ] as $name => $value) {
            $parameters->create(['name' => $name, 'value' => $value]);
        }

        $token = $this->createApiKeyToken();

        [$error, $result] = $this->tool($token, 'settings_get', ['limit' => 100]);
        $this->assertFalse($error, json_encode($result));

        $items = array_column($result['items'], null, 'name');
        $this->assertEquals('Мой магазин', $items['common_title']['value']);
        $this->assertEquals('smtp.example.com', $items['smtp_host']['value']);
        $this->assertEquals('public-site-key', $items['recaptcha_public']['value']);
        $this->assertEquals('user.login.twig', $items['user_login_template']['value'], '"login" in the middle of a name is not a secret');

        foreach (['smtp_pass', 'smtp_login', 'recaptcha_private', 'PaykeeperPlugin_secret', 'Form2TelegramPlugin_token', 'catalog_import_key', 'weird_value', 'weird_jwt'] as $name) {
            $this->assertTrue($items[$name]['hidden'], $name);
            $this->assertTrue($items[$name]['filled'], $name);
            $this->assertArrayNotHasKey('value', $items[$name], $name);
        }
        $this->assertFalse($items['weird_empty_pass']['filled'], 'empty - says so');

        // nothing of a secret anywhere in the answer, notes of the administrators are not there at all
        $this->assertStringNotContainsString($secretToken, json_encode($result));
        $this->assertArrayNotHasKey('notepad_boss', $items);
        $this->assertArrayNotHasKey('notepad', $result['groups']);

        // a long value is cut, by name it comes whole
        $this->assertTrue($items['long_text']['cut']);
        $this->assertEquals(500, mb_strlen($items['long_text']['value']));
        $this->assertEquals(1600, $items['long_text']['length']);
        [, $whole] = $this->tool($token, 'settings_get', ['name' => 'long_text']);
        $this->assertEquals(1600, mb_strlen($whole['value']));
        $this->assertFalse($whole['cut']);

        // a secret by name is not given either
        [, $secret] = $this->tool($token, 'settings_get', ['name' => 'smtp_pass']);
        $this->assertTrue($secret['hidden']);
        $this->assertStringNotContainsString($secretToken, json_encode($secret));

        // groups, search, paging
        $this->assertGreaterThan(1, $result['groups']['smtp']);
        [, $group] = $this->tool($token, 'settings_get', ['group' => 'smtp']);
        $this->assertEqualsCanonicalizing(['smtp_host', 'smtp_login', 'smtp_pass'], array_column($group['items'], 'name'));
        [, $found] = $this->tool($token, 'settings_get', ['search' => 'RECAPTCHA']);
        $this->assertCount(2, $found['items']);
        [, $page] = $this->tool($token, 'settings_get', ['limit' => 3, 'offset' => 1]);
        $this->assertCount(3, $page['items']);
        $this->assertTrue($page['has_more']);

        [$error] = $this->tool($token, 'settings_get', ['name' => 'no_such_setting']);
        $this->assertTrue($error);
        [$error, $text] = $this->tool($token, 'settings_get', ['name' => 'notepad_boss']);
        $this->assertTrue($error, 'a private note is not there');
        [$error, $text] = $this->tool($token, 'settings_get', ['group' => 'nogroup']);
        $this->assertTrue($error);
        $this->assertStringContainsString('smtp', $text);

        // only for full access keys
        $this->assertNotContains('settings_get', $this->tools($this->createApiKeyToken(['read' => ['catalog/product', 'page']], false)));
    }

    public function testFormData(): void
    {
        $form = $this->getService(FormService::class)->create(['title' => 'Обратная связь', 'address' => 'feedback']);
        $this->getService(FormService::class)->create(['title' => 'Другая', 'address' => 'other']);
        $data = $this->getService(FormDataService::class)->create([
            'form_uuid' => $form->uuid,
            'data' => ['name' => 'Иван', 'phone' => '+79000000001', 'recaptcha' => 'secret'],
            'message' => 'Перезвоните',
        ]);

        $token = $this->createApiKeyToken();

        [, $result] = $this->tool($token, 'form_data_list');
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Обратная связь', $result['items'][0]['form']);
        $this->assertArrayNotHasKey('recaptcha', $result['items'][0]['preview']);
        $this->assertEqualsCanonicalizing(['feedback', 'other'], array_column($result['forms'], 'address'));

        [, $result] = $this->tool($token, 'form_data_list', ['form' => 'other']);
        $this->assertCount(0, $result['items']);

        [$error] = $this->tool($token, 'form_data_list', ['form' => 'missing']);
        $this->assertTrue($error);

        [, $result] = $this->tool($token, 'form_data_get', ['uuid' => (string) $data->uuid]);
        $this->assertEquals('Иван', $result['fields']['name']);
        $this->assertEquals('Перезвоните', $result['message']);
        $this->assertArrayNotHasKey('recaptcha', $result['fields']);
    }
}

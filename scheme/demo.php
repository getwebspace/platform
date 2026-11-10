<?php declare(strict_types=1);

/**
 * Demo data for the admin panel: a few rows in every section.
 * Run inside the dev container: php scheme/demo.php (TEST=1 uses var/database-test.sqlite)
 */

use Ramsey\Uuid\Uuid;

$_ENV['TEST'] = 1;
require __DIR__ . '/../src/bootstrap.php';

/** @var \Slim\App $app */
$c = $app->getContainer();
$ok = 0;

// idempotent: wipe what this script adds, then add it again
$db = new \PDO('sqlite:' . VAR_DIR . '/database-test.sqlite');
foreach (['page', 'publication', 'publication_category', 'catalog_product', 'catalog_category', 'catalog_attribute', 'form', 'form_data', 'review', 'api_key', 'user_subscriber', 'catalog_order_product', 'task'] as $t) {
    $db->exec("delete from $t");
}
$db->exec("delete from reference where type in ('country','currency','delivery','manufacturer','store_location')");
$db->exec("delete from user_group where title <> 'Admin'");

$try = function (string $what, callable $fn) use (&$ok) {
    try {
        $r = $fn();
        $ok++;

        return $r;
    } catch (\Throwable $e) {
        echo "FAIL $what: " . get_class($e) . ' ' . $e->getMessage() . "\n";

        return null;
    }
};
$ago = fn (int $d, int $h = 10) => (new DateTime("-$d days"))->setTime($h, rand(0, 59));

// pages
$pageService = $c->get(\App\Domain\Service\Page\PageService::class);
foreach ([['About us', 'about', 'We are a small team that makes furniture to order.'], ['Delivery and payment', 'delivery', 'Delivery across the country, payment by card or cash.'], ['Contacts', 'contacts', 'Mon-Fri 9:00-18:00, hello@example.com'], ['Privacy policy', 'privacy', 'How we store and use your data.'], ['Warranty', 'warranty', '24 months on every product.']] as $i => [$t, $a, $body]) {
    $try("page $t", fn () => $pageService->create(['title' => $t, 'address' => $a, 'content' => "<p>$body</p>", 'date' => $ago($i * 9)]));
}

// publications
$pubCat = $c->get(\App\Domain\Service\Publication\CategoryService::class);
$pubs = $c->get(\App\Domain\Service\Publication\PublicationService::class);
$cats = [];
foreach ([['News', 'news'], ['Articles', 'articles'], ['Cases', 'cases']] as [$t, $a]) {
    $cats[$a] = $try("pubcat $t", fn () => $pubCat->create(['title' => $t, 'address' => $a, 'is_public' => true]));
}
$admin = \App\Domain\Models\User::firstWhere(['username' => 'admin']);
foreach ([['Spring collection is here', 'spring-collection', 'news'], ['How to choose a table', 'choose-table', 'articles'], ['Office for a startup: a case', 'startup-office', 'cases'], ['We moved to a new workshop', 'new-workshop', 'news'], ['Care for a wooden surface', 'wood-care', 'articles']] as $i => [$t, $a, $cat]) {
    $try("pub $t", fn () => $pubs->create(['title' => $t, 'address' => $a, 'category_uuid' => $cats[$cat]?->uuid, 'user_uuid' => $admin?->uuid, 'content' => "<p>$t. Sample text of the publication.</p>", 'date' => $ago($i * 6 + 1)]));
}

// catalog
$catCat = $c->get(\App\Domain\Service\Catalog\CategoryService::class);
$prod = $c->get(\App\Domain\Service\Catalog\ProductService::class);
$attr = $c->get(\App\Domain\Service\Catalog\AttributeService::class);
$cc = [];
foreach ([['Tables', 'tables'], ['Chairs', 'chairs'], ['Shelves', 'shelves']] as $i => [$t, $a]) {
    $cc[$a] = $try("category $t", fn () => $catCat->create(['title' => $t, 'address' => $a, 'order' => $i + 1]));
}
$products = [];
foreach ([
    ['Oak dining table', 'tables', 'T-100', 45900, 5], ['Coffee table Loft', 'tables', 'T-101', 12900, 0], ['Desk Office', 'tables', 'T-102', 0, 3],
    ['Chair Classic', 'chairs', 'C-200', 5400, 12], ['Bar stool', 'chairs', 'C-201', 3900, 0], ['Wall shelf', 'shelves', 'S-300', 2700, 8],
] as $i => [$t, $cat, $code, $price, $qty]) {
    $products[] = $try("product $t", fn () => $prod->create(['title' => $t, 'address' => 'p-' . ($i + 1), 'category_uuid' => $cc[$cat]?->uuid, 'vendorcode' => $code, 'barcode' => '46000000' . (1000 + $i), 'price' => $price, 'quantity' => $qty, 'order' => $i + 1, 'special' => $i === 0, 'description' => "<p>$t</p>"]));
}
foreach ([['Material', 'material', 'string'], ['Color', 'color', 'string'], ['Width, cm', 'width', 'integer']] as [$t, $a, $type]) {
    $try("attribute $t", fn () => $attr->create(['title' => $t, 'address' => $a, 'type' => $type, 'group' => 'Main', 'is_filter' => true]));
}

// references the seeder leaves empty
$ref = fn (string $type, string $title, array $value, int $order) => $try("ref $type $title", fn () => \App\Domain\Models\Reference::create(['type' => $type, 'title' => $title, 'value' => $value, 'order' => $order, 'status' => 1]));
foreach ([['Russia', 'RU'], ['Ukraine', 'UA'], ['Belarus', 'BY'], ['Kazakhstan', 'KZ']] as $i => [$t, $iso]) {
    $ref('country', $t, ['iso_2' => $iso, 'address_format' => ''], $i + 1);
}
foreach ([['Ruble', 'RUB', '', '₽', 1], ['Dollar', 'USD', '$', '', 92.5], ['Euro', 'EUR', '€', '', 100.3]] as $i => [$t, $code, $l, $r, $v]) {
    $ref('currency', $t, ['code' => $code, 'symbol' => ['left' => $l, 'right' => $r], 'value' => $v, 'decimal' => 2], $i + 1);
}
foreach (['Courier', 'Pickup', 'Post office', 'Transport company'] as $i => $t) {
    $ref('delivery', $t, [], $i + 1);
}
foreach (['IKEA Factory', 'WoodWorks', 'Mebel-Group'] as $i => $t) {
    $ref('manufacturer', $t, [], $i + 1);
}
foreach ([['Main store', 'Moscow, Tverskaya 1'], ['Warehouse', 'Moscow, Industrial 12'], ['Showroom', 'Saint Petersburg, Nevsky 20']] as $i => [$t, $addr]) {
    $ref('store_location', $t, ['address' => $addr, 'geocode' => '', 'times' => 'Mon-Fri 9-18', 'comment' => '', 'contact' => ['email' => 'shop@example.com', 'phone' => '+7 495 000-00-0' . $i]], $i + 1);
}

// users, groups
$group = $c->get(\App\Domain\Service\User\GroupService::class);
foreach ([['Customers', ''], ['Partners', 'Wholesale buyers'], ['Editors', 'Content managers']] as [$t, $d]) {
    $try("group $t", fn () => $group->create(['title' => $t, 'description' => $d, 'access' => []]));
}
$sub = $c->get(\App\Domain\Service\User\SubscriberService::class);
foreach (['anna@example.com', 'ivan@example.com', 'olga@example.com', 'pavel@example.com'] as $e) {
    $try("subscriber $e", fn () => $sub->create(['email' => $e]));
}

// forms and submissions
$form = $c->get(\App\Domain\Service\Form\FormService::class);
$data = $c->get(\App\Domain\Service\Form\DataService::class);
$forms = [];
foreach ([['Feedback', 'feedback'], ['Callback', 'callback'], ['Quote request', 'quote']] as [$t, $a]) {
    $forms[$a] = $try("form $t", fn () => $form->create(['title' => $t, 'address' => $a, 'mailto' => 'shop@example.com']));
}
foreach ([['feedback', 'Great service, thanks!', 0, 0], ['callback', 'Please call me back after 18:00', 1, 0], ['quote', 'Need 10 chairs for a cafe', 2, 0], ['feedback', 'Buy cheap watches http://spam.example', 3, 1], ['callback', 'Call me tomorrow', 5, 0]] as [$f, $m, $d, $spam]) {
    $try("form data $f", fn () => $data->create(['form_uuid' => $forms[$f]?->uuid, 'data' => ['message' => $m, 'name' => 'Visitor'], 'message' => $m, 'date' => $ago($d), 'is_spam' => (bool) $spam, 'spam_reason' => $spam ? 'content' : '']));
}

// guestbook (the table already has a few), reviews
$review = $c->get(\App\Domain\Service\Review\ReviewService::class);
foreach ([[5, 'Excellent table, very sturdy', 'work', 0], [4, 'Nice chair, delivery was late', 'work', 2], [2, 'Colour differs from the photo', 'moderate', 3], [5, 'Love it!', 'moderate', 6]] as $i => [$rate, $m, $st, $d]) {
    $try("review $i", fn () => $review->create(['type' => 'review', 'entity_type' => 'catalog_product', 'entity_uuid' => $products[$i % count($products)]?->uuid, 'user_uuid' => $admin?->uuid, 'rating' => $rate, 'message' => $m, 'status' => $st, 'date' => $ago($d)]));
}

foreach ([[5, 'Useful article, thank you', 'work', 1], [3, 'Would like more photos', 'moderate', 4], [4, 'Clear and to the point', 'work', 8]] as $i => [$rate, $m, $st, $d]) {
    $try("publication review $i", fn () => $review->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => \App\Domain\Models\Publication::query()->skip($i)->first()?->uuid, 'user_uuid' => $admin?->uuid, 'rating' => $rate, 'message' => $m, 'status' => $st, 'date' => $ago($d)]));
}

// files: tiny generated samples of different types
$fileService = $c->get(\App\Domain\Service\File\FileService::class);
$db->exec('delete from file');
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
foreach ([['price-list.csv', "title;price\nTable;45900\nChair;5400\n"], ['logo.png', $png], ['catalog.txt', str_repeat("Sample catalog text\n", 200)], ['photo-1.png', $png . 'a'], ['readme.md', "# Demo\nSample file\n"]] as [$name, $body]) {
    $tmp = sys_get_temp_dir() . '/' . uniqid() . '-' . $name;
    file_put_contents($tmp, $body);
    $try("file $name", fn () => $fileService->createFromPath($tmp, $name));
}

// api keys
$key = $c->get(\App\Domain\Service\ApiKey\ApiKeyService::class);
foreach ([['Website integration', false], ['1C exchange', false], ['Read only', false]] as [$t, $full]) {
    $try("api key $t", fn () => $key->create(['title' => $t, 'is_full_access' => $full, 'scopes' => ['read' => ['page', 'publication'], 'write' => []], 'status' => 'work']));
}

// users: names and phones for the accounts that exist, tasks for the system log
foreach (['zed' => ['Zed', 'Zimin', '+7 925 111-22-33'], 'bob' => ['Bob', 'Belov', '+7 926 222-33-44'], 'alice' => ['Alice', 'Alexeeva', '+7 927 333-44-55'], 'yan' => ['Yan', 'Yakovlev', '+7 928 444-55-66']] as $u => [$f, $l, $ph]) {
    $db->prepare('update user set firstname = ?, lastname = ?, phone = ?, city = ? where username = ?')->execute([$f, $l, $ph, 'Moscow', $u]);
}
$task = $c->get(\App\Domain\Service\Task\TaskService::class);
foreach ([['Catalog import', 'import', 100, 'done'], ['Image conversion', 'convert', 40, 'work'], ['Newsletter', 'mail', 0, 'fail']] as [$t, $a, $pr, $st]) {
    $try("task $t", fn () => $task->create(['title' => $t, 'action' => $a, 'progress' => $pr, 'status' => $st, 'params' => [], 'output' => '']));
}

// orders: positions, statuses and payments for the orders that exist, plus a few new ones
$pdo = $db;
$statuses = $pdo->query("select uuid from reference where type='order_status' order by `order`")->fetchAll(\PDO::FETCH_COLUMN);
$payments = $pdo->query("select uuid from reference where type='payment' order by `order`")->fetchAll(\PDO::FETCH_COLUMN);
$orders = $pdo->query('select uuid from catalog_order order by serial')->fetchAll(\PDO::FETCH_COLUMN);
$names = ['Anna Petrova', 'Ivan Sidorov', 'Olga Smirnova', 'Pavel Volkov', 'Maria Kuznetsova', 'Dmitry Orlov'];
foreach ($orders as $i => $uuid) {
    $pdo->prepare('update catalog_order set status_uuid = ?, payment_uuid = ?, delivery = ? where uuid = ?')
        ->execute([$statuses[$i % max(1, count($statuses))] ?? null, $payments[$i % max(1, count($payments))] ?? null, json_encode(['client' => $names[$i % 6], 'address' => 'Moscow, Lenina 1']), $uuid]);
    foreach (array_slice(array_filter($products), $i % 3, 2) as $p) {
        $pdo->prepare('insert into catalog_order_product (order_uuid, product_uuid, price, price_type, count, discount, tax, tax_included) values (?,?,?,?,?,?,?,?)')
            ->execute([$uuid, $p->uuid, $p->price ?: 1000, 'price', 1 + $i % 3, 0, 0, 0]);
    }
}

echo "done, ok=$ok\n";

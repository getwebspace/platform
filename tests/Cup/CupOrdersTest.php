<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\File;
use App\Domain\Models\Reference;
use App\Domain\Models\Task;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Reference\ReferenceService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Orders of the shop in the panel: the list, the form, documents, statistics and the Excel in and out
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupOrdersTest extends CupTestCase
{
    private function category(string $title = 'Phones', string $address = 'phones'): CatalogCategory
    {
        $this->admin();

        return $this->getService(CategoryService::class)->create(['title' => $title, 'address' => $address]);
    }

    private function product(CatalogCategory $category, string $title = 'Phone', float $price = 10): CatalogProduct
    {
        return $this->getService(ProductService::class)->create([
            'title' => $title, 'address' => $category->address . '/' . strtolower($title), 'category_uuid' => $category->uuid, 'type' => 'product', 'price' => $price,
            'vendorcode' => 'V-' . strtoupper($title), 'country' => 'UA', 'manufacturer' => 'Acme',
        ]);
    }

    private function order(array $extra = []): CatalogOrder
    {
        $this->admin();

        // the lists show the name of the client, not the address: give each order a name to look for
        return $this->getService(OrderService::class)->create($extra + [
            'email' => 'buyer@example.com', 'phone' => '+380501234567',
            'delivery' => ['client' => $extra['email'] ?? 'buyer@example.com', 'address' => 'Main st 1'],
        ]);
    }

    private function orderStatus(string $title = 'New'): Reference
    {
        return $this->getService(ReferenceService::class)->create(['title' => $title, 'type' => 'order_status', 'value' => ['color' => 'blue']]);
    }

    /**
     * @return array<int, array<int, mixed>> the rows of the first sheet of an Excel answer
     */
    private function sheet(\Psr\Http\Message\ResponseInterface $response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, (string) $response->getBody());

        try {
            return IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } finally {
            @unlink($path);
        }
    }

    // ---------------------------------------------------------------------- list

    public function testTheOrderListShowsTheLastThirtyDaysByDefault(): void
    {
        $recent = $this->order(['email' => 'recent@example.com']);
        $old = $this->order(['email' => 'old@example.com']);
        CatalogOrder::where('uuid', $old->uuid)->update(['date' => date('Y-m-d H:i:s', strtotime('-60 days'))]);

        $body = (string) $this->cup('GET', '/cup/catalog/order')->getBody();

        $this->assertStringContainsString('recent@example.com', $body);
        $this->assertStringNotContainsString('old@example.com', $body);
        $this->assertNotNull($recent);
    }

    public function testThePeriodOfTheListCanBeChosen(): void
    {
        $this->admin();
        $dates = ['today' => '0 days', 'week' => '-3 days', 'month' => '-20 days', 'ancient' => '-400 days'];
        foreach ($dates as $name => $ago) {
            $order = $this->order(['email' => $name . '@example.com']);
            CatalogOrder::where('uuid', $order->uuid)->update(['date' => date('Y-m-d 12:00:00', strtotime($ago))]);
        }

        $shown = fn (string $query): array => array_values(array_filter(array_keys($dates), fn (string $name) => str_contains((string) $this->cup('GET', '/cup/catalog/order' . $query)->getBody(), $name . '@example.com')));

        $this->assertSame(['today'], $shown('?period=today'));
        $this->assertContains('week', $shown('?period=week'));
        $this->assertNotContains('ancient', $shown('?period=month'));
        $this->assertSame(array_keys($dates), $shown('?period=all'));
        $this->assertSame(['today', 'week'], $shown('?period=custom&date[from]=' . date('Y-m-d', strtotime('-5 days')) . '&date[to]=' . date('Y-m-d')));
    }

    public function testTheOrderListCanBeSearchedAndFiltered(): void
    {
        $paid = $this->getService(ReferenceService::class)->create(['title' => 'Card', 'type' => 'payment', 'value' => ['plugin' => '']]);
        $new = $this->orderStatus('New');
        $done = $this->orderStatus('Done');
        $this->order(['email' => 'one@example.com', 'status_uuid' => $new->uuid, 'payment_uuid' => $paid->uuid, 'comment' => 'ring twice']);
        $this->order(['email' => 'two@example.com', 'status_uuid' => $done->uuid]);

        $show = fn (string $query): string => (string) $this->cup('GET', '/cup/catalog/order?period=all&' . $query)->getBody();

        $this->assertStringContainsString('one@example.com', $show('status=' . $new->uuid));
        $this->assertStringNotContainsString('two@example.com', $show('status=' . $new->uuid));
        $this->assertStringContainsString('one@example.com', $show('payment=' . $paid->uuid));
        $this->assertStringNotContainsString('two@example.com', $show('payment=' . $paid->uuid));
        $this->assertStringContainsString('two@example.com', $show('q=TWO@example'));
        $this->assertStringNotContainsString('one@example.com', $show('q=TWO@example'));
        $none = $show('q=nobody-is-called-this');
        $this->assertStringNotContainsString('one@example.com', $none);
        $this->assertStringNotContainsString('two@example.com', $none);
    }

    public function testAnUnknownPeriodOrFilterValueDoesNotBreakTheList(): void
    {
        $this->order();

        foreach (['?period=forever', '?status=not-a-uuid', '?payment=%27%3B--', '?date[from]=garbage&period=custom', '?q=%25'] as $query) {
            $this->assertRenders($this->cup('GET', '/cup/catalog/order' . $query), $query);
        }
    }

    // --------------------------------------------------------------------- add

    public function testAnOrderIsAddedWithItsProducts(): void
    {
        $category = $this->category();
        $phone = $this->product($category, 'Phone', 100);
        $cable = $this->product($category, 'Cable', 5);
        $status = $this->orderStatus();

        $response = $this->cup('POST', '/cup/catalog/order/add', ['form_params' => [
            'email' => 'buyer@example.com', 'phone' => '+380501234567', 'comment' => 'ring twice', 'status_uuid' => $status->uuid,
            'delivery' => ['client' => 'Ann', 'address' => 'Main st 1'],
            'products' => [$phone->uuid => ['count' => '2', 'price_type' => 'price'], $cable->uuid => ['count' => '3', 'price_type' => 'price']],
        ]]);

        $this->assertRedirectsTo($response, '/cup/catalog/order');
        $order = CatalogOrder::firstWhere(['email' => 'buyer@example.com']);
        $this->assertSame('ring twice', $order->comment);
        $this->assertSame($status->uuid, $order->status_uuid);
        $this->assertSame('Ann', $order->delivery['client']);
        $this->assertCount(2, $order->products);
        $this->assertEquals(215.0, $order->totalSum());
        $this->assertNotSame('', $order->serial);
    }

    public function testAnOrderWithAnUnknownProductKeepsOnlyTheKnownOnes(): void
    {
        $product = $this->product($this->category());

        $this->cup('POST', '/cup/catalog/order/add', ['form_params' => [
            'email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x'],
            'products' => [$product->uuid => ['count' => '1', 'price_type' => 'price'], \Ramsey\Uuid\Uuid::uuid4()->toString() => ['count' => '9', 'price_type' => 'price']],
        ]]);

        $this->assertCount(1, CatalogOrder::first()->products);
    }

    public function testAnOrderNeedsAWellFormedAddressAndPhone(): void
    {
        $this->admin();

        foreach (['email' => 'not-an-address', 'phone' => 'abc'] as $field => $value) {
            $response = $this->cup('POST', '/cup/catalog/order/add', ['form_params' => [$field => $value] + ['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }
        $this->assertSame(0, CatalogOrder::count());
    }

    public function testSaveAndContinueOpensTheNewOrder(): void
    {
        $this->admin();

        $response = $this->cup('POST', '/cup/catalog/order/add', ['form_params' => ['save' => 'continue', 'email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]]);

        $this->assertRedirectsTo($response, '/cup/catalog/order/' . CatalogOrder::value('uuid') . '/edit');
    }

    // -------------------------------------------------------------------- edit

    public function testAnOrderIsEditedAndItsStatusChanged(): void
    {
        $new = $this->orderStatus('New');
        $shipped = $this->orderStatus('Shipped');
        $order = $this->order(['status_uuid' => $new->uuid]);

        $response = $this->cup('POST', "/cup/catalog/order/{$order->uuid}/edit", ['form_params' => [
            'email' => 'buyer@example.com', 'phone' => '+380501234567', 'status_uuid' => $shipped->uuid, 'comment' => 'left at the door',
            'delivery' => ['client' => 'Ann', 'address' => 'Side st 2'],
        ]]);

        $this->assertRedirectsTo($response, '/cup/catalog/order');
        $fresh = CatalogOrder::find($order->uuid);
        $this->assertSame($shipped->uuid, $fresh->status_uuid);
        $this->assertSame('left at the door', $fresh->comment);
        $this->assertSame('Side st 2', $fresh->delivery['address']);
    }

    public function testTheProductsOfAnOrderAreReplacedByTheFormAndTheTotalFollows(): void
    {
        $category = $this->category();
        $phone = $this->product($category, 'Phone', 100);
        $cable = $this->product($category, 'Cable', 5);
        $order = $this->order(['products' => [$phone->uuid => ['count' => 1, 'price_type' => 'price']]]);
        $this->assertEquals(100.0, CatalogOrder::find($order->uuid)->totalSum());

        $this->cup('POST', "/cup/catalog/order/{$order->uuid}/edit", ['form_params' => [
            'email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x'],
            'products' => [$cable->uuid => ['count' => '4', 'price_type' => 'price']],
        ]]);

        $fresh = CatalogOrder::find($order->uuid);
        $this->assertSame([$cable->uuid], $fresh->products->pluck('uuid')->all());
        $this->assertEquals(20.0, $fresh->totalSum());
    }

    public function testAMalformedAddressOrPhoneOnEditKeepsTheOrder(): void
    {
        $order = $this->order();

        foreach (['email' => 'not-an-address', 'phone' => 'abc'] as $field => $value) {
            $response = $this->cup('POST', "/cup/catalog/order/{$order->uuid}/edit", ['form_params' => [$field => $value] + ['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']]]);

            $this->assertSame(200, $response->getStatusCode(), $field . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $field);
        }

        $this->assertSame('buyer@example.com', CatalogOrder::find($order->uuid)->email);
    }

    public function testTheOrderFormShowsTheOrder(): void
    {
        $order = $this->order(['comment' => 'ring twice']);

        $body = (string) $this->cup('GET', "/cup/catalog/order/{$order->uuid}/edit")->getBody();

        $this->assertStringContainsString('buyer@example.com', $body);
        $this->assertStringContainsString('ring twice', $body);
        $this->assertStringContainsString('Main st 1', $body);
    }

    public function testEditingOrDeletingAnUnknownOrderGoesBackToTheList(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('GET', "/cup/catalog/order/{$uuid}/edit"), '/cup/catalog/order', $uuid);
            $this->assertRedirectsTo($this->cup('POST', "/cup/catalog/order/{$uuid}/delete"), '/cup/catalog/order', $uuid);
        }
    }

    public function testAnOrderIsDeleted(): void
    {
        $order = $this->order();

        $response = $this->cup('POST', "/cup/catalog/order/{$order->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/catalog/order');
        $this->assertNull(CatalogOrder::find($order->uuid));
    }

    public function testWhatTheBuyerTypedIsEscapedInTheOrderPages(): void
    {
        $hostile = '<script>alert("o")</script>';
        $order = $this->order(['comment' => $hostile, 'delivery' => ['client' => $hostile, 'address' => $hostile]]);

        foreach (['/cup/catalog/order?period=all', "/cup/catalog/order/{$order->uuid}/edit", "/cup/catalog/order/{$order->uuid}/dispatch"] as $path) {
            $this->assertStringNotContainsString($hostile, (string) $this->cup('GET', $path)->getBody(), $path);
        }
    }

    // ---------------------------------------------------- documents and dispatch

    public function testADocumentIsPrintedFromTheOrder(): void
    {
        $order = $this->order(['email' => 'printed@example.com']);
        $document = $this->getService(ReferenceService::class)->create([
            'title' => 'Invoice', 'type' => 'document', 'value' => ['template' => '<h1>Invoice {{ order.serial }} for {{ order.email }}</h1>', 'target' => 'order'],
        ]);

        $response = $this->cup('GET', "/cup/catalog/order/{$order->uuid}/document/{$document->uuid}");

        $this->assertRenders($response);
        $this->assertStringContainsString('Invoice ' . $order->serial . ' for printed@example.com', (string) $response->getBody());
    }

    public function testADocumentOfAnUnknownOrderOrWithBadIdsGoesBackToTheList(): void
    {
        $order = $this->order();
        $document = $this->getService(ReferenceService::class)->create(['title' => 'Invoice', 'type' => 'document', 'value' => ['template' => 'x', 'target' => 'order']]);

        foreach ([
            '/cup/catalog/order/' . \Ramsey\Uuid\Uuid::uuid4() . "/document/{$document->uuid}",
            "/cup/catalog/order/not-a-uuid/document/{$document->uuid}",
            "/cup/catalog/order/{$order->uuid}/document/not-a-uuid",
        ] as $path) {
            $this->assertRedirectsTo($this->cup('GET', $path), '/cup/catalog/order', $path);
        }
    }

    public function testTheDispatchPageShowsTheTemplateOfTheShop(): void
    {
        $order = $this->order();
        $this->getService(ParameterService::class)->create(['name' => 'catalog_dispatch', 'value' => 'Your order is on its way']);

        $this->assertRenders($this->cup('GET', "/cup/catalog/order/{$order->uuid}/dispatch"));
        $this->assertRedirectsTo($this->cup('GET', '/cup/catalog/order/' . \Ramsey\Uuid\Uuid::uuid4() . '/dispatch'), '/cup/catalog/order');
    }

    // --------------------------------------------------------------- statistics

    public function testTheStatisticsOfAnEmptyShopOpen(): void
    {
        $this->admin();

        $this->assertRenders($this->cup('GET', '/cup/catalog/statistic'));
    }

    public function testTheStatisticsShowTheBestSellersAndTheBestBuyers(): void
    {
        $category = $this->category();
        $hit = $this->product($category, 'Bestseller', 50);
        $rare = $this->product($category, 'Rarity', 7);
        $this->order(['email' => 'loyal@example.com', 'products' => [$hit->uuid => ['count' => 5, 'price_type' => 'price'], $rare->uuid => ['count' => 1, 'price_type' => 'price']]]);
        $this->order(['email' => 'loyal@example.com', 'products' => [$hit->uuid => ['count' => 3, 'price_type' => 'price']]]);
        $this->order(['email' => 'once@example.com', 'products' => [$rare->uuid => ['count' => 1, 'price_type' => 'price']]]);

        $body = (string) $this->cup('GET', '/cup/catalog/statistic')->getBody();

        $this->assertRenders($this->cup('GET', '/cup/catalog/statistic'));
        $this->assertStringContainsString('Bestseller', $body);
        $this->assertLessThan(strpos($body, 'Rarity'), strpos($body, 'Bestseller'), 'the product sold most comes first');
        $this->assertStringContainsString('loyal@example.com', $body);
    }

    // ------------------------------------------------------------------- export

    public function testTheProductsAreExportedToExcelByCategory(): void
    {
        $phones = $this->category();
        $cables = $this->category('Cables', 'cables');
        $this->product($phones, 'Phone', 100);
        $this->product($cables, 'Wire', 5);

        $response = $this->cup('GET', '/cup/catalog/product/export');
        $rows = $this->sheet($response);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertStringContainsString('ms-excel', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('export', $response->getHeaderLine('Content-Disposition'));
        $this->assertSame(['uuid', 'title', 'vendorcode', 'price', 'country', 'manufacturer', 'order'], array_slice($rows[0], 0, 7));
        $flat = array_merge(...$rows);
        foreach (['Phones', 'Cables', 'Phone', 'Wire', 'V-PHONE', 'V-WIRE'] as $cell) {
            $this->assertContains($cell, $flat, $cell);
        }
    }

    public function testTheExportCanBeLimitedToACategoryAndItsBranch(): void
    {
        $phones = $this->getService(CategoryService::class)->create(['title' => 'Phones', 'address' => 'phones', 'is_allow_nested' => true]);
        $smart = $this->getService(CategoryService::class)->create(['title' => 'Smart', 'address' => 'smart', 'parent_uuid' => $phones->uuid]);
        $cables = $this->category('Cables', 'cables');
        $this->product($phones, 'Handset');
        $this->product($smart, 'Clever');
        $this->product($cables, 'Wire');

        $flat = array_merge(...$this->sheet($this->cup('GET', '/cup/catalog/product/export?category=' . $phones->uuid)));

        $this->assertContains('Handset', $flat);
        $this->assertContains('Clever', $flat, 'the branch goes with it');
        $this->assertNotContains('Wire', $flat);
    }

    public function testTheExportOfADeletedOrUnknownCategoryIsNotAFile(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString()] as $uuid) {
            $response = $this->cup('GET', '/cup/catalog/product/export?category=' . $uuid);

            $this->assertRedirectsTo($response, '/cup/catalog/product', $uuid);
        }
    }

    public function testTheColumnsOfTheExportFollowTheSettings(): void
    {
        $this->product($this->category(), 'Phone', 100);
        $this->getService(ParameterService::class)->create(['name' => 'catalog_export_columns', 'value' => "title\nprice"]);

        $rows = $this->sheet($this->cup('GET', '/cup/catalog/product/export'));

        $this->assertSame(['title', 'price'], array_slice($rows[0], 0, 2));
        $this->assertNull($rows[0][2] ?? null);
    }

    public function testTheOrdersAreExportedForAPeriod(): void
    {
        $this->order(['email' => 'today@example.com']);
        $old = $this->order(['email' => 'old@example.com']);
        CatalogOrder::where('uuid', $old->uuid)->update(['date' => date('Y-m-d H:i:s', strtotime('-40 days'))]);

        $response = $this->cup('GET', '/cup/catalog/order/export?from=' . date('Y-m-d', strtotime('-7 days')) . '&to=' . date('Y-m-d'));
        $rows = $this->sheet($response);

        $this->assertStringContainsString('ms-excel', $response->getHeaderLine('Content-Type'));
        $this->assertSame('serial', $rows[0][0]);
        $flat = array_merge(...$rows);
        $this->assertContains('today@example.com', $flat, 'an order of the last day of the period is in it');
        $this->assertNotContains('old@example.com', $flat);
    }

    public function testTheOrderExportCanBeLimitedToAStatus(): void
    {
        $new = $this->orderStatus('New');
        $done = $this->orderStatus('Done');
        $this->order(['email' => 'one@example.com', 'status_uuid' => $new->uuid]);
        $this->order(['email' => 'two@example.com', 'status_uuid' => $done->uuid]);

        $response = $this->cup('GET', '/cup/catalog/order/export?status=' . $new->uuid . '&from=' . date('Y-m-d', strtotime('-1 day')) . '&to=' . date('Y-m-d'));

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $flat = array_merge(...$this->sheet($response));
        $this->assertContains('one@example.com', $flat);
        $this->assertNotContains('two@example.com', $flat);
    }

    public function testNoOrdersInThePeriodIsNotAFile(): void
    {
        $this->order();

        $none = $this->cup('GET', '/cup/catalog/order/export?from=2001-01-01&to=2001-01-31');
        $noPeriod = $this->cup('GET', '/cup/catalog/order/export');

        $this->assertRedirectsTo($none, '/cup/catalog/order');
        $this->assertRedirectsTo($noPeriod, '/cup/catalog/order');
    }

    // ------------------------------------------------------------------- import

    private function workbook(): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(['uuid', 'title', 'vendorcode', 'price'], null, 'A1');
        $spreadsheet->getActiveSheet()->fromArray([null, 'Imported', 'V-1', 10], null, 'A2');
        $path = tempnam(sys_get_temp_dir(), 'import') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    public function testAnUploadedPriceListIsQueuedForTheImport(): void
    {
        $this->admin();
        $path = $this->workbook();

        $response = $this->cup('POST', '/cup/catalog/product/import', [
            'headers' => ['Referer' => 'http://127.0.0.1/cup/catalog/product'],
            'multipart' => [['name' => 'excel', 'contents' => fopen($path, 'r'), 'filename' => 'prices.xlsx']],
        ]);
        @unlink($path);

        $this->assertRedirectsTo($response, '/cup/catalog/product');
        $task = Task::where('action', \App\Domain\Tasks\Catalog\ProductImportTask::class)->first();
        $this->assertNotNull($task, $this->why($response));
        $this->assertSame('queue', $task->status);
        $file = File::find($task->params['file']);
        $this->assertNotNull($file, 'the file is in the library');
        $this->cupFiles[] = $file;
    }

    public function testNothingIsQueuedWithoutAFile(): void
    {
        $this->admin();

        $this->assertRedirectsTo($this->cup('POST', '/cup/catalog/product/import'), '/cup/catalog/product');
        $this->assertSame(0, Task::count());
    }
}

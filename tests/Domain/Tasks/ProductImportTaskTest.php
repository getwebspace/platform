<?php declare(strict_types=1);

namespace tests\Domain\Tasks;

use App\Application\PubSub;
use App\Domain\Casts\Task\Status;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\File;
use App\Domain\Service\File\FileService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Tasks\Catalog\ProductImportTask;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use tests\Concerns\RunsTasks;
use tests\TestCase;

/**
 * Importing a price list from an Excel file
 *
 * @internal
 *
 * #[CoversNothing]
 */
class ProductImportTaskTest extends TestCase
{
    use RunsTasks;

    /**
     * Columns follow the default import layout:
     * uuid, title, vendorcode, price, country, manufacturer, order
     *
     * @param array<int, array<int, mixed>|string> $rows a string is a category heading (a merged row)
     */
    private function workbook(array $rows): File
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // the first row is a header and is skipped
        $sheet->fromArray(['uuid', 'title', 'vendorcode', 'price', 'country', 'manufacturer', 'order'], null, 'A1');

        $line = 2;
        foreach ($rows as $row) {
            if (is_string($row)) {
                $sheet->setCellValue('A' . $line, $row);
                $sheet->mergeCells("A{$line}:G{$line}");
            } else {
                $sheet->fromArray($row, null, 'A' . $line);
            }
            ++$line;
        }

        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $this->getService(FileService::class)->createFromPath($path);
    }

    private function setAction(string $action): void
    {
        $this->getService(ParameterService::class)->create(['name' => 'catalog_import_action', 'value' => $action]);

        // without it every new category and product of a price list gets the same empty address,
        // and only the first of them is created
        $this->getService(ParameterService::class)->create(['name' => 'common_auto_generate_address', 'value' => 'yes']);
    }

    public function testNewProductsAreCreatedUnderTheirCategories(): void
    {
        $this->setAction('insert');
        $file = $this->workbook([
            'Phones',
            [null, 'Phone One', 'V-1', 100, 'UA', 'Acme', 1],
            [null, 'Phone Two', 'V-2', 200, 'UA', 'Acme', 2],
            'Cables',
            [null, 'Cable', 'V-3', 5, 'PL', 'Wire', 3],
        ]);

        $task = $this->runTask(ProductImportTask::class, ['file' => $file->uuid]);

        $this->assertEquals(Status::DONE, $task->status, $task->output);

        $phones = CatalogCategory::firstWhere(['title' => 'Phones']);
        $cables = CatalogCategory::firstWhere(['title' => 'Cables']);
        $this->assertNotNull($phones, json_encode(CatalogCategory::pluck('title')->all()));
        $this->assertNotNull($cables, json_encode(CatalogCategory::pluck('title')->all()));
        $this->assertSame(['Phone One', 'Phone Two'], CatalogProduct::where('category_uuid', $phones->uuid)->orderBy('order')->pluck('title')->all());
        $this->assertSame(['Cable'], CatalogProduct::where('category_uuid', $cables->uuid)->pluck('title')->all());
        $this->assertEquals(200, CatalogProduct::firstWhere(['vendorcode' => 'V-2'])->price);
    }

    public function testExistingProductsAreUpdatedByTheKeyColumn(): void
    {
        $this->setAction('update');
        $category = $this->getService(\App\Domain\Service\Catalog\CategoryService::class)->create(['title' => 'Phones', 'address' => 'phones']);
        $product = $this->getService(\App\Domain\Service\Catalog\ProductService::class)->create([
            'title' => 'Old name',
            'address' => 'old-name',
            'vendorcode' => 'V-1',
            'price' => 100,
            'category_uuid' => $category->uuid,
            'type' => \App\Domain\Casts\Catalog\Product\Type::PRODUCT,
        ]);

        $file = $this->workbook([
            'Phones',
            [null, 'New name', 'V-1', 150, 'UA', 'Acme', 1],
            [null, 'Not in the catalog', 'V-404', 1, 'UA', 'Acme', 2],
        ]);

        $task = $this->runTask(ProductImportTask::class, ['file' => $file->uuid]);

        $this->assertEquals(Status::DONE, $task->status, $task->output);
        $fresh = CatalogProduct::find($product->uuid);
        $this->assertSame('New name', $fresh->title);
        $this->assertEquals(150, $fresh->price);
        $this->assertSame(1, CatalogProduct::count(), 'update mode never creates');
        $this->assertSame(1, CatalogCategory::count(), 'nor does it create categories');
    }

    public function testRunningTheImportTwiceDoesNotDuplicate(): void
    {
        $this->setAction('insert');
        $rows = ['Phones', [null, 'Phone One', 'V-1', 100, 'UA', 'Acme', 1]];

        $this->runTask(ProductImportTask::class, ['file' => $this->workbook($rows)->uuid]);
        $this->runTask(ProductImportTask::class, ['file' => $this->workbook($rows)->uuid]);

        $this->assertSame(1, CatalogProduct::count());
        $this->assertSame(1, CatalogCategory::count());
    }

    public function testTheUploadedFileIsRemovedAndTheImportAnnounced(): void
    {
        $this->setAction('insert');
        $file = $this->workbook(['Phones', [null, 'Phone One', 'V-1', 100, 'UA', 'Acme', 1]]);
        $announced = 0;
        $this->getService(PubSub::class)->subscribe('task:catalog:import', function () use (&$announced): void {
            ++$announced;
        });

        $this->runTask(ProductImportTask::class, ['file' => $file->uuid]);

        $this->assertNull(File::find($file->uuid));
        $this->assertFileDoesNotExist($file->internal_path());
        $this->assertSame(1, $announced);
    }

    public function testRowsWithoutATitleAreSkippedWithoutStoppingTheImport(): void
    {
        $this->setAction('insert');
        $file = $this->workbook([
            'Phones',
            [null, '', 'V-1', 100, 'UA', 'Acme', 1],
            [null, 'Fine', 'V-2', 100, 'UA', 'Acme', 2],
        ]);

        $task = $this->runTask(ProductImportTask::class, ['file' => $file->uuid]);

        $this->assertEquals(Status::DONE, $task->status, $task->output);
        $this->assertSame(['Fine'], CatalogProduct::pluck('title')->all());
    }

    public function testRowsAboveTheFirstCategoryAreSkipped(): void
    {
        $this->setAction('insert');
        $file = $this->workbook([
            [null, 'Orphan', 'V-0', 1, 'UA', 'Acme', 1],
            'Phones',
            [null, 'Phone One', 'V-1', 100, 'UA', 'Acme', 2],
        ]);

        $task = $this->runTask(ProductImportTask::class, ['file' => $file->uuid]);

        $this->assertEquals(Status::DONE, $task->status, $task->output);
        $this->assertSame(['Phone One'], CatalogProduct::pluck('title')->all());
    }

    public function testAMissingFileFailsTheTask(): void
    {
        $task = $this->runTask(ProductImportTask::class, ['file' => \Ramsey\Uuid\Uuid::uuid4()->toString()]);

        $this->assertEquals(Status::FAIL, $task->status);
    }

    public function testTheKeyColumnAndTheLayoutCanBeConfigured(): void
    {
        $this->setAction('insert');
        $this->getService(ParameterService::class)->create(['name' => 'catalog_import_columns', 'value' => "title\nvendorcode\nprice"]);
        $this->getService(ParameterService::class)->create(['name' => 'catalog_import_export_offset_rows', 'value' => '1']);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['company name'], null, 'A1');
        $sheet->fromArray(['title', 'vendorcode', 'price'], null, 'A2');
        $sheet->setCellValue('A3', 'Gadgets');
        $sheet->mergeCells('A3:C3');
        $sheet->fromArray(['Laid out differently', 'X-9', 42], null, 'A4');
        $path = CACHE_DIR . '/' . md5(uniqid('', true)) . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = $this->getService(FileService::class)->createFromPath($path);

        $task = $this->runTask(ProductImportTask::class, ['file' => $file->uuid]);

        $this->assertEquals(Status::DONE, $task->status, $task->output);
        $this->assertSame(['Laid out differently'], CatalogProduct::pluck('title')->all());
        $this->assertEquals(42, CatalogProduct::first()->price);
    }
}

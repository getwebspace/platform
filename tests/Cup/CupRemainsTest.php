<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Models\Publication;
use App\Domain\Models\PublicationCategory;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Parameter\ParameterService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The corners of the panel the scenarios of each section do not reach: "save and stay" on the forms
 * that had no test for it, a value taken by another record, a record that is gone, and the export
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupRemainsTest extends CupTestCase
{
    /**
     * [fixture, edit path, what the form sends, where "save and continue" goes]
     */
    public static function forms(): array
    {
        return [
            'catalog category' => ['catalog_category', '/cup/catalog/category/%s/edit', ['title' => 'Phones', 'address' => 'phones'], '/cup/catalog/category/%s/edit'],
            'catalog attribute' => ['attribute', '/cup/catalog/attribute/%s/edit', ['title' => 'Color', 'address' => 'color', 'type' => 'string'], '/cup/catalog/attribute/%s/edit'],
            'catalog product' => ['product', '/cup/catalog/product/%s/edit', ['title' => 'Phone', 'address' => 'phones/phone', 'type' => 'product', 'price' => '10'], '/cup/catalog/product/%s/edit'],
            'catalog order' => ['order', '/cup/catalog/order/%s/edit', ['email' => 'buyer@example.com', 'phone' => '+380501234567', 'delivery' => ['address' => 'x']], '/cup/catalog/order/%s/edit'],
            'guest book' => ['guestbook', '/cup/guestbook/%s/edit', ['name' => 'Ann', 'email' => 'ann@example.com', 'message' => 'Hi', 'status' => 'work'], '/cup/guestbook/%s/edit'],
            'reference' => ['reference', '/cup/reference/currencies/%s/edit', ['title' => 'USD', 'value' => ['code' => 'USD']], '/cup/reference/currencies/%s/edit'],
            'user group' => ['group', '/cup/user/group/%s/edit', ['title' => 'Staff', 'access' => ['cup:main']], '/cup/user/group/%s/edit'],
            'user' => ['user', '/cup/user/%s/edit', ['username' => 'customer', 'email' => 'customer@example.com'], '/cup/user/%s/edit'],
        ];
    }

    #[DataProvider('forms')]
    public function testSaveAndContinueStaysOnTheEditPage(string $fixture, string $path, array $fields, string $expected): void
    {
        $uuid = $this->fixtures()[$fixture];
        if ($fixture === 'product') {
            $fields['category'] = $this->fixtures()['catalog_category'];
        }

        $response = $this->cup('POST', sprintf($path, $uuid), ['form_params' => ['save' => 'continue'] + $fields]);

        $this->assertRedirectsTo($response, sprintf($expected, $uuid));
    }

    public static function newForms(): array
    {
        return [
            'catalog category' => ['/cup/catalog/category/add', ['title' => 'Cables', 'address' => 'cables'], '/cup/catalog/category/'],
            'catalog attribute' => ['/cup/catalog/attribute/add', ['title' => 'Size', 'address' => 'size', 'type' => 'string'], '/cup/catalog/attribute/'],
        ];
    }

    #[DataProvider('newForms')]
    public function testSaveAndContinueOnANewRecordOpensIt(string $path, array $fields, string $prefix): void
    {
        $this->admin();

        $response = $this->cup('POST', $path, ['form_params' => ['save' => 'continue'] + $fields]);

        $this->assertStringStartsWith($prefix, $response->getHeaderLine('Location'));
        $this->assertStringEndsWith('/edit', $response->getHeaderLine('Location'));
    }

    // ------------------------------------------------------------- taken values

    public function testACategoryCannotTakeTheAddressOfAnother(): void
    {
        $f = $this->fixtures();
        $other = $this->getService(CategoryService::class)->create(['title' => 'Cables', 'address' => 'cables']);

        $category = $this->cup('POST', "/cup/catalog/category/{$other->uuid}/edit", ['form_params' => ['title' => 'Cables', 'address' => 'phones', 'external_id' => '']]);
        $this->assertSame(200, $category->getStatusCode(), $this->why($category));
        $this->assertStringContainsString('has-error', (string) $category->getBody());

        $this->assertSame('phones', \App\Domain\Models\CatalogCategory::find($f['catalog_category'])->address);
    }

    public function testAnAttributeCannotTakeTheTitleOfAnother(): void
    {
        $f = $this->fixtures();
        $other = $this->getService(AttributeService::class)->create(['title' => 'Size', 'address' => 'size', 'type' => 'string']);

        $taken = $this->cup('POST', "/cup/catalog/attribute/{$other->uuid}/edit", ['form_params' => ['title' => 'Color', 'address' => 'size', 'type' => 'string']]);

        $this->assertSame(200, $taken->getStatusCode(), $this->why($taken));
        $this->assertStringContainsString('has-error', (string) $taken->getBody());
        $this->assertSame('Size', \App\Domain\Models\CatalogAttribute::find($other->uuid)->title);
        $this->assertSame('Color', \App\Domain\Models\CatalogAttribute::find($f['attribute'])->title);
    }

    // ------------------------------------------------------------ gone records

    public function testARecordThatIsGoneTakesTheAdministratorBackToTheList(): void
    {
        $this->admin();
        $gone = \Ramsey\Uuid\Uuid::uuid4()->toString();

        $this->assertRedirectsTo($this->cup('POST', "/cup/catalog/category/{$gone}/edit", ['form_params' => ['title' => 'x', 'address' => 'x']]), '/cup/catalog/category');
        $this->assertRedirectsTo($this->cup('POST', "/cup/guestbook/{$gone}/edit", ['form_params' => ['name' => 'x', 'email' => 'x@example.com', 'message' => 'x']]), '/cup/guestbook');
        $this->assertRedirectsTo($this->cup('POST', "/cup/guestbook/{$gone}/delete"), '/cup/guestbook');
    }

    public function testAnUnknownAttributeOnAProductFormIsReportedOnTheField(): void
    {
        $f = $this->fixtures();
        $gone = \Ramsey\Uuid\Uuid::uuid4()->toString();

        $product = ['title' => 'Phone', 'category' => $f['catalog_category'], 'address' => 'phones/new', 'type' => 'product', 'price' => '1', 'attributes' => [$gone => 'red']];
        $before = \App\Domain\Models\CatalogProduct::count();

        $add = $this->cup('POST', '/cup/catalog/product/add', ['form_params' => $product]);
        $this->assertSame(200, $add->getStatusCode(), $this->why($add));
        $this->assertSame($before, \App\Domain\Models\CatalogProduct::count());

        $edit = $this->cup('POST', "/cup/catalog/product/{$f['product']}/edit", ['form_params' => ['address' => 'phones/phone'] + $product]);
        $this->assertSame(200, $edit->getStatusCode(), $this->why($edit));
        $this->assertSame(0, \Illuminate\Database\Capsule\Manager::table('catalog_attribute_product')->where('attribute_uuid', $gone)->count(), 'no row for an attribute that is not there');
    }

    public function testTheListOfUsersCanBeFilteredByGroupAndState(): void
    {
        $f = $this->fixtures();

        $this->assertRenders($this->cup('GET', '/cup/user?group_uuid=' . $f['group']));
        $this->assertRenders($this->cup('GET', '/cup/user?state=all&q=customer'));
        $this->assertStringContainsString('customer@example.com', (string) $this->cup('GET', '/cup/user?state=all')->getBody());
    }

    // ----------------------------------------------------- what is deleted with it

    public function testDeletingAPublicationCategoryDeletesItsPublicationsAndBranch(): void
    {
        $this->admin();
        $categories = $this->getService(PublicationCategoryService::class);
        $top = $categories->create(['title' => 'Top', 'address' => 'top']);
        $child = $categories->create(['title' => 'Child', 'address' => 'child', 'parent_uuid' => $top->uuid]);
        $publications = $this->getService(PublicationService::class);
        $publications->create(['title' => 'In top', 'address' => 'top/a', 'category_uuid' => $top->uuid, 'content' => ['short' => 'a', 'full' => 'b']]);
        $publications->create(['title' => 'In child', 'address' => 'child/a', 'category_uuid' => $child->uuid, 'content' => ['short' => 'a', 'full' => 'b']]);
        $fixtures = $this->fixtures();

        $response = $this->cup('POST', "/cup/publication/category/{$top->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/publication/category');
        $this->assertNull(PublicationCategory::find($top->uuid));
        $this->assertNull(PublicationCategory::find($child->uuid));
        $this->assertSame(['Hello'], Publication::pluck('title')->all(), 'only the publication of another category is left');
        $this->assertNotNull($fixtures['publication']);
    }

    // ------------------------------------------------------------------- form data

    public function testASubmissionIsShownThroughTheTemplateOfItsForm(): void
    {
        $f = $this->fixtures();
        $formUuid = \App\Domain\Models\FormData::find($f['form_data'])->form_uuid;
        \App\Domain\Models\Form::where('uuid', $formUuid)->update(['template' => '<p>Mail: {{ email }}</p>']);
        \App\Domain\Models\FormData::where('uuid', $f['form_data'])->update(['message' => '']);

        $response = $this->cup('GET', "/cup/form/{$formUuid}/view/{$f['form_data']}/preview");

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertStringContainsString('Mail: v@example.com', (string) $response->getBody());
    }

    // ------------------------------------------------------------------ export

    public function testEveryKindOfColumnOfTheExportIsWritten(): void
    {
        $f = $this->fixtures();
        $attributes = $this->getService(AttributeService::class);
        $text = $attributes->create(['title' => 'Note', 'address' => 'note', 'type' => 'string']);
        $count = $attributes->create(['title' => 'Count', 'address' => 'count', 'type' => 'integer']);
        $ratio = $attributes->create(['title' => 'Ratio', 'address' => 'ratio', 'type' => 'float']);
        $this->getService(ProductService::class)->update($f['product'], [
            'priceFirst' => 15, 'priceWholesale' => 8, 'tax' => 10, 'discount' => 1, 'stock' => 7, 'vendorcode' => 'V-9', 'barcode' => '4600', 'country' => 'UA',
            'attributes' => [$text->uuid => 'plain', $count->uuid => '3', $ratio->uuid => '1.5'],
        ]);
        $columns = ['uuid', 'category', 'title', 'priceFirst', 'price', 'priceWholesale', 'priceWholesaleFrom', 'tax', 'discount', 'quantity', 'quantityMin', 'special', 'tax_included', 'vendorcode', 'barcode', 'volume', 'stock', 'order', 'date', 'country', 'note', 'count', 'ratio', 'unknown_column'];
        $this->getService(ParameterService::class)->create(['name' => 'catalog_export_columns', 'value' => implode("\n", $columns)]);
        $this->getService(ParameterService::class)->create(['name' => 'catalog_import_export_offset_rows', 'value' => '1']);
        $this->getService(ParameterService::class)->create(['name' => 'catalog_import_export_offset_cols', 'value' => '1']);

        $response = $this->cup('GET', '/cup/catalog/product/export');

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, (string) $response->getBody());
        try {
            $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet()->toArray(null, true, false);
        } finally {
            @unlink($file);
        }
        $flat = array_merge(...$rows);
        foreach (['Phone', 'Phones', 'V-9', 'plain', 'note', 'ratio'] as $cell) {
            $this->assertContains($cell, $flat, (string) $cell);
        }
        $this->assertContains(3, array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, $flat), 'the number attribute');
        $this->assertContains(1.5, array_map(fn ($v) => is_numeric($v) ? (float) $v : $v, $flat), 'the fraction attribute');
    }
}

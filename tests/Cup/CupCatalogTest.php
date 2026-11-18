<?php declare(strict_types=1);

namespace tests\Cup;

use App\Domain\Casts\Catalog\Status;
use App\Domain\Models\CatalogAttribute;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductService;

/**
 * The shop in the panel: categories, attributes and products
 *
 * @internal
 *
 * #[CoversNothing]
 */
class CupCatalogTest extends CupTestCase
{
    private function category(array $extra = []): CatalogCategory
    {
        $this->admin();

        return $this->getService(CategoryService::class)->create($extra + ['title' => 'Phones', 'address' => 'phones']);
    }

    private function product(CatalogCategory $category, array $extra = []): CatalogProduct
    {
        return $this->getService(ProductService::class)->create($extra + [
            'title' => 'Phone', 'address' => $category->address . '/phone', 'category_uuid' => $category->uuid, 'type' => 'product', 'price' => 10,
        ]);
    }

    // ---------------------------------------------------------------- categories

    public function testACategoryIsAddedWithItsSettings(): void
    {
        $this->admin();

        $response = $this->cup('POST', '/cup/catalog/category/add', ['form_params' => [
            'title' => 'Phones', 'address' => 'phones', 'description' => 'All of them', 'pagination' => '5',
            'sort' => ['by' => 'price', 'direction' => 'desc'], 'meta' => ['title' => 'Buy phones'], 'is_hidden' => '0',
        ]]);

        $this->assertRedirectsTo($response, '/cup/catalog/category');
        $category = CatalogCategory::firstWhere(['title' => 'Phones']);
        $this->assertSame(5, $category->pagination);
        $this->assertSame('price', $category->sort['by']);
        $this->assertSame('desc', $category->sort['direction']);
        $this->assertSame('Buy phones', $category->meta['title']);
        $this->assertSame(Status::WORK, $category->status);
    }

    public function testASubCategoryIsAddedUnderItsParent(): void
    {
        $parent = $this->category(['is_allow_nested' => true]);

        $this->cup('POST', '/cup/catalog/category/add', ['form_params' => ['title' => 'Smart', 'address' => 'smart', 'parent' => $parent->uuid]]);

        $this->assertSame($parent->uuid, CatalogCategory::firstWhere(['title' => 'Smart'])->parent_uuid);
    }

    public function testACategoryWithoutATitleOrWithATakenAddressIsReported(): void
    {
        $first = $this->category();

        $missing = $this->cup('POST', '/cup/catalog/category/add', ['form_params' => ['title' => '', 'address' => 'other']]);
        $taken = $this->cup('POST', '/cup/catalog/category/add', ['form_params' => ['title' => 'Phones again', 'address' => $first->address]]);

        foreach (['missing' => $missing, 'taken' => $taken] as $which => $response) {
            $this->assertSame(200, $response->getStatusCode(), $which . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody());
        }
        $this->assertSame(1, CatalogCategory::count());
    }

    public function testACategoryIsEditedAndKeepsItsProducts(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        $response = $this->cup('POST', "/cup/catalog/category/{$category->uuid}/edit", ['form_params' => ['title' => 'Mobile phones', 'address' => 'phones']]);

        $this->assertRedirectsTo($response, '/cup/catalog/category');
        $this->assertSame('Mobile phones', CatalogCategory::find($category->uuid)->title);
        $this->assertSame($category->uuid, CatalogProduct::find($product->uuid)->category_uuid);
    }

    public function testACategoryCannotBeEditedIntoAnEmptyTitle(): void
    {
        $category = $this->category();

        $response = $this->cup('POST', "/cup/catalog/category/{$category->uuid}/edit", ['form_params' => ['title' => '', 'address' => 'phones']]);

        $this->assertSame(200, $response->getStatusCode(), $this->why($response));
        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertSame('Phones', CatalogCategory::find($category->uuid)->title);
    }

    public function testACategoryCannotBePutUnderItselfOrUnderWhatIsUnderIt(): void
    {
        $top = $this->category(['is_allow_nested' => true]);
        $middle = $this->getService(CategoryService::class)->create(['title' => 'Middle', 'address' => 'middle', 'parent_uuid' => $top->uuid, 'is_allow_nested' => true]);
        $bottom = $this->getService(CategoryService::class)->create(['title' => 'Bottom', 'address' => 'bottom', 'parent_uuid' => $middle->uuid]);

        foreach ([$top->uuid, $middle->uuid, $bottom->uuid] as $parent) {
            $response = $this->cup('POST', "/cup/catalog/category/{$top->uuid}/edit", ['form_params' => ['title' => 'Phones', 'address' => 'phones', 'parent' => $parent]]);

            $this->assertSame(200, $response->getStatusCode(), $parent . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $parent);
        }

        $this->assertNull(CatalogCategory::find($top->uuid)->parent_uuid);
        // and the tree can still be walked
        $this->assertSame(3, CatalogCategory::find($top->uuid)->nested(true)->count());
    }

    public function testACategoryMayMoveToAnotherParent(): void
    {
        $one = $this->category(['title' => 'One', 'address' => 'one', 'is_allow_nested' => true]);
        $two = $this->category(['title' => 'Two', 'address' => 'two']);
        $child = $this->getService(CategoryService::class)->create(['title' => 'Child', 'address' => 'child', 'parent_uuid' => $one->uuid]);

        $this->cup('POST', "/cup/catalog/category/{$child->uuid}/edit", ['form_params' => ['title' => 'Child', 'address' => 'child', 'parent' => $two->uuid]]);

        $this->assertSame($two->uuid, CatalogCategory::find($child->uuid)->parent_uuid);
    }

    public function testDeletingACategoryRemovesItsWholeBranchAndProducts(): void
    {
        $top = $this->category(['is_allow_nested' => true]);
        $child = $this->getService(CategoryService::class)->create(['title' => 'Child', 'address' => 'child', 'parent_uuid' => $top->uuid]);
        $other = $this->category(['title' => 'Cables', 'address' => 'cables']);
        $inTop = $this->product($top);
        $inChild = $this->product($child, ['title' => 'Deep', 'address' => 'child/deep']);
        $safe = $this->product($other, ['title' => 'Wire', 'address' => 'cables/wire']);

        $response = $this->cup('POST', "/cup/catalog/category/{$top->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/catalog/category');
        foreach ([$top, $child] as $gone) {
            $this->assertSame(Status::DELETE, CatalogCategory::find($gone->uuid)->status);
        }
        foreach ([$inTop, $inChild] as $gone) {
            $this->assertSame(Status::DELETE, CatalogProduct::find($gone->uuid)->status);
        }
        $this->assertSame(Status::WORK, CatalogProduct::find($safe->uuid)->status);
        $this->assertSame(Status::WORK, CatalogCategory::find($other->uuid)->status);

        $list = (string) $this->cup('GET', '/cup/catalog/category')->getBody();
        $this->assertStringNotContainsString('Child', $list);
        $this->assertStringContainsString('Cables', $list);
        $this->assertSame(404, $this->http('GET', '/catalog/phones?format=json')->getStatusCode(), 'and the shop does not show it');
    }

    public function testDeletingAnUnknownCategoryChangesNothing(): void
    {
        $category = $this->category();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('POST', "/cup/catalog/category/{$uuid}/delete"), '/cup/catalog/category', $uuid);
        }

        $this->assertSame(Status::WORK, CatalogCategory::find($category->uuid)->status);
    }

    public function testACategoryTitleIsEscapedInTheListAndTheForms(): void
    {
        $hostile = '<script>alert("c")</script>';
        $category = $this->category(['title' => $hostile, 'address' => 'hostile']);

        foreach (['/cup/catalog/category', "/cup/catalog/category/{$category->uuid}/edit", '/cup/catalog/product/add', '/cup/catalog/product'] as $path) {
            $this->assertStringNotContainsString($hostile, (string) $this->cup('GET', $path)->getBody(), $path);
        }
    }

    // ---------------------------------------------------------------- attributes

    public function testAnAttributeIsAddedEditedAndDeleted(): void
    {
        $this->admin();

        $created = $this->cup('POST', '/cup/catalog/attribute/add', ['form_params' => ['title' => 'Color', 'address' => 'color', 'type' => 'string', 'group' => 'Look', 'is_filter' => '1']]);
        $this->assertRedirectsTo($created, '/cup/catalog/attribute');
        $attribute = CatalogAttribute::firstWhere(['title' => 'Color']);
        $this->assertSame('Look', $attribute->group);
        $this->assertTrue((bool) $attribute->is_filter);

        $this->cup('POST', "/cup/catalog/attribute/{$attribute->uuid}/edit", ['form_params' => ['title' => 'Colour', 'address' => 'color', 'type' => 'string', 'group' => 'Look']]);
        $this->assertSame('Colour', CatalogAttribute::find($attribute->uuid)->title);
        $this->assertFalse((bool) CatalogAttribute::find($attribute->uuid)->is_filter, 'an unticked box is off');

        $this->assertRedirectsTo($this->cup('POST', "/cup/catalog/attribute/{$attribute->uuid}/delete"), '/cup/catalog/attribute');
        $this->assertNull(CatalogAttribute::find($attribute->uuid));
    }

    public function testAnAttributeWithoutATitleOrWithATakenOneIsReported(): void
    {
        $this->admin();
        $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string']);

        foreach (['' => 'size', 'Color' => 'other'] as $title => $address) {
            $response = $this->cup('POST', '/cup/catalog/attribute/add', ['form_params' => ['title' => $title, 'address' => $address, 'type' => 'string']]);

            $this->assertSame(200, $response->getStatusCode(), $title . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), $title);
        }
        $this->assertSame(1, CatalogAttribute::count());
    }

    public function testEditingOrDeletingAnUnknownAttributeGoesBackToTheList(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('GET', "/cup/catalog/attribute/{$uuid}/edit"), '/cup/catalog/attribute', $uuid);
            $this->assertRedirectsTo($this->cup('POST', "/cup/catalog/attribute/{$uuid}/delete"), '/cup/catalog/attribute', $uuid);
        }
    }

    public function testADeletedAttributeLeavesTheProductsAndTheirFormsWorking(): void
    {
        $category = $this->category();
        $attribute = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string']);
        $product = $this->product($category, ['attributes' => [$attribute->uuid => 'red']]);
        $this->assertCount(1, CatalogProduct::find($product->uuid)->attributes);

        $this->cup('POST', "/cup/catalog/attribute/{$attribute->uuid}/delete");

        $this->assertCount(0, CatalogProduct::find($product->uuid)->attributes()->get(), 'nothing points at the attribute that is gone');
        $this->assertRenders($this->cup('GET', "/cup/catalog/product/{$product->uuid}/edit"));
    }

    // ------------------------------------------------------------------ products

    private function productForm(CatalogCategory $category, array $extra = []): array
    {
        return $extra + ['title' => 'Phone', 'category' => $category->uuid, 'address' => 'phones/phone', 'type' => 'product', 'price' => '99.5', 'vendorcode' => 'V-1'];
    }

    public function testAProductIsAddedToItsCategory(): void
    {
        $category = $this->category();

        $response = $this->cup('POST', '/cup/catalog/product/add', ['form_params' => $this->productForm($category, ['discount' => '5', 'stock' => '12', 'country' => 'UA', 'tags' => 'new,hit'])]);

        $this->assertRedirectsTo($response, '/cup/catalog/product/' . $category->uuid, 'back to the list of its category');
        $product = CatalogProduct::firstWhere(['title' => 'Phone']);
        $this->assertSame($category->uuid, $product->category_uuid);
        $this->assertEquals(99.5, $product->price);
        $this->assertSame('V-1', $product->vendorcode);
        $this->assertSame('UA', $product->country);
        $this->assertEquals(12, $product->stock);
    }

    public function testSaveAndContinueOpensTheNewProduct(): void
    {
        $category = $this->category();

        $response = $this->cup('POST', '/cup/catalog/product/add', ['form_params' => $this->productForm($category, ['save' => 'continue'])]);

        $this->assertRedirectsTo($response, '/cup/catalog/product/' . CatalogProduct::value('uuid') . '/edit');
    }

    public function testAProductNeedsATitleAndACategoryThatExists(): void
    {
        $category = $this->category();

        foreach ([['title' => ''], ['category' => ''], ['category' => \Ramsey\Uuid\Uuid::uuid4()->toString()]] as $case) {
            $response = $this->cup('POST', '/cup/catalog/product/add', ['form_params' => $case + $this->productForm($category)]);

            $this->assertSame(200, $response->getStatusCode(), json_encode($case) . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), json_encode($case));
        }
        $this->assertSame(0, CatalogProduct::count());
    }

    public function testTwoProductsCannotShareAnAddressInOneCategory(): void
    {
        $category = $this->category();
        $this->cup('POST', '/cup/catalog/product/add', ['form_params' => $this->productForm($category)]);

        $response = $this->cup('POST', '/cup/catalog/product/add', ['form_params' => $this->productForm($category, ['title' => 'Other'])]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('has-error', (string) $response->getBody());
        $this->assertSame(1, CatalogProduct::count());
    }

    public function testTheAttributesOfAProductAreSavedAndCleared(): void
    {
        $category = $this->category();
        $color = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string']);
        $size = $this->getService(AttributeService::class)->create(['title' => 'Size', 'address' => 'size', 'type' => 'string']);

        $this->cup('POST', '/cup/catalog/product/add', ['form_params' => $this->productForm($category, ['attributes' => [$color->uuid => 'red', $size->uuid => '']])]);
        $product = CatalogProduct::firstWhere(['title' => 'Phone']);

        $this->assertSame(['red'], $product->attributes->map(fn ($a) => $a->pivot->value)->all(), 'an empty value is not stored');

        $this->cup('POST', "/cup/catalog/product/{$product->uuid}/edit", ['form_params' => $this->productForm($category, ['attributes' => [$color->uuid => '', $size->uuid => 'XL']])]);
        $this->assertSame(['XL'], CatalogProduct::find($product->uuid)->attributes->map(fn ($a) => $a->pivot->value)->all());
    }

    public function testAProductIsEditedAndMovedToAnotherCategory(): void
    {
        $phones = $this->category();
        $cables = $this->category(['title' => 'Cables', 'address' => 'cables']);
        $product = $this->product($phones);

        $response = $this->cup('POST', "/cup/catalog/product/{$product->uuid}/edit", ['form_params' => $this->productForm($cables, ['title' => 'Cable', 'price' => '5', 'address' => 'cables/cable'])]);

        $this->assertRedirectsTo($response, '/cup/catalog/product');
        $fresh = CatalogProduct::find($product->uuid);
        $this->assertSame('Cable', $fresh->title);
        $this->assertSame($cables->uuid, $fresh->category_uuid);
        $this->assertEquals(5, $fresh->price);
    }

    public function testAProductCannotBeEditedIntoOneWithoutATitleOrACategory(): void
    {
        $category = $this->category();
        $product = $this->product($category);

        foreach ([['title' => ''], ['category' => ''], ['category' => \Ramsey\Uuid\Uuid::uuid4()->toString()]] as $case) {
            $response = $this->cup('POST', "/cup/catalog/product/{$product->uuid}/edit", ['form_params' => $case + $this->productForm($category)]);

            $this->assertSame(200, $response->getStatusCode(), json_encode($case) . ' ' . $this->why($response));
            $this->assertStringContainsString('has-error', (string) $response->getBody(), json_encode($case));
        }

        $fresh = CatalogProduct::find($product->uuid);
        $this->assertSame('Phone', $fresh->title);
        $this->assertSame($category->uuid, $fresh->category_uuid);
    }

    public function testTheEditFormOfAProductShowsIt(): void
    {
        $category = $this->category();
        $product = $this->product($category, ['vendorcode' => 'SKU-777', 'price' => 123.45]);

        $body = (string) $this->cup('GET', "/cup/catalog/product/{$product->uuid}/edit")->getBody();

        $this->assertStringContainsString('SKU-777', $body);
        $this->assertStringContainsString('123.45', $body);
    }

    public function testDeletingAProductHidesItFromThePanelAndTheShop(): void
    {
        $category = $this->category();
        $product = $this->product($category);
        $this->assertSame(200, $this->http('GET', '/catalog/phones/phone?format=json')->getStatusCode());

        $response = $this->cup('POST', "/cup/catalog/product/{$product->uuid}/delete");

        $this->assertRedirectsTo($response, '/cup/catalog/product/' . $category->uuid, 'back to the category of the product');
        $this->assertSame(Status::DELETE, CatalogProduct::find($product->uuid)->status);
        $this->assertStringNotContainsString('SKU', (string) $this->cup('GET', '/cup/catalog/product')->getBody());
        $this->assertSame(404, $this->http('GET', '/catalog/phones/phone?format=json')->getStatusCode());
    }

    public function testEditingOrDeletingAnUnknownProductGoesBackToTheList(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('GET', "/cup/catalog/product/{$uuid}/edit"), '/cup/catalog/product', $uuid);
            $this->assertRedirectsTo($this->cup('POST', "/cup/catalog/product/{$uuid}/delete"), '/cup/catalog/product', $uuid);
        }
    }

    public function testTheProductListCanBeLimitedToACategory(): void
    {
        $phones = $this->category();
        $cables = $this->category(['title' => 'Cables', 'address' => 'cables']);
        $this->product($phones, ['title' => 'Handset']);
        $this->product($cables, ['title' => 'Wire', 'address' => 'cables/wire']);

        $one = (string) $this->cup('GET', "/cup/catalog/product/{$phones->uuid}")->getBody();
        $all = (string) $this->cup('GET', '/cup/catalog/product')->getBody();

        $this->assertStringContainsString('Handset', $one);
        $this->assertStringNotContainsString('Wire', $one);
        $this->assertStringContainsString('Handset', $all);
        $this->assertStringContainsString('Wire', $all);
    }

    public function testTheProductListOfAnUnknownCategoryGoesBackToTheList(): void
    {
        $this->admin();

        foreach ([\Ramsey\Uuid\Uuid::uuid4()->toString(), 'not-a-uuid'] as $uuid) {
            $this->assertRedirectsTo($this->cup('GET', "/cup/catalog/product/{$uuid}"), '/cup/catalog/product', $uuid);
        }
    }

    public function testTheTitleOfAProductIsEscaped(): void
    {
        $hostile = '<img src=x onerror=alert(1)>';
        $category = $this->category();
        $product = $this->product($category, ['title' => $hostile]);

        foreach (['/cup/catalog/product', "/cup/catalog/product/{$product->uuid}/edit", '/cup/catalog/statistic'] as $path) {
            $this->assertStringNotContainsString($hostile, (string) $this->cup('GET', $path)->getBody(), $path);
        }
    }
}

<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductAttributeService;
use App\Domain\Service\Catalog\ProductService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class AttributeTest extends TestCase
{
    protected AttributeService $attributeService;

    protected CategoryService $categoryService;

    protected ProductService $productService;

    protected ProductAttributeService $productAttributeService;

    public function setUp(): void
    {
        parent::setUp();

        $this->attributeService = $this->getService(AttributeService::class);
        $this->categoryService = $this->getService(CategoryService::class);
        $this->productService = $this->getService(ProductService::class);
    }

    public function testCategoryWithAttribute(): void
    {
        // create test attributes
        $attr1 = $this->attributeService->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
        ]);
        $attr2 = $this->attributeService->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
        ]);

        // create test category
        $category = $this->categoryService->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(100),
        ]);
        $this->assertInstanceOf(CatalogCategory::class, $category);

        // set attributes to category
        $category = $this->categoryService->update($category, [
            'attributes' => [$attr1->uuid, $attr2->uuid],
        ]);

        // read again category and check
        $category = $this->categoryService->read(['uuid' => $category->uuid]);
        $this->assertInstanceOf(CatalogCategory::class, $category);

        $this->assertCount(2, $category->attributes);
        $this->assertEquals(true, $category->attributes->contains($attr1));
        $this->assertEquals(true, $category->attributes->contains($attr2));

        // keep one attribute
        $category = $this->categoryService->update($category, [
            'attributes' => [$attr1->uuid],
        ]);

        // read again and check
        $category = $this->categoryService->read(['uuid' => $category->uuid]);
        $this->assertInstanceOf(CatalogCategory::class, $category);
        $this->assertCount(1, $category->attributes);
        $this->assertEquals(true, $category->attributes->contains($attr1));

        // remove all attributes from category
        $category = $this->categoryService->update($category, [
            'attributes' => [],
        ]);

        // read again and check
        $category = $this->categoryService->read(['uuid' => $category->uuid]);
        $this->assertInstanceOf(CatalogCategory::class, $category);
        $this->assertCount(0, $category->attributes);
    }

    public function testProductWithAttribute(): void
    {
        // create test attributes
        $attr1 = $this->attributeService->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
        ]);
        $attr2 = $this->attributeService->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'type' => $this->getFaker()->randomElement(\App\Domain\Casts\Catalog\Attribute\Type::LIST),
        ]);

        // create test category
        $category = $this->categoryService->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(100),
        ]);
        $this->assertInstanceOf(CatalogCategory::class, $category);

        // create test product
        $product = $this->productService->create([
            'category_uuid' => $category->uuid,
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'description' => $this->getFaker()->text(100),

            // set attributes to product
            'attributes' => [
                $attr1->uuid => $this->getFaker()->word,
                $attr2->uuid => $this->getFaker()->word,
            ],
        ]);
        $this->assertInstanceOf(CatalogProduct::class, $product);
        $this->assertEquals(true, $product->attributes->contains($attr1));
        $this->assertEquals(true, $product->attributes->contains($attr2));

        // remove all attributes from product
        $product = $this->productService->update($product, [
            'attributes' => [],
        ]);

        // read again and check
        $product = $this->productService->read(['uuid' => $product->uuid]);
        $this->assertInstanceOf(CatalogProduct::class, $product);
        $this->assertCount(0, $product->attributes);
    }
}

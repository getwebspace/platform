<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Category;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductAttributeService;
use App\Domain\Service\Catalog\ProductService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
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
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);
        $attr2 = $this->attributeService->create([
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);

        // create test category
        $category = $this->categoryService->create([
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text(100),
        ]);
        $this->assertInstanceOf(Category::class, $category);

        // set attributes to category
        $category = $category->setAttributes($this->attributeService->read()->all());
        $this->categoryService->write($category);

        // read again category and check
        $category = $this->categoryService->read(['uuid' => $category->getUuid()]);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertCount(2, $category->getAttributes());
        $this->assertContains($attr1, $category->getAttributes());
        $this->assertContains($attr2, $category->getAttributes());

        // keep one attribute
        $category = $category->setAttributes([$attr1]);
        $this->categoryService->write($category);

        // read again and check
        $category = $this->categoryService->read(['uuid' => $category->getUuid()]);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertCount(1, $category->getAttributes());
        $this->assertContains($attr1, $category->getAttributes());

        // remove all attributes from category
        $category = $category->setAttributes();
        $this->categoryService->write($category);

        // read again and check
        $category = $this->categoryService->read(['uuid' => $category->getUuid()]);
        $this->assertInstanceOf(Category::class, $category);
        $this->assertCount(0, $category->getAttributes());
    }

    public function testProductWithAttribute(): void
    {
        // create test attributes
        $attr1 = $this->attributeService->create([
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);
        $attr2 = $this->attributeService->create([
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);

        // create test product
        $product = $this->productService->create([
            'title' => $this->getFaker()->word,
            'description' => $this->getFaker()->text(100),

            // set attributes to product
            'attributes' => [
                $attr1->getUuid()->toString() => $this->getFaker()->word,
                $attr2->getUuid()->toString() => $this->getFaker()->word,
            ],
        ]);
        $this->assertInstanceOf(Product::class, $product);

        // todo add few asserts
    }
}

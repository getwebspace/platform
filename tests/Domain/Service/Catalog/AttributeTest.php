<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Category;
use App\Domain\Entities\Catalog\Product;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\ProductAttributeService;
use App\Domain\Service\Catalog\ProductService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

class AttributeTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var AttributeService
     */
    protected $attributeService;

    /**
     * @var CategoryService
     */
    protected $categoryService;

    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * @var ProductAttributeService
     */
    protected $productAttributeService;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->attributeService = AttributeService::getWithEntityManager($this->em);
        $this->categoryService = CategoryService::getWithEntityManager($this->em);
        $this->productService = ProductService::getWithEntityManager($this->em);
        $this->productAttributeService = ProductAttributeService::getWithEntityManager($this->em);
    }

    public function testCategoryWithAttribute(): void
    {
        // create test attributes
        $attr1 = $this->attributeService->create([
            'title' => $this->getFaker()->word,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);
        $attr2 = $this->attributeService->create([
            'title' => $this->getFaker()->title,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);

        // create test category
        $category = $this->categoryService->create([
            'title' => $this->getFaker()->title,
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
            'title' => $this->getFaker()->title,
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\AttributeTypeType::LIST),
        ]);

        // create test product
        $product = $this->productService->create([
            'title' => $this->getFaker()->title,
            'description' => $this->getFaker()->text(100),
        ]);
        $this->assertInstanceOf(Product::class, $product);

        // set attributes to product
        $this->productAttributeService->proccess([
            $attr1->getUuid()->toString() => $this->getFaker()->word,
            $attr2->getUuid()->toString() => $this->getFaker()->word,
        ], $product);

        // to do add few asserts
    }
}

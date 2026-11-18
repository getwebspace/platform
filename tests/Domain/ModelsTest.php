<?php declare(strict_types=1);

namespace tests\Domain;

use App\Domain\Models\CatalogAttribute;
use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\File;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use App\Domain\Models\PublicationCategory;
use App\Domain\Models\Reference;
use App\Domain\Models\Review;
use App\Domain\Models\User;
use App\Domain\Service\Catalog\AttributeService;
use App\Domain\Service\Catalog\CategoryService;
use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Catalog\ProductService;
use App\Domain\Service\Page\PageService;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\PublicationService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Service\Review\ReviewService;
use App\Domain\Service\User\UserService;
use PHPUnit\Framework\Attributes\DataProvider;
use tests\TestCase;

/**
 * The arithmetic and the shape of the records: prices, taxes, attributes, trees, names, what they turn into when sent out
 *
 * @internal
 *
 * #[CoversNothing]
 */
class ModelsTest extends TestCase
{
    private function category(string $title = 'Phones', string $address = 'phones', array $extra = []): CatalogCategory
    {
        return $this->getService(CategoryService::class)->create($extra + ['title' => $title, 'address' => $address]);
    }

    private function product(array $extra = [], ?CatalogCategory $category = null): CatalogProduct
    {
        static $n = 0;
        ++$n;
        $category ??= $this->category('Cat ' . $n, 'cat-' . $n);

        return $this->getService(ProductService::class)->create($extra + [
            'title' => 'Product ' . $n, 'address' => $category->address . '/product-' . $n, 'category_uuid' => $category->uuid, 'type' => 'product', 'price' => 100,
        ]);
    }

    private function order(array $lines, array $extra = []): CatalogOrder
    {
        $products = [];
        foreach ($lines as [$product, $count]) {
            $products[$product->uuid] = ['count' => $count, 'price_type' => 'price'];
        }

        return $this->getService(OrderService::class)->create($extra + ['email' => 'a@example.com', 'phone' => '+380501234567', 'delivery' => ['client' => 'Ann', 'address' => 'x'], 'products' => $products]);
    }

    // ----------------------------------------------------------- product prices

    public static function priceCases(): array
    {
        return [
            'a plain price' => [['price' => 100], 'price', 100.0],
            'a discount comes off' => [['price' => 100, 'discount' => 10], 'price', 90.0],
            'a tax is added' => [['price' => 100, 'tax' => 20], 'price', 120.0],
            'a tax that is in the price is not added' => [['price' => 100, 'tax' => 20, 'tax_included' => true], 'price', 100.0],
            'the discount first, then the tax' => [['price' => 100, 'discount' => 10, 'tax' => 20], 'price', 108.0],
            'a discount above the price is nothing, not a negative price' => [['price' => 5, 'discount' => 10], 'price', 0.0],
            'a negative stored discount works as a discount' => [['price' => 100, 'discount' => -10], 'price', 90.0],
            'the wholesale price' => [['price' => 100, 'priceWholesale' => 70, 'tax' => 10], 'price_wholesale', 77.0],
            'the first price' => [['price' => 100, 'priceFirst' => 150], 'price_first', 150.0],
        ];
    }

    #[DataProvider('priceCases')]
    public function testThePriceOfAProduct(array $fields, string $type, float $expected): void
    {
        $product = $this->product($fields);

        $this->assertEqualsWithDelta($expected, $product->price($type), 0.0001);
    }

    public function testThePriceIsRoundedToTheAskedPrecision(): void
    {
        $product = $this->product(['price' => 99.456]);

        $this->assertEquals(99.0, $product->price('price'), 'whole units by default');
        $this->assertEquals(99.5, $product->price('price', 1));
        $this->assertEquals(99.46, $product->price('price', 2));
    }

    public function testAnUnknownKindOfPriceIsAnError(): void
    {
        $this->expectException(\UnhandledMatchError::class);

        $this->product()->price('retail');
    }

    public static function taxCases(): array
    {
        return [
            'no tax' => [['price' => 100], 0.0],
            'a tax on top of the price' => [['price' => 100, 'tax' => 20], 20.0],
            'a tax inside the price' => [['price' => 120, 'tax' => 20, 'tax_included' => true], 20.0],
            'the tax of the price after the discount' => [['price' => 100, 'discount' => 10, 'tax' => 20], 18.0],
            'inside, after the discount' => [['price' => 100, 'discount' => 10, 'tax' => 20, 'tax_included' => true], 15.0],
            'nothing to tax' => [['price' => 5, 'discount' => 10, 'tax' => 20], 0.0],
        ];
    }

    #[DataProvider('taxCases')]
    public function testTheTaxOfAProduct(array $fields, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, $this->product($fields)->tax('price'), 0.0001);
    }

    // -------------------------------------------------------------- order lines

    public function testTheLinesOfAnOrderAreCountedAsTheShopCountsThem(): void
    {
        $phone = $this->product(['price' => 100, 'discount' => 10, 'tax' => 20]);
        $cable = $this->product(['price' => 5]);
        $order = CatalogOrder::find($this->order([[$phone, 3], [$cable, 2]])->uuid);

        $line = $order->products->firstWhere('uuid', $phone->uuid);
        $this->assertEqualsWithDelta(108.0, $line->totalPrice(), 0.0001, 'one piece: 100 - 10, plus 20%');
        $this->assertEquals(3.0, $line->totalCount());
        $this->assertEqualsWithDelta(324.0, $line->totalSum(), 0.0001);
        $this->assertEqualsWithDelta(-30.0, $line->totalDiscount(), 0.0001, 'a discount is a minus');
        $this->assertEqualsWithDelta(54.0, $line->totalTax(), 0.0001, '18 for each of three');

        $this->assertEqualsWithDelta(334.0, $order->totalSum(), 0.0001);
        $this->assertEqualsWithDelta(-30.0, $order->totalDiscount(), 0.0001);
        $this->assertEqualsWithDelta(54.0, $order->totalTax(), 0.0001);
    }

    public function testTheTaxOfALineCountsOnlyWhatIsNotInThePrice(): void
    {
        $product = $this->product(['price' => 120, 'tax' => 20, 'tax_included' => true]);
        $line = CatalogOrder::find($this->order([[$product, 2]])->uuid)->products->first();

        $this->assertEqualsWithDelta(120.0, $line->totalPrice(), 0.0001);
        $this->assertEqualsWithDelta(240.0, $line->totalSum(), 0.0001);
        $this->assertEqualsWithDelta(40.0, $line->totalTax(), 0.0001, '20 inside each of two');
    }

    public function testAnOrderKeepsThePriceOfTheDayItWasMade(): void
    {
        $product = $this->product(['price' => 100]);
        $order = $this->order([[$product, 1]]);

        $this->getService(ProductService::class)->update($product, ['price' => 999]);

        $this->assertEqualsWithDelta(100.0, CatalogOrder::find($order->uuid)->totalSum(), 0.0001);
    }

    public function testTheOrderGoesOutWithItsLinesAndTotals(): void
    {
        $status = $this->getService(ReferenceService::class)->create(['title' => 'New', 'type' => 'order_status', 'value' => ['color' => 'blue']]);
        $phone = $this->product(['price' => 100, 'title' => 'Phone']);
        $order = CatalogOrder::find($this->order([[$phone, 2]], ['status_uuid' => $status->uuid])->uuid);

        $array = $order->toArray();

        $this->assertSame('New', $array['status']['title']);
        $this->assertNull($array['payment']);
        $line = $array['products'][$phone->uuid];
        $this->assertSame('Phone', $line['title']);
        $this->assertEquals(100.0, $line['price']);
        $this->assertEquals(2.0, $line['count']);
        $this->assertEquals(200.0, $line['amount']);
        $this->assertEquals(100.0, $line['raw']['price']);
        $this->assertEquals(200.0, $array['calculated']['total']);
    }

    public function testTheLowestPriceOfTheLastDaysIsTheLowestLineOfTheOrders(): void
    {
        $product = $this->product(['price' => 100]);
        $this->order([[$product, 1]]);
        $this->getService(ProductService::class)->update($product, ['price' => 120, 'discount' => 30]);
        $this->order([[$product, 1]]);
        $this->getService(ProductService::class)->update($product, ['price' => 150, 'discount' => 0]);
        $cheap = $this->order([[$product, 1]]);
        CatalogOrder::where('uuid', $cheap->uuid)->update(['date' => date('Y-m-d H:i:s', strtotime('-60 days'))]);
        $this->getService(ProductService::class)->update($product, ['price' => 10]);
        $old = $this->order([[$product, 1]]);
        CatalogOrder::where('uuid', $old->uuid)->update(['date' => date('Y-m-d H:i:s', strtotime('-60 days'))]);

        $lowest = CatalogProduct::find($product->uuid)->price_lowest();

        $this->assertEquals(90.0, $lowest, '120 - 30 is under 100; the 10 of 60 days ago does not count');
        $this->assertEquals(10.0, CatalogProduct::find($product->uuid)->price_lowest(90));
    }

    public function testTheLowestPriceCountsTheTaxThatIsNotInThePrice(): void
    {
        $product = $this->product(['price' => 100, 'discount' => 10, 'tax' => 20]);
        $this->order([[$product, 1]]);

        $this->assertEquals(108.0, CatalogProduct::find($product->uuid)->price_lowest());
    }

    public function testThereIsNoLowestPriceWithoutOrders(): void
    {
        $this->assertNull($this->product()->price_lowest());
    }

    // -------------------------------------------------------------- the product

    public function testTheSizeAndWeightOfAProduct(): void
    {
        $product = $this->product(['dimension' => ['length' => 50, 'width' => 40, 'height' => 25, 'length_class' => 'cm', 'weight' => '1.5', 'weight_class' => 'kg']]);

        $this->assertSame('50×40×25', $product->specification());
        $this->assertSame('50×40×25 cm', $product->specificationWithClass());
        $this->assertSame(1.5, $product->weight());
        $this->assertSame('1.5 kg', $product->weightWithClass());

        $bare = $this->product();
        $this->assertSame('0×0×0', $bare->specification());
        $this->assertSame(0.0, $bare->weight());
        $this->assertSame('0', $bare->weightWithClass());
    }

    public function testTheAttributesOfAProductAreTypedValues(): void
    {
        $attributes = $this->getService(AttributeService::class);
        $make = fn (string $address, string $type) => $attributes->create(['title' => ucfirst($address), 'address' => $address, 'type' => $type]);
        $values = [
            $make('color', 'string')->uuid => 'red',
            $make('stock', 'integer')->uuid => '12',
            $make('weight', 'float')->uuid => '1.25',
            $make('wireless', 'boolean')->uuid => 'yes',
            $make('wired', 'boolean')->uuid => 'no',
            $make('warranty', 'integer')->uuid => '0',
            $make('ratio', 'float')->uuid => '0.0',
        ];
        $product = CatalogProduct::find($this->product(['attributes' => $values])->uuid);

        $this->assertSame('red', $product->attribute('color'));
        $this->assertSame(12, $product->attribute('stock'));
        $this->assertSame(1.25, $product->attribute('weight'));
        $this->assertTrue($product->attribute('wireless'));
        $this->assertFalse($product->attribute('wired'));
        $this->assertSame(0, $product->attribute('warranty'), 'zero is a value');
        $this->assertSame(0.0, $product->attribute('ratio'));
        $this->assertNull($product->attribute('absent'));
        $this->assertSame('fallback', $product->attribute('absent', 'fallback'));
    }

    public function testAnAttributeWithoutAProductHasNoValueAndAProductsOneGoesOut(): void
    {
        $attribute = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string']);
        $this->assertNull($attribute->value());
        $this->assertNull($attribute->toArray()['value']);

        $product = CatalogProduct::find($this->product(['attributes' => [$attribute->uuid => 'red']])->uuid);
        $array = $product->toArray();

        $this->assertSame('red', $array['attributes']['color']->value());
        $this->assertSame('Color', $array['attributes']['color']->title);
    }

    public function testTheValuesOfAnAttributeAreCountedAndCanBeLimitedToACategory(): void
    {
        $attribute = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string']);
        $top = $this->category('Top', 'top', ['is_allow_nested' => true]);
        $child = $this->category('Child', 'child', ['parent_uuid' => $top->uuid]);
        $other = $this->category('Other', 'other');
        $this->product(['attributes' => [$attribute->uuid => 'red']], $top);
        $this->product(['attributes' => [$attribute->uuid => 'red']], $child);
        $this->product(['attributes' => [$attribute->uuid => 'blue']], $child);
        $this->product(['attributes' => [$attribute->uuid => 'green']], $other);
        $this->product(['attributes' => [$attribute->uuid => '']], $other);

        $this->assertSame(['blue' => 1, 'green' => 1, 'red' => 2], $attribute->values()->sortKeys()->all());
        $this->assertSame(['blue' => 1, 'red' => 2], $attribute->values($top)->sortKeys()->all(), 'the branch of the category, not the others');
        $this->assertSame(['green' => 1], $attribute->values($other)->all());
        $this->assertCount(4, CatalogAttribute::find($attribute->uuid)->products);
    }

    public function testRelatedProductsThatPointAtEachOtherDoNotRunAway(): void
    {
        $category = $this->category();
        $a = $this->product(['title' => 'A'], $category);
        $b = $this->product(['title' => 'B'], $category);
        $this->getService(ProductService::class)->update($a, ['relations' => [$b->uuid => 1]]);
        $this->getService(ProductService::class)->update($b, ['relations' => [$a->uuid => 1]]);

        $array = CatalogProduct::find($a->uuid)->toArray();

        $this->assertSame(['B'], array_column($array['relations']->all(), 'title'));
        $this->assertCount(0, $array['relations'][$b->uuid]['relations'], 'one level, the way back is not followed');
    }

    public function testAProductGoesOutWithItsCategoryAndCalculatedPrices(): void
    {
        $category = $this->category('Phones', 'phones');
        $product = CatalogProduct::find($this->product(['price' => 100, 'priceWholesale' => 80, 'tax' => 10], $category)->uuid);

        $array = $product->toArray();

        $this->assertSame(['uuid' => $category->uuid, 'title' => 'Phones', 'address' => 'phones'], $array['category']);
        $this->assertEquals(110.0, $array['calculated']['price']);
        $this->assertEquals(88.0, $array['calculated']['wholesale'] ?? $array['calculated']['price_wholesale']);
    }

    public function testTheReviewsAndQuestionsOfAProductAreTopLevelAndOfItsOwnKind(): void
    {
        $product = $this->product();
        $reviews = $this->getService(ReviewService::class);
        $make = fn (string $type, string $entity, string $uuid, ?string $parent = null) => $reviews->create([
            'type' => $type, 'entity_type' => $entity, 'entity_uuid' => $uuid, 'message' => $type . ' ' . uniqid(), 'parent_uuid' => $parent,
        ]);
        $review = $make('review', 'catalog_product', $product->uuid);
        $make('review', 'catalog_product', $product->uuid, $review->uuid);
        $question = $make('question', 'catalog_product', $product->uuid);
        $make('review', 'publication', $product->uuid);
        $make('review', 'catalog_product', \Ramsey\Uuid\Uuid::uuid4()->toString());

        $fresh = CatalogProduct::find($product->uuid);

        $this->assertSame([$review->uuid], $fresh->reviews->pluck('uuid')->all());
        $this->assertSame([$question->uuid], $fresh->questions->pluck('uuid')->all());
    }

    // ---------------------------------------------------------------- categories

    public function testACategoryKnowsItsBranchAndItsAncestors(): void
    {
        $top = $this->category('Top', 'top', ['is_allow_nested' => true]);
        $middle = $this->category('Middle', 'middle', ['parent_uuid' => $top->uuid, 'is_allow_nested' => true]);
        $bottom = $this->category('Bottom', 'bottom', ['parent_uuid' => $middle->uuid]);
        $closed = $this->category('Closed', 'closed', ['parent_uuid' => $top->uuid]);
        $this->category('Hidden child', 'child-of-closed', ['parent_uuid' => $closed->uuid]);

        $this->assertEqualsCanonicalizing([$top->uuid, $middle->uuid, $bottom->uuid, $closed->uuid], CatalogCategory::find($top->uuid)->nested()->pluck('uuid')->all());
        $this->assertSame([$closed->uuid], CatalogCategory::find($closed->uuid)->nested()->pluck('uuid')->all(), 'a category that takes no nested ones stops there');
        $this->assertCount(5, CatalogCategory::find($top->uuid)->nested(true), 'unless it is asked to go all the way');
        $this->assertEqualsCanonicalizing([$bottom->uuid, $middle->uuid, $top->uuid], CatalogCategory::find($bottom->uuid)->parents()->pluck('uuid')->all());
    }

    public function testACategoryServesItsWorkingProductsAndItsFilters(): void
    {
        $category = $this->category();
        $live = $this->product(['title' => 'Live'], $category);
        $gone = $this->product(['title' => 'Gone', 'status' => 'delete'], $category);
        $filter = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string', 'is_filter' => true]);
        $plain = $this->getService(AttributeService::class)->create(['title' => 'Note', 'address' => 'note', 'type' => 'string', 'is_filter' => false]);
        $this->getService(CategoryService::class)->update($category, ['attributes' => [$filter->uuid, $plain->uuid]]);

        $fresh = CatalogCategory::find($category->uuid);

        $this->assertSame([$live->uuid], $fresh->products->pluck('uuid')->all());
        $this->assertNotContains($gone->uuid, $fresh->products->pluck('uuid')->all());
        $this->assertSame(['color'], $fresh->filtres->pluck('address')->all());
    }

    public function testACategoryGoesOutWithItsParentAndAttributes(): void
    {
        $top = $this->category('Top', 'top', ['is_allow_nested' => true]);
        $child = $this->category('Child', 'child', ['parent_uuid' => $top->uuid]);
        $attribute = $this->getService(AttributeService::class)->create(['title' => 'Color', 'address' => 'color', 'type' => 'string']);
        $this->getService(CategoryService::class)->update($child, ['attributes' => [$attribute->uuid]]);

        $array = CatalogCategory::find($child->uuid)->toArray();

        $this->assertSame(['title' => 'Top', 'address' => 'top'], $array['parent']);
        $this->assertSame(['color'], $array['attributes']->keys()->all());
        $this->assertNull(CatalogCategory::find($top->uuid)->toArray()['parent']);
    }

    public function testAPublicationCategoryKnowsItsAncestorsAndGoesOutWithItsParent(): void
    {
        $service = $this->getService(PublicationCategoryService::class);
        $top = $service->create(['title' => 'Top', 'address' => 'top']);
        $child = $service->create(['title' => 'Child', 'address' => 'child', 'parent_uuid' => $top->uuid]);

        $fresh = PublicationCategory::find($child->uuid);

        $this->assertSame([$child->uuid, $top->uuid], $fresh->parents()->pluck('uuid')->all());
        $this->assertSame(['title' => 'Top', 'address' => 'top'], $fresh->toArray()['parent']);
        $this->assertNull(PublicationCategory::find($top->uuid)->toArray()['parent']);
    }

    // -------------------------------------------------------------- publications

    public function testAPublicationGoesOutWithItsCategoryAndAuthor(): void
    {
        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Blog', 'address' => 'blog']);
        $author = $this->getService(UserService::class)->create(['username' => 'writer', 'firstname' => 'Wanda', 'lastname' => 'Writer']);
        $publication = $this->getService(PublicationService::class)->create([
            'title' => 'Post', 'address' => 'blog/post', 'category_uuid' => $category->uuid, 'user_uuid' => $author->uuid, 'content' => ['short' => 's', 'full' => 'f'],
        ]);

        $array = Publication::find($publication->uuid)->toArray();

        $this->assertSame(['uuid' => $category->uuid, 'title' => 'Blog', 'address' => 'blog'], $array['category']);
        $this->assertSame($author->uuid, $array['user']['uuid']);
        $this->assertSame('Writer Wanda', $array['user']['name']);
        $this->assertStringContainsString('ui-avatars.com', $array['user']['avatar']);
    }

    public function testAPublicationWithoutAnAuthorGoesOutWithoutOne(): void
    {
        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Blog', 'address' => 'blog']);
        $publication = $this->getService(PublicationService::class)->create(['title' => 'Post', 'address' => 'blog/post', 'category_uuid' => $category->uuid, 'content' => ['short' => 's', 'full' => 'f']]);

        $this->assertNull(Publication::find($publication->uuid)->toArray()['user']);
    }

    public function testTheReviewsOfAPublicationAreTopLevelOnes(): void
    {
        $category = $this->getService(PublicationCategoryService::class)->create(['title' => 'Blog', 'address' => 'blog']);
        $publication = $this->getService(PublicationService::class)->create(['title' => 'Post', 'address' => 'blog/post', 'category_uuid' => $category->uuid, 'content' => ['short' => 's', 'full' => 'f']]);
        $reviews = $this->getService(ReviewService::class);
        $top = $reviews->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $publication->uuid, 'message' => 'Good']);
        $reviews->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $publication->uuid, 'message' => 'Thanks', 'parent_uuid' => $top->uuid]);
        $reviews->create(['type' => 'question', 'entity_type' => 'publication', 'entity_uuid' => $publication->uuid, 'message' => 'Why?']);

        $this->assertSame([$top->uuid], Publication::find($publication->uuid)->reviews->pluck('uuid')->all());
    }

    // ------------------------------------------------------------------- reviews

    public function testAReviewGoesOutWithItsPublishedAnswersOnly(): void
    {
        $page = $this->getService(PageService::class)->create(['title' => 'Subject', 'address' => 'subject', 'content' => 'x']);
        $author = $this->getService(UserService::class)->create(['username' => 'ann', 'firstname' => 'Ann']);
        $reviews = $this->getService(ReviewService::class);
        $review = $reviews->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $page->uuid, 'message' => 'Good', 'user_uuid' => $author->uuid]);
        $published = $reviews->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $page->uuid, 'message' => 'Answer', 'parent_uuid' => $review->uuid, 'status' => 'work']);
        $reviews->create(['type' => 'review', 'entity_type' => 'publication', 'entity_uuid' => $page->uuid, 'message' => 'Draft answer', 'parent_uuid' => $review->uuid, 'status' => 'moderate']);

        $fresh = Review::find($review->uuid);
        $array = $fresh->toArray();

        $this->assertSame($author->uuid, $array['user']['uuid']);
        $this->assertSame([$published->uuid], $array['children']->pluck('uuid')->all());
        $this->assertCount(2, $fresh->children, 'both are there, only the published one is shown');
        $this->assertSame($review->uuid, Review::find($published->uuid)->parent->uuid);
        $this->assertNull($array['children'][0]['user'] ?? null);
    }

    // ------------------------------------------------------------ small records

    public function testAReferenceValueIsReadWholeOrByKey(): void
    {
        $reference = Reference::find($this->getService(ReferenceService::class)->create(['title' => 'USD', 'type' => 'currency', 'value' => ['code' => 'USD', 'rate' => 41.5]])->uuid);

        $this->assertSame(['code' => 'USD', 'rate' => 41.5], $reference->value());
        $this->assertSame('USD', $reference->value('code'));
        $this->assertNull($reference->value('missing'));
        $this->assertSame('none', $reference->value('missing', 'none'));
    }

    public function testAPageGoesOutWithItsFiles(): void
    {
        $page = $this->getService(PageService::class)->create(['title' => 'About', 'address' => 'about', 'content' => 'x']);
        $file = File::create(['name' => 'doc', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => 'abc123', 'hash' => sha1('page-file')]);
        $page->files()->sync([$file->uuid => ['comment' => 'The terms', 'order' => 3]]);

        $files = Page::find($page->uuid)->toArray()['files'];

        $this->assertCount(1, $files);
        $this->assertSame('The terms', $files[0]->comment());
        $this->assertSame(3, $files[0]->order());
    }

    // -------------------------------------------------------------------- users

    public static function nameCases(): array
    {
        $all = ['lastname' => 'Smith', 'firstname' => 'John', 'patronymic' => 'Adam'];

        return [
            'the full name' => [$all, 'full', 'Smith John Adam'],
            'without the patronymic' => [$all, 'name', 'Smith John'],
            'initials' => [$all, 'initials', 'S. A. John'],
            'short' => [$all, 'short', 'S. John'],
            'only a first name' => [['firstname' => 'John'], 'full', 'John'],
            'only a first name, initials' => [['firstname' => 'John'], 'initials', 'John'],
            'only a first name, short' => [['firstname' => 'John'], 'short', 'John'],
            'only a last name' => [['lastname' => 'Smith'], 'initials', 'S.'],
            'an unknown kind of name is the login' => [$all + ['username' => 'jsmith'], 'nickname', 'jsmith'],
            'no name, the login' => [['username' => 'jsmith'], 'full', 'jsmith'],
            'no name, no login: the part of the e-mail before the @' => [['email' => 'john.smith@example.com'], 'full', 'john.smith'],
            'nothing at all' => [['phone' => '+380501234567'], 'full', ''],
        ];
    }

    #[DataProvider('nameCases')]
    public function testTheNameOfAUser(array $fields, string $type, string $expected): void
    {
        $user = new User($fields);

        $this->assertSame($expected, $user->name($type));
    }

    public function testTheAvatarIsTheFirstPictureOrAGeneratedOne(): void
    {
        $user = $this->getService(UserService::class)->create(['username' => 'ann', 'firstname' => 'Ann', 'lastname' => 'Lee']);

        $generated = $user->avatar(32, 'ffffff', '000000');
        $this->assertStringStartsWith('https://ui-avatars.com/api/?', $generated);
        parse_str(parse_url($generated, PHP_URL_QUERY), $query);
        $this->assertSame(['name' => 'Lee Ann', 'size' => '32', 'background' => 'ffffff', 'color' => '000000'], $query);

        $picture = File::create(['name' => 'me', 'ext' => 'jpg', 'type' => 'image/jpeg', 'size' => 1, 'salt' => 'pic123', 'hash' => sha1('avatar')]);
        $user->files()->sync([$picture->uuid => ['comment' => '', 'order' => 1]]);

        $this->assertSame('/uploads/pic123/me.jpg', User::find($user->uuid)->avatar());
    }

    public function testAUserGoesOutWithHisNameAndGroupButNeverHisPassword(): void
    {
        $group = $this->getService(\App\Domain\Service\User\GroupService::class)->create(['title' => 'Staff', 'access' => []]);
        $user = $this->getService(UserService::class)->create(['username' => 'ann', 'firstname' => 'Ann', 'lastname' => 'Lee', 'password' => 'secret', 'group_uuid' => $group->uuid]);

        $array = User::find($user->uuid)->toArray();

        $this->assertSame(['full' => 'Lee Ann', 'short' => 'L. Ann'], $array['name']);
        $this->assertSame('Staff', $array['group']['title']);
        $this->assertArrayNotHasKey('password', $array);
        $this->assertStringNotContainsString($user->getAttributes()['password'], json_encode($array));
    }

    public function testTheLatestOrdersOfAUserNewestFirstAndLimited(): void
    {
        $user = $this->getService(UserService::class)->create(['username' => 'buyer', 'email' => 'buyer@example.com']);
        foreach (range(1, 12) as $i) {
            $this->order([], ['user_uuid' => $user->uuid]);
        }

        $orders = User::find($user->uuid)->orders()->get();
        $this->assertCount(10, $orders, 'ten by default');
        $this->assertSame($orders->pluck('serial')->sortDesc()->values()->all(), $orders->pluck('serial')->all());
        $this->assertCount(3, User::find($user->uuid)->orders(3)->get());
    }

    // -------------------------------------------------------------------- files

    public function testAFileKnowsWhereItIsAndWhatItIsCalled(): void
    {
        $file = File::create(['name' => 'photo', 'ext' => 'jpg', 'type' => 'image/jpeg', 'size' => 2048, 'salt' => 'salt42', 'hash' => sha1('where')]);

        $this->assertSame('photo.jpg', $file->filename());
        $this->assertSame('/uploads/salt42/photo.jpg', $file->public_path());
        $this->assertSame(UPLOAD_DIR . '/salt42/photo.jpg', $file->internal_path());
        $this->assertSame(UPLOAD_DIR . '/salt42', $file->dir());
        $this->assertSame('/uploads/salt42/photo.jpg', $file->public_path('small'), 'a copy that is not there is the file itself');
        $this->assertSame('/uploads/salt42/photo.jpg', $file->public_path('../../etc'), 'and so is a size that is not a size');
        $this->assertSame(1, $file->order());
        $this->assertSame('', $file->comment());
    }

    public function testACopyOfAFileIsUsedOnceItIsThere(): void
    {
        $file = File::create(['name' => 'photo', 'ext' => 'jpg', 'type' => 'image/jpeg', 'size' => 1, 'salt' => 'copy' . bin2hex(random_bytes(3)), 'hash' => sha1(uniqid())]);
        @mkdir($file->dir() . '/small', 0o777, true);
        file_put_contents($file->dir() . '/small/photo.jpg', 'x');

        try {
            $this->assertSame("/uploads/{$file->salt}/small/photo.jpg", $file->public_path('small'));
            $this->assertSame("/uploads/{$file->salt}/photo.jpg", $file->public_path('middle'), 'the other sizes are still not there');
            $this->assertSame(['big' => "/uploads/{$file->salt}/photo.jpg", 'middle' => "/uploads/{$file->salt}/photo.jpg", 'small' => "/uploads/{$file->salt}/small/photo.jpg"], $file->toArray()['path']);
        } finally {
            @unlink($file->dir() . '/small/photo.jpg');
            @rmdir($file->dir() . '/small');
            @rmdir($file->dir());
        }
    }

    public function testTheFilesOfARecordAreSortedAndSplitByKind(): void
    {
        $page = $this->getService(PageService::class)->create(['title' => 'Gallery', 'address' => 'gallery', 'content' => 'x']);
        $make = fn (string $name, string $type, int $order) => File::create(['name' => $name, 'ext' => 'bin', 'type' => $type, 'size' => 1, 'salt' => 'f' . $order . bin2hex(random_bytes(2)), 'hash' => sha1($name)]);
        $files = [
            $make('b-image', 'image/png', 2), $make('a-image', 'image/jpeg', 1), $make('movie', 'video/mp4', 3), $make('song', 'audio/mpeg', 4),
            $make('sheet', 'application/pdf', 5), $make('notes', 'text/plain', 6),
        ];
        $page->files()->sync(collect($files)->mapWithKeys(fn (File $f, int $i) => [$f->uuid => ['comment' => '', 'order' => $i + 1]])->all());
        $fresh = Page::find($page->uuid);

        $this->assertSame(['b-image', 'a-image'], $fresh->images->pluck('name')->all(), 'in the order they were given, not by name');
        $this->assertSame(['movie'], $fresh->videos->pluck('name')->all());
        $this->assertSame(['song'], $fresh->audios->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['sheet', 'notes'], $fresh->documents->pluck('name')->all());
    }

    public function testTheDocumentsOfOneRecordAreNotTheDocumentsOfAnother(): void
    {
        $pages = $this->getService(PageService::class);
        $mine = $pages->create(['title' => 'Mine', 'address' => 'mine', 'content' => 'x']);
        $theirs = $pages->create(['title' => 'Theirs', 'address' => 'theirs', 'content' => 'x']);
        $ownNote = File::create(['name' => 'own', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => 'own123', 'hash' => sha1('own')]);
        $foreignNote = File::create(['name' => 'foreign', 'ext' => 'txt', 'type' => 'text/plain', 'size' => 1, 'salt' => 'foreign1', 'hash' => sha1('foreign')]);
        $foreignPdf = File::create(['name' => 'secret', 'ext' => 'pdf', 'type' => 'application/pdf', 'size' => 1, 'salt' => 'foreign2', 'hash' => sha1('secret')]);
        $mine->files()->sync([$ownNote->uuid => ['comment' => '', 'order' => 1]]);
        $theirs->files()->sync([$foreignNote->uuid => ['comment' => '', 'order' => 1], $foreignPdf->uuid => ['comment' => '', 'order' => 2]]);

        $this->assertSame(['own'], Page::find($mine->uuid)->documents->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['foreign', 'secret'], Page::find($theirs->uuid)->documents->pluck('name')->all());
    }
}

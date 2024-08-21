<?php declare(strict_types=1);

namespace tests\Domain\Service\Page;

use App\Domain\Casts\Page\Type as PageType;
use App\Domain\Models\Page;
use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Page\PageService;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class PageServiceTest extends TestCase
{
    protected PageService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(PageService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->name,
            'address' => $this->getFaker()->url,
            'content' => $this->getFaker()->text,
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => $this->getFaker()->text(50),
            'type' => $this->getFaker()->randomElement(PageType::LIST),
        ];

        $page = $this->service->create($data);
        $this->assertInstanceOf(Page::class, $page);
        $this->assertEquals($data['title'], $page->title);
        $this->assertEquals($data['content'], $page->content);
        $this->assertEquals($data['template'], $page->template);
        $this->assertEquals($data['type'], $page->type);
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create([
            'address' => $this->getFaker()->word,
            'content' => $this->getFaker()->text,
        ]);
    }

    public function testCreateWithTitleAlreadyExistent(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->word,
            'content' => $this->getFaker()->text,
        ];

        Page::create($data);

        $this->service->create($data);
    }

    public function testCreateWithAddressAlreadyExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->word,
            'address' => $this->getFaker()->word,
            'content' => $this->getFaker()->text,
        ];

        $page = Page::create($data);

        $this->service->create([
            'title' => $page->title . '-skip',
            'address' => $page->address,
            'content' => $page->content,
        ]);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->word,
            'address' => $this->getFaker()->word,
            'content' => $this->getFaker()->text,
        ];

        $this->service->create($data);

        $page = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Page::class, $page);
        $this->assertEquals($data['title'], $page->title);

        $page = $this->service->read(['address' => $page->address]);
        $this->assertInstanceOf(Page::class, $page);
        $this->assertEquals($data['title'], $page->title);
    }

    public function testReadWithPageNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->word]);
    }

    public function testUpdateSuccess(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->word,
            'address' => $this->getFaker()->word,
            'content' => $this->getFaker()->text,
        ]);

        $data = [
            'title' => $this->getFaker()->word,
            'address' => 'test-address-page',
            'content' => $this->getFaker()->text,
            'meta' => [
                'title' => $this->getFaker()->word,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => 'page.derect.twig',
            'type' => $this->getFaker()->randomElement(PageType::LIST),
        ];

        $page = $this->service->update($page, $data);
        $this->assertEquals($data['title'], $page->title);
        $this->assertEquals($data['address'], $page->address);
        $this->assertEquals($data['content'], $page->content);
        $this->assertEquals($data['meta'], $page->meta);
        $this->assertEquals($data['template'], $page->template);
        $this->assertEquals($data['type'], $page->type);
    }

    public function testUpdateWithPageNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->word,
            'address' => $this->getFaker()->word,
            'content' => $this->getFaker()->text,
        ]);

        $result = $this->service->delete($page);

        $this->assertTrue($result);
    }

    public function testDeleteWithPageNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->service->delete(null);
    }
}

<?php declare(strict_types=1);

namespace tests\Domain\Service\Page;

use App\Domain\Entities\Page;
use App\Domain\Repository\PageRepository;
use App\Domain\Service\Page\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Page\Exception\MissingTitleValueException;
use App\Domain\Service\Page\Exception\PageNotFoundException;
use App\Domain\Service\Page\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Page\PageService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
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
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->title,
            'content' => $this->getFaker()->text,
            'date' => $this->getFaker()->dateTime,
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => $this->getFaker()->text(50),
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\PageTypeType::LIST),
        ];

        $page = $this->service->create($data);
        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame($data['title'], $page->getTitle());
        // $this->assertSame($data['address'], $page->getAddress());
        $this->assertSame($data['content'], $page->getContent());
        $this->assertSame($data['template'], $page->getTemplate());
        $this->assertSame($data['type'], $page->getType());

        /** @var PageRepository $pageRepo */
        $pageRepo = $this->em->getRepository(Page::class);
        $p = $pageRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Page::class, $p);
        $this->assertSame($data['title'], $p->getTitle());
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create([
            'address' => $this->getFaker()->title,
            'content' => $this->getFaker()->text,
        ]);
    }

    public function testCreateWithTitleAlreadyExistent(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->title,
            'content' => $this->getFaker()->text,
        ];

        $page = (new Page())
            ->setTitle($data['title'])
            ->setContent($data['content'])
            ->setDate('now');

        $this->em->persist($page);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testCreateWithAddressAlreadyExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->title,
            'content' => $this->getFaker()->text,
        ];

        $page = (new Page())
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setContent($data['content'])
            ->setDate('now');

        $this->em->persist($page);
        $this->em->flush();

        $this->service->create([
            'title' => $page->getTitle() . '-skip',
            'address' => $page->getAddress(),
            'content' => $page->getContent(),
        ]);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->title,
            'content' => $this->getFaker()->text,
        ];

        $this->service->create($data);

        $page = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame($data['title'], $page->getTitle());

        $page = $this->service->read(['address' => $page->getAddress()]);
        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame($data['title'], $page->getTitle());
    }

    public function testReadWithPageNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->title]);
    }

    public function testUpdateSuccess(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->title,
            'content' => $this->getFaker()->text,
        ]);

        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'test-address-page',
            'content' => $this->getFaker()->text,
            'meta' => [
                'title' => $this->getFaker()->title,
                'description' => $this->getFaker()->text,
                'keywords' => $this->getFaker()->text,
            ],
            'template' => 'page.derect.twig',
            'type' => $this->getFaker()->randomElement(\App\Domain\Types\PageTypeType::LIST),
        ];

        $page = $this->service->update($page, $data);
        $this->assertSame($data['title'], $page->getTitle());
        $this->assertSame($data['address'], $page->getAddress());
        $this->assertSame($data['content'], $page->getContent());
        $this->assertSame($data['meta'], $page->getMeta());
        $this->assertSame($data['template'], $page->getTemplate());
        $this->assertSame($data['type'], $page->getType());
    }

    public function testUpdateWithPageNotFound(): void
    {
        $this->expectException(PageNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $page = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => $this->getFaker()->title,
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

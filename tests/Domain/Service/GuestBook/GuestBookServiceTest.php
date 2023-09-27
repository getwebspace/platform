<?php declare(strict_types=1);

namespace tests\Domain\Service\GuestBook;

use App\Domain\Entities\GuestBook;
use App\Domain\Repository\GuestBookRepository;
use App\Domain\Service\GuestBook\Exception\EntryNotFoundException;
use App\Domain\Service\GuestBook\Exception\MissingEmailValueException;
use App\Domain\Service\GuestBook\Exception\MissingMessageValueException;
use App\Domain\Service\GuestBook\Exception\MissingNameValueException;
use App\Domain\Service\GuestBook\GuestBookService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class GuestBookServiceTest extends TestCase
{
    protected GuestBookService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(GuestBookService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'name' => $this->getFaker()->firstName,
            'email' => $this->getFaker()->email,
            'message' => $this->getFaker()->text,
            'response' => $this->getFaker()->text,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\GuestBookStatusType::LIST),
            'date' => $this->getFaker()->dateTime,
        ];

        $gb = $this->service->create($data);
        $this->assertInstanceOf(GuestBook::class, $gb);
        $this->assertEquals($data['name'], $gb->getName());
        $this->assertEquals($data['email'], $gb->getEmail());
        $this->assertEquals($data['message'], $gb->getMessage());
        $this->assertEquals($data['response'], $gb->getResponse());
        $this->assertEquals($data['status'], $gb->getStatus());

        /** @var GuestBookRepository $guestBookRepo */
        $guestBookRepo = $this->em->getRepository(GuestBook::class);
        $gb = $guestBookRepo->findOneByUuid($gb->getUuid());
        $this->assertInstanceOf(GuestBook::class, $gb);
        $this->assertEquals($data['name'], $gb->getName());
    }

    public function testCreateWithMissingNameValue(): void
    {
        $this->expectException(MissingNameValueException::class);

        $this->service->create([]);
    }

    public function testCreateWithMissingEmailValue(): void
    {
        $this->expectException(MissingEmailValueException::class);

        $this->service->create([
            'name' => $this->getFaker()->userName,
        ]);
    }

    public function testCreateWithMissingMessageValue(): void
    {
        $this->expectException(MissingMessageValueException::class);

        $this->service->create([
            'name' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
        ]);
    }

    public function testReadSuccess(): void
    {
        $data = [
            'name' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'message' => $this->getFaker()->text,
        ];

        $gb = $this->service->create($data);

        $gb = $this->service->read(['uuid' => $gb->getUuid()]);
        $this->assertInstanceOf(GuestBook::class, $gb);
        $this->assertEquals($data['name'], $gb->getName());
        $this->assertEquals($data['email'], $gb->getEmail());
        $this->assertEquals($data['message'], $gb->getMessage());
    }

    public function testReadWithEntryNotFound(): void
    {
        $this->expectException(EntryNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdateSuccess(): void
    {
        $gb = $this->service->create([
            'name' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'message' => $this->getFaker()->text,
        ]);

        $data = [
            'name' => $this->getFaker()->word,
            'email' => $this->getFaker()->email,
            'message' => $this->getFaker()->text,
            'response' => $this->getFaker()->text,
            'status' => \App\Domain\Types\GuestBookStatusType::STATUS_WORK,
        ];

        $gb = $this->service->update($gb, $data);
        $this->assertEquals($data['name'], $gb->getName());
        $this->assertEquals($data['email'], $gb->getEmail());
        $this->assertEquals($data['message'], $gb->getMessage());
        $this->assertEquals($data['response'], $gb->getResponse());
        $this->assertEquals($data['status'], $gb->getStatus());
    }

    public function testUpdateWithEntryNotFound(): void
    {
        $this->expectException(EntryNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $gb = $this->service->create([
            'name' => $this->getFaker()->userName,
            'email' => $this->getFaker()->email,
            'message' => $this->getFaker()->text,
        ]);

        $result = $this->service->delete($gb);

        $this->assertTrue($result);
    }

    public function testDeleteWithEntryNotFound(): void
    {
        $this->expectException(EntryNotFoundException::class);

        $this->service->delete(null);
    }
}

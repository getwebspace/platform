<?php declare(strict_types=1);

namespace tests\Domain\Service\Catalog;

use App\Domain\Entities\Catalog\Order;
use App\Domain\Repository\Catalog\OrderRepository;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\OrderService;
use Doctrine\ORM\EntityManager;
use Illuminate\Support\Collection;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class OrderServiceTest extends TestCase
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var OrderService
     */
    protected $service;

    public function setUp(): void
    {
        parent::setUp();
        $this->em = $this->getEntityManager();
        $this->service = OrderService::getWithEntityManager($this->em);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'list' => [
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\OrderStatusType::LIST),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ];

        $order = $this->service->create($data);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['delivery'], $order->getDelivery());
        $this->assertEquals($data['list'], $order->getList());
        $this->assertSame($data['phone'], $order->getPhone());
        $this->assertSame($data['email'], $order->getEmail());
        $this->assertSame($data['status'], $order->getStatus());
        $this->assertSame($data['comment'], $order->getComment());
        $this->assertEquals($data['shipping'], $order->getShipping());
        $this->assertEquals($data['date'], $order->getDate());
        $this->assertSame($data['external_id'], $order->getExternalId());
        $this->assertSame($data['export'], $order->getExport());
        $this->assertSame($data['system'], $order->getSystem());

        /** @var OrderRepository $orderRepo */
        $orderRepo = $this->em->getRepository(Order::class);
        $o = $orderRepo->findOneByUuid($order->getUuid());
        $this->assertInstanceOf(Order::class, $o);
        $this->assertEquals($data['delivery'], $o->getDelivery());
        $this->assertEquals($data['list'], $o->getList());
        $this->assertSame($data['phone'], $o->getPhone());
        $this->assertSame($data['email'], $o->getEmail());
        $this->assertSame($data['status'], $o->getStatus());
        $this->assertSame($data['comment'], $o->getComment());
        $this->assertEquals($data['shipping'], $o->getShipping());
        $this->assertEquals($data['date'], $o->getDate());
        $this->assertSame($data['external_id'], $o->getExternalId());
        $this->assertSame($data['export'], $o->getExport());
        $this->assertSame($data['system'], $order->getSystem());
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'list' => [
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\OrderStatusType::LIST),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $order = $this->service->read(['external_id' => $data['external_id']]);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($data['external_id'], $order->getExternalId());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'list' => [
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\OrderStatusType::LIST),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $order = $this->service->read(['status' => $data['status']]);
        $this->assertInstanceOf(Collection::class, $order);
    }

    public function testReadWithOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->read(['uuid' => $this->getFaker()->uuid]);
    }

    public function testUpdate(): void
    {
        $order = $this->service->create([
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'list' => [
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\OrderStatusType::LIST),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ]);

        $data = [
            'delivery' => [
                'client' => $this->getFaker()->word,
                'address' => $this->getFaker()->text,
            ],
            'list' => [
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
                $this->getFaker()->uuid => $this->getFaker()->randomNumber(),
            ],
            'phone' => $this->getFaker()->e164PhoneNumber,
            'email' => $this->getFaker()->email,
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\OrderStatusType::LIST),
            'comment' => $this->getFaker()->text,
            'shipping' => $this->getFaker()->dateTime,
            'date' => $this->getFaker()->dateTime,
            'external_id' => $this->getFaker()->word,
            'export' => $this->getFaker()->word,
            'system' => $this->getFaker()->text,
        ];

        $order = $this->service->update($order, $data);
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($data['delivery'], $order->getDelivery());
        $this->assertEquals($data['list'], $order->getList());
        $this->assertSame($data['phone'], $order->getPhone());
        $this->assertSame($data['email'], $order->getEmail());
        $this->assertSame($data['status'], $order->getStatus());
        $this->assertSame($data['comment'], $order->getComment());
        $this->assertEquals($data['shipping'], $order->getShipping());
        $this->assertEquals($data['date'], $order->getDate());
        $this->assertSame($data['external_id'], $order->getExternalId());
        $this->assertSame($data['export'], $order->getExport());
        $this->assertSame($data['system'], $order->getSystem());
    }

    public function testUpdateWithOrderNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $order = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'status' => $this->getFaker()->randomElement(\App\Domain\Types\Catalog\OrderStatusType::LIST),
        ]);

        $result = $this->service->delete($order);

        $this->assertTrue($result);
    }

    public function testDeleteWithProductNotFound(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->service->delete(null);
    }
}

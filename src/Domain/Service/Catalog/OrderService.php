<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class OrderService extends AbstractService
{
    protected static array $eager = ['status', 'payment', 'products', 'products.files'];

    protected static array $search_columns = ['serial', 'phone', 'email', 'external_id', 'delivery'];

    /**
     * @throws Exception\WrongEmailValueException
     * @throws Exception\WrongPhoneValueException
     */
    public function create(array $data = []): CatalogOrder
    {
        $default = [
            'serial' => '',
            'products' => [],
        ];
        $data = array_merge($default, $data);

        $order = $this->db->transaction(function () use ($data) {
            $order = new CatalogOrder();
            $order->fill(array_merge($data, ['serial' => $this->generateSerial()]));

            $this->failIfDropped($order, $data, 'email', new Exception\WrongEmailValueException());
            $this->failIfDropped($order, $data, 'phone', new Exception\WrongPhoneValueException());
            $this->assertUserExists($order);
            $order->save();

            return $order;
        });

        // sync products
        if ($data['products']) {
            $order->products()->sync($this->products($data['products']));
        }

        return $order;
    }

    /**
     * @throws OrderNotFoundException
     *
     * @return CatalogOrder|Collection
     */
    public function read(array $data = [])
    {
        $default = [
            'uuid' => null,
            'user_uuid' => null,
            'serial' => null,
            'phone' => null,
            'email' => null,
            'status_uuid' => null,
            'payment_uuid' => null,
            'external_id' => null,
            'export' => null,
        ];
        $data = array_merge($default, static::$default_read, $data);

        $criteria = [];

        if ($data['uuid'] !== null) {
            $criteria['uuid'] = $data['uuid'];
        }
        if ($data['user_uuid'] !== null) {
            $criteria['user_uuid'] = $data['user_uuid'];
        }
        if ($data['serial'] !== null) {
            $criteria['serial'] = $data['serial'];
        }
        if ($data['phone'] !== null) {
            $criteria['phone'] = $data['phone'];
        }
        if ($data['email'] !== null) {
            $criteria['email'] = $data['email'];
        }
        if ($data['status_uuid'] !== null) {
            $criteria['status_uuid'] = $data['status_uuid'];
        }
        if ($data['payment_uuid'] !== null) {
            $criteria['payment_uuid'] = $data['payment_uuid'];
        }
        if ($data['external_id'] !== null) {
            $criteria['external_id'] = $data['external_id'];
        }
        if ($data['export'] !== null) {
            $criteria['export'] = $data['export'];
        }

        switch (true) {
            case !is_array($data['uuid']) && $data['uuid'] !== null:
            case !is_array($data['serial']) && $data['serial'] !== null:
            case !is_array($data['external_id']) && $data['external_id'] !== null:
                /** @var null|CatalogOrder $catalogOrder */
                $catalogOrder = $this->buildQuery(CatalogOrder::query(), $criteria, $data)->first();

                return $catalogOrder ?: throw new OrderNotFoundException();

            default:
                $query = $this->buildQuery(CatalogOrder::query(), $criteria, $data);

                return $query->get();
        }
    }

    /**
     * @param CatalogOrder|string|Uuid $entity
     *
     * @throws OrderNotFoundException
     */
    /**
     * An order of a customer who is not there is a row nobody can open from the customer's side
     *
     * @throws \App\Domain\Service\User\Exception\UserNotFoundException
     */
    private function assertUserExists(CatalogOrder $order): void
    {
        if (!blank($order->user_uuid) && !\App\Domain\Models\User::query()->whereKey($order->user_uuid)->exists()) {
            throw new \App\Domain\Service\User\Exception\UserNotFoundException();
        }
    }

    public function update($entity, array $data = []): CatalogOrder
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogOrder::class)) {
            $entity->fill($data);

            $this->failIfDropped($entity, $data, 'email', new Exception\WrongEmailValueException());
            $this->failIfDropped($entity, $data, 'phone', new Exception\WrongPhoneValueException());
            $this->assertUserExists($entity);

            $entity->save();

            // sync products
            if (isset($data['products'])) {
                $entity->products()->sync($this->products($data['products']));
            }

            return $entity;
        }

        throw new OrderNotFoundException();
    }

    /**
     * @param CatalogOrder|string|Uuid $entity
     *
     * @throws OrderNotFoundException
     */
    public function delete($entity): bool
    {
        switch (true) {
            case is_string($entity) && \Ramsey\Uuid\Uuid::isValid($entity):
            case is_object($entity) && is_a($entity, Uuid::class):
                $entity = $this->read(['uuid' => $entity]);

                break;
        }

        if (is_object($entity) && is_a($entity, CatalogOrder::class)) {
            $entity->delete();

            return true;
        }

        throw new OrderNotFoundException();
    }

    /**
     * How many orders are already booked for shipping on the given day
     */
    public function countShippingOn($date = 'now'): int
    {
        return CatalogOrder::query()->whereDate('shipping', datetime($date)->format('Y-m-d'))->count();
    }

    /**
     * The number the next order created on that day gets (it numbers the serial)
     */
    public function getDayCount($date = 'now'): int
    {
        $date = datetime($date)->format('Y-m-d');
        $count = CatalogOrder::query()->whereDate('date', $date)->count();

        return $count + 1;
    }

    private function generateSerial(): string
    {
        $currentDate = datetime();
        $dayOfYear = mb_str_pad(strval((+$currentDate->format('z')) + 1), 3, '0', STR_PAD_LEFT);
        $year = $currentDate->format('y');
        $prefix = "{$year}{$dayOfYear}";

        // the next number after the last one of the day: counting the orders of the day gives a number
        // that is already taken as soon as one of them is deleted or its date is changed
        $last = CatalogOrder::query()->where('serial', 'like', $prefix . '%')->max('serial');
        $next = $last === null ? 1 : ((int) substr((string) $last, mb_strlen($prefix))) + 1;

        return $prefix . mb_str_pad(strval($next), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Reduce products posted from the public cart to what a customer may choose:
     * only existing products on sale, a positive count, and the catalog price
     * (the wholesale one only when the count reaches the product's threshold),
     * or the self price when the shop allows it (`catalog_order_price_self`, off by default).
     */
    public function sanitizeCartProducts(array $products): array
    {
        $result = [];

        foreach ($products as $uuid => $opts) {
            if (!is_string($uuid) || !is_array($opts)) {
                continue;
            }

            $count = $opts['count'] ?? 0;
            if (!is_numeric($count) || floatval($count) <= 0) {
                continue;
            }

            /** @var ?CatalogProduct $product */
            $product = CatalogProduct::find($uuid);
            if (!$product || $product->status !== \App\Domain\Casts\Catalog\Status::WORK) {
                continue;
            }

            $wholesale = $product->priceWholesaleFrom > 0
                && $product->priceWholesale > 0
                && floatval($count) >= $product->priceWholesaleFrom;

            // the self price is the customer's own price (with discount and tax), taken as posted:
            // it is kept only where the shop switched it on, otherwise the catalog price is charged
            if (
                ($opts['price_type'] ?? '') === \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF
                && $this->parameter('catalog_order_price_self', 'off') === 'on'
            ) {
                $result[$uuid] = array_merge(
                    array_intersect_key($opts, array_flip(['price', 'discount', 'tax', 'tax_included'])),
                    ['count' => floatval($count), 'price_type' => \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF]
                );

                continue;
            }

            $result[$uuid] = [
                'count' => floatval($count),
                'price_type' => $wholesale
                    ? \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_WHOLESALE
                    : \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE,
            ];
        }

        return $result;
    }

    private function products(array $products = []): array
    {
        $productsForSync = [];

        foreach ($products as $uuid => $opts) {
            /** @var CatalogProduct $product */
            $product = CatalogProduct::find($uuid);

            if ($product) {
                // a line without a positive count is no line
                if (!is_array($opts) || floatval($opts['count'] ?? 0) <= 0) {
                    continue;
                }

                $priceType = in_array($opts['price_type'] ?? '', \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE, true) ? $opts['price_type'] : 'price';
                $price = match ($priceType) {
                    \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE => $product->price,
                    \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_WHOLESALE => $product->priceWholesale,
                    \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF => floatval($opts['price'] ?? 0),
                };
                $discount = $product->discount;
                $count = floatval($opts['count'] ?? 0);
                $tax = $product->tax;
                $tax_included = $product->tax_included;

                // in case self price
                if ($priceType === \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF) {
                    $discount = floatval($opts['discount'] ?? $product->discount);
                    $tax = floatval($opts['tax'] ?? $product->tax);
                    $tax_included = floatval($opts['tax_included'] ?? $product->tax_included);
                }

                $productsForSync[$uuid] = [
                    'price' => $price,
                    'price_type' => $priceType,
                    'count' => $count,
                    'discount' => -abs($discount),
                    'tax' => $tax,
                    'tax_included' => $tax_included,
                ];
            }
        }

        return $productsForSync;
    }
}

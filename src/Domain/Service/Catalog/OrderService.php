<?php declare(strict_types=1);

namespace App\Domain\Service\Catalog;

use App\Domain\AbstractService;
use App\Domain\Models\CatalogOrder;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\User;
use App\Domain\Models\CatalogCategory;
use App\Domain\Repository\Catalog\OrderRepository;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;
use App\Domain\Service\Catalog\Exception\WrongEmailValueException;
use App\Domain\Service\Catalog\Exception\WrongPhoneValueException;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Ramsey\Uuid\UuidInterface as Uuid;

class OrderService extends AbstractService
{
    /**
     * @throws Exception\WrongEmailValueException
     * @throws Exception\WrongPhoneValueException
     */
    public function create(array $data = []): CatalogOrder
    {
        $default = [
            'serial' => $this->generateSerial(),
            'products' => [],
        ];
        $data = array_merge($default, $data);

        $order = $this->db->transaction(function () use ($data) {
            $order = new CatalogOrder();
            $order->fill(array_merge(
                $data,
                [
                    'serial' => $this->generateSerial(),
                ]
            ));
            $order->save();

            return $order;
        });

        // sync products
        if ($data['products']) {
            $order->products()->sync($this->products($data['products']));
        }

        return $order;
    }

    public function getDayCount($date = 'now'): int
    {
        $date = datetime($date)->format('Y-m-d');
        $count = CatalogOrder::query()->whereDate('date', $date)->count();

        return $count + 1;
    }

    private function generateSerial(): string
    {
        $currentDate = datetime();
        $dayOfYear = str_pad(strval((+$currentDate->format('z')) + 1), 3, '0', STR_PAD_RIGHT);
        $year = $currentDate->format('y');

        $ordersCount = $this->getDayCount();
        $dailyOrderNumberFormatted = str_pad(strval($ordersCount), 3, '0', STR_PAD_LEFT);

        return sprintf('%s%03d%s', $year, $dayOfYear, $dailyOrderNumberFormatted);
    }

    private function products(array $products = []): array
    {
        $productsForSync = [];

        foreach ($products as $uuid => $opts) {
            /** @var CatalogProduct $product */
            $product = CatalogProduct::find($uuid);

            if ($product) {
                $price = floatval($opts['price'] ?? 0);
                $priceType = in_array($opts['price_type'], \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE, true) ? $opts['price_type'] : 'price';
                $count = floatval($opts['count'] ?? 0);
                $discount = $product->discount;
                $tax = $product->tax;
                $tax_included = $product->tax_included;

                $price = match ($priceType) {
                    \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE => $product->price,
                    \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_WHOLESALE => $product->priceWholesale,
                    \App\Domain\References\Catalog::PRODUCT_PRICE_TYPE_PRICE_SELF => $price,
                };

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
                    'discount' => $discount,
                    'tax' => $tax,
                    'tax_included' => $tax_included,
                ];
            }
        }

        return $productsForSync;
    }

    /**
     * @throws OrderNotFoundException
     *
     * @return Collection|CatalogOrder
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
                /** @var CatalogOrder $catalogOrder */
                $catalogOrder = CatalogOrder::firstWhere($criteria);

                return $catalogOrder ?: throw new OrderNotFoundException();

            default:
                $query = CatalogOrder::query();
                /** @var Builder $query */

                foreach ($criteria as $key => $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
                foreach ($data['order'] as $column => $direction) {
                    $query = $query->orderBy($column, $direction);
                }
                if ($data['limit']) {
                    $query = $query->limit($data['limit']);
                }
                if ($data['offset']) {
                    $query = $query->offset($data['offset']);
                }

                return $query->get();
        }
    }

    /**
     * @param CatalogOrder|string|Uuid $entity
     *
     * @throws OrderNotFoundException
     */
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
}

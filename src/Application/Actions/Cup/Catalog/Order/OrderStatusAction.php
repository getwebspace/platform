<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog\Order;

use App\Application\Actions\Cup\Catalog\CatalogAction;
use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\Service\Catalog\Exception\OrderNotFoundException;

/**
 * Moves an order to another status: the card of the board dropped into a column
 */
class OrderStatusAction extends CatalogAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $uuid = (string) $this->resolveArg('order');
        $status = (string) $this->getParam('status_uuid', '');

        if (!$this->isPost() || !\Ramsey\Uuid\Uuid::isValid($uuid)) {
            return $this->respondWithJson(['ok' => false, 'error' => 'Bad request'])->withStatus(400);
        }

        // the empty one takes the order out of every column, any other must be a status that is on
        if ($status !== '') {
            $known = $this->referenceService
                ->read(['type' => ReferenceType::ORDER_STATUS, 'status' => true])
                ->pluck('uuid')
                ->map(fn ($id) => (string) $id)
                ->all();

            if (!in_array($status, $known, true)) {
                return $this->respondWithJson(['ok' => false, 'error' => 'Unknown status'])->withStatus(422);
            }
        }

        try {
            $order = $this->catalogOrderService->update($uuid, ['status_uuid' => $status ?: null]);
        } catch (OrderNotFoundException $e) {
            return $this->respondWithJson(['ok' => false, 'error' => 'Order not found'])->withStatus(404);
        } catch (\Throwable $e) {
            // an order that cannot be saved as it is (a broken e-mail, a customer who is gone) is told about, not hidden
            return $this->respondWithJson(['ok' => false, 'error' => $e->getMessage()])->withStatus(422);
        }

        $this->container->get(\App\Application\PubSub::class)->publish('cup:catalog:order:edit', $order);

        return $this->respondWithJson(['ok' => true, 'status_uuid' => (string) $order->status_uuid]);
    }
}

<?php declare(strict_types=1);

namespace App\Application;

use App\Domain\Service\Catalog\OrderService;
use App\Domain\Service\Reference\ReferenceService;
use App\Domain\Traits\ParameterTrait;
use App\Domain\Traits\RendererTrait;
use App\Domain\Casts\Reference\Type as ReferenceType;
use Psr\Container\ContainerInterface;

class PubSub
{
    use ParameterTrait;
    use RendererTrait;

    protected ContainerInterface $container;

    protected array $subscribers = [];

    final public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->renderer = $container->get('view');

        // todo come up with a new place for it
        // ----------------------------------------------------

        // reindex search
        $this->subscribe(
            [
                'api:page:create',
                'api:page:edit',
                'api:page:delete',
                'api:publication:create',
                'api:publication:edit',
                'api:publication:delete',
                'api:catalog:product:create',
                'api:catalog:product:edit',
                'api:catalog:product:delete',
                'cup:page:create',
                'cup:page:edit',
                'cup:page:delete',
                'cup:publication:create',
                'cup:publication:edit',
                'cup:publication:delete',
                'cup:catalog:product:create',
                'cup:catalog:product:edit',
                'cup:catalog:product:delete',
                'task:catalog:import',
            ],
            function ($data, $container): void {
                $task = new \App\Domain\Tasks\SearchIndexTask($container);
                $task->execute();

                // run worker
                \App\Domain\AbstractTask::worker($task);
            }
        );

        // send mail when order created
        $this->subscribe(
            [
                'api:catalog:order:create',
                'common:catalog:order:create',
                'cup:catalog:order:create',
            ],
            function ($order, $container): void {
                $isNeedRunWorker = false;

                // mail to administrator
                if (
                    ($this->parameter('catalog_mail_admin', 'off') === 'on')
                    && ($email = $this->parameter('mail_from', '')) !== ''
                    && ($tpl = $this->parameter('catalog_mail_admin_template', '')) !== ''
                ) {
                    // add task send admin mail
                    $task = new \App\Domain\Tasks\SendMailTask($container);
                    $task->execute([
                        'to' => $email,
                        'template' => $this->render($tpl, ['order' => $order]),
                        'isHtml' => true,
                    ]);
                    $isNeedRunWorker = $task;
                }

                // mail to client
                if (
                    ($this->parameter('catalog_mail_client', 'off') === 'on')
                    && $order->getEmail()
                    && ($tpl = $this->parameter('catalog_mail_client_template', '')) !== ''
                ) {
                    // add task send client mail
                    $task = new \App\Domain\Tasks\SendMailTask($container);
                    $task->execute([
                        'to' => $order->getEmail(),
                        'template' => $this->render($tpl, ['order' => $order]),
                        'isHtml' => true,
                    ]);
                    $isNeedRunWorker = $task;
                }

                // run worker
                if ($isNeedRunWorker) {
                    \App\Domain\AbstractTask::worker($isNeedRunWorker);
                }
            }
        );

        // automatic update order status after payment via plugin
        $this->subscribe('plugin:order:payment', function ($order, $container): void {
            if (($status_uuid = $this->parameter('catalog_order_status_payed', '')) !== '') {
                $referenceService = $container->get(ReferenceService::class);
                $orderService = $container->get(OrderService::class);

                $orderService->update($order, [
                    'status' => $status_uuid ? $referenceService->read(['uuid' => $status_uuid, 'type' => ReferenceType::ORDER_STATUS]) : null,
                ]);
            }
        });

        // ----------------------------------------------------
    }

    /**
     * Publish a data to a channel
     */
    public function publish(string $channel, mixed $data = []): void
    {
        foreach (static::getSubscribersForChannel($channel) as $handler) {
            call_user_func($handler, $data, $this->container);
        }
    }

    /**
     * Subscribe a handler to a channel
     */
    public function subscribe(string|array $channels, callable|array $handler): self
    {
        foreach ((array) $channels as $channel) {
            if (!isset($this->subscribers[$channel])) {
                $this->subscribers[$channel] = [];
            }
            $this->subscribers[$channel][] = $handler;
        }

        return $this;
    }

    /**
     * Return all subscribers on the given channel.
     */
    protected function getSubscribersForChannel(string $channel): array
    {
        $subscribers = [];

        foreach ($this->subscribers as $key => $handlers) {
            if ($channel === $key) {
                $subscribers = array_merge($subscribers, $handlers);
            }
        }

        return $subscribers;
    }
}

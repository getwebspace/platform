<?php declare(strict_types=1);

namespace App\Application;

use Psr\Container\ContainerInterface;

class PubSub
{
    protected ContainerInterface $container;

    protected array $subscribers = [];

    final public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Publish a data to a channel
     */
    public function publish(string $channel, mixed $data = []): void
    {
        foreach (static::getSubscribersForChannel($channel) as $handler) {
            call_user_func($handler, $data);
        }
    }

    /**
     * Subscribe a handler to a channel
     *
     * @return PubSub
     */
    public function subscribe(string|array $channels, callable $handler): self
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

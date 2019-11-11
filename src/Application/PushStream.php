<?php

namespace App\Application;

use Psr\Container\ContainerInterface;

class PushStream
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Send message
     *
     * @param array $data
     *
     * @return bool
     */
    public function send(array $data = [])
    {
        $default = [
            'all' => false,
            'group' => '',
            'user_uuid' => '',
            'content' => [],
        ];
        $data = array_merge($default, $data);

        if (is_a($data['content'], \Alksily\Entity\Interfaces\ModelInterface::class)) {
            $name = explode('\\', get_class($data['content']));
            $data['content'] = array_merge($data['content']->toArray(), ['type' => strtolower(array_pop($name))]);
        }
        if (is_array($data['content'])) {
            $data['content'] = json_encode($data['content'], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        }

        if ($this->isOnline($data['user_uuid'])) {
            $this->http([
                'url' => '/pub?id=' . $this->getChannel($data['user_uuid']),
                'params' => $data['content'],
            ]);

            return true;
        }

        if ($data['group']) {
            $this->http([
                'url' => '/pub?id=' . $this->getChannel($data['group']),
                'params' => $data['content'],
            ]);

            return true;
        }

        if ($data['all']) {
            $this->http([
                'url' => '/pub?id=all',
                'params' => $data['content'],
            ]);

            return true;
        }

        return false;
    }

    /**
     * Generate and return unique user channel id
     *
     * @param $user_uuid
     *
     * @return string
     */
    public function getChannel($user_uuid)
    {
        return md5(($this->container->get('secret')['salt'] ?? '') . is_string($user_uuid) ? $user_uuid : $user_uuid->toString());
    }

    /**
     * Check if user online
     *
     * @param $user_uuid
     *
     * @return bool
     */
    protected function isOnline($user_uuid)
    {
        if ($user_uuid) {
            $result = @$this->http(['url' => '/channels-stats?id=' . $this->getChannel($user_uuid)]);
            $stat = json_decode($result, true);
            if ($stat && $stat['subscribers'] > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make http request to stream service
     *
     * @param array $data
     *
     * @return false|string
     */
    protected function http(array $data = [])
    {
        $default = [
            'url' => '',
            'params' => '',
        ];
        $data = array_merge($default, $data);

        return file_get_contents('http://' . $_ENV['SERVER_ADDR'] . ':' . $_ENV['SERVER_PORT'] . $data['url'], false, stream_context_create([
            'http' =>
                [
                    'method' => $data['params'] ? 'POST' : 'GET',
                    'header' => 'Content-type: application/' . ($data['params'] ? 'x-www-form-urlencoded' : 'json'),
                    'content' => $data['params'] ? $data['params'] : '',
                    'timeout' => 30,
                ],
        ]));
    }
}

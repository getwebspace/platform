<?php declare(strict_types=1);

namespace App\Domain\Tasks;

use App\Domain\AbstractTask;

class SendJSONTask extends AbstractTask
{
    public const TITLE = 'Отправка JSON';

    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'url' => '',
            'data' => [],
            'files' => [],
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    /**
     * @throws \App\Domain\Service\Task\Exception\TaskNotFoundException
     */
    protected function action(array $args = []): void
    {
        $data = (array) $args['data'];

        if ($args['files']) {
            $data['files'] = $args['files'];
        }

        $result = file_get_contents($args['url'], false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => json_encode($data),
                'header' => 'Content-Type: application/json;' . PHP_EOL . 'Accept: application/json' . PHP_EOL,
                'timeout' => 30,
            ],
        ]));

        if ($result !== false) {
            $this->container->get(\App\Application\PubSub::class)->publish('task:json:send');

            $this->setStatusDone($result);
        } else {
            $this->setStatusFail();
        }
    }
}

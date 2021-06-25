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
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    protected function action(array $args = []): void
    {
        $result = file_get_contents($args['url'], false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => json_encode((array) $args['data']),
                'header' => 'Content-Type: application/json;' . PHP_EOL . 'Accept: application/json' . PHP_EOL,
                'timeout' => 60,
            ],
        ]));

        if ($result !== false) {
            $this->setStatusDone($result);
        } else {
            $this->setStatusFail();
        }
    }
}

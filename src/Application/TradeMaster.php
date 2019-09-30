<?php

namespace App\Application;

use AEngine\Entity\Collection;
use Doctrine\ORM\EntityManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

class TradeMaster
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var Collection
     */
    public $params;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $container->get('monolog');
        $this->entityManager = $container->get(\Doctrine\ORM\EntityManager::class);

        // получение параметров интеграции

        /** @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository $parametersRepository */
        $parametersRepository = $this->entityManager->getRepository(\App\Domain\Entities\Parameter::class);

        $this->params = collect($parametersRepository->findBy([
            'key' => [
                'integration_trademaster_cache_folder', 'integration_trademaster_cache_host',
                'integration_trademaster_checkout', 'integration_trademaster_contractor',
                'integration_trademaster_currency', 'integration_trademaster_host',
                'integration_trademaster_key', 'integration_trademaster_legal',
                'integration_trademaster_scheme', 'integration_trademaster_storage',
                'integration_trademaster_struct', 'integration_trademaster_user',
                'integration_trademaster_version',
                'catalog_category_template', 'catalog_product_template',
            ],
        ]))->pluck('value', 'key');
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function api(array $data = [])
    {
        $default = [
            'endpoint' => '',
            'params' => [],
            'method' => 'GET',
        ];
        $data = array_merge($default, $data);
        $data['method'] = strtoupper($data['method']);

        if (($key = $this->params->get('integration_trademaster_key', null)) != null) {
            $pathParts = [$this->params->get('integration_trademaster_host'), 'v' . $this->params->get('integration_trademaster_version'), $data['endpoint']];

            if ($data['method'] == "GET") {
                $data['params']['apikey'] = $key;
                $path = implode('/', $pathParts) . '?' . http_build_query($data['params']);

                $result = file_get_contents($path);
            } else {
                $path = implode('/', $pathParts) . '?' . http_build_query(['apikey' => $key]);

                $result = file_get_contents($path, false, stream_context_create([
                    'http' =>
                        [
                            'method' => 'POST',
                            'header' => 'Content-type: application/x-www-form-urlencoded',
                            'content' => http_build_query($data['params']),
                            'timeout' => 60,
                        ],
                ]));
            }

            return json_decode($result, true);
        }

        return [];
    }

    /**
     * Возвращает путь до удаленного файла по имени файла
     *
     * @param string $name
     *
     * @return string
     */
    public function getFilePath(string $name)
    {
        $entities = ['%20', '%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'];
        $replacements = [' ', '!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]"];

        $name = str_replace($entities, $replacements, urlencode($name));

        return $this->params->get('integration_trademaster_cache_host') . '/tradeMasterImages/' . $this->params->get('integration_trademaster_cache_folder') . '/' . trim($name);
    }
}

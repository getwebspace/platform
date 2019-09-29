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

    //    /**
    //     * Загружает файлы
    //     *
    //     * @param \App\Domain\Entities\Catalog\Category|\App\Domain\Entities\Catalog\Product $model
    //     * @param string                                                                     $photo
    //     *
    //     * @throws \Doctrine\ORM\ORMException
    //     */
    //    public function proccess_photo($model, string $photo)
    //    {
    //        foreach (explode(';', $photo) as $name) {
    //            $remotePath = $this->params->get('cache_host') . '/tradeMasterImages/' . $this->params->get('cache_folder') . '/' . str_replace(' ', '%20', trim($name));
    //            $file = $this->getFileIfExists($remotePath);
    //
    //            if ($file) {
    //                $hash = sha1($file);
    //
    //                if (!$this->fileRepository->count(['hash' => $hash])) {
    //                    $file_info = pathinfo($remotePath);
    //                    $salt = uniqid();
    //                    $name = Str::translate(strtolower($file_info['basename']));
    //                    $path = UPLOAD_DIR . '/' . $salt;
    //
    //                    if (!file_exists($path)) {
    //                        mkdir($path);
    //                    }
    //
    //                    file_put_contents($path . '/' . $name, $file);
    //
    //                    // create model
    //                    $file_model = new \App\Domain\Entities\File([
    //                        'name' => $name,
    //                        'type' => filetype($path),
    //                        'size' => filesize($path),
    //                        'salt' => $salt,
    //                        'hash' => $hash,
    //                        'date' => new \DateTime(),
    //                        'item' => is_a($model, \App\Domain\Entities\Catalog\Category::class) ? \App\Domain\Types\FileItemType::ITEM_CATALOG_CATEGORY : \App\Domain\Types\FileItemType::ITEM_CATALOG_PRODUCT,
    //                        'item_uuid' => $model->uuid,
    //                    ]);
    //
    //                    // save model
    //                    $this->entityManager->persist($file_model);
    //                }
    //            }
    //        }
    //    }

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
     * Загружает файл в временную папку
     *
     * @param string $name
     *
     * @return array
     */
    public function getFile(string $name)
    {
        $remotePath = $this->params->get('cache_host') . '/tradeMasterImages/' . $this->params->get('integration_trademaster_cache_folder') . '/' . str_replace(' ', '%20', trim($name));
        $file = $this->downloadFileIfExists($remotePath);

        if ($file) {
            $info = pathinfo($remotePath);
            $salt = uniqid();
            $name = \AEngine\Support\Str::translate(strtolower($info['basename']));
            $path = CACHE_DIR . '/' . $salt;

            if (!file_exists($path)) {
                mkdir($path);
            }

            file_put_contents($path . '/' . $name, $file);

            return [
                'path' => $path . '/',
                'file' => $path . '/' . $name,
                'info' => $info,
                'type' => filetype($path . '/' . $name),
                'size' => filesize($path . '/' . $name),
                'hash' => sha1_file($path . '/' . $name),
            ];
        }

        return [];
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    public function removeFile(string $path)
    {
        if (file_exists($path)) {
            $info = pathinfo($path);

            unlink($path);
            unlink($info['dirname']);

            return true;
        }

        return false;
    }

    /**
     * @param string $path
     * @param string $to
     *
     * @return bool
     */
    public function moveFile(string $path, string $to)
    {
        if (file_exists($path)) {
            if (!file_exists($to)) {
                mkdir($to);
            }
            copy($path, $to);

            return $this->removeFile($path);
        }

        return false;
    }

    protected function downloadFileIfExists($path)
    {
        $entities = ['%21', '%2A', '%27', '%28', '%29', '%3B', '%3A', '%40', '%26', '%3D', '%2B', '%24', '%2C', '%2F', '%3F', '%25', '%23', '%5B', '%5D'];
        $replacements = ['!', '*', "'", "(", ")", ";", ":", "@", "&", "=", "+", "$", ",", "/", "?", "%", "#", "[", "]"];

        $path = str_replace($entities, $replacements, urlencode($path));

        $headers = get_headers($path);
        $code = substr($headers[0], 9, 3);

        if ($code == 200) {
            return @file_get_contents($path);
        }

        $this->logger->error('File upload failed from remote', ['code' => $code, 'path' => $path]);

        return null;
    }
}

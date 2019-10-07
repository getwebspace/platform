<?php

namespace App\Domain\Tasks\TradeMaster;

use App\Domain\Tasks\Task;

class SendOrderTask extends Task
{
    public function execute(array $params = []): \App\Domain\Entities\Task
    {
        $default = [
            'item' => '',
        ];
        $params = array_merge($default, $params);

        return parent::execute($params);
    }

    /**
     * @var \App\Application\TradeMaster
     */
    protected $trademaster;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $productRepository;

    /**
     * @var \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository
     */
    protected $orderRepository;

    protected function action(array $args = [])
    {
        $this->trademaster = $this->container->get('trademaster');
        $this->productRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Product::class);
        $this->orderRepository = $this->entityManager->getRepository(\App\Domain\Entities\Catalog\Order::class);

        /**
         * @var \App\Domain\Entities\Catalog\Order $order
         */
        $order = $this->orderRepository->findOneBy(['uuid' => $args['uuid']]);
        if ($order) {
            $products = [];

            /** @var \App\Domain\Entities\Catalog\Product $model */
            foreach ($this->productRepository->findBy(['uuid' => array_keys($order->list)]) as $model) {
                if ($model->external_id) {
                    $quantity = $order->list[$model->uuid->toString()];
                    $products[] = [
                        'id' => $model->external_id,
                        'name' => $model->title,
                        'quantity' => $quantity,
                        'price' => (float)$model->price * $quantity,
                    ];
                }
            }

            $result = $this->trademaster->api([
                'method' => 'POST',
                'endpoint' => 'order/cart/anonym',
                'params' => [
                    'sklad' => $this->getParameter('integration_trademaster_storage'),
                    'urlico' => $this->getParameter('integration_trademaster_legal'),
                    'ds' => $this->getParameter('integration_trademaster_checkout'),
                    'kontragent' => $this->getParameter('integration_trademaster_contractor'),
                    'shema' => $this->getParameter('integration_trademaster_scheme'),
                    'valuta' => $this->getParameter('integration_trademaster_currency'),
                    'userID' => $this->getParameter('integration_trademaster_user'),
                    'nameKontakt' => $order->delivery['client'] ?? '',
                    'adresKontakt' => $order->delivery['address'] ?? '',
                    'telefonKontakt' => $order->phone,
                    'other1Kontakt' => $order->email,
                    'dateDost' => $order->shipping->format('Y-m-d H:i:s'),
                    'komment' => $order->comment,
                    'tovarJson' => json_encode($products, JSON_UNESCAPED_UNICODE),
                ],
            ]);

            if ($result && !empty($result['nomerZakaza'])) {
                $order->external_id = $result['nomerZakaza'];

                $this->entityManager->persist($order);
                $this->status_done();

                return;
            }
        }

        $this->status_fail();
    }
}

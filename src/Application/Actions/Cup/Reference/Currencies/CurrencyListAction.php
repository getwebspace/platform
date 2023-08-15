<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference\Currencies;

use App\Application\Actions\Cup\Reference\ReferenceAction;
use App\Domain\Types\ReferenceTypeType;

class CurrencyListAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithTemplate('cup/reference/currencies/index.twig', [
            'list' => $this->referenceService->read([
                'type' => ReferenceTypeType::TYPE_CURRENCY,
            ]),
        ]);
    }
}

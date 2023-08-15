<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

class ReferenceListAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $type = $this->resolveArg('type');

        return $this->respondWithTemplate("cup/reference/{$type}/index.twig", [
            'list' => $this->referenceService->read([
                'type' => $this->getReferenceType($type),
            ])
        ]);
    }
}

<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

class ReferenceListAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $entity = $this->resolveArg('entity');

        return $this->respondWithTemplate("cup/reference/{$entity}/index.twig", [
            'list' => $this->referenceService->read([
                'type' => $this->getReferenceType($entity),
            ]),
        ]);
    }
}

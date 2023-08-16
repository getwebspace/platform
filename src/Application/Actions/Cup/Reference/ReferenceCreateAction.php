<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;

class ReferenceCreateAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $entity = $this->resolveArg('entity');

        if ($this->isPost()) {
            try {
                $ref = $this->referenceService->create([
                    'type' => $this->getReferenceType($entity),
                    'title' => $this->getParam('title'),
                    'value' => $this->getParam('value', []),
                    'order' => $this->getParam('order'),
                    'status' => $this->getParam('status'),
                ]);

                $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:create', $ref);

                switch (true) {
                    case $this->getParam('save', 'exit') === 'exit':
                        return $this->respondWithRedirect("/cup/reference/{$entity}");

                    default:
                        return $this->respondWithRedirect("/cup/reference/{$entity}/{$ref->getUuid()}/edit");
                }
            } catch (MissingTitleValueException|TitleAlreadyExistsException $e) {
                $this->addError('title', $e->getMessage());
            }
        }

        return $this->respondWithTemplate("cup/reference/{$entity}/form.twig", [
            'list' => $this->referenceService->read(['type' => $this->getReferenceType($entity)]),
        ]);
    }
}

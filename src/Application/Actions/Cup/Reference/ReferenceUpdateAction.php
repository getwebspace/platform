<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\Service\Reference\Exception\MissingTitleValueException;
use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Reference\Exception\WrongTitleValueException;

class ReferenceUpdateAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $entity = $this->resolveArg('entity');
        $type = $this->resolveReferenceType($entity);

        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $ref = $this->referenceService->read([
                    'uuid' => $this->resolveArg('uuid'),
                    'type' => $type,
                ]);

                if ($this->isPost()) {
                    try {
                        $ref = $this->referenceService->update($ref, [
                            'type' => $type,
                            'title' => $this->getParam('title'),
                            'value' => $this->getParam('value', []),
                            'order' => $this->getParam('order'),
                            'status' => $this->getParam('status'),
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:edit', $ref);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect("/cup/reference/{$entity}");

                            default:
                                return $this->respondWithRedirect("/cup/reference/{$entity}/{$ref->uuid}/edit");
                        }
                    } catch (MissingTitleValueException|TitleAlreadyExistsException|WrongTitleValueException $e) {
                        $this->addError('title', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate("cup/reference/{$entity}/form.twig", [
                    'item' => $ref,
                    'list' => $this->referenceService->read([
                        'type' => $type,
                    ]),
                ]);
            } catch (ReferenceNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect("/cup/reference/{$entity}");
    }
}

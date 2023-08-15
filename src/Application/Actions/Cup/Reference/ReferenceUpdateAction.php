<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Reference;

use App\Domain\Service\Reference\Exception\ReferenceNotFoundException;
use App\Domain\Service\Reference\Exception\TitleAlreadyExistsException;

class ReferenceUpdateAction extends ReferenceAction
{
    protected function action(): \Slim\Psr7\Response
    {
        $type = $this->resolveArg('type');

        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $ref = $this->referenceService->read([
                    'uuid' => $this->resolveArg('uuid'),
                    'type' => $this->getReferenceType($type),
                ]);

                if ($this->isPost()) {
                    try {
                        $ref = $this->referenceService->update($ref, [
                            'type' => $this->getReferenceType($type),
                            'title' => $this->getParam('title'),
                            'value' => $this->getParam('value'),
                            'order' => $this->getParam('order'),
                            'status' => $this->getParam('status'),
                        ]);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:reference:edit', $ref);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->respondWithRedirect("/cup/reference/{$type}");

                            default:
                                return $this->respondWithRedirect("/cup/reference/{$type}/{$ref->getUuid()}/edit");
                        }
                    } catch (TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate("cup/reference/{$type}/form.twig", [
                    'item' => $ref,
                ]);
            } catch (ReferenceNotFoundException $e) {
                // nothing
            }
        }

        return $this->respondWithRedirect("/cup/reference/{$type}");
    }
}

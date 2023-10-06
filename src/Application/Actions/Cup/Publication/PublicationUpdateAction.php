<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingCategoryValueException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Publication\Exception\WrongTitleValueException;

class PublicationUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publication = $this->publicationService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($publication) {
                if ($this->isPost()) {
                    try {
                        $publication = $this->publicationService->update($publication, [
                            'user' => $this->request->getAttribute('user'),
                            'title' => $this->getParam('title'),
                            'address' => $this->getParam('address'),
                            'date' => $this->getParam('date'),
                            'category_uuid' => $this->getParam('category'),
                            'content' => $this->getParam('content'),
                            'poll' => $this->getParam('poll'),
                            'meta' => $this->getParam('meta'),
                            'external_id' => $this->getParam('external_id'),
                        ]);
                        $publication = $this->processEntityFiles($publication);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:publication:edit', $publication);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);

                            default:
                                return $this->response->withAddedHeader('Location', '/cup/publication/' . $publication->getUuid() . '/edit')->withStatus(301);
                        }
                    } catch (MissingTitleValueException|WrongTitleValueException|TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (MissingCategoryValueException $e) {
                        $this->addError('category', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/publication/form.twig', [
                    'list' => $this->publicationCategoryService->read(),
                    'publication' => $publication,
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);
    }
}

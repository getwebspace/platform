<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;

class PublicationUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publication = $this->publicationService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($publication) {
                if ($this->request->isPost()) {
                    try {
                        $publication = $this->publicationService->update($publication, [
                            'user' => $this->request->getAttribute('user'),
                            'title' => $this->request->getParam('title'),
                            'address' => $this->request->getParam('address'),
                            'date' => $this->request->getParam('date'),

                            'category' => $this->publicationCategoryService->read([
                                'uuid' => $this->request->getParam('category'),
                            ]),
                            'content' => $this->request->getParam('content'),
                            'poll' => $this->request->getParam('poll'),
                            'meta' => $this->request->getParam('meta'),
                        ]);
                        $publication = $this->processEntityFiles($publication);

                        switch (true) {
                            case $this->request->getParam('save', 'exit') === 'exit':
                                return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);

                            default:
                                return $this->response->withAddedHeader('Location', '/cup/publication/' . $publication->getUuid() . '/edit')->withStatus(301);
                        }
                    } catch (TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
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

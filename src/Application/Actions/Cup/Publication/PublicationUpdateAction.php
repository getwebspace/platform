<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Publication\PublicationService;

class PublicationUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publicationCategoryService = PublicationCategoryService::getFromContainer($this->container);
            $publicationService = PublicationService::getFromContainer($this->container);
            $publication = $publicationService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($publication) {
                if ($this->request->isPost()) {
                    try {
                        $publication = $publicationService->update($publication, [
                            'title' => $this->request->getParam('title'),
                            'address' => $this->request->getParam('address'),
                            'date' => $this->request->getParam('date'),
                            'category' => $this->request->getParam('category'),
                            'content' => $this->request->getParam('content'),
                            'poll' => $this->request->getParam('poll'),
                            'meta' => $this->request->getParam('meta'),
                        ]);
                        $publication = $this->handlerEntityFiles($publication);

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
                    'list' => $publicationCategoryService->read(),
                    'publication' => $publication,
                ]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication')->withStatus(301);
    }
}

<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication;

use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;

class PublicationCreateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $publication = $this->publicationService->create([
                    'user' => $this->request->getAttribute('user'),
                    'title' => $this->request->getParam('title'),
                    'address' => $this->request->getParam('address'),
                    'date' => $this->request->getParam('date'),
                    'category' => $this->request->getParam('category'),
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
            } catch (MissingTitleValueException | TitleAlreadyExistsException $e) {
                $this->addError('title', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/publication/form.twig', ['list' => $this->publicationCategoryService->read()]);
    }
}

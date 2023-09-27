<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Publication\Exception\WrongTitleValueException;

class CategoryUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publicationCategory = $this->publicationCategoryService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($publicationCategory) {
                if ($this->isPost()) {
                    try {
                        $publicationCategory = $this->publicationCategoryService->update($publicationCategory, [
                            'title' => $this->getParam('title'),
                            'address' => $this->getParam('address'),
                            'description' => $this->getParam('description'),
                            'parent' => $this->getParam('parent'),
                            'public' => $this->getParam('public'),
                            'children' => $this->getParam('children'),
                            'pagination' => $this->getParam('pagination'),
                            'sort' => $this->getParam('sort'),
                            'meta' => $this->getParam('meta'),
                            'template' => $this->getParam('template'),
                        ]);
                        $publicationCategory = $this->processEntityFiles($publicationCategory);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:publication:category:edit', $publicationCategory);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);

                            default:
                                return $this->response->withAddedHeader('Location', '/cup/publication/category/' . $publicationCategory->getUuid() . '/edit')->withStatus(301);
                        }
                    } catch (MissingTitleValueException|WrongTitleValueException|TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                return $this->respondWithTemplate('cup/publication/category/form.twig', ['list' => $this->publicationCategoryService->read(), 'item' => $publicationCategory]);
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
    }
}

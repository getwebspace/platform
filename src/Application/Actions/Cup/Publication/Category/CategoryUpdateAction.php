<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\CategoryNotFoundException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Publication\Exception\WrongTitleValueException;

class CategoryUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Psr7\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            try {
                $category = $this->publicationCategoryService->read([
                    'uuid' => $this->resolveArg('uuid'),
                ]);

                if ($this->isPost()) {
                    try {
                        $category = $this->publicationCategoryService->update($category, [
                            'title' => $this->getParam('title'),
                            'address' => $this->getParam('address'),
                            'description' => $this->getParam('description'),
                            'parent_uuid' => $this->getParam('parent'),
                            'public' => $this->getParam('public'),
                            'children' => $this->getParam('children'),
                            'pagination' => $this->getParam('pagination'),
                            'sort' => $this->getParam('sort'),
                            'meta' => $this->getParam('meta'),
                            'template' => $this->getParam('template'),
                        ]);
                        $category = $this->processEntityFiles($category);

                        $this->container->get(\App\Application\PubSub::class)->publish('cup:publication:category:edit', $category);

                        switch (true) {
                            case $this->getParam('save', 'exit') === 'exit':
                                return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);

                            default:
                                return $this->response->withAddedHeader('Location', '/cup/publication/category/' . $category->getUuid() . '/edit')->withStatus(301);
                        }
                    } catch (MissingTitleValueException|WrongTitleValueException|TitleAlreadyExistsException $e) {
                        $this->addError('title', $e->getMessage());
                    } catch (AddressAlreadyExistsException $e) {
                        $this->addError('address', $e->getMessage());
                    }
                }

                $categories = $this->publicationCategoryService->read();

                return $this->respondWithTemplate('cup/publication/category/form.twig', [
                    'categories' => $categories,
                    'category' => $category,
                ]);
            } catch (CategoryNotFoundException $e) {
                // nothing
            }
        }

        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
    }
}

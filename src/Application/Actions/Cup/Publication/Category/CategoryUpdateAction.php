<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use App\Domain\Service\Publication\CategoryService as PublicationCategoryService;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;

class CategoryUpdateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->resolveArg('uuid') && \Ramsey\Uuid\Uuid::isValid($this->resolveArg('uuid'))) {
            $publicationCategory = $this->publicationCategoryService->read(['uuid' => $this->resolveArg('uuid')]);

            if ($publicationCategory) {
                if ($this->request->isPost()) {
                    try {
                        $publicationCategory = $this->publicationCategoryService->update($publicationCategory, [
                            'title' => $this->request->getParam('title'),
                            'address' => $this->request->getParam('address'),
                            'description' => $this->request->getParam('description'),
                            'parent' => $this->request->getParam('parent'),
                            'public' => $this->request->getParam('public'),
                            'children' => $this->request->getParam('children'),
                            'pagination' => $this->request->getParam('pagination'),
                            'sort' => $this->request->getParam('sort'),
                            'meta' => $this->request->getParam('meta'),
                            'template' => $this->request->getParam('template'),
                        ]);
                        $publicationCategory = $this->handlerEntityFiles($publicationCategory);

                        switch (true) {
                            case $this->request->getParam('save', 'exit') === 'exit':
                                return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
                            default:
                                return $this->response->withAddedHeader('Location', '/cup/publication/category/' . $publicationCategory->getUuid() . '/edit')->withStatus(301);
                        }
                    } catch (TitleAlreadyExistsException $e) {
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

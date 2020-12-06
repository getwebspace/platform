<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Publication\Category;

use App\Application\Actions\Cup\Publication\PublicationAction;
use App\Domain\Service\Publication\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Publication\Exception\MissingTitleValueException;
use App\Domain\Service\Publication\Exception\TitleAlreadyExistsException;

class CategoryCreateAction extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            try {
                $publicationCategory = $this->publicationCategoryService->create([
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
                $publicationCategory = $this->processEntityFiles($publicationCategory);

                switch (true) {
                    case $this->request->getParam('save', 'exit') === 'exit':
                        return $this->response->withAddedHeader('Location', '/cup/publication/category')->withStatus(301);
                    default:
                        return $this->response->withAddedHeader('Location', '/cup/publication/category/' . $publicationCategory->getUuid() . '/edit')->withStatus(301);
                }
            } catch (MissingTitleValueException|TitleAlreadyExistsException $e) {
                $this->addError('title', $e->getMessage());
            } catch (AddressAlreadyExistsException $e) {
                $this->addError('address', $e->getMessage());
            }
        }

        return $this->respondWithTemplate('cup/publication/category/form.twig', ['list' => $this->publicationCategoryService->read()]);
    }
}

<?php declare(strict_types=1);

namespace App\Application\Actions\Api\Publication;

class Category extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        $categories = from_service_to_array($this->publicationCategoryService->read([
            'uuid' => $this->request->getParam('uuid'),
            'parent' => $this->request->getParam('parent'),
            'address' => $this->request->getParam('address'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ]));

        /** @var \App\Domain\Entities\Publication\Category $category */
        foreach ($categories as &$category) {
            $files = [];

            /** @var \App\Domain\Entities\File $file */
            foreach ($category->getFiles() as $file) {
                $files[] = [
                    'full' => $file->getPublicPath('full'),
                    'middle' => $file->getPublicPath('middle'),
                    'small' => $file->getPublicPath('small'),
                ];
            }

            $category = $category->toArray();
            $category['files'] = $files;
        }

        return $this->respondWithJson($categories);
    }
}

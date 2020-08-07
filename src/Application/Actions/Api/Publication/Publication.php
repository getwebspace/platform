<?php declare(strict_types=1);

namespace App\Application\Actions\Api\Publication;

class Publication extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        $publications = from_service_to_array($this->publicationService->read([
            'uuid' => $this->request->getParam('uuid'),
            'category' => $this->request->getParam('category'),
            'address' => $this->request->getParam('address'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ]));

        /** @var \App\Domain\Entities\Publication $publication */
        foreach ($publications as &$publication) {
            $files = [];

            /** @var \App\Domain\Entities\File $file */
            foreach ($publication->getFiles() as $file) {
                $files[] = [
                    'full' => $file->getPublicPath('full'),
                    'middle' => $file->getPublicPath('middle'),
                    'small' => $file->getPublicPath('small'),
                ];
            }

            $publication = $publication->toArray();
            $publication['files'] = $files;

            unset($publication['poll']);
        }

        return $this->respondWithJson($publications);
    }
}

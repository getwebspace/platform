<?php declare(strict_types=1);

namespace App\Application\Actions\Api\Publication;

class Publication extends PublicationAction
{
    protected function action(): \Slim\Http\Response
    {
        $data = [
            'uuid' => $this->request->getParam('uuid'),
            'category' => $this->request->getParam('category'),
            'address' => $this->request->getParam('address'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ];

        $criteria = [];

        if ($data['uuid']) {
            $criteria['uuid'] = $this->array_criteria_uuid($data['uuid']);
        }
        if ($data['category']) {
            $criteria['category'] = $this->array_criteria_uuid($data['category']);
        }
        if ($data['address']) {
            $criteria['address'] = urldecode($data['address']);
        }

        $publications = $this->publicationRepository->findBy($criteria, $data['order'], $data['limit'], $data['offset']);

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

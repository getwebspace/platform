<?php declare(strict_types=1);

namespace App\Application\Actions\Api\User;

class Info extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        $users = from_service_to_array($this->userService->read([
            'uuid' => $this->request->getParam('uuid'),
            'identifier' => $this->request->getParam('identifier'),
            'username' => $this->request->getParam('username'),
            'email' => $this->request->getParam('email'),
            'phone' => $this->request->getParam('phone'),
            'status' => $this->request->getParam('status'),
            'ip' => $this->request->getParam('ip'),

            'order' => $this->request->getParam('order', []),
            'limit' => $this->request->getParam('limit', 1000),
            'offset' => $this->request->getParam('offset', 0),
        ]));

        /** @var \App\Domain\Entities\User $user */
        foreach ($users as &$user) {
            $files = [];

            /** @var \App\Domain\Entities\File $file */
            foreach ($user->getFiles() as $file) {
                $files[] = [
                    'full' => $file->getPublicPath('full'),
                    'middle' => $file->getPublicPath('middle'),
                    'small' => $file->getPublicPath('small'),
                ];
            }

            $user = $user->toArray();
            $user['files'] = $files;
            unset($user['password'], $user['session']);
        }

        return $this->respondWithJson($users);
    }
}

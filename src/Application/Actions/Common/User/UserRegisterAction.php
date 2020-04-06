<?php declare(strict_types=1);

namespace App\Application\Actions\Common\User;

use Ramsey\Uuid\Uuid;

class UserRegisterAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        if ($this->request->isPost()) {
            $data = [
                'email' => $this->request->getParam('email'),
                'username' => $this->request->getParam('username'),
                'password' => $this->request->getParam('password'),
                'password_again' => $this->request->getParam('password_again'),
            ];

            $check = \App\Domain\Filters\User::check($data);

            if ($check === true) {
                if ($this->isRecaptchaChecked()) {
                    $uuid = Uuid::uuid4();
                    $session = new \App\Domain\Entities\User\Session();
                    $session->set('uuid', $uuid);
                    $this->entityManager->persist($session);

                    $model = new \App\Domain\Entities\User($data);
                    $model->set('uuid', $uuid);
                    $model->register = $model->change = new \DateTime();
                    $model->session = $session;
                    $this->entityManager->persist($model);

                    $this->entityManager->flush();

                    return $this->response->withAddedHeader('Location', '/user/login')->withStatus(301);
                }
                $this->addError('grecaptcha', \App\Domain\References\Errors\Common::WRONG_GRECAPTCHA);
            } else {
                $this->addErrorFromCheck($check);
            }
        }

        return $this->respondRender($this->getParameter('user_register_template', 'user.register.twig'));
    }
}

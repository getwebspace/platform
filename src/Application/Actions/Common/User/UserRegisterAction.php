<?php

namespace App\Application\Actions\Common\User;

class UserRegisterAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender($this->getParameter('user_register_template', 'user.register.twig'));
    }
}

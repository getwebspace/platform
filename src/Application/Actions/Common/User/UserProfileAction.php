<?php

namespace App\Application\Actions\Common\User;

class UserProfileAction extends UserAction
{
    protected function action(): \Slim\Http\Response
    {
        return $this->respondRender($this->getParameter('user_profile_template', 'user.profile.twig'));
    }
}

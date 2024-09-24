<?php declare(strict_types=1);

namespace App\Application\Actions\Api\v1;

use App\Application\Actions\Api\ActionApi;
use App\Domain\Traits\UseSecurity;

class SignatureAction extends ActionApi
{
    use UseSecurity;

    protected function action(): \Slim\Psr7\Response
    {
        return $this->respondWithText($this->getPublicKey());
    }
}

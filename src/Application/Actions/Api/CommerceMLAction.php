<?php declare(strict_types=1);

namespace App\Application\Actions\Api;

use Alksily\Support\Crypta;

class CommerceMLAction extends ActionApi
{
    protected const COOKIE_NAME = 'WSE_CML';
    protected const COOKIE_TIME = 10; // minutes
    protected const MAX_FILE_SIZE = 100 * 1000 * 1000; // bytes

    protected function action(): \Slim\Http\Response
    {
        $this->logger->debug('1c', [
            'method' => $this->request->getMethod(),
            'auth' => $this->request->getUri()->getAuthority(),
            'cookie' => $this->request->getCookieParams(),
            'post' => $this->request->getParams(),
        ]);

        if ($this->isSecure()) {
            switch ($this->request->getParam('type')) {
                case 'catalog':
                    switch ($this->request->getParam('mode')) {
                        case 'checkauth':
                            return $this->respondWithText(['success', self::COOKIE_NAME, $this->getCookieValue()]);

                        case 'init':
                            return $this->respondWithText(['zip=yes', 'file_limit=' . self::MAX_FILE_SIZE]);

                        case 'file':
                            if (($file = $this->getFileFromBody($this->request->getParam('filename', 'import.xml'))) !== null) {

                                return $this->respondWithText('success');
                            }

                            return $this->respondWithText('failed');
                    }

                    break;

                case 'sale':

                    break;
            }

            return $this->respondWithText('wrong method')->withStatus(405);
        }

        return $this->respondWithText('forbidden')->withStatus(403);
    }

    // check user info or cookie value
    protected function isSecure(): bool
    {
        $userInfo = implode(':', [$this->parameter('cml_login', ''), $this->parameter('cml_password', '')]);

        return $this->request->getUri()->getUserInfo() === $userInfo || $this->checkCookieIsAlive();
    }

    // get cookie secure value
    protected function getCookieValue(): string
    {
        return Crypta::encrypt('' . time());
    }

    // check cookie is alive
    protected function checkCookieIsAlive(): bool
    {
        $value = $this->request->getCookieParam(self::COOKIE_NAME);

        if ($value) {
            return (time() - Crypta::decrypt($value)) / 60 <= self::COOKIE_TIME;
        }

        return false;
    }
}

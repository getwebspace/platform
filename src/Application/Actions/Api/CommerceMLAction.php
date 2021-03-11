<?php declare(strict_types=1);

namespace App\Application\Actions\Api;

class CommerceMLAction extends ActionApi
{
    protected function action(): \Slim\Http\Response
    {
        $this->logger->debug('1c', [
            'method' => $this->request->getMethod(),
            'auth' => $this->request->getUri()->getAuthority(),
            'cookie' => $this->request->getCookieParams(),
            'post' => $this->request->getParams(),
        ]);

        $cookie_name = 'wse_1c';
        $cookie_value = 'wse_1c_value';

        switch ($this->request->getParam('type')) {
            case 'catalog':
                switch ($this->request->getParam('mode')) {
                    case 'checkauth':
                        return $this->response->write(
                            implode("\n", [
                                'success',
                                $cookie_name,
                                $cookie_value,
                            ])
                        );

                    case 'init':
                        return $this->response->write(
                            implode("\n", [
                                'zip=no',
                                'file_limit=' . (100 * 1000 * 1000),
                            ])
                        );

                    case 'file':
                        $path = UPLOAD_DIR . '/' . $this->request->getParam('filename', 'import.xml');

                        file_put_contents($path, $this->request->getBody()->getContents());

                        return $this->response->write('success');
                }

                break;

            case 'sale':

                break;
        }

        return $this->response->write('test');
    }
}

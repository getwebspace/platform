<?php

namespace Application\Core;

use Closure;
use DateTime;
use Ramsey\Uuid\Uuid;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

class Auth
{
    /**
     * Текущий пользрватель
     *
     * @var \Entity\User|null
     */
    public static $user = null;

    /**
     * Прослойка авторизации
     *
     * @param Request  $request
     * @param Response $response
     * @param Closure  $next
     *
     * @return mixed
     */
    public function __invoke(Request $request, Response $response, $next)
    {
        /** @var App $app */
        $app = $GLOBALS['app'];
        $data = [
            'uuid' => $request->getCookieParam('uuid', null),
            'session' => $request->getCookieParam('session', null),
        ];

        if ($data['uuid'] && Uuid::isValid($data['uuid']) && $data['session']) {
            $container = $app->getContainer();

            /** @var \Entity\User\Session $session */
            $session = $container->get(\Resource\User\Session::class)->fetchOne(['uuid' => $data['uuid']]);

            if ($session && $data['session'] === static::session($session)) {
                static::$user = $container->get(\Resource\User::class)->fetchOne([
                    'uuid' => $session->uuid,
                    'status' => \Reference\User::STATUS_WORK,
                ]);
            }
        }

        return $next($request, $response);
    }

    /**
     * Возвращает ключ сессии
     *
     * @param \Entity\User\Session $model
     *
     * @return string
     */
    public static function session(\Entity\User\Session $model)
    {
        if (!$model->isEmpty()) {
            $default = [
                'uuid' => null,
                'ip' => null,
                'agent' => null,
                'date' => new DateTime(),
            ];
            $data = array_merge($default, $model->toArray());

            return sha1(
                'salt:' . Common::$salt . ';' .
                'uuid:' . $data['uuid'] . ';' .
                'ip:' . md5($data['ip']) . ';' .
                'agent:' . md5($data['agent']) . ';' .
                'date:' . $data['date']->getTimestamp()
            );
        }

        return null;
    }

    /**
     * Возвращает хэш от строки
     *
     * @param string $str
     *
     * @return string хэш
     */
    public static function hash(string $str)
    {
        return crypta_hash($str, Common::$salt);
    }

    /**
     * Возвращает хэш от строки
     *
     * @param string $str
     * @param string $hashStr
     *
     * @return string хэш
     */
    public static function hash_check(string $str, string $hashStr)
    {
        return crypta_hash_check($str, $hashStr);
    }

    /**
     * Метод проверки reCAPTCHA
     *
     * @param array $data
     *
     * @return bool
     */
    public static function checkReCAPTCHA(array $data = [])
    {
        $default = [
            'secret' => Common::get('recaptcha_private'),
            'response' => '',
            'remoteip' => '',
        ];
        $data = array_merge($default, $data);

        if (Common::get('security_recaptcha') === 'on') {
            $query = http_build_query($data);
            $verify = json_decode(file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
                                "Content-Length: ".strlen($query)."\r\n",
                    'content' => $query,
                ],
            ])));

            return $verify->success;
        }

        return true;
    }
}

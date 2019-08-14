<?php

namespace Core;

use AEngine\Entity\Collection;
use Closure;
use Ramsey\Uuid\Uuid;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

class Common
{
    // general salt
    public static $salt = 'Li8.1Ej2-<Cid3[bE';

    /**
     * @var Collection|null
     */
    public static $parameter = null;

    /**
     * Общая прослойка
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

        static::$parameter = $app->getContainer()->get(\Resource\Parameter::class)->fetch();

        return $next($request, $response);
    }

    /**
     * Возвращает значение параметра по переданному ключу
     * Если передан массив ключей, возвращает массив найденных ключей и их значения
     *
     * @param string|string[] $key
     * @param mixed  $default
     *
     * @return |null
     */
    public static function get($key, $default = null)
    {
        if (is_string($key)) {
            return static::$parameter->where('key', $key)->first()->value ?? $default;
        }

        return static::$parameter->whereIn('key', (array)$key)->pluck('value', 'key')->all() ?? $default;
    }

    /**
     * Возвращает уникальный UUID
     *
     * @return string
     */
    public static function uuid()
    {
        return Uuid::uuid5(Uuid::NAMESPACE_OID, static::$salt . microtime(true));
    }
}

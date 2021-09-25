<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Application\i18n;
use App\Domain\AbstractMiddleware;
use Slim\Http\Request;
use Slim\Http\Response;

class LocaleMiddleware extends AbstractMiddleware
{
    /**
     * @param callable $next
     *
     * @throws \Exception
     */
    public function __invoke(Request $request, Response $response, callable $next): \Slim\Http\Response
    {
        \RunTracy\Helpers\Profiler\Profiler::start('middleware:locale');

        $default_locale = $this->parameter('common_lang', 'ru');
        $user_locale = $request->getCookieParam('lang');

        // change lang by cookie
        if (($lang = $request->getParam('lang')) !== null) {
            $user_locale = $lang;
            setcookie('lang', $lang, time() + \App\Domain\References\Date::YEAR, '/');
        }

        // change lang by user settings
        /*if (!$user_locale && ($user = $request->getAttribute('user')) !== null) {
            // todo user locale
        }*/

        i18n::init([
            'locale' => i18n::getLanguageFromHeader($request->getHeaderLine('Accept-Language'), $default_locale),
            'default' => $default_locale,
            'force' => $user_locale,
        ]);

        \RunTracy\Helpers\Profiler\Profiler::finish('middleware:locale');

        return $next($request, $response);
    }
}

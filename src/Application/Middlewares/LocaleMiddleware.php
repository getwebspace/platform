<?php declare(strict_types=1);

namespace App\Application\Middlewares;

use App\Application\i18n;
use App\Domain\AbstractMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Request;

class LocaleMiddleware extends AbstractMiddleware
{
    /**
     * @throws \Exception
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): \Slim\Psr7\Response
    {
        \Netpromotion\Profiler\Profiler::start('middleware:locale');

        $default_locale = $this->parameter('common_lang', 'ru');
        $user_locale = $request->getCookieParams()['lang'] ?? null;
        $query_locale = $request->getQueryParams()['lang'] ?? null;

        // change lang by cookie
        if ($query_locale !== null) {
            $user_locale = $query_locale;
            setcookie('lang', $query_locale, time() + \App\Domain\References\Date::YEAR, '/');
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

        \Netpromotion\Profiler\Profiler::finish('middleware:locale');

        return $handler->handle($request);
    }
}

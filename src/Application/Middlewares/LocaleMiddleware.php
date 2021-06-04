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
    public function __invoke(Request $request, Response $response, $next): \Slim\Http\Response
    {
        \RunTracy\Helpers\Profiler\Profiler::start('middleware:locale');

        $default_locale = $this->parameter('common_lang', 'ru');
        $user_locale = null;

        /*if (($user = $request->getAttribute('user')) !== null) {
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

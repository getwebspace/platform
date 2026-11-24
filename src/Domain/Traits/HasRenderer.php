<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Exceptions\HttpBadRequestException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Slim\Views\Twig;

/**
 * @property \Slim\Psr7\Request $request
 * @property array $error
 */
trait HasRenderer
{
    protected Twig $renderer;

    /**
     * @throws HttpBadRequestException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    protected function render(string $template, array $data = []): string
    {
        try {
            $data = array_merge(
                [
                    'SHA' => !empty($_ENV['COMMIT_SHA']) ? mb_substr($_ENV['COMMIT_SHA'], 0, 7) : 'specific',
                    '_request' => str_escape($_REQUEST),
                    '_error' => \Alksily\Support\Form::$globalError = $this->error ?? [],
                    '_language' => \App\Application\i18n::$localeCode ?? 'en-US',
                    '_locales' => \App\Application\i18n::$accept,
                    'plugins' => $this->container->get('plugin')->get(),
                    'user' => isset($this->request) ? $this->request->getAttribute('user', false) : false,
                ],
                $data
            );

            if (($path = realpath(THEME_DIR . '/' . $this->parameter('common_theme', 'default'))) !== false) {
                $this->renderer->getLoader()->addPath($path);
            }

            // add default errors pages
            $this->renderer->getLoader()->addPath(VIEW_ERROR_DIR);

            if ($this->renderDebug()) {
                $this->assertNoExtendsCycle($template);

                // a PHP warning raised while a template renders would be printed into the page where
                // it happens (into an attribute, say) with the status 200 and nobody the wiser: it
                // becomes an error that names the template and the line
                set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
                    if (!(error_reporting() & $level)) {
                        return false;
                    }

                    throw new \ErrorException($message, 0, $level, $file, $line);
                });
            }

            try {
                return $this->injectFormGuard($this->renderer->fetch($template, $data));
            } finally {
                if ($this->renderDebug()) {
                    restore_error_handler();
                }
            }
        } catch (\Twig\Error\LoaderError|\Twig\Error\RuntimeError|\Twig\Error\SyntaxError $exception) {
            throw new HttpBadRequestException($exception->getMessage());
        }
    }

    /**
     * Whether the strict development checks of the render are on (DEBUG)
     */
    protected function renderDebug(): bool
    {
        return (bool) ($this->container->get('settings')['displayErrorDetails'] ?? false);
    }

    /**
     * A template that extends itself, directly or through others, never finishes: it ends with a
     * timeout that names no template. Follows the `{% extends 'name' %}` chain and says where it closes.
     *
     * @throws HttpBadRequestException
     */
    private function assertNoExtendsCycle(string $template): void
    {
        $loader = $this->renderer->getLoader();
        $chain = [];
        $name = $template;

        while ($name !== null) {
            try {
                $source = $loader->getSourceContext($name);
            } catch (\Twig\Error\LoaderError $e) {
                return; // a missing template is reported by Twig itself
            }

            $chain[] = $name . ' (' . ($source->getPath() ?: 'string') . ')';

            if (in_array($name, array_map(static fn (string $item) => explode(' (', $item, 2)[0], array_slice($chain, 0, -1)), true)) {
                throw new HttpBadRequestException('Template "' . $name . '" extends itself: ' . implode(' -> ', $chain));
            }

            $name = preg_match('/\{%-?\s*extends\s+([\'"])(.+?)\1\s*-?%\}/', $source->getCode(), $match) === 1 ? $match[2] : null;
        }
    }

    /**
     * Adds the spam protection script (mixin/form-guard.twig) to a public page that has forms
     *
     * Themes are not changed by hand: the script finds the forms posting to /form/{address} itself
     */
    private function injectFormGuard(string $html): string
    {
        if (
            $this->parameter('form_guard_mode', 'on') === 'off'
            || mb_stripos($html, '<form') === false
            || mb_stripos($html, 'data-form-guard') !== false
            || ($end = mb_strripos($html, '</body>')) === false
            || (isset($this->request) && str_starts_with($this->request->getUri()->getPath(), '/cup'))
        ) {
            return $html;
        }

        $script = $this->renderer->fetch('mixin/form-guard.twig', [
            'token' => (new \App\Application\FormGuard())->token(),
        ]);

        return mb_substr($html, 0, $end) . $script . mb_substr($html, $end);
    }

    protected function renderFromString(string $template, array $data = []): string
    {
        try {
            return $this->renderer->fetchFromString($template, $data);
        } catch (\Twig\Error\LoaderError|\Twig\Error\SyntaxError $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }
}

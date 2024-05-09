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
                    '_request' => &$_REQUEST,
                    '_error' => \Alksily\Support\Form::$globalError = $this->error ?? [],
                    '_language' => \App\Application\i18n::$localeCode ?? 'en-US',
                    '_locales' => \App\Application\i18n::$accept,
                    'plugins' => collect($this->container->get('plugin')->get()),
                    'user' => isset($this->request) ? $this->request->getAttribute('user', false) : false,
                ],
                $data
            );

            if (($path = realpath(THEME_DIR . '/' . $this->parameter('common_theme', 'default'))) !== false) {
                $this->renderer->getLoader()->addPath($path);
            }

            // add default errors pages
            $this->renderer->getLoader()->addPath(VIEW_ERROR_DIR);

            return $this->renderer->fetch($template, $data);
        } catch (\Twig\Error\LoaderError $exception) {
            throw new HttpBadRequestException($exception->getMessage());
        }
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

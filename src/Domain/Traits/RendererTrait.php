<?php declare(strict_types=1);

namespace App\Domain\Traits;

use App\Domain\Exceptions\HttpBadRequestException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Slim\Views\Twig;

trait RendererTrait
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
                    'sha' => mb_substr($_ENV['COMMIT_SHA'] ?? 'specific', 0, 7),
                    'NIL' => \Ramsey\Uuid\Uuid::NIL,
                    '_request' => &$_REQUEST,
                    '_error' => \Alksily\Support\Form::$globalError = $this->error,
                    '_language' => \App\Application\i18n::$localeCode ?? 'ru',
                    'plugins' => $this->container->get('plugin')->get(),
                    'user' => $this->request->getAttribute('user', false),
                ],
                $data
            );

            // auto reload template
            if ($this->parameter('common_theme_reload', 'off') === 'on') {
                if (!$this->renderer->getEnvironment()->isAutoReload()) {
                    $this->renderer->getEnvironment()->enableAutoReload();
                }
            }

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
        } catch (\Twig\Error\SyntaxError|\Twig\Error\LoaderError $exception) {
            throw new \RuntimeException($exception->getMessage());
        }
    }
}

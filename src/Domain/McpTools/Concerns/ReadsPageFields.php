<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use App\Domain\Casts\Page\Type as PageType;

/**
 * The page fields shared by page_create and page_update
 */
trait ReadsPageFields
{
    protected function pageFieldsSchema(): array
    {
        $string = ['type' => 'string'];

        return [
            'type' => ['type' => 'string', 'enum' => PageType::LIST, 'description' => 'html - HTML content (default), text - plain text'],
            'template' => $string + ['description' => 'Twig template of the active theme, e.g. "page.twig" (empty - the default one)'],
            'date' => $string + ['description' => 'Page date, YYYY-MM-DD HH:MM'],
        ];
    }

    /**
     * @return array validated type / template / date, only the keys that were passed
     */
    protected function pageFields(array $args): array
    {
        $data = [];

        if (array_key_exists('type', $args)) {
            if (!in_array($args['type'], PageType::LIST, true)) {
                throw new \InvalidArgumentException('Field type must be one of: ' . implode(', ', PageType::LIST));
            }
            $data['type'] = $args['type'];
        }

        if (array_key_exists('template', $args)) {
            $template = trim((string) $args['template']);

            if ($template !== '') {
                $theme = (string) $this->parameter('common_theme', 'default');

                if (
                    preg_match('/^[\w\-.\/]+\.twig$/', $template) !== 1
                    || str_contains($template, '..')
                    || preg_match('/^[\w\-]+$/', $theme) !== 1
                    || !is_file(THEME_DIR . '/' . $theme . '/' . $template)
                ) {
                    throw new \InvalidArgumentException('Template not found in the active theme (' . $theme . '): ' . $template);
                }
            }
            $data['template'] = $template;
        }

        if (!blank($args['date'] ?? null)) {
            $value = trim((string) $args['date']);

            if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $value) !== 1 || strtotime($value) === false) {
                throw new \InvalidArgumentException('Field date must be YYYY-MM-DD or YYYY-MM-DD HH:MM');
            }
            $data['date'] = datetime($value)->format('Y-m-d H:i:s');
        }

        return $data;
    }
}

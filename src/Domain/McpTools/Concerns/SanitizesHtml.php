<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

/**
 * HTML written by an AI client ends up on the public site: scripts, event handlers
 * and javascript: links are removed, everything else (headings, lists, tables, images, links) is kept
 */
trait SanitizesHtml
{
    private const HTML_REMOVE_TAGS = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'link', 'meta', 'base', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math', 'noscript', 'template'];

    private const HTML_URL_ATTRIBUTES = ['href', 'src', 'srcset', 'action', 'formaction', 'xlink:href', 'poster', 'background', 'data'];

    protected function sanitizeHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '' || !str_contains($html, '<')) {
            return $html;
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="sanitize-root">' . $html . '</div>', LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->getElementById('sanitize-root');

        if ($root === null) {
            return htmlspecialchars($html, ENT_QUOTES);
        }

        $xpath = new \DOMXPath($dom);

        foreach (self::HTML_REMOVE_TAGS as $tag) {
            foreach (iterator_to_array($xpath->query('.//' . $tag, $root)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        /** @var \DOMElement $element */
        foreach ($xpath->query('.//*', $root) as $element) {
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = mb_strtolower($attribute->nodeName);
                // schemes may be hidden by whitespace / control chars: "java\tscript:"
                $value = mb_strtolower((string) preg_replace('/[\s\x00-\x1f]+/', '', html_entity_decode($attribute->nodeValue, ENT_QUOTES | ENT_HTML5)));

                if (str_starts_with($name, 'on')
                    || $name === 'style' && preg_match('/expression|javascript:|url\(/', $value)
                    || in_array($name, self::HTML_URL_ATTRIBUTES, true) && preg_match('/^(javascript|vbscript|data):/', $value) && !($name === 'src' && str_starts_with($value, 'data:image/') && !str_contains($value, 'svg'))) {
                    $element->removeAttribute($attribute->nodeName);
                }
            }
        }

        $result = '';

        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return trim($result);
    }
}

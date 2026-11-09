<?php declare(strict_types=1);

namespace App\Domain\McpTools\Concerns;

use App\Domain\Casts\AddressUrl;
use App\Domain\Models\Page;

/**
 * Page address (URL path) of a new record: the same rules as the site applies when it generates addresses
 */
trait BuildsAddress
{
    /**
     * @param string      $parent   address of the category the record lives in (may be empty)
     * @param null|string $explicit address the operator asked for
     *
     * @throws \InvalidArgumentException when nothing usable can be made of the title
     */
    protected function buildAddress(string $parent, string $title, ?string $explicit = null): string
    {
        $source = !blank($explicit)
            ? trim((string) $explicit)
            : implode('/', array_filter([trim($parent), trim($title)], fn ($part) => $part !== ''));

        // the model is not used when the value is not empty, which it never is here
        $address = (new AddressUrl())->set(new Page(), 'address', $source, []);

        // letters of a language the site cannot transliterate are dropped, what is left may be nothing
        foreach (explode('/', $address) as $segment) {
            if (trim($segment, '-') === '') {
                throw new \InvalidArgumentException('An address cannot be made of this title, pass "address" (latin letters, digits and dashes)');
            }
        }

        return $address;
    }
}

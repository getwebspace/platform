<?php declare(strict_types=1);

namespace App\Application\Actions\Cup\Catalog;

use App\Domain\AbstractAction;
use Tightenco\Collect\Support\Collection;

abstract class CatalogAction extends AbstractAction
{
    /**
     * @param bool $list
     * if false return key:value
     * if true return key:list
     *
     * @return Collection
     */
    protected function getMeasure($list = false)
    {
        $measure = $this->getParameter('catalog_measure');
        $result = [];

        if ($measure) {
            preg_match_all('/([\w\d]+)\:\s?([\w\d]+)\;\s?([\w\d]+)\;\s?([\w\d]+)(?>\s|$)/u', $measure, $matches);

            foreach ($matches[1] as $index => $key) {
                $result[$key] = $list ? [$matches[2][$index], $matches[3][$index], $matches[4][$index]] : $matches[2][$index];
            }
        }

        return collect($result);
    }
}

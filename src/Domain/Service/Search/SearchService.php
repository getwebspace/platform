<?php declare(strict_types=1);

namespace App\Domain\Service\Search;

use App\Domain\Models\CatalogCategory;
use App\Domain\Models\CatalogProduct;
use App\Domain\Models\Page;
use App\Domain\Models\Publication;
use App\Domain\Models\PublicationCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Psr\Container\ContainerInterface;

/**
 * Full text search of the site: one index (TNTSearch, sqlite file) per kind of records
 *
 * The kinds are described once in definitions(): what is indexed, what the site shows to a visitor
 * (hidden products, drafts and closed categories never get into the index nor into the result,
 * even if the index is older than the change) and how the link to a record looks
 */
class SearchService
{
    /**
     * Candidates taken from every index before the visibility check and the common ranking
     */
    public const CANDIDATES = 200;

    public const MIN_QUERY_LENGTH = 2;

    private const CHUNK = 500;

    public function __construct(private readonly ContainerInterface $container) {}

    /**
     * Kinds of records in the order of ties of the common ranking
     *
     * @return string[]
     */
    public static function types(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Admin entity (scope of an API key) of a kind
     */
    public static function entity(string $type): ?string
    {
        return self::definitions()[$type]['entity'] ?? null;
    }

    /**
     * Kinds out of a request value: "a,b", ['a', 'b'] or empty (all of them)
     *
     * @return array{0: string[], 1: string[]} known kinds, unknown ones
     */
    public static function parseTypes(mixed $raw): array
    {
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);
        $list = array_values(array_unique(array_filter(array_map(fn ($v) => mb_trim((string) $v), $list), fn ($v) => $v !== '')));

        return [
            array_values(array_intersect($list, self::types())),
            array_values(array_diff($list, self::types())),
        ];
    }

    /**
     * Searches in the given kinds (all of them by default)
     *
     * @param string[] $types
     *
     * @return array{items: array<int, array>, total: int, counts: array<string, int>, missing: string[]}
     *                 items - the requested page, every one has the record itself in `model`;
     *                 counts - how many were found in each kind (for the tabs of the result page);
     *                 missing - kinds with no index yet
     */
    public function search(string $query, array $types = [], int $limit = 10, int $offset = 0): array
    {
        $query = mb_trim($query);
        $types = $types ?: self::types();
        $result = ['items' => [], 'total' => 0, 'counts' => [], 'missing' => []];

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return $result;
        }

        /** @var \TeamTNT\TNTSearch\TNTSearch $tnt */
        $tnt = $this->container->get(\TeamTNT\TNTSearch\TNTSearch::class);
        $found = [];

        foreach (self::types() as $order => $type) {
            if (!in_array($type, $types, true)) {
                continue;
            }

            $definition = self::definitions()[$type];

            try {
                $tnt->selectIndex("{$type}.index");
                $hits = $tnt->search($query, self::CANDIDATES);
            } catch (\Throwable $e) {
                $result['missing'][] = $type;

                continue;
            }

            $result['counts'][$type] = 0;

            if (empty($hits['ids'])) {
                continue;
            }

            // the index may be older than the record: the visibility is checked again
            $records = $definition['visible'](($definition['model'])::query())
                ->whereIn('uuid', $hits['ids'])
                ->get()
                ->keyBy(fn (Model $model) => (string) $model->getKey());

            $scores = $hits['docScores'] ?? [];
            $max = $scores ? max($scores) : 0;

            foreach ($hits['ids'] as $id) {
                if (!($model = $records->get((string) $id))) {
                    continue;
                }

                $score = (float) ($scores[$id] ?? 0);

                $found[] = [
                    'type' => $type,
                    'order' => $order,
                    // scores of different indexes are not comparable: every kind is scaled to its own best hit
                    'score' => $max > 0 ? round($score / $max, 4) : 1.0,
                    'model' => $model,
                ];
                ++$result['counts'][$type];
            }
        }

        usort($found, fn (array $a, array $b) => [$b['score'], $a['order'], $a['model']->title] <=> [$a['score'], $b['order'], $b['model']->title]);

        $result['total'] = count($found);

        foreach (array_slice($found, max(0, $offset), max(1, $limit)) as $hit) {
            $definition = self::definitions()[$hit['type']];
            $model = $hit['model'];

            $result['items'][] = [
                'type' => $hit['type'],
                'uuid' => (string) $model->getKey(),
                'title' => (string) $model->title,
                'address' => (string) $model->address,
                'url' => '/' . mb_ltrim($definition['prefix'] . $model->address, '/'),
                'excerpt' => $this->excerpt($model, $query),
                'score' => $hit['score'],
                'model' => $model,
            ];
        }

        return $result;
    }

    /**
     * Builds every index (or one kind) again
     *
     * A new index is made aside and put in place of the old one at once, so the search works all the time
     *
     * @return array<string, int> documents in every index
     */
    public function reindex(?string $only = null): array
    {
        $total = [];

        foreach (self::definitions() as $type => $definition) {
            if ($only !== null && $only !== $type) {
                continue;
            }

            $total[$type] = $this->buildIndex($type, $definition);
        }

        return $total;
    }

    private function buildIndex(string $type, array $definition): int
    {
        // an own engine for every index: the prepared statements of an engine stay with the first index it has made,
        // the words of the next ones would be written to it
        /** @var \TeamTNT\TNTSearch\TNTSearch $tnt */
        $tnt = $this->container instanceof \DI\FactoryInterface
            ? $this->container->make(\TeamTNT\TNTSearch\TNTSearch::class)
            : $this->container->get(\TeamTNT\TNTSearch\TNTSearch::class);

        $name = "{$type}.index";
        $temporary = "{$name}.tmp";
        $path = CACHE_DIR . '/' . $name;

        $indexer = $tnt->createIndex($temporary, true);
        $indexer->setPrimaryKey('uuid');
        $indexer->setInMemory(false);
        $indexer->setTokenizer(new \TeamTNT\TNTSearch\Support\Tokenizer());
        $indexer->setStemmer(new \TeamTNT\TNTSearch\Stemmer\NoStemmer());
        $indexer->setStopWords([]);

        // title of the category: a product is found by the name of its category too
        $categories = isset($definition['category'])
            ? ($definition['category'])::query()->pluck('title', 'uuid')->all()
            : [];

        $count = 0;

        $definition['visible'](($definition['model'])::query())
            ->chunkById(self::CHUNK, function ($rows) use ($indexer, $definition, $categories, &$count): void {
                foreach ($rows as $row) {
                    $document = ['uuid' => (string) $row->getKey()];

                    foreach ($definition['fields'] as $field) {
                        $document[$field] = $this->flatten($row->getAttribute($field));
                    }

                    if ($categories) {
                        $document['category'] = $this->flatten($categories[(string) $row->getAttribute('category_uuid')] ?? '');
                    }

                    $indexer->insert($document);
                    ++$count;
                }
            }, 'uuid');

        unset($indexer, $tnt);

        @chmod(CACHE_DIR . '/' . $temporary, 0o666);

        if (!@rename(CACHE_DIR . '/' . $temporary, $path)) {
            @unlink(CACHE_DIR . '/' . $temporary);

            throw new \RuntimeException("Search index {$name} cannot be put in place");
        }

        return $count;
    }

    /**
     * Plain text of a value of a column: arrays (json columns) are walked through, tags and entities are removed
     */
    private function flatten(mixed $value): string
    {
        if (is_array($value)) {
            return mb_trim(implode(' ', array_filter(array_map(fn ($item) => $this->flatten($item), $value), fn ($item) => $item !== '')));
        }

        if (!is_scalar($value)) {
            return '';
        }

        $text = preg_replace('/<[^>]*>/u', ' ', (string) $value) ?? '';
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * A piece of the text around the first word of the query
     */
    private function excerpt(Model $model, string $query, int $length = 200): string
    {
        $text = mb_trim($this->flatten($model->getAttribute('description')) . ' ' . $this->flatten($model->getAttribute('content')));

        if ($text === '') {
            return '';
        }

        $position = 0;

        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            if (($found = mb_stripos($text, $word)) !== false) {
                $position = $found;

                break;
            }
        }

        $start = max(0, $position - (int) ($length / 4));
        $piece = mb_substr($text, $start, $length);

        return ($start > 0 ? '…' : '') . mb_trim($piece) . ($start + $length < mb_strlen($text) ? '…' : '');
    }

    /**
     * What is searched: the order is the order of ties of the ranking
     *
     * model - the records, fields - indexed columns, category - model of titles for the `category_uuid` column,
     * visible - what a visitor of the site can see, prefix - what is before the address in a link,
     * entity - the scope of an API key that is needed to read the kind
     */
    private static function definitions(): array
    {
        return [
            'catalog_category' => [
                'model' => CatalogCategory::class,
                'entity' => 'catalog/category',
                'fields' => ['title', 'description', 'meta'],
                'prefix' => 'catalog/',
                'visible' => fn (Builder $query) => $query
                    ->where('status', \App\Domain\Casts\Catalog\Status::WORK)
                    ->where('is_hidden', false),
            ],
            'catalog_product' => [
                'model' => CatalogProduct::class,
                'entity' => 'catalog/product',
                'fields' => ['title', 'description', 'meta', 'extra', 'vendorcode', 'barcode', 'country', 'manufacturer', 'tags'],
                'category' => CatalogCategory::class,
                'prefix' => 'catalog/',
                'visible' => fn (Builder $query) => $query
                    ->where('status', \App\Domain\Casts\Catalog\Status::WORK)
                    ->whereIn('category_uuid', CatalogCategory::query()
                        ->select('uuid')
                        ->where('status', \App\Domain\Casts\Catalog\Status::WORK)
                        ->where('is_hidden', false)),
            ],
            'publication_category' => [
                'model' => PublicationCategory::class,
                'entity' => 'publication/category',
                'fields' => ['title', 'description', 'meta'],
                'prefix' => '',
                'visible' => fn (Builder $query) => $query->where('is_public', true),
            ],
            'publication' => [
                'model' => Publication::class,
                'entity' => 'publication',
                'fields' => ['title', 'meta', 'content'],
                'category' => PublicationCategory::class,
                'prefix' => '',
                // scheduled publications are not on the site yet
                'visible' => fn (Builder $query) => $query->where('date', '<=', datetime()->toDateTimeString()),
            ],
            'page' => [
                'model' => Page::class,
                'entity' => 'page',
                'fields' => ['title', 'meta', 'content'],
                'prefix' => '',
                'visible' => fn (Builder $query) => $query,
            ],
        ];
    }
}

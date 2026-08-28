<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Traits\HasParameters;
use Illuminate\Cache\ArrayStore as ArrayCache;
use Illuminate\Cache\FileStore as FileCache;
use Illuminate\Database\Connection as DataBase;
use Illuminate\Database\Eloquent\Builder;
use Psr\Container\ContainerInterface;

abstract class AbstractService
{
    use HasParameters;

    protected ContainerInterface $container;

    protected DataBase $db;

    protected ArrayCache $arrayCache;

    protected FileCache $fileCache;

    protected static array $default_read = [
        'search' => null,
        'order' => [],
        'limit' => null,
        'offset' => null,
    ];

    /**
     * Columns a non-strict `search` looks through, empty means search is not supported
     */
    protected static array $search_columns = [];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $container->get(DataBase::class);
        $this->arrayCache = $container->get(ArrayCache::class);
        $this->fileCache = $container->get(FileCache::class);
    }

    abstract public function create(array $data = []);

    abstract public function read(array $data = []);

    abstract public function update($entity, array $data = []);

    abstract public function delete($entity);

    /**
     * Keep only the values present in the reference list
     *
     * Used for enum-like criteria (status, type), where an unknown value
     * must not become a filter at all
     */
    protected function limitByList(mixed $value, array $list): array
    {
        if (is_array($value)) {
            return array_values(array_intersect($value, $list));
        }

        return in_array($value, $list, true) ? [$value] : [];
    }

    /**
     * Apply strict equality criteria: scalars match exactly, arrays match any of
     */
    protected function applyCriteria(Builder $query, array $criteria): Builder
    {
        foreach ($criteria as $key => $value) {
            if (is_array($value)) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query;
    }

    /**
     * Apply a non-strict, case-insensitive partial match across the given columns
     *
     * Unlike the criteria above this never narrows a column to an exact value,
     * it is the "search box" half of `read()`
     */
    protected function applySearch(Builder $query, mixed $search, array $columns): Builder
    {
        if (!is_string($search) || blank($search) || !$columns) {
            return $query;
        }

        $lower = $this->lowerFunction();
        // the needle is user input, so its own wildcards must not act as wildcards
        $needle = '%' . addcslashes(mb_strtolower(trim($search)), '%_\\') . '%';

        // grouped, otherwise the OR chain would escape and defeat the strict criteria
        return $query->where(function (Builder $query) use ($columns, $lower, $needle): void {
            foreach ($columns as $column) {
                if (preg_match('/^[a-z0-9_.]+$/i', $column) !== 1) {
                    continue;
                }

                $query->orWhereRaw("{$lower}({$column}) like ? escape '\\'", [$needle]);
            }
        });
    }

    /**
     * Apply order, limit and offset
     */
    protected function applyOptions(Builder $query, array $data): Builder
    {
        foreach ($data['order'] ?? [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }
        if ($data['limit'] ?? null) {
            $query->limit($data['limit']);
        }
        if ($data['offset'] ?? null) {
            $query->offset($data['offset']);
        }

        return $query;
    }

    /**
     * Strict criteria + non-strict search + order/limit/offset in one call
     */
    protected function buildQuery(Builder $query, array $criteria, array $data): Builder
    {
        $this->applyCriteria($query, $criteria);
        $this->applySearch($query, $data['search'] ?? null, static::$search_columns);

        return $this->applyOptions($query, $data);
    }

    /**
     * Name of a SQL function that lowercases multibyte text
     *
     * MySQL does this natively, but sqlite's own lower() only folds ASCII -
     * lower('Ёлка') comes back unchanged - so a PHP-backed function is
     * registered on the connection instead
     */
    private function lowerFunction(): string
    {
        if ($this->db->getDriverName() !== 'sqlite') {
            return 'lower';
        }

        static $registered = [];

        $pdo = $this->db->getPdo();
        $id = spl_object_id($pdo);

        if (!isset($registered[$id])) {
            if (!method_exists($pdo, 'sqliteCreateFunction')) {
                return 'lower';
            }

            $pdo->sqliteCreateFunction(
                'mb_lower',
                fn (?string $value): ?string => $value === null ? null : mb_strtolower($value),
                1
            );
            $registered[$id] = true;
        }

        return 'mb_lower';
    }
}

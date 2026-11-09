<?php declare(strict_types=1);

namespace App\Domain\McpTools;

use App\Domain\AbstractMcpTool;
use App\Domain\Models\Parameter;
use App\Domain\Service\Parameter\ParameterService;

class SettingsGetTool extends AbstractMcpTool
{
    public const NAME = 'settings_get';
    public const TITLE = 'Site settings';
    public const DESCRIPTION = 'Reads the settings of the site and of its plugins (title, templates, mail, catalog, users, search...). Group = the part of the name before the first underscore (common, catalog, smtp, ...). '
        . 'Passwords, keys, tokens and secrets are NOT shown: such a setting has "hidden": true and only says whether it is filled in. Private notes of the administrators are not shown at all. '
        . 'Read only: settings are changed in the admin panel. Long values are cut to 500 characters, ask by name for the whole text. Requires a full access API key.';
    public const PRIVATE = true;

    public const LIMIT_DEFAULT = 50;

    private const CUT = 500;

    private const WHOLE = 20000;

    /**
     * Names that hold a secret: a word of the name is one of these, or the name ends with "login" (a user name of an account)
     */
    private const SECRET_WORDS = ['pass', 'password', 'passwd', 'pwd', 'secret', 'token', 'key', 'keys', 'private', 'credential', 'credentials', 'salt', 'hash', 'auth', 'cert', 'signature', 'apikey'];

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Exact name, ex: common_title - the whole value'],
                'group' => ['type' => 'string', 'description' => 'Part of the name before the first underscore, ex: catalog'],
                'search' => ['type' => 'string', 'description' => 'Part of the name'],
            ] + $this->paginationSchema(),
        ];
    }

    public function execute(array $args = []): mixed
    {
        $all = $this->container->get(ParameterService::class)->read();

        // notes of the administrators are theirs
        $all = $all->reject(fn (Parameter $parameter) => str_starts_with($parameter->name, 'notepad_'))->sortBy('name')->values();

        if (!blank($name = trim((string) ($args['name'] ?? '')))) {
            $found = $all->first(fn (Parameter $parameter) => $parameter->name === $name);

            if ($found === null) {
                throw new \InvalidArgumentException('No such setting, search by a part of the name');
            }

            return $this->card($found, self::WHOLE);
        }

        $groups = $all->countBy(fn (Parameter $parameter) => $this->group($parameter->name))->sortKeys()->all();

        if (!blank($group = trim((string) ($args['group'] ?? '')))) {
            $all = $all->filter(fn (Parameter $parameter) => $this->group($parameter->name) === $group);

            if ($all->isEmpty()) {
                throw new \InvalidArgumentException('No such group, allowed: ' . implode(', ', array_keys($groups)));
            }
        }
        if (!blank($search = trim((string) ($args['search'] ?? '')))) {
            $all = $all->filter(fn (Parameter $parameter) => mb_stripos($parameter->name, $search) !== false);
        }

        $all = $all->values();

        return ['total' => $all->count(), 'groups' => $groups] + $this->paginate(
            $args,
            fn (int $limit, int $offset) => $all->slice($offset, $limit),
            fn (Parameter $parameter) => $this->card($parameter, self::CUT)
        );
    }

    private function card(Parameter $parameter, int $cut): array
    {
        $value = (string) $parameter->value;

        if ($this->isSecret($parameter->name, $value)) {
            return ['name' => $parameter->name, 'group' => $this->group($parameter->name), 'hidden' => true, 'filled' => trim($value) !== ''];
        }

        return [
            'name' => $parameter->name,
            'group' => $this->group($parameter->name),
            'value' => mb_strlen($value) > $cut ? mb_substr($value, 0, $cut) : $value,
            'length' => mb_strlen($value),
            'cut' => mb_strlen($value) > $cut,
        ];
    }

    private function group(string $name): string
    {
        return explode('_', $name, 2)[0];
    }

    /**
     * By the name, and by the look of the value: a secret can be saved under any name
     */
    private function isSecret(string $name, string $value): bool
    {
        $words = preg_split('/[^a-z0-9]+|(?<=[a-z])(?=[A-Z])/', $name) ?: [];
        $words = array_map('mb_strtolower', array_filter($words));

        if (array_intersect($words, self::SECRET_WORDS) !== [] || (end($words) ?: '') === 'login') {
            return true;
        }

        $value = trim($value);

        return $value !== '' && (
            // JWT, PEM, a bot token (123456:ABC...), a long run of letters and digits without spaces
            preg_match('/^eyJ[\w-]+\.[\w-]+\.[\w-]+$/', $value) === 1
            || str_contains($value, '-----BEGIN')
            || preg_match('/^\d{6,}:[A-Za-z0-9_-]{25,}$/', $value) === 1
            || preg_match('/^[A-Za-z0-9_\-+=\/]{28,}$/', $value) === 1
        );
    }
}

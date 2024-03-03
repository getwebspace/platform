<?php declare(strict_types=1);

namespace App\Domain\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    use HasUuids;

    protected $table = 'params';
    protected $primaryKey = 'key';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable  = [
        'key' => '',
        'value' => '',
    ];

    protected $casts = [
        'key' => 'string',
        'value' => 'string',
    ];

    protected $attributes = [
        'key' => '',
        'value' => '',
    ];

    /**
     * @return $this
     */
    public function setKey(string $key): static
    {
        if ($this->checkStrLenMax($key, 255)) {
            $this->attributes['key'] = $key;
        }

        return $this;
    }

    public function getKey(): string
    {
        return $this->attributes['key'];
    }

    /**
     * @return $this
     */
    public function setValue(mixed $value): static
    {
        $value = (string) $value;

        if ($this->checkStrLenMax($value, 100000)) {
            $this->attributes['value'] = $value;
        }

        return $this;
    }

    public function getValue(): string
    {
        return $this->attributes['value'];
    }
}

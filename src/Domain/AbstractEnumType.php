<?php declare(strict_types=1);

namespace App\Domain;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Class EnumType
 */
abstract class AbstractEnumType extends Type
{
    /**
     * Unique name of type
     */
    public const NAME = null;

    /**
     * List of values
     */
    public const LIST = [];

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'VARCHAR(100)';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, static::LIST, true) && !in_array($value, array_keys(static::LIST), true)) {
            throw new \InvalidArgumentException("Invalid '" . static::NAME . "' value.");
        }

        return $value;
    }

    public function getName(): ?string
    {
        return static::NAME;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}

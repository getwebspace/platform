<?php declare(strict_types=1);

namespace App\Application\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Class EnumType
 */
abstract class EnumType extends Type
{
    /**
     * Unique name of type
     */
    public const NAME = null;

    /**
     * List of values
     */
    public const LIST = [];

    /**
     * {@inheritdoc}
     */
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'VARCHAR(100)';
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if (!in_array($value, static::LIST, true) && !in_array($value, array_keys(static::LIST), true)) {
            throw new \InvalidArgumentException("Invalid '" . static::NAME . "' value.");
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return static::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresSQLCommentHint(AbstractPlatform $platform)
    {
        return true;
    }
}

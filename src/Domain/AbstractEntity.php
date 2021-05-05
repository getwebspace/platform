<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Exceptions\WrongIpValueException;
use App\Domain\Exceptions\WrongPhoneValueException;
use BadMethodCallException;
use DateTime;
use Ramsey\Uuid\Uuid;

abstract class AbstractEntity
{
    /**
     * @param string[] $args
     *
     * @throws \Exception
     */
    protected function getAddressByValue(string ...$args): string
    {
        foreach ($args as $str) {
            if ($str) {
                $str = mb_strtolower($str);
                $str = str_translate($str);
                $str = trim($str);

                return preg_replace(['/\s/', '/[^a-z0-9\-\/]/'], ['-', ''], $str);
            }
        }

        return Uuid::uuid4()->toString();
    }

    protected function checkStrLenBetween(string $value, int $min = 0, int $max = INF): bool
    {
        if (!is_scalar($value)) {
            return false;
        }
        $len = mb_strlen($value);

        return $len >= $min && $len <= $max;
    }

    protected function checkStrLenMax(string $value, int $max = INF): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return mb_strlen($value) <= $max;
    }

    protected function checkStrLenMin(string $value, int $min = 0): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return mb_strlen($value) >= $min;
    }

    /**
     * @throws WrongEmailValueException
     */
    protected function checkEmailByValue(string $value): bool
    {
        if ($value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return true;
            }

            throw new WrongEmailValueException();
        }

        return false;
    }

    /**
     * @throws WrongPhoneValueException
     */
    protected function checkPhoneByValue(string &$value): bool
    {
        if ($value) {
            $value = str_replace([' ', '-', '.', '(', ')'], '', $value);

            if (isset($_ENV['SIMPLE_PHONE_CHECK']) && $_ENV['SIMPLE_PHONE_CHECK']) {
                return true;
            }

            $pattern = '/\(?\+[0-9]{1,3}\)? ?-?[0-9]{1,3} ?-?[0-9]{3,5} ?-?[0-9]{4}( ?-?[0-9]{3})? ?(\w{1,10}\s?\d{1,6})?/';

            if (preg_match($pattern, $value)) {
                return true;
            }

            throw new WrongPhoneValueException();
        }

        return false;
    }

    /**
     * @param $value
     */
    protected function getBooleanByValue($value): bool
    {
        if (is_string($value) || is_int($value) || is_bool($value)) {
            switch (true) {
                case $value === true || in_array(mb_strtolower(trim((string) $value)), ['1', 'on', 'true', 't', 'yes', 'y'], true):
                    return true;

                case $value === false || in_array(mb_strtolower(trim((string) $value)), ['0', 'off', 'false', 'f', 'no', 'n'], true):
                    return false;
            }
        }

        return false;
    }

    /**
     * @throws WrongIpValueException
     */
    protected function getIpByValue(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return true;
        }

        throw new WrongIpValueException();
    }

    protected function getPasswordHashByValue(string $value): string
    {
        return crypta_hash($value, ($_ENV['SALT'] ?? 'Li8.1Ej2-<Cid3[bE'));
    }

    /**
     * @param $value
     *
     * @throws \Exception
     */
    protected function getDateTimeByValue($value): DateTime
    {
        switch (true) {
            case is_string($value):
            case is_numeric($value):
                return new DateTime($value);

            case is_null($value):
                return new DateTime('now');

            case is_a($value, DateTime::class):
                return clone $value;
        }

        return new DateTime('now');
    }

    /**
     * @param $value
     *
     * @return \Ramsey\Uuid\UuidInterface|string
     */
    protected function getUuidByValue($value)
    {
        if (Uuid::isValid((string) $value)) {
            if (is_string($value)) {
                return Uuid::fromString($value);
            }

            return $value;
        }

        return Uuid::fromString(Uuid::NIL);
    }

    /**
     * @param array|string $string
     *
     * @return array|false|string[]
     */
    protected function getArrayByExplodeValue($string, string $delimiter, int $limit = null)
    {
        if (is_array($string)) {
            return $string;
        }
        if (is_string($delimiter) && is_string($string) && mb_strlen($string) > 0) {
            if ($limit) {
                return explode($delimiter, $string, $limit);
            }

            return explode($delimiter, $string);
        }

        return [];
    }

    /**
     * @return AbstractEntity
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Return model as array
     */
    public function toArray(): array
    {
        return array_serialize(array_except(get_object_vars($this), ['__initializer__', '__cloner__', '__isInitialized__']));
    }

    /**
     * Return model as string
     */
    public function __toString(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT, 2048);
    }

    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }

    /**
     * Access to read property
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        throw new BadMethodCallException(
            sprintf("Unknown property '%s' in class '%s'.", $name, get_class($this))
        );
    }

    /**
     * Denied to write property
     *
     * @param mixed  $value
     *
     * @return mixed
     */
    public function __set(string $name, $value)
    {
        throw new BadMethodCallException(
            sprintf("You cannot change value '%s' = '%s' by this way in class '%s'.", $name, $value, get_class($this))
        );
    }
}

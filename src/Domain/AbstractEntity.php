<?php declare(strict_types=1);

namespace App\Domain;

use Alksily\Support\Str;
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
     *
     * @return string
     */
    protected function getAddressByValue(string ...$args): string
    {
        foreach ($args as $str) {
            if ($str) {
                $str = mb_strtolower($str);
                $str = Str::translate($str);
                $str = trim($str);
                $str = preg_replace(['/[^a-z0-9\-]/', '/\s/'], ['', '-'], $str);

                return $str;
            }
        }

        return Uuid::uuid4()->toString();
    }

    /**
     * @param string $value
     * @param int    $min
     * @param int    $max
     *
     * @return bool
     */
    protected function checkStrLenBetween(string $value, int $min = 0, int $max = INF)
    {
        if (!is_scalar($value)) {
            return false;
        }
        $len = mb_strlen($value);

        return $len >= $min && $len <= $max;
    }

    /**
     * @param string $value
     * @param int    $max
     *
     * @return bool
     */
    protected function checkStrLenMax(string $value, int $max = INF)
    {
        if (!is_scalar($value)) {
            return false;
        }

        return mb_strlen($value) <= $max;
    }

    /**
     * @param string $value
     * @param int    $min
     *
     * @return bool
     */
    protected function checkStrLenMin(string $value, int $min = 0)
    {
        if (!is_scalar($value)) {
            return false;
        }

        return mb_strlen($value) >= $min;
    }

    /**
     * @param string $value
     *
     * @throws WrongEmailValueException
     *
     * @return string
     */
    protected function checkEmailByValue(string $value)
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
     * @param string $value
     *
     * @throws WrongPhoneValueException
     *
     * @return bool
     */
    protected function checkPhoneByValue(string &$value)
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
     *
     * @return bool
     */
    protected function getBooleanByValue($value)
    {
        if (is_string($value) || is_int($value) || is_bool($value)) {
            switch (true) {
                case $value === true || in_array(mb_strtolower(trim($value)), ['1', 'on', 'true', 't', 'yes', 'y'], true):
                    return true;

                case $value === false || in_array(mb_strtolower(trim($value)), ['0', 'off', 'false', 'f', 'no', 'n'], true):
                    return false;
            }
        }

        return false;
    }

    /**
     * @param string $value
     *
     * @throws WrongIpValueException
     *
     * @return string
     */
    protected function getIpByValue(string $value)
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return true;
        }

        throw new WrongIpValueException();
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function getPasswordHashByValue(string $value)
    {
        return crypta_hash($value, ($_ENV['SALT'] ?? 'Li8.1Ej2-<Cid3[bE'));
    }

    /**
     * @param $value
     *
     * @throws \Exception
     *
     * @return DateTime
     */
    protected function getDateTimeByValue($value)
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

    protected function checkUuidByValue($value)
    {
        return Uuid::isValid((string) $value);
    }

    /**
     * @return AbstractEntity
     */
    public function clone()
    {
        return clone $this;
    }

    /**
     * Return model as array
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * Return model as string
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function __isset($name)
    {
        return property_exists($this, $name);
    }

    /**
     * Доступ на чтение параметра
     *
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new BadMethodCallException(
            sprintf("Unknown property '%s' in class '%s'.", $name, get_class($this))
        );
    }

    /**
     * Запрет на изменение
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        throw new BadMethodCallException(
            sprintf("You cannot change value '%s' = '%s' by this way in class '%s'.", $name, $value, get_class($this))
        );
    }
}

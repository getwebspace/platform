<?php declare(strict_types=1);

namespace App\Domain;

use App\Domain\Exceptions\WrongEmailValueException;
use App\Domain\Exceptions\WrongIpValueException;
use App\Domain\Exceptions\WrongPhoneValueException;
use DateTime;

abstract class AbstractEntity
{
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
            return $value;
        }

        throw new WrongIpValueException();
    }

    /**
     * @param string $value
     *
     * @throws WrongEmailValueException
     *
     * @return string
     */
    protected function getEmailByValue(string $value)
    {
        if ($value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }

            throw new WrongEmailValueException();
        }

        return '';
    }

    protected function getPasswordHashByValue(string $value)
    {
        return crypta_hash($value, ($_ENV['SALT'] ?? 'Li8.1Ej2-<Cid3[bE'));
    }

    /**
     * @param string $value
     *
     * @throws WrongPhoneValueException
     *
     * @return string|string[]
     */
    protected function checkPhoneByValue(string $value)
    {
        if ($value) {
            if (isset($_ENV['SIMPLE_PHONE_CHECK']) && $_ENV['SIMPLE_PHONE_CHECK']) {
                return str_replace([' ', '+', '-', '(', ')'], '', $value);
            }

            $pattern = '/\(?\+[0-9]{1,3}\)? ?-?[0-9]{1,3} ?-?[0-9]{3,5} ?-?[0-9]{4}( ?-?[0-9]{3})? ?(\w{1,10}\s?\d{1,6})?/';

            if (preg_match($pattern, $value)) {
                return $value;
            }

            throw new WrongPhoneValueException();
        }

        return '';
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

    public function clone()
    {
        return clone $this;
    }
}

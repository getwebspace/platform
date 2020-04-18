<?php

namespace App\Domain\Entities;

use DateTime;

abstract class AbstractEntity
{
    protected function getIpByValue(string $value)
    {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) ? $value : '';
    }

    protected function getEmailByValue(string $value)
    {
        return !!filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    protected function getPasswordHashByValue(string $value)
    {
        return crypta_hash($value, ($_ENV['SALT'] ?? 'Li8.1Ej2-<Cid3[bE'));
    }

    protected function checkPhoneByValue(string $value)
    {
        if (isset($_ENV['SIMPLE_PHONE_CHECK']) && $_ENV['SIMPLE_PHONE_CHECK']) {
            return str_replace([' ', '+', '-', '(', ')'], '', $value);
        }

        $pattern = '/\(?\+[0-9]{1,3}\)? ?-?[0-9]{1,3} ?-?[0-9]{3,5} ?-?[0-9]{4}( ?-?[0-9]{3})? ?(\w{1,10}\s?\d{1,6})?/';

        return preg_match($pattern, $value) ? $value : '';
    }

    protected function getDateTimeByValue($var)
    {
        switch (true) {
            case is_string($var):
            case is_numeric($var):
                return new DateTime($var);

            case is_null($var):
                return new DateTime();

            case is_a($var, DateTime::class):
                return clone $var;
        }

        return new DateTime('now');
    }

    public function clone()
    {
        return clone $this;
    }
}

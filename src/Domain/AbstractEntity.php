<?php declare(strict_types=1);

namespace App\Domain;

use App\Application\i18n;

abstract class AbstractEntity implements \JsonSerializable
{
    protected function validName(string $str): bool
    {
        return !$str || !!preg_match('/^[\p{L}\p{N}\s.,!?\'"-–—(){}[\]_+=*<>:;|\\/@#$%^&`~]+$/u', $str);
    }

    protected function validText(string $str): bool
    {
        return !$str || !!preg_match('/^[\p{L}\p{N}\s.,!?\'"-–—(){}[\]_+=*<>:;|\\/@#$%^&`~]+$/u', $str);
    }

    protected function validUsername(string $str): bool
    {
        return !$str || !!preg_match('/^[\p{L}\p{N}\s\-\.]+$/u', $str);
    }

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
                $str = i18n::getTranslatedText($str);
                $str = trim($str);

                return preg_replace(['/\s/', '/[^a-z0-9\-\/]/'], ['-', ''], $str);
            }
        }

        return \Ramsey\Uuid\Uuid::uuid4()->toString();
    }

    protected function checkStrLenBetween(string $value, int $min = 0, int $max = INF): bool
    {
        $len = mb_strlen($value);

        return $len >= $min && $len <= $max;
    }

    protected function checkStrLenMax(string $value, int $max = INF): bool
    {
        return mb_strlen($value) <= $max;
    }

    protected function checkStrLenMin(string $value, int $min = 0): bool
    {
        return mb_strlen($value) >= $min;
    }

    /**
     * @throws \RuntimeException
     */
    protected function checkEmailByValue(string $value): bool
    {
        if ($value) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return true;
            }

            throw new \RuntimeException();
        }

        return false;
    }

    /**
     * @throws \RuntimeException
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

            throw new \RuntimeException();
        }

        return false;
    }

    protected function getBooleanByValue(mixed $value): bool
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
     * @throws \RuntimeException
     */
    protected function getIpByValue(string $value): bool
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            return true;
        }

        throw new \RuntimeException();
    }

    protected function getPasswordHashByValue(string $value): string
    {
        return password_hash($value, PASSWORD_ARGON2ID);
    }

    protected function getDateTimeByValue($value, string $timezone = 'UTC'): \DateTime
    {
        $value = datetime($value, $timezone);

        if ($value->getTimezone()->getName() !== 'UTC') {
            $value->setTimeZone(new \DateTimeZone('UTC'));
        }

        return $value;
    }

    protected function getDateByValue($value): \DateTime
    {
        return match (true) {
            is_string($value), is_numeric($value) => new \DateTime($value),
            is_a($value, \DateTime::class) => clone $value,
            default => new \DateTime('now'),
        };
    }

    protected function getUuidByValue(mixed $value): string|\Ramsey\Uuid\UuidInterface
    {
        if (\Ramsey\Uuid\Uuid::isValid((string) $value)) {
            if (is_string($value)) {
                return \Ramsey\Uuid\Uuid::fromString($value);
            }

            return $value;
        }

        return \Ramsey\Uuid\Uuid::fromString(\Ramsey\Uuid\Uuid::NIL);
    }

    protected function getArrayByExplodeValue(array|string $string, string $delimiter, int $limit = null): array
    {
        if (is_array($string)) {
            return $string;
        }
        if (is_string($string) && mb_strlen($string) > 0) {
            if ($limit) {
                return explode($delimiter, $string, $limit);
            }

            return explode($delimiter, $string);
        }

        return [];
    }

    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Return model as array
     */
    public function toArray(): array
    {
        return array_serialize(
            array_except(
                get_object_vars($this),
                ['__initializer__', '__cloner__', '__isInitialized__', 'logger', 'entityManager', 'container']
            )
        );
    }

    public function toString(): string
    {
        $flags = ($_ENV['DEBUG'] ?? false) ? JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE : JSON_UNESCAPED_UNICODE;

        return json_encode($this->toArray(), $flags);
    }

    /**
     * Return model as string
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __isset(string $name): bool
    {
        return property_exists($this, $name);
    }

    /**
     * access to read property
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        throw new \BadMethodCallException(
            sprintf("Unknown property '%s' in class '%s'.", $name, get_class($this))
        );
    }

    /**
     * forbidden to change properties
     */
    public function __set(string $name, mixed $value): void
    {
        throw new \BadMethodCallException(
            sprintf("You cannot change value '%s' = '%s' by this way in class '%s'.", $name, $value, get_class($this))
        );
    }
}

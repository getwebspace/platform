<?php declare(strict_types=1);

namespace App\Domain\Traits;

/**
 * Counts failed attempts and blocks the pair once there are too many
 *
 * Nothing limited how often a password could be guessed, which matters most on
 * the control panel login. Counting per identifier *and* per address keeps one
 * attacked account from locking out everyone behind the same NAT
 *
 * @property \Illuminate\Cache\FileStore $fileCache
 */
trait UseThrottle
{
    protected function isThrottled(string $scope, string $identifier, string $ip): bool
    {
        return $this->throttleCount($scope, $identifier, $ip) >= $this->throttleLimit();
    }

    protected function throttleHit(string $scope, string $identifier, string $ip): void
    {
        if ($this->throttleLimit() <= 0) {
            return;
        }

        $this->fileCache->put(
            $this->throttleKey($scope, $identifier, $ip),
            $this->throttleCount($scope, $identifier, $ip) + 1,
            $this->throttleWindow()
        );
    }

    protected function throttleClear(string $scope, string $identifier, string $ip): void
    {
        $this->fileCache->forget($this->throttleKey($scope, $identifier, $ip));
    }

    protected function throttleCount(string $scope, string $identifier, string $ip): int
    {
        return (int) $this->fileCache->get($this->throttleKey($scope, $identifier, $ip));
    }

    private function throttleKey(string $scope, string $identifier, string $ip): string
    {
        return 'throttle-' . $scope . '-' . sha1(mb_strtolower(trim($identifier)) . '|' . $ip);
    }

    private function throttleLimit(): int
    {
        return (int) $this->parameter('user_login_attempts', 10);
    }

    private function throttleWindow(): int
    {
        return max(1, (int) $this->parameter('user_login_block_time', 15)) * \App\Domain\References\Date::MINUTE;
    }
}

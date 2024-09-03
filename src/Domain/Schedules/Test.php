<?php declare(strict_types=1);

namespace App\Domain\Schedules;

use App\Domain\AbstractSchedule;

class Test extends AbstractSchedule
{
    public function run(): void
    {
        // $this->logger->info(date('Y-m-d H:i:s') . " - Running LogJob\n");
    }
}

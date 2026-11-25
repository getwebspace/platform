<?php declare(strict_types=1);

namespace App\Application\Actions\Cup;

use App\Domain\AbstractAction;
use App\Domain\AbstractSchedule;
use App\Domain\Service\Schedule\ScheduleService;

class SchedulePageAction extends AbstractAction
{
    protected function action(): \Slim\Psr7\Response
    {
        /** @var ScheduleService $service */
        $service = $this->container->get(ScheduleService::class);
        $jobs = $this->container->get('scheduler')->get();

        if ($this->isPost()) {
            $key = $this->getParam('key', '');
            $do = $this->getParam('do', '');

            // only a registered job, nothing else is touched
            if (is_string($key) && $jobs->has($key)) {
                switch ($do) {
                    case 'run':
                        // the next pass of cron runs it, not this request
                        $service->update($key, ['run_now' => true]);

                        break;

                    case 'enable':
                    case 'disable':
                        $service->update($key, ['enabled' => $do === 'enable']);

                        break;
                }
            }

            return $this->respondWithRedirect('/cup/schedule');
        }

        $states = $service->read();
        $now = datetime();
        $list = [];

        foreach ($jobs as $key => $job) {
            $state = $states->get($key);
            $list[] = [
                'key' => $key,
                'class' => $job['class'],
                'schedule' => $job['schedule'],
                'catch_up' => $job['catch_up'],
                'state' => $state,
                'next' => AbstractSchedule::nextDue($job['schedule'], $now),
            ];
        }

        return $this->respondWithTemplate('cup/schedule.twig', ['jobs' => $list]);
    }
}

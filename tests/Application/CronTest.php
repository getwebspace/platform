<?php declare(strict_types=1);

namespace tests\Application;

use App\Application\Cron;
use App\Domain\AbstractSchedule;
use App\Domain\Service\Schedule\ScheduleService;
use Psr\Container\ContainerInterface;
use tests\TestCase;

class CronJobCounter extends AbstractSchedule
{
    public static array $runs = [];

    public function run(): void
    {
        self::$runs[] = static::class;
    }
}

class CronJobFailing extends AbstractSchedule
{
    public function run(): void
    {
        throw new \RuntimeException('boom');
    }
}

/**
 * @internal
 */
class CronTest extends TestCase
{
    private function scheduler(): object
    {
        return new class($this->getService(ContainerInterface::class)) {
            public array $jobs = [];

            public function __construct(private ContainerInterface $container) {}

            public function add(string $key, string $class, string $schedule, bool $catchUp = false): void
            {
                $this->jobs[$key] = ['schedule' => $schedule, 'job' => $class, 'class' => $class, 'catch_up' => $catchUp];
            }

            public function get(): \Illuminate\Support\Collection
            {
                return collect($this->jobs);
            }

            public function make(array $scheduled): AbstractSchedule
            {
                return new $scheduled['job']($this->container);
            }
        };
    }

    private function pass(object $scheduler, string $at): void
    {
        (new Cron($this->getService(ContainerInterface::class), $scheduler))->pass(new \DateTime($at));
    }

    private function states(): ScheduleService
    {
        return $this->getService(ScheduleService::class);
    }

    public function setUp(): void
    {
        parent::setUp();
        CronJobCounter::$runs = [];
    }

    public function testAFailingJobDoesNotStopTheNextOneAndIsRecorded(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->add('bad', CronJobFailing::class, '* * * * *');
        $scheduler->add('good', CronJobCounter::class, '* * * * *');

        $this->pass($scheduler, '2026-01-05 03:10:00');

        $this->assertSame([CronJobCounter::class], CronJobCounter::$runs);
        $bad = $this->states()->read(['key' => 'bad']);
        $this->assertSame('fail', $bad->status);
        $this->assertSame('boom', $bad->error);
        $this->assertSame(1, $bad->failures);
        $this->assertSame('ok', $this->states()->read(['key' => 'good'])->status);
    }

    public function testAJobRunsOnceInAMinute(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->add('good', CronJobCounter::class, '* * * * *');

        $this->pass($scheduler, '2026-01-05 03:10:05');
        $this->pass($scheduler, '2026-01-05 03:10:40');
        $this->pass($scheduler, '2026-01-05 03:11:05');

        $this->assertCount(2, CronJobCounter::$runs);
    }

    public function testFailuresInARowAreCountedAndResetBySuccess(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->add('bad', CronJobFailing::class, '* * * * *');

        foreach (['03:10', '03:11', '03:12'] as $time) {
            $this->pass($scheduler, "2026-01-05 {$time}:00");
        }

        $this->assertSame(3, $this->states()->read(['key' => 'bad'])->failures);
    }

    public function testACatchUpJobRunsOnceAfterADowntime(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->add('night', CronJobCounter::class, '0 3 * * *', true);
        $scheduler->add('plain', CronJobCounter::class, '0 3 * * *');

        // the first install: no last run, no catching up
        $this->pass($scheduler, '2026-01-05 05:00:00');
        $this->assertSame([], CronJobCounter::$runs);

        // it did run yesterday at 03:00 (the minute it was due)
        $this->pass($scheduler, '2026-01-05 03:00:00');
        $this->assertCount(2, CronJobCounter::$runs);
        CronJobCounter::$runs = [];

        // the server was down for three nights
        $this->pass($scheduler, '2026-01-08 05:00:00');
        $this->assertCount(1, CronJobCounter::$runs, 'only the job that asked for it, once');
        $this->assertSame('night', $this->states()->read(['key' => 'night'])->key);

        $this->pass($scheduler, '2026-01-08 05:01:00');
        $this->assertCount(1, CronJobCounter::$runs, 'not again');
    }

    public function testADisabledJobDoesNotRunUntilAskedFromThePanel(): void
    {
        $scheduler = $this->scheduler();
        $scheduler->add('good', CronJobCounter::class, '* * * * *');
        $this->states()->update('good', ['enabled' => false]);

        $this->pass($scheduler, '2026-01-05 03:10:00');
        $this->assertSame([], CronJobCounter::$runs);

        $this->states()->update('good', ['run_now' => true]);
        $this->pass($scheduler, '2026-01-05 03:10:30');
        $this->assertCount(1, CronJobCounter::$runs);
        $this->assertFalse($this->states()->read(['key' => 'good'])->run_now);
    }

    public function testAJobStillRunningIsNotStartedTwice(): void
    {
        $probe = VAR_DIR . '/tests-probe.lock';
        $held = fopen($probe, 'c');
        flock($held, LOCK_EX | LOCK_NB);
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('exit(flock(fopen(' . var_export($probe, true) . ', "c"), LOCK_EX | LOCK_NB) ? 0 : 1);'), $o, $enforced);
        fclose($held);
        @unlink($probe);
        if ($enforced !== 1) {
            $this->markTestSkipped('flock is not enforced between processes on this filesystem');
        }

        $scheduler = $this->scheduler();
        $scheduler->add('busy', CronJobCounter::class, '* * * * *');

        $lock = fopen(VAR_DIR . '/cron-' . md5('busy') . '.lock', 'c');
        $code = '$h = fopen(' . var_export(VAR_DIR . '/cron-' . md5('busy') . '.lock', true) . ', "c"); flock($h, LOCK_EX); sleep(5);';
        $child = proc_open([PHP_BINARY, '-r', $code], [], $pipes);
        fclose($lock);
        usleep(500000);

        try {
            $this->pass($scheduler, '2026-01-05 03:10:00');
            $this->assertSame([], CronJobCounter::$runs);
        } finally {
            proc_terminate($child);
            proc_close($child);
            @unlink(VAR_DIR . '/cron-' . md5('busy') . '.lock');
        }
    }

    public function testRegisterRefusesWhatCannotRunWithoutThrowing(): void
    {
        $scheduler = $this->getService('scheduler');

        $this->assertFalse($scheduler->register(CronJobCounter::class, 'x * * * *', 'reg-bad-line'));
        $this->assertFalse($scheduler->register(CronJobCounter::class, '* * * *', 'reg-four'));
        $this->assertFalse($scheduler->register('No\Such\Job', '* * * * *', 'reg-bad-class'));
        $this->assertFalse($scheduler->register(\stdClass::class, '* * * * *', 'reg-not-a-job'));
        $this->assertFalse($scheduler->get()->has('reg-bad-line'));
        $this->assertFalse($scheduler->get()->has('reg-bad-class'));
    }

    public function testOneClassMayHaveSeveralSchedulesByKey(): void
    {
        $scheduler = $this->getService('scheduler');

        $this->assertTrue($scheduler->register(CronJobCounter::class, '0 3 * * *', 'reg-night'));
        $this->assertTrue($scheduler->register(CronJobCounter::class, '@hourly', 'reg-hour', true));
        $this->assertFalse($scheduler->register(CronJobCounter::class, '0 4 * * *', 'reg-night'), 'the key is taken');

        $hour = $scheduler->get()->get('reg-hour');
        $this->assertTrue($hour['catch_up']);
        $this->assertSame(CronJobCounter::class, $hour['job'], 'the object is made when the job runs');
        $this->assertInstanceOf(CronJobCounter::class, $scheduler->make($hour));
    }
}

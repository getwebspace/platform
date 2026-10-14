<?php declare(strict_types=1);

namespace tests\Scheme;

use App\Domain\Casts\Reference\Type as ReferenceType;
use App\Domain\References\Documents;
use App\Domain\Service\Reference\ReferenceService;
use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use tests\TestCase;

/**
 * @internal
 *
 * #[CoversNothing]
 */
class ReferenceSeederTest extends TestCase
{
    public function testSeedOnEmptyDatabase(): void
    {
        $phinxApp = new PhinxApplication();
        $phinxApp->setAutoExit(false);
        $phinxApp->setCatchExceptions(false);

        $output = new BufferedOutput();
        $code = $phinxApp->run(new StringInput('seed:run -s ReferenceSeeder'), $output);
        $this->assertEquals(0, $code, $output->fetch());

        /** @var ReferenceService $service */
        $service = $this->getService(ReferenceService::class);

        $documents = $service->read(['type' => ReferenceType::DOCUMENT]);
        $this->assertCount(3, $documents);

        // the same filter the order edit page uses to offer documents for printing
        $this->assertCount(3, $documents->where('value.target', Documents::ORDER_TARGET));

        foreach ($documents as $document) {
            $this->assertNotEmpty($document->value['template']);
        }
    }
}

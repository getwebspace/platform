<?php declare(strict_types=1);

namespace tests\Cup;

use tests\Concerns\UsesCup;
use tests\TestCase;

/**
 * Base of the admin panel tests: every test starts with an empty database and may call $this->cup()
 */
abstract class CupTestCase extends TestCase
{
    use UsesCup;

    public function tearDown(): void
    {
        $this->cupTearDown();

        parent::tearDown();
    }
}

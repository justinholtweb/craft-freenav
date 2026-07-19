<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use justinholt\freenav\enums\Propagation;
use PHPUnit\Framework\TestCase;

final class PropagationTest extends TestCase
{
    public function testBackingValues(): void
    {
        self::assertSame('none', Propagation::None->value);
        self::assertSame('siteGroup', Propagation::SiteGroup->value);
        self::assertSame('language', Propagation::Language->value);
        self::assertSame('all', Propagation::All->value);
    }

    public function testTryFrom(): void
    {
        self::assertSame(Propagation::All, Propagation::tryFrom('all'));
        self::assertNull(Propagation::tryFrom('bogus'));
    }

    public function testEveryCaseHasNonEmptyLabel(): void
    {
        foreach (Propagation::cases() as $case) {
            self::assertNotSame('', $case->label(), "{$case->name} has empty label");
        }
    }
}

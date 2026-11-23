<?php

declare(strict_types=1);

namespace JustinHoltWeb\FreeNav\Tests\Unit;

use JustinHoltWeb\FreeNav\Enums\Propagation;
use PHPUnit\Framework\TestCase;

final class PropagationTest extends TestCase
{
    public function test_backing_values(): void
    {
        self::assertSame('none', Propagation::None->value);
        self::assertSame('siteGroup', Propagation::SiteGroup->value);
        self::assertSame('language', Propagation::Language->value);
        self::assertSame('all', Propagation::All->value);
    }

    public function test_try_from(): void
    {
        self::assertSame(Propagation::All, Propagation::tryFrom('all'));
        self::assertNull(Propagation::tryFrom('bogus'));
    }

    public function test_every_case_has_non_empty_label(): void
    {
        foreach (Propagation::cases() as $case) {
            self::assertNotSame('', $case->label(), "{$case->name} has empty label");
        }
    }
}

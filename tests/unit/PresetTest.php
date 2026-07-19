<?php

declare(strict_types=1);

namespace justinholt\freenav\tests\unit;

use justinholt\freenav\enums\Preset;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Preset enum, including that every preset resolves to a
 * template that actually ships with the plugin.
 */
final class PresetTest extends TestCase
{
    private const PRESETS_DIR = __DIR__ . '/../../src/templates/_presets';

    public function testBackingValues(): void
    {
        self::assertSame('default', Preset::Default->value);
        self::assertSame('dropdown', Preset::Dropdown->value);
        self::assertSame('sidebar', Preset::Sidebar->value);
        self::assertSame('breadcrumb', Preset::Breadcrumb->value);
        self::assertSame('footer', Preset::Footer->value);
        self::assertSame('mega', Preset::Mega->value);
    }

    public function testEveryCaseHasNonEmptyLabel(): void
    {
        foreach (Preset::cases() as $case) {
            self::assertNotSame('', $case->label(), "{$case->name} has empty label");
        }
    }

    public function testTemplateNameIsUnderscorePrefixedValue(): void
    {
        self::assertSame('_default', Preset::Default->templateName());
        self::assertSame('_mega', Preset::Mega->templateName());
    }

    /**
     * The Renderer resolves 'free-nav/_presets/' . $preset->templateName().
     * A missing template silently breaks rendering for that preset, so assert
     * every enum case has a matching .twig file on disk.
     */
    public function testEveryPresetHasAMatchingTemplate(): void
    {
        foreach (Preset::cases() as $case) {
            $path = self::PRESETS_DIR . '/' . $case->templateName() . '.twig';
            self::assertFileExists($path, "Missing template for preset {$case->name}");
        }
    }
}

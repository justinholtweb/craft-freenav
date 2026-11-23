<?php

declare(strict_types=1);

use JustinHoltWeb\FreeNav\Services\Breadcrumbs;
use JustinHoltWeb\FreeNav\Services\VerbbImporter;

it('turns a slug into a title', function () {
    expect(Breadcrumbs::segmentToTitle('about-us'))->toBe('About Us')
        ->and(Breadcrumbs::segmentToTitle('our_team'))->toBe('Our Team');
});

it('maps Verbb Navigation node types, categories to entries', function () {
    expect(VerbbImporter::mapType('verbb\\navigation\\nodetypes\\CustomType'))->toBe('custom')
        ->and(VerbbImporter::mapType('verbb\\\\navigation\\\\nodetypes\\\\PassiveType'))->toBe('passive')
        ->and(VerbbImporter::mapType('craft\\elements\\Entry'))->toBe('entry')
        ->and(VerbbImporter::mapType('craft\\elements\\Category'))->toBe('entry')
        ->and(VerbbImporter::mapType('craft\\elements\\Asset'))->toBe('asset')
        ->and(VerbbImporter::mapType(null))->toBe('custom')
        ->and(VerbbImporter::mapType('something\\Else'))->toBe('custom');
});

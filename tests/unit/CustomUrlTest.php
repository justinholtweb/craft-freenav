<?php

declare(strict_types=1);

use JustinHoltWeb\FreeNav\Elements\Node;

/**
 * `Node::resolveCustomUrl()` is the only gate between what an editor types and an `href` on every
 * page and in the REST API.
 */
dataset('urls', [
    ['https://example.com/about', 'https://example.com/about'],
    ['http://example.com', 'http://example.com'],
    ['/about/team', '/about/team'],
    ['about', 'about'],
    ['#contact', '#contact'],
    ['?q=1', '?q=1'],
    ['mailto:hi@example.com', 'mailto:hi@example.com'],
    ['tel:+15555550100', 'tel:+15555550100'],
    ['sms:+15555550100', 'sms:+15555550100'],
    ['javascript:alert(1)', null],
    ['JavaScript:alert(1)', null],
    [" java\tscript:alert(1)", null],
    ["java\nscript:alert(1)", null],
    ['data:text/html,<script>alert(1)</script>', null],
    ['vbscript:msgbox', null],
    ['', null],
    ['   ', null],
]);

it('lets only safe links through', function (string $url, ?string $expected) {
    expect(Node::resolveCustomUrl($url))->toBe($expected);
})->with('urls');

it('refuses environment values that are not one http(s) variable', function () {
    // These never reach Env::parse(): nested ${VAR} would let a query string carry a secret.
    expect(Node::resolveCustomUrl('${PRIMARY_SITE_URL}/x?k=${APP_KEY}'))->toBeNull()
        ->and(Node::resolveCustomUrl('$PRIMARY_SITE_URL/x?k=$APP_KEY'))->toBeNull()
        ->and(Node::resolveCustomUrl('@web/$APP_KEY'))->toBeNull();
});

<?php

declare(strict_types=1);

use JustinHoltWeb\FreeNav\Elements\Node;

/**
 * The 5.2.1 custom attribute guard, ported unchanged: names are an allowlist, values must be text.
 * The same three functions back the node validator, every import path and the render loop.
 */
dataset('attribute names', [
    // Plain attributes on the allowlist
    ['title', true],
    ['rel', true],
    ['id', true],
    ['lang', true],
    ['role', true],
    ['itemprop', true],
    ['TITLE', true],
    // aria-* and data-*
    ['aria-label', true],
    ['data-track', true],
    ['data-nav-item', true],
    ['data-tracking_id', true],
    // Event handlers, URL attributes, style
    ['onmouseover', false],
    ['onclick', false],
    ['OnLoad', false],
    ['href', false],
    ['src', false],
    ['srcdoc', false],
    ['formaction', false],
    ['style', false],
    ['xlink:href', false],
    ['class', false],
    // Framework directives
    ['x-init', false],
    ['@click', false],
    [':class', false],
    ['hx-on:click', false],
    ['hx-get', false],
    ['data-hx-get', false],
    ['v-html', false],
    ['ng-click', false],
    ['data-ng-click', false],
    ['data-x-data', false],
    ['data-v-bind', false],
    // Datastar attribute plugins
    ['data-on-click', false],
    ['data-bind', false],
    ['data-attr-href', false],
    ['data-signals', false],
    ['data-json-signals', false],
    ['data-text', false],
    ['data-show', false],
    ['data-class', false],
    ['data-style', false],
    ['data-star-on-click', false],
    ['data-teleport', false],
    // Names Craft's HTML helper expands, and malformed names
    ['aria', false],
    ['data', false],
    ['ng', false],
    ['data-', false],
    ['', false],
    ['data track', false],
    ['data-"x"', false],
]);

it('allows only safe attribute names', function (string $name, bool $safe) {
    expect(Node::isSafeAttributeName($name))->toBe($safe);
})->with('attribute names');

it('only takes text as an attribute value', function () {
    expect(Node::customAttributeValue('nav'))->toBe('nav')
        ->and(Node::customAttributeValue(3))->toBe('3')
        ->and(Node::customAttributeValue(true))->toBe('1')
        ->and(Node::customAttributeValue(null))->toBe('')
        ->and(Node::customAttributeValue(['onclick' => 'x']))->toBeNull()
        ->and(Node::customAttributeValue(new stdClass))->toBeNull();
});

it('drops unsafe attributes from imports and keeps the rest in order', function () {
    $filtered = Node::safeCustomAttributes([
        ['key' => 'data-track', 'value' => 'nav'],
        ['key' => 'onmouseover', 'value' => 'alert(1)'],
        ['key' => 'href', 'value' => 'javascript:alert(1)'],
        ['key' => 'style', 'value' => 'display:none'],
        'not-an-object',
        ['key' => 'data', 'value' => ['onclick' => 'alert(1)']],
        ['key' => 'title', 'value' => ['nested']],
        ['key' => ' rel ', 'value' => 'nofollow'],
    ]);

    expect($filtered)->toBe([
        ['key' => 'data-track', 'value' => 'nav'],
        ['key' => ' rel ', 'value' => 'nofollow'],
    ]);
});

it('passes null through', function () {
    expect(Node::safeCustomAttributes(null))->toBeNull();
});

it('skips unsafe attributes when building link attributes', function () {
    $node = new class extends Node
    {
        public function __construct() {}

        public function getAriaAttributes(): array
        {
            return [];
        }
    };
    $node->customAttributes = json_encode([
        ['key' => 'data-track', 'value' => 'nav'],
        ['key' => 'onmouseover', 'value' => 'alert(1)'],
        ['key' => 'href', 'value' => 'javascript:alert(1)'],
        ['key' => 'data', 'value' => ['onclick' => 'alert(1)']],
        'garbage',
        ['key' => 'title', 'value' => 'Home'],
    ]);
    $node->classes = 'nav-link';
    $node->newWindow = true;

    expect($node->getLinkAttributes())->toBe([
        'class' => 'nav-link',
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
        'data-track' => 'nav',
        'title' => 'Home',
    ]);
});

it('renders custom attributes for the presets, leaving out what the tag already writes', function () {
    $node = new class extends Node
    {
        public function __construct() {}
    };
    $node->customAttributes = [
        ['key' => 'data-track', 'value' => 'a "quoted" <nav>'],
        ['key' => 'rel', 'value' => 'nofollow'],
        ['key' => 'role', 'value' => 'link'],
        ['key' => 'onclick', 'value' => 'alert(1)'],
    ];

    expect((string) $node->getCustomAttributesHtml(['class', 'rel', 'role']))
        ->toBe(' data-track="a &quot;quoted&quot; &lt;nav&gt;" rel="nofollow"');

    $node->newWindow = true;
    expect((string) $node->getCustomAttributesHtml(['rel']))
        ->toBe(' data-track="a &quot;quoted&quot; &lt;nav&gt;" role="link"');

    $node->customAttributes = null;
    expect((string) $node->getCustomAttributesHtml())->toBe('');
});

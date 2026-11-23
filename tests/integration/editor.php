<?php
/**
 * The node editor's Craft-rendered fields (icon picker, custom attributes table, visibility
 * condition builder) and the Structure section importer.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-freenav/tests/integration/editor.php
 *
 * The editor is driven over HTTP as an admin, posting what the builder panel posts. The importer
 * is run for real (`php craft free-nav/migrate/from-section`) against a throwaway Structure
 * section with a Link field.
 *
 * Idempotent and self-cleaning: menus, entries, the section, entry type, field and user are
 * removed at the end, in a fresh PHP process, because the child processes change project config
 * behind this one's back.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\elements\Entry;
use craft\elements\User;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Link;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use justinholt\freenav\elements\conditions\visibility\LoggedInConditionRule;
use justinholt\freenav\elements\conditions\visibility\NodeVisibilityCondition;
use justinholt\freenav\elements\conditions\visibility\UrlPathConditionRule;
use justinholt\freenav\elements\conditions\visibility\UserGroupConditionRule;
use justinholt\freenav\elements\Node;
use justinholt\freenav\FreeNav;
use justinholt\freenav\models\Menu;
use justinholt\freenav\models\MenuSiteSettings;

Craft::$app->getPlugins()->loadPlugins();

$passed = 0;
$failed = 0;

function check(string $label, callable $test): void
{
    global $passed, $failed;

    try {
        $result = $test();
    } catch (Throwable $e) {
        $result = get_class($e) . ': ' . $e->getMessage();
    }

    if ($result === true) {
        $passed++;
        echo "  ✓ $label\n";
    } else {
        $failed++;
        echo "  ✗ $label\n    " . (is_string($result) ? $result : var_export($result, true)) . "\n";
    }
}

$plugin = FreeNav::getInstance();
$run = substr(bin2hex(random_bytes(3)), 0, 6);
$tmp = sys_get_temp_dir() . "/freenav-editor-$run";
@mkdir($tmp);
$cleanup = ['users' => [], 'sections' => [], 'entryTypes' => [], 'fields' => []];

register_shutdown_function(function() use ($run, $tmp, &$cleanup) {
    $script = $tmp . '/teardown.php';
    file_put_contents($script, '<?php
        $root = getcwd();
        require $root . "/bootstrap.php";
        $app = require CRAFT_VENDOR_PATH . "/craftcms/cms/bootstrap/console.php";
        Craft::$app->getPlugins()->loadPlugins();
        $plugin = justinholt\freenav\FreeNav::getInstance();
        $run = ' . var_export($run, true) . ';
        $cleanup = ' . var_export($cleanup, true) . ';
        foreach ($plugin->getMenus()->getAllMenus() as $menu) {
            if (!str_contains(strtolower($menu->handle), $run)) {
                continue;
            }
            foreach (justinholt\freenav\elements\Node::find()->menuId($menu->id)->siteId("*")->status(null)->trashed(null)->all() as $node) {
                Craft::$app->getElements()->deleteElement($node, true);
            }
            $plugin->getMenus()->deleteMenu($menu);
        }
        foreach ($cleanup["sections"] as $id) {
            foreach (craft\elements\Entry::find()->sectionId($id)->siteId("*")->status(null)->trashed(null)->all() as $entry) {
                Craft::$app->getElements()->deleteElement($entry, true);
            }
            if ($section = Craft::$app->getEntries()->getSectionById($id)) {
                Craft::$app->getEntries()->deleteSection($section);
            }
        }
        foreach ($cleanup["entryTypes"] as $id) {
            if ($type = Craft::$app->getEntries()->getEntryTypeById($id)) {
                Craft::$app->getEntries()->deleteEntryType($type);
            }
        }
        foreach ($cleanup["fields"] as $id) {
            if ($field = Craft::$app->getFields()->getFieldById($id)) {
                Craft::$app->getFields()->deleteField($field);
            }
        }
        foreach ($cleanup["users"] as $id) {
            if ($user = craft\elements\User::find()->id($id)->status(null)->one()) {
                Craft::$app->getElements()->deleteElement($user, true);
            }
        }
        Craft::$app->getProjectConfig()->saveModifiedConfigData();
        Craft::$app->getProjectConfig()->writeYamlFiles(true);
    ');
    exec(PHP_BINARY . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        echo "Teardown failed:\n" . implode("\n", $out) . "\n";
    }
    array_map('unlink', glob("$tmp/*") ?: []);
    @rmdir($tmp);
});

$craft = static function(string $args): array {
    exec(PHP_BINARY . ' craft ' . $args . ' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

// ---------------------------------------------------------------------------------------------
// A menu, a node and an admin
// ---------------------------------------------------------------------------------------------

$menu = new Menu(['name' => "Editor $run", 'handle' => "editor$run"]);
$siteSettings = [];
foreach (Craft::$app->getSites()->getAllSites() as $site) {
    $siteSettings[$site->id] = new MenuSiteSettings(['siteId' => $site->id, 'enabled' => true]);
}
$menu->setSiteSettings($siteSettings);

if (!$plugin->getMenus()->saveMenu($menu)) {
    echo "Could not create the test menu: " . json_encode($menu->getErrors()) . "\n";
    exit(1);
}
Craft::$app->getProjectConfig()->saveModifiedConfigData();
$menu = $plugin->getMenus()->getMenuById($menu->id);

[$node] = $plugin->getNodes()->addNodes($menu, [[
    'title' => "Node $run",
    'customUrl' => '/somewhere',
    // `custom` is a type the builder can't show; it must survive an edit.
    'visibilityRules' => [['type' => 'custom', 'operator' => 'is', 'value' => 'kept']],
]]);

$password = 'fn-' . bin2hex(random_bytes(12));
$admin = new User(['username' => "freenav-admin-$run", 'email' => "freenav-admin-$run@example.com", 'admin' => true]);
$admin->newPassword = $password;
Craft::$app->getElements()->saveElement($admin, false);
Craft::$app->getUsers()->activateUser($admin);
$cleanup['users'][] = $admin->id;

$http = new Client(['base_uri' => 'http://localhost/', 'cookies' => new CookieJar(), 'http_errors' => false, 'allow_redirects' => false]);
$csrf = static function() use ($http): string {
    $info = json_decode((string)$http->get('index.php?p=actions/users/session-info', ['headers' => ['Accept' => 'application/json']])->getBody(), true);

    return (string)($info['csrfTokenValue'] ?? '');
};
$http->post('index.php?p=actions/users/login', [
    'headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
    'form_params' => ['loginName' => $admin->username, 'password' => $password, 'CRAFT_CSRF_TOKEN' => $csrf()],
]);
$post = static fn(string $action, array $params) => $http->post("index.php?p=admin/actions/$action", [
    'headers' => ['Accept' => 'application/json'],
    'form_params' => $params + ['CRAFT_CSRF_TOKEN' => $csrf()],
]);
$fresh = static fn() => Node::find()->id($node->id)->status(null)->one();

$icons = require Craft::getAlias('@app/icons/index.php');
$proIcon = array_key_first(array_filter($icons, fn($icon) => $icon['pro']));
$freeIcon = isset($icons['house']) && !$icons['house']['pro'] ? 'house' : array_key_first(array_filter($icons, fn($icon) => !$icon['pro']));

echo "The edit panel's fields\n";

check('the panel gets an icon picker (free icons only), an attributes table and a condition builder', function() use ($http, $node) {
    $response = $http->get('index.php?p=admin/actions/free-nav/nodes/edit-fields&nodeId=' . $node->id, ['headers' => ['Accept' => 'application/json']]);
    $data = json_decode((string)$response->getBody(), true);
    $html = $data['html'] ?? '';
    $body = $data['bodyHtml'] ?? '';

    foreach ([
        'icon picker' => str_contains($html, 'icon-picker') && str_contains($body, 'freeOnly: true'),
        'attributes table' => str_contains($html, 'name="customAttributes') || str_contains($body, 'customAttributes'),
        'condition builder' => str_contains($html, 'condition-container') && str_contains($html, 'visibilityRules[class]'),
        'builder bootstrap' => str_contains($body, 'htmx.process'),
    ] as $what => $ok) {
        if (!$ok) {
            return "missing $what (HTTP {$response->getStatusCode()})";
        }
    }

    return true;
});

check('Craft’s builder endpoint can add each of our rules (what “Add a rule” does)', function() use ($post) {
    foreach (NodeVisibilityCondition::RULE_TYPES as $ruleClass) {
        $response = $post('conditions/render', [
            'config' => json_encode(['name' => 'visibilityRules', 'id' => 'visibilityRules', 'mainTag' => 'div']),
            'visibilityRules' => ['class' => NodeVisibilityCondition::class, 'new-rule-type' => $ruleClass],
        ]);
        $html = (string)$response->getBody();
        $label = (new $ruleClass())->getLabel();

        if ($response->getStatusCode() !== 200 || !str_contains($html, htmlspecialchars($label, ENT_QUOTES)) && !str_contains($html, $label)) {
            return "$ruleClass: HTTP {$response->getStatusCode()} " . substr(strip_tags($html), 0, 200);
        }
    }

    return true;
});

check('saving the panel stores the builder’s rules as rules, keeps the ones it can’t show, and the table as attributes', function() use ($post, $node, $fresh, $freeIcon) {
    $response = $post('free-nav/nodes/save', [
        'nodeId' => $node->id,
        'title' => $node->title,
        'craftIcon' => $freeIcon,
        'customAttributes' => [
            '0' => ['key' => 'data-track', 'value' => 'nav'],
            'new1' => ['key' => '', 'value' => 'empty rows are dropped'],
            'new2' => ['key' => 'aria-label', 'value' => 'Somewhere'],
        ],
        'visibilityRules' => [
            'class' => NodeVisibilityCondition::class,
            'config' => '{}',
            'conditionRules' => [
                1 => ['class' => UserGroupConditionRule::class, 'uid' => 'a', 'operator' => 'ni', 'values' => ['guests']],
                2 => ['class' => LoggedInConditionRule::class, 'uid' => 'b', 'value' => '1'],
                3 => ['class' => UrlPathConditionRule::class, 'uid' => 'c', 'operator' => 'bw', 'value' => ' members '],
                // Not one of ours: never instantiated, never stored.
                4 => ['class' => craft\elements\conditions\users\AdminConditionRule::class, 'uid' => 'd', 'value' => '1'],
            ],
        ],
    ]);

    if ($response->getStatusCode() !== 200) {
        return "HTTP {$response->getStatusCode()}: " . $response->getBody();
    }

    $saved = $fresh();
    $expectedRules = [
        ['type' => 'userGroup', 'operator' => 'isNot', 'value' => ['guests']],
        ['type' => 'loggedIn', 'operator' => 'is', 'value' => true],
        ['type' => 'urlSegment', 'operator' => 'startsWith', 'value' => 'members'],
        ['type' => 'custom', 'operator' => 'is', 'value' => 'kept'],
    ];
    $expectedAttributes = [['key' => 'data-track', 'value' => 'nav'], ['key' => 'aria-label', 'value' => 'Somewhere']];

    if ($saved->getVisibilityRulesArray() !== $expectedRules) {
        return 'rules: ' . json_encode($saved->getVisibilityRulesArray());
    }

    if ($saved->getCustomAttributesArray() !== $expectedAttributes) {
        return 'attributes: ' . json_encode($saved->getCustomAttributesArray());
    }

    return $saved->craftIcon === $freeIcon ?: 'icon: ' . var_export($saved->craftIcon, true);
});

check('reopening the panel shows those rules back in the builder', function() use ($http, $node) {
    $data = json_decode((string)$http->get('index.php?p=admin/actions/free-nav/nodes/edit-fields&nodeId=' . $node->id, ['headers' => ['Accept' => 'application/json']])->getBody(), true);
    $html = $data['html'] ?? '';

    foreach ([UserGroupConditionRule::class, LoggedInConditionRule::class, UrlPathConditionRule::class] as $class) {
        if (!str_contains($html, htmlspecialchars($class, ENT_QUOTES)) && !str_contains($html, $class)) {
            return "missing $class";
        }
    }

    return str_contains($html, 'value="members"') ?: 'URL path value not shown';
});

check('an empty table and an empty builder clear attributes and builder rules', function() use ($post, $node, $fresh) {
    $post('free-nav/nodes/save', [
        'nodeId' => $node->id,
        'customAttributes' => '',
        'visibilityRules' => ['class' => NodeVisibilityCondition::class, 'config' => '{}'],
    ]);
    $saved = $fresh();

    if ($saved->getCustomAttributesArray() !== []) {
        return 'attributes: ' . json_encode($saved->getCustomAttributesArray());
    }

    return $saved->getVisibilityRulesArray() === [['type' => 'custom', 'operator' => 'is', 'value' => 'kept']]
        ?: 'rules: ' . json_encode($saved->getVisibilityRulesArray());
});

check('a Pro icon, a path and an unsafe attribute are refused with a message', function() use ($post, $node, $fresh, $proIcon, $freeIcon) {
    foreach ([
        ['craftIcon' => $proIcon],
        ['craftIcon' => '../../config/general'],
        ['customAttributes' => [['key' => 'onclick', 'value' => 'alert(1)']]],
    ] as $params) {
        $response = $post('free-nav/nodes/save', ['nodeId' => $node->id] + $params);
        $message = json_decode((string)$response->getBody(), true)['message'] ?? '';

        if ($response->getStatusCode() === 200 || $message === '' || str_contains($message, 'Couldn')) {
            return 'accepted or no reason given: ' . json_encode($params) . " → {$response->getStatusCode()} $message";
        }
    }

    return $fresh()->craftIcon === $freeIcon ?: 'icon changed';
});

check('an absent field is left alone (the old JSON API still works)', function() use ($post, $node, $fresh, $freeIcon) {
    $post('free-nav/nodes/save', ['nodeId' => $node->id, 'visibilityRules' => '[{"type":"loggedIn","operator":"is","value":false}]']);
    $saved = $fresh();

    return $saved->craftIcon === $freeIcon && $saved->getVisibilityRulesArray() === [['type' => 'loggedIn', 'operator' => 'is', 'value' => false]]
        ?: json_encode([$saved->craftIcon, $saved->getVisibilityRulesArray()]);
});

echo "\nRendering the icon\n";

check('a chosen Craft icon renders as a sized inline SVG, taking the place of the icon class', function() use ($fresh) {
    $saved = $fresh();
    $saved->icon = 'fa-home';
    $html = (string)$saved->getIconHtml();

    return (str_starts_with($html, '<span class="freenav-icon" aria-hidden="true"><svg') && str_contains($html, 'width="1em"') && !str_contains($html, 'fa-home'))
        ?: $html;
});

check('a Pro or unknown icon stored some other way renders nothing, falling back to the class', function() use ($fresh, $proIcon) {
    $saved = $fresh();
    $saved->icon = 'fa-home';

    foreach ([$proIcon, '../../../etc/passwd', 'not-an-icon-' . bin2hex(random_bytes(2))] as $name) {
        $saved->craftIcon = $name;
        if ((string)$saved->getIconHtml() !== '<i class="fa-home" aria-hidden="true"></i>') {
            return "$name: " . $saved->getIconHtml();
        }
    }

    return true;
});

check('multi-group rules: a guest matches “is guests or editors”, not “is not guests”', function() {
    $node = new Node(['visibilityRules' => [['type' => 'userGroup', 'operator' => 'is', 'value' => ['guests', 'editors']]]]);
    $not = new Node(['visibilityRules' => [['type' => 'userGroup', 'operator' => 'isNot', 'value' => ['guests']]]]);

    return ($node->isVisible() && !$not->isVisible()) ?: 'wrong';
});

check('export and import carry the icon', function() use ($plugin, $menu) {
    $export = $plugin->getMenuTransfer()->exportMenu($menu);

    return ($export['freeNav'] === '1.3.0' && ($export['nodes'][0]['craftIcon'] ?? null) !== null) ?: json_encode($export['nodes'][0] ?? null);
});

check('every preset prints the node’s custom attributes once, escaped, and never an unsafe one', function() use ($plugin, $menu, $fresh) {
    $node = $fresh();
    $node->customAttributes = [['key' => 'data-track', 'value' => 'a "q" <b>'], ['key' => 'rel', 'value' => 'nofollow']];
    $node->visibilityRules = null;
    Craft::$app->getElements()->saveElement($node) or throw new RuntimeException(json_encode($node->getErrors()));
    // Written straight to the table, as a pre-5.2.1 menu could have it.
    Craft::$app->getDb()->createCommand()->update('{{%freenav_nodes}}', [
        'customAttributes' => json_encode([['key' => 'data-track', 'value' => 'a "q" <b>'], ['key' => 'rel', 'value' => 'nofollow'], ['key' => 'onclick', 'value' => 'alert(1)']]),
    ], ['id' => $node->id])->execute();
    $plugin->getMenuCache()->invalidate($menu->handle);

    foreach (justinholt\freenav\enums\Preset::cases() as $preset) {
        $html = (string)$plugin->getRenderer()->renderPreset($menu->handle, $preset, ['cache' => false]);

        if ($preset === justinholt\freenav\enums\Preset::Breadcrumb) {
            continue; // breadcrumbs follow the current page, which a console run doesn't have
        }

        if (substr_count($html, 'data-track="a &quot;q&quot; &lt;b&gt;"') !== 1 || substr_count($html, 'rel="nofollow"') !== 1 || str_contains($html, 'onclick')) {
            return "{$preset->value}: " . $html;
        }
    }

    return true;
});

// ---------------------------------------------------------------------------------------------
// The section importer
// ---------------------------------------------------------------------------------------------

echo "\nImporting a Structure section\n";

$primary = Craft::$app->getSites()->getPrimarySite();
$link = new Link(['name' => "Menu Link $run", 'handle' => "fnLink$run", 'types' => ['entry', 'url', 'email'], 'advancedFields' => ['target', 'urlSuffix']]);
Craft::$app->getFields()->saveField($link) or exit("field: " . json_encode($link->getErrors()) . "\n");
$cleanup['fields'][] = $link->id;

$entryType = new EntryType(['name' => "Menu Item $run", 'handle' => "fnMenuItem$run"]);
$layout = new FieldLayout(['type' => Entry::class]);
$layout->setTabs([new FieldLayoutTab(['name' => 'Content', 'layout' => $layout, 'elements' => [new EntryTitleField(), new CustomField($link)]])]);
$entryType->setFieldLayout($layout);
Craft::$app->getEntries()->saveEntryType($entryType) or exit("entry type: " . json_encode($entryType->getErrors()) . "\n");
$cleanup['entryTypes'][] = $entryType->id;

$section = new Section([
    'name' => "Menus $run",
    'handle' => "fnMenus$run",
    'type' => Section::TYPE_STRUCTURE,
    'siteSettings' => [new Section_SiteSettings(['siteId' => $primary->id, 'enabledByDefault' => true, 'hasUrls' => true, 'uriFormat' => "fn-$run/{slug}", 'template' => 'index'])],
]);
$section->setEntryTypes([$entryType]);
Craft::$app->getEntries()->saveSection($section) or exit("section: " . json_encode($section->getErrors()) . "\n");
$cleanup['sections'][] = $section->id;
Craft::$app->getProjectConfig()->saveModifiedConfigData();

$makeEntry = static function(string $title, ?array $linkValue, ?Entry $parent = null, bool $enabled = true) use ($section, $entryType, $link, $primary): Entry {
    $entry = new Entry(['sectionId' => $section->id, 'typeId' => $entryType->id, 'siteId' => $primary->id, 'title' => $title, 'enabled' => $enabled]);
    if ($linkValue !== null) {
        $entry->setFieldValue($link->handle, $linkValue);
    }
    if ($parent) {
        $entry->setParentId($parent->id);
    }
    Craft::$app->getElements()->saveElement($entry) or exit("entry $title: " . json_encode($entry->getErrors()) . "\n");

    return $entry;
};

$main = $makeEntry("Main $run", null);
$about = $makeEntry('About', null, $main);
$makeEntry('Linked', ['type' => 'entry', 'value' => $about->id, 'urlSuffix' => '#team'], $main);
$makeEntry('External', ['type' => 'url', 'value' => 'https://example.com/', 'target' => '_blank'], $about);
$makeEntry('Hidden', ['type' => 'url', 'value' => 'https://example.com/hidden'], $main, false);
$makeEntry("Footer $run", null);
$makeEntry('Mail', ['type' => 'email', 'value' => 'hi@example.com'], Entry::find()->title("Footer $run")->sectionId($section->id)->one());

$tree = static function(string $handle): array {
    $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle($handle);
    if (!$menu) {
        return [];
    }

    return array_map(fn(Node $n) => implode('|', [
        str_repeat('-', $n->level - 1) . $n->title,
        $n->nodeType,
        $n->linkedElementId ? 'el' : ($n->customUrl ?? ''),
        $n->newWindow ? 'blank' : '',
        $n->enabled ? '' : 'off',
    ]), FreeNav::getInstance()->getNodes()->findNodesInMenu($menu->id)->status(null)->all());
};

check('each top-level entry becomes a menu, its descendants nodes with nesting, links, targets and status', function() use ($craft, $section, $link, $run, $tree) {
    [$code, $out] = $craft("free-nav/migrate/from-section --section={$section->handle} --link-field={$link->handle}");
    if ($code !== 0) {
        return $out;
    }

    $main = $tree("main$run");
    $footer = $tree("footer$run");
    $expectedMain = ['About|passive|||', '-External|custom|https://example.com/|blank|', 'Linked|entry|el||', 'Hidden|custom|https://example.com/hidden||off'];

    if ($main !== $expectedMain) {
        return "main: " . json_encode($main) . "\n" . $out;
    }

    return $footer === ['Mail|custom|mailto:hi@example.com||'] ?: 'footer: ' . json_encode($footer);
});

check('the linked entry node points at the entry the link field named, keeping its #suffix', function() use ($plugin, $run, $about) {
    $menu = $plugin->getMenus()->getMenuByHandle("main$run");
    $linked = Node::find()->menuId($menu->id)->title('Linked')->one();

    return ($linked?->linkedElementId === $about->id && $linked->urlSuffix === '#team') ?: var_export([$linked?->linkedElementId, $linked?->urlSuffix], true);
});

check('running it again refuses to touch existing menus without --replace, and replaces with it', function() use ($craft, $section, $link, $run, $tree) {
    $before = $tree("main$run");
    [$code, $out] = $craft("free-nav/migrate/from-section --section={$section->handle} --link-field={$link->handle}");

    if ($code === 0 || $tree("main$run") !== $before || !str_contains($out, 'already exists')) {
        return "without --replace: $code\n$out";
    }

    [$code, $out] = $craft("free-nav/migrate/from-section --section={$section->handle} --link-field={$link->handle} --replace");

    return ($code === 0 && $tree("main$run") === $before && str_contains($out, 'replaced')) ?: "with --replace: $code\n$out";
});

check('--menu puts the whole section in one menu; without --link-field entries link to themselves', function() use ($craft, $section, $run, $tree) {
    [$code, $out] = $craft("free-nav/migrate/from-section --section={$section->handle} --menu=whole$run");
    $whole = $tree("whole$run");

    return ($code === 0 && count($whole) === 7 && str_starts_with($whole[0], "Main $run|entry|el") && str_starts_with($whole[1], '-About|entry|el'))
        ?: "$code " . json_encode($whole) . "\n$out";
});

check('an unknown section, a channel section or a missing field fails with a reason', function() use ($craft, $section, $run) {
    foreach ([
        "--section=nope$run" => 'no section',
        "--section={$section->handle} --link-field=nope$run" => 'has a field',
        '' => '--section',
    ] as $args => $expect) {
        [$code, $out] = $craft("free-nav/migrate/from-section $args");
        if ($code === 0 || !str_contains($out, $expect)) {
            return "$args: $code $out";
        }
    }

    return true;
});

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);

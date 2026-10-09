<?php
/**
 * Menu JSON export/import — the console commands, the service they share with the control
 * panel, and the control panel's own import.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-freenav/tests/integration/transfer.php
 *
 * The console commands are run for real (`php craft free-nav/menus/…`) in a child process. An
 * import is all-or-nothing, so each refusal checks that the menu's nodes are exactly the ones
 * that were there before, not merely that the command failed.
 *
 * Idempotent and self-cleaning: the menus it creates (here, from the console and over HTTP) and
 * the users are removed at the end, in a fresh PHP process, because the child processes change
 * project config behind this one's back.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

use craft\elements\Entry;
use craft\elements\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
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
$transfer = $plugin->getMenuTransfer();
$run = substr(bin2hex(random_bytes(3)), 0, 6);
$handle = "transfer$run";
$tmp = sys_get_temp_dir() . "/freenav-transfer-$run";
@mkdir($tmp);
$userIds = [];

register_shutdown_function(function() use ($run, $tmp, &$userIds) {
    // Fresh process: the console runs and the CP import changed project config under us.
    $script = $tmp . '/teardown.php';
    file_put_contents($script, '<?php
        $root = getcwd();
        require $root . "/bootstrap.php";
        $app = require CRAFT_VENDOR_PATH . "/craftcms/cms/bootstrap/console.php";
        Craft::$app->getPlugins()->loadPlugins();
        $plugin = justinholt\freenav\FreeNav::getInstance();
        foreach ($plugin->getMenus()->getAllMenus() as $menu) {
            if (!str_starts_with($menu->handle, "transfer' . $run . '") && !str_starts_with($menu->handle, "cliMade' . $run . '")) {
                continue;
            }
            foreach (justinholt\freenav\elements\Node::find()->menuId($menu->id)->siteId("*")->status(null)->trashed(null)->all() as $node) {
                Craft::$app->getElements()->deleteElement($node, true);
            }
            $plugin->getMenus()->deleteMenu($menu);
        }
        foreach (' . var_export($userIds, true) . ' as $id) {
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

$craft = static function(string $args, ?string $stdin = null): array {
    $cmd = PHP_BINARY . ' craft ' . $args . ' 2>&1';
    if ($stdin !== null) {
        $cmd = 'cat ' . escapeshellarg($stdin) . ' | ' . $cmd;
    }
    exec($cmd, $out, $code);

    return [$code, implode("\n", $out)];
};

// What a menu holds right now: [id, title, level, linkedElementId], in structure order.
$snapshot = static function(string $handle): array {
    $menu = FreeNav::getInstance()->getMenus()->getMenuByHandle($handle);
    if (!$menu) {
        return [];
    }

    return array_map(
        fn(Node $n) => [$n->id, $n->title, (int)$n->level, $n->linkedElementId],
        FreeNav::getInstance()->getNodes()->findNodesInMenu($menu->id)->all(),
    );
};
$titles = static fn(array $snap) => array_map(fn($row) => str_repeat('-', $row[2] - 1) . $row[1], $snap);

// A menu with two parents that each have children — the shape the old export got wrong.
$menu = new Menu();
$menu->name = "Transfer $run";
$menu->handle = $handle;
$siteSettings = [];
foreach (Craft::$app->getSites()->getAllSites() as $site) {
    $siteSettings[$site->id] = new MenuSiteSettings(['siteId' => $site->id, 'enabled' => true]);
}
$menu->setSiteSettings($siteSettings);

if (!$plugin->getMenus()->saveMenu($menu)) {
    echo 'Could not create the test menu: ' . json_encode($menu->getErrors()) . "\n";
    exit(1);
}

Craft::$app->getProjectConfig()->saveModifiedConfigData();
Craft::$app->getProjectConfig()->writeYamlFiles(true);
$menu = $plugin->getMenus()->getMenuById($menu->id);

$entry = Entry::find()->status(null)->site('*')->unique()->one();
if (!$entry) {
    echo "The harness has no entries to link to.\n";
    exit(1);
}

$nodes = $plugin->getNodes();
[$a] = $nodes->addNodes($menu, [['title' => 'A', 'customUrl' => '/a']]);
$nodes->addNodes($menu, [['title' => 'A1', 'customUrl' => '/a/1', 'parentId' => $a->id], ['title' => 'A2', 'customUrl' => '/a/2', 'parentId' => $a->id]]);
[$b] = $nodes->addNodes($menu, [['title' => 'B', 'nodeType' => 'entry', 'linkedElementId' => $entry->id, 'visibilityRules' => [['type' => 'loggedIn', 'operator' => 'is', 'value' => true]]]]);
$nodes->addNodes($menu, [['title' => 'B1', 'nodeType' => 'passive', 'parentId' => $b->id]]);
$nodes->addNodes($menu, [['title' => 'C', 'customUrl' => 'https://example.com/c', 'newWindow' => true]]);

$expected = ['A', '-A1', '-A2', 'B', '-B1', 'C'];

echo "\nExport\n";

check('the export nests each node under its own parent', function() use ($transfer, $menu, $entry) {
    $export = $transfer->exportMenu($menu);
    $shape = array_map(fn($n) => [$n['title'], array_column($n['children'] ?? [], 'title')], $export['nodes']);
    $b = $export['nodes'][1];

    return $shape === [['A', ['A1', 'A2']], ['B', ['B1']], ['C', []]]
        && $b['linkedElementUid'] === $entry->uid
        && $b['visibilityRules'][0]['type'] === 'loggedIn'
        ?: json_encode($shape);
});

check('`free-nav/menus/export <handle> --file` writes the same file the CP downloads', function() use ($craft, $handle, $tmp, $transfer, $menu) {
    [$code, $out] = $craft("free-nav/menus/export $handle --file=$tmp/menu.json");
    $written = json_decode((string)@file_get_contents("$tmp/menu.json"), true);

    return $code === 0 && $written === json_decode($transfer->encode($transfer->exportMenu($menu)), true) ?: "exit $code: $out";
});

check('without --file it prints the JSON to stdout', function() use ($craft, $handle) {
    [$code, $out] = $craft("free-nav/menus/export $handle");

    return $code === 0 && (json_decode($out, true)['menu']['handle'] ?? null) === $handle ?: "exit $code: " . substr($out, 0, 200);
});

check('an unknown handle fails', function() use ($craft, $run) {
    [$code] = $craft("free-nav/menus/export nope$run");

    return $code !== 0 ?: 'exit 0';
});

echo "\nImport from the console\n";

$before = $snapshot($handle);

check('importing over an existing menu without --replace changes nothing', function() use ($craft, $handle, $tmp, $snapshot, $before) {
    [$code, $out] = $craft("free-nav/menus/import --file=$tmp/menu.json");

    return $code !== 0 && str_contains($out, 'already exists') && $snapshot($handle) === $before ?: "exit $code: $out";
});

// An edited export: A renamed, A2 moved under B, C dropped, a new nested node added.
$file = json_decode(file_get_contents("$tmp/menu.json"), true);
$file['nodes'][0]['title'] = 'A (edited)';
$a2 = array_pop($file['nodes'][0]['children']);
$file['nodes'][1]['children'][] = $a2;
array_pop($file['nodes']);
$file['nodes'][1]['children'][0]['children'] = [['title' => 'B1a', 'nodeType' => 'custom', 'customUrl' => 'mailto:hello@example.com']];
file_put_contents("$tmp/edited.json", json_encode($file));

check('--replace swaps the nodes for the file’s, nesting and entry links intact', function() use ($craft, $handle, $tmp, $snapshot, $titles, $before, $entry) {
    [$code, $out] = $craft("free-nav/menus/import --file=$tmp/edited.json --replace");
    $after = $snapshot($handle);
    $oldIds = array_column($before, 0);
    $survivors = array_intersect($oldIds, array_column($after, 0));
    $b = array_values(array_filter($after, fn($row) => $row[1] === 'B'))[0] ?? null;

    return $code === 0
        && $titles($after) === ['A (edited)', '-A1', 'B', '-B1', '--B1a', '-A2']
        && $survivors === []
        && $b && $b[3] === $entry->id
        ?: "exit $code: $out\n    " . json_encode($titles($after));
});

check('the replaced menu renders the new nodes (cache invalidated)', function() use ($plugin, $handle) {
    $plugin->getSettings()->cacheEnabled = true;
    $html = (string)$plugin->getRenderer()->render($handle);

    return str_contains($html, 'A (edited)') && !str_contains($html, '>C<') ?: substr(strip_tags($html), 0, 200);
});

$current = $snapshot($handle);

check('a file with a javascript: custom URL is refused and the menu is untouched', function() use ($craft, $handle, $tmp, $snapshot, $current, $file) {
    $bad = $file;
    $bad['nodes'][] = ['title' => 'Evil', 'nodeType' => 'custom', 'customUrl' => "java\tscript:alert(1)"];
    file_put_contents("$tmp/bad.json", json_encode($bad));
    [$code, $out] = $craft("free-nav/menus/import --file=$tmp/bad.json --replace");

    return $code !== 0 && str_contains($out, 'Evil') && $snapshot($handle) === $current ?: "exit $code: $out";
});

check('a custom URL naming a secret is refused too', function() use ($craft, $handle, $tmp, $snapshot, $current, $file) {
    $bad = $file;
    $bad['nodes'][0]['children'][0]['customUrl'] = '$CRAFT_SECURITY_KEY';
    $bad['nodes'][0]['children'][0]['nodeType'] = 'custom';
    file_put_contents("$tmp/secret.json", json_encode($bad));
    [$code, $out] = $craft("free-nav/menus/import --file=$tmp/secret.json --replace");

    return $code !== 0 && $snapshot($handle) === $current ?: "exit $code: $out";
});

check('a file that isn’t an export, or a missing --file, fails cleanly', function() use ($craft, $tmp) {
    file_put_contents("$tmp/junk.json", '{"hello": "world"}');
    [$junk] = $craft("free-nav/menus/import --file=$tmp/junk.json --replace");
    [$missing] = $craft('free-nav/menus/import --replace');
    [$nofile] = $craft("free-nav/menus/import --file=$tmp/does-not-exist.json");

    return $junk !== 0 && $missing !== 0 && $nofile !== 0 ?: "junk $junk, missing $missing, nofile $nofile";
});

check('a handle argument creates a new menu from stdin (--file=-)', function() use ($craft, $run, $tmp, $snapshot, $titles) {
    [$code, $out] = $craft("free-nav/menus/import cliMade$run --file=-", "$tmp/edited.json");
    Craft::$app->getProjectConfig()->reset();
    $made = FreeNav::getInstance()->getMenus()->getMenuByHandle("cliMade$run");

    return $code === 0 && $made && $titles($snapshot("cliMade$run")) === ['A (edited)', '-A1', 'B', '-B1', '--B1a', '-A2']
        ?: "exit $code: $out";
});

echo "\nThe import service's guards\n";

check('a linked-element class in the file other than the node type’s is never queried', function() use ($transfer, $handle, $snapshot) {
    $admin = User::find()->admin(true)->one();
    $data = ['freeNav' => '1.2.0', 'menu' => ['handle' => $handle], 'nodes' => [
        ['title' => 'Spoof', 'nodeType' => 'entry', 'linkedElementType' => User::class, 'linkedElementUid' => $admin->uid],
    ]];
    $result = $transfer->importMenu($data, replace: true);
    $row = $snapshot($handle)[0] ?? null;

    return $result->getSuccess() && $row && $row[3] === null && $result->warnings !== [] ?: json_encode([$result->errors, $row]);
});

check('a menu’s node limit is enforced before anything is replaced', function() use ($transfer, $handle, $snapshot, $file) {
    // Menus are read fresh from their table, so the limit goes there (and only for this check).
    $current = $snapshot($handle);
    $db = Craft::$app->getDb();
    $db->createCommand()->update('{{%freenav_menus}}', ['maxNodes' => 2], ['handle' => $handle])->execute();

    try {
        $result = $transfer->importMenu($file, replace: true);
    } finally {
        $db->createCommand()->update('{{%freenav_menus}}', ['maxNodes' => null], ['handle' => $handle])->execute();
    }

    return !$result->getSuccess() && $snapshot($handle) === $current ?: json_encode($result->errors);
});

check('creating a menu needs allowAdminChanges; replacing nodes doesn’t', function() use ($transfer, $plugin, $file, $run, $handle) {
    $general = Craft::$app->getConfig()->getGeneral();
    $was = $general->allowAdminChanges;
    $general->allowAdminChanges = false;

    try {
        $create = $transfer->importMenu($file, "transfer{$run}Locked");
        $replace = $transfer->importMenu($file, $handle, replace: true);
    } finally {
        $general->allowAdminChanges = $was;
    }

    return !$create->getSuccess() && !$create->createdMenu && $plugin->getMenus()->getMenuByHandle("transfer{$run}Locked") === null
        && $replace->getSuccess()
        ?: json_encode([$create->errors, $replace->errors]);
});

Craft::$app->getProjectConfig()->saveModifiedConfigData();
Craft::$app->getProjectConfig()->writeYamlFiles(true);

echo "\nThe control panel import (same service)\n";

$makeUser = static function(string $name, array $permissions, bool $admin = false) use ($run, &$userIds): array {
    $password = 'fn-' . bin2hex(random_bytes(12));
    $user = new User();
    $user->username = "freenav-$name-$run";
    $user->email = "freenav-$name-$run@example.com";
    $user->newPassword = $password;
    $user->admin = $admin;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);
    Craft::$app->getUserPermissions()->saveUserPermissions($user->id, array_map('strtolower', $permissions));
    $userIds[] = $user->id;

    return [$user, $password];
};

$signIn = static function(User $user, string $password): array {
    $http = new Client(['base_uri' => 'http://localhost/', 'cookies' => new CookieJar(), 'http_errors' => false, 'allow_redirects' => false]);
    $csrf = static function() use ($http): string {
        $info = json_decode((string)$http->get('index.php?p=actions/users/session-info', ['headers' => ['Accept' => 'application/json']])->getBody(), true);

        return (string)($info['csrfTokenValue'] ?? '');
    };
    $http->post('index.php?p=actions/users/login', [
        'headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'],
        'form_params' => ['loginName' => $user->username, 'password' => $password, 'CRAFT_CSRF_TOKEN' => $csrf()],
    ]);
    $upload = static fn(string $json) => $http->post('index.php?p=admin/actions/free-nav/import-export/import', [
        'multipart' => [
            ['name' => 'importFile', 'contents' => $json, 'filename' => 'menu.json'],
            ['name' => 'CRAFT_CSRF_TOKEN', 'contents' => $csrf()],
        ],
    ]);

    return [$http, $upload];
};

[$viewer, $viewerPassword] = $makeUser('viewer', ['accessCp', 'accessPlugin-free-nav']);
[$admin, $adminPassword] = $makeUser('admin', [], true);

check('a CP user without "manage menus" can’t import', function() use ($signIn, $viewer, $viewerPassword, $tmp, $handle) {
    [, $upload] = $signIn($viewer, $viewerPassword);
    $status = $upload(file_get_contents("$tmp/menu.json"))->getStatusCode();

    return $status === 403 && FreeNav::getInstance()->getMenus()->getMenuByHandle($handle . '_imported') === null ?: "status $status";
});

check('an admin’s upload creates a copy beside the existing menu, nested correctly', function() use ($signIn, $admin, $adminPassword, $tmp, $handle, $snapshot, $titles) {
    [, $upload] = $signIn($admin, $adminPassword);
    $response = $upload(file_get_contents("$tmp/menu.json"));
    Craft::$app->getProjectConfig()->reset();
    $copy = $titles($snapshot($handle . '_imported'));

    return $response->getStatusCode() === 302 && str_contains($response->getHeaderLine('Location'), '/build') && $copy === ['A', '-A1', '-A2', 'B', '-B1', 'C']
        ?: 'status ' . $response->getStatusCode() . ' ' . $response->getHeaderLine('Location') . ' ' . json_encode($copy);
});

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);

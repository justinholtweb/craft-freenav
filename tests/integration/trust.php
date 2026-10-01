<?php
/**
 * Who can do what in FreeNav — checked over HTTP, plus the parts of rendering that depend on who
 * is looking.
 *
 *     docker exec -w /var/www/html ddev-plugin-testing-web php /var/www/craft-freenav/tests/integration/trust.php
 *
 * Until 5.1.5 no CP controller action checked a permission, so any signed-in user — a front-end
 * member included — could delete menus, edit nodes or change settings. Each refusal here is paired
 * with something the same user *is* allowed, so a pass means "refused", not "broken".
 *
 * Idempotent and self-cleaning: the users, the menu and its nodes are removed at the end, and the
 * REST API setting is put back.
 */

$root = getcwd();
require $root . '/bootstrap.php';

/** @var craft\console\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/console.php';

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
$run = substr(bin2hex(random_bytes(3)), 0, 6);
$users = [];
$menu = null;
$restApiWas = $plugin->getSettings()->restApiEnabled;

register_shutdown_function(function() use (&$users, &$menu, $restApiWas) {
    $plugin = FreeNav::getInstance();

    if ($plugin->getSettings()->restApiEnabled !== $restApiWas) {
        Craft::$app->getPlugins()->savePluginSettings($plugin, ['restApiEnabled' => $restApiWas]);
    }

    if ($menu !== null && ($fresh = $plugin->getMenus()->getMenuById($menu->id))) {
        foreach (Node::find()->menuId($fresh->id)->siteId('*')->status(null)->all() as $node) {
            Craft::$app->getElements()->deleteElement($node, true);
        }

        $plugin->getMenus()->deleteMenu($fresh);
    }

    foreach ($users as $user) {
        Craft::$app->getElements()->deleteElement($user, true);
    }
});

// A menu of our own, enabled everywhere.
$menu = new Menu();
$menu->name = "Trust $run";
$menu->handle = "trust$run";
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

[$public, $membersOnly] = $plugin->getNodes()->addNodes($menu, [
    ['title' => "Public $run", 'customUrl' => '/public'],
    ['title' => "Members $run", 'customUrl' => '/members', 'visibilityRules' => [['type' => 'loggedIn', 'operator' => 'is', 'value' => true]]],
]);

$makeUser = static function(string $name, array $permissions) use ($run, &$users): array {
    $password = 'fn-' . bin2hex(random_bytes(12));
    $user = new User();
    $user->username = "freenav-$name-$run";
    $user->email = "freenav-$name-$run@example.com";
    $user->newPassword = $password;
    Craft::$app->getElements()->saveElement($user, false);
    Craft::$app->getUsers()->activateUser($user);
    Craft::$app->getUserPermissions()->saveUserPermissions($user->id, array_map('strtolower', $permissions));
    $users[] = $user;

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

    $post = static fn(string $action, array $params = []) => $http->post("index.php?p=admin/actions/free-nav/$action", [
        'headers' => ['Accept' => 'application/json'],
        'form_params' => $params + ['CRAFT_CSRF_TOKEN' => $csrf()],
    ]);

    return [$http, $post, $csrf];
};

$menuExists = static fn() => FreeNav::getInstance()->getMenus()->getMenuById($menu->id) !== null;

echo "\nA front-end member (no control panel access)\n";

[$member, $memberPassword] = $makeUser('member', []);
[$memberHttp, $memberPost, $memberCsrf] = $signIn($member, $memberPassword);

check('cannot delete a menu through the CP action URL', function() use ($memberPost, $menu, $menuExists) {
    $status = $memberPost('menus/delete', ['id' => $menu->id])->getStatusCode();

    return $status === 403 && $menuExists() ?: "status $status, menu " . ($menuExists() ? 'kept' : 'DELETED');
});

check('cannot delete a menu through the site action URL either', function() use ($memberHttp, $memberCsrf, $menu, $menuExists) {
    $status = $memberHttp->post('index.php?p=actions/free-nav/menus/delete', [
        'headers' => ['Accept' => 'application/json'],
        'form_params' => ['id' => $menu->id, 'CRAFT_CSRF_TOKEN' => $memberCsrf()],
    ])->getStatusCode();

    return $status >= 400 && $menuExists() ?: "status $status, menu " . ($menuExists() ? 'kept' : 'DELETED');
});

check('cannot change a node', function() use ($memberPost, $public) {
    $status = $memberPost('nodes/save', ['nodeId' => $public->id, 'title' => 'Hijacked'])->getStatusCode();
    $title = Node::find()->id($public->id)->status(null)->one()?->title;

    return $status === 403 && $title !== 'Hijacked' ?: "status $status, title $title";
});

echo "\nA CP user with FreeNav access but no FreeNav permissions\n";

[$viewer, $viewerPassword] = $makeUser('viewer', ['accessCp', 'accessPlugin-free-nav']);
[, $viewerPost] = $signIn($viewer, $viewerPassword);

check('cannot save plugin settings', function() use ($viewerPost) {
    $status = $viewerPost('menus/save-settings', ['restApiEnabled' => '1'])->getStatusCode();

    return $status === 403 ?: "status $status";
});

check('cannot import a menu', function() use ($viewerPost) {
    $status = $viewerPost('import-export/import')->getStatusCode();

    return $status === 403 ?: "status $status";
});

echo "\nAn editor allowed to edit nodes in this one menu\n";

// Craft's own site access applies too: the builder works on a site the editor may edit.
$siteAccess = array_map(fn($site) => 'editSite:' . $site->uid, Craft::$app->getSites()->getAllSites());
[$editor, $editorPassword] = $makeUser('editor', array_merge(['accessCp', 'accessPlugin-free-nav', 'freeNav-editNodes:' . $menu->uid], $siteAccess));
[$editorHttp, $editorPost] = $signIn($editor, $editorPassword);

check('can open the builder and edit a node', function() use ($editorHttp, $editorPost, $menu, $public, $run) {
    $build = $editorHttp->get("admin/free-nav/menus/{$menu->id}/build")->getStatusCode();
    $save = $editorPost('nodes/save', ['nodeId' => $public->id, 'title' => "Edited $run"])->getStatusCode();
    $title = Node::find()->id($public->id)->status(null)->one()?->title;

    return in_array($build, [200, 302], true) && $save === 200 && $title === "Edited $run" ?: "build $build, save $save, title $title";
});

check('cannot delete a node, edit the menu, or delete it', function() use ($editorPost, $editorHttp, $menu, $public, $menuExists) {
    $deleteNode = $editorPost('nodes/delete', ['nodeId' => $public->id])->getStatusCode();
    $editMenu = $editorHttp->get("admin/free-nav/menus/{$menu->id}")->getStatusCode();
    $deleteMenu = $editorPost('menus/delete', ['id' => $menu->id])->getStatusCode();
    $nodeKept = Node::find()->id($public->id)->status(null)->exists();

    return $deleteNode === 403 && $editMenu === 403 && $deleteMenu === 403 && $nodeKept && $menuExists()
        ?: "node delete $deleteNode, menu edit $editMenu, menu delete $deleteMenu";
});

check('cannot point a node at javascript: or at a secret', function() use ($editorPost, $public) {
    $script = json_decode((string)$editorPost('nodes/save', ['nodeId' => $public->id, 'customUrl' => "java\tscript:alert(1)"])->getBody(), true);
    $secret = json_decode((string)$editorPost('nodes/save', ['nodeId' => $public->id, 'customUrl' => '$CRAFT_SECURITY_KEY'])->getBody(), true);
    $stored = Node::find()->id($public->id)->status(null)->one()?->customUrl;

    return empty($script['success'] ?? null) && empty($secret['success'] ?? null) && $stored === '/public'
        ?: 'script ' . json_encode($script) . ', secret ' . json_encode($secret) . ", stored $stored";
});

echo "\nRendering\n";

check('a URL stored before 5.1.5 that runs script or names a secret renders as no link', function() use ($public) {
    $node = Node::find()->id($public->id)->status(null)->one();
    $node->customUrl = 'javascript:alert(1)';
    $script = $node->getUrl();
    $node->customUrl = '$CRAFT_SECURITY_KEY';
    $secret = $node->getUrl();
    $node->customUrl = 'mailto:hello@example.com';
    $mail = $node->getUrl();

    return $script === null && $secret === null && $mail === 'mailto:hello@example.com'
        ?: json_encode([$script, $secret === null ? null : 'LEAKED', $mail]);
});

check('a members-only link rendered for a member is not served from cache to a visitor', function() use ($plugin, $menu, $run) {
    $settings = $plugin->getSettings();
    $settings->cacheEnabled = true;
    $plugin->getMenuCache()->invalidate($menu->handle);

    $someone = User::find()->admin(true)->one();
    Craft::$app->getUser()->setIdentity($someone);
    $asMember = (string)$plugin->getRenderer()->render($menu->handle);

    Craft::$app->getUser()->setIdentity(null);
    $asVisitor = (string)$plugin->getRenderer()->render($menu->handle);

    return str_contains($asMember, "Members $run") && !str_contains($asVisitor, "Members $run")
        ?: 'member ' . (str_contains($asMember, "Members $run") ? 'sees it' : 'does not') . ', visitor ' . (str_contains($asVisitor, "Members $run") ? 'SEES IT' : 'does not');
});

echo "\nThe REST API\n";

Craft::$app->getPlugins()->savePluginSettings($plugin, ['restApiEnabled' => true]);
Craft::$app->getProjectConfig()->saveModifiedConfigData();

check('an anonymous caller does not get members-only links, and gets each node once', function() use ($menu, $run) {
    $body = (string)(new Client(['base_uri' => 'http://localhost/', 'http_errors' => false]))
        ->get('index.php?p=actions/free-nav/api/get-menu&handle=' . $menu->handle, ['headers' => ['Accept' => 'application/json']])
        ->getBody();
    $titles = [];
    $walk = function(array $nodes) use (&$walk, &$titles) {
        foreach ($nodes as $node) {
            $titles[] = $node['title'];
            $walk($node['children'] ?? []);
        }
    };
    $walk(json_decode($body, true)['nodes'] ?? []);

    return !in_array("Members $run", $titles, true) && count($titles) === count(array_unique($titles)) && $titles !== []
        ?: 'got ' . json_encode($titles);
});

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);

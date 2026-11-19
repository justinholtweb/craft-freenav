# Upgrading FreeNav from Craft 5 to Craft 6

FreeNav 6 is a native Craft 6 plugin. It runs on Laravel and has no dependency on the Yii 2
adapter. Your menus, nodes, templates, REST API calls and GraphQL queries carry over. What changes
is the control panel, which now uses Craft's own element index and editor for nodes, and the PHP
around the plugin: namespaces, events and console commands.

> **Beta.** FreeNav 6 is in beta, like Craft 6 itself. Test the upgrade on a copy of your site
> before relying on it in production. FreeNav 5 remains the stable release for Craft 5.

| | FreeNav 5.x | FreeNav 6.x |
| --- | --- | --- |
| Status | Stable | Beta |
| Craft CMS | 5.0+ | 6.0+ |
| PHP | 8.2+ | 8.5+ |
| Commands | `php craft free-nav/menus/clear-cache` | `php artisan free-nav:menus:clear-cache` (old names still work) |
| Node types | Entry, category, asset, product, custom, passive, site | Entry, asset, custom, passive, site (product with Commerce 6) |

## Before you start

1. **Update to FreeNav 5.2.1** while the site is still on Craft 5, and run its migrations.
   FreeNav 6 won't install over anything older.

   ```bash
   composer require justinholtweb/craft-free-nav:^5.2.1
   php craft up
   ```

   5.2.1 also refuses unsafe custom attributes and URL suffixes. Saving a node that has one shows
   which; it's worth fixing them before you upgrade.

2. **Convert categories to entries before upgrading Craft.** Craft 6 has no categories, and Craft's
   own upgrade expects them converted first (`php craft entrify/categories <group>` on Craft 5).
   That conversion keeps each category's element ID, and FreeNav's upgrade relies on it: a category
   node whose category became an entry becomes an entry node, linked to the same element. A
   category node whose category wasn't converted becomes a custom URL node pointing to where the
   category was; one with no URL is disabled. Each is logged.

3. **Commerce product nodes** need Commerce 6, which isn't released yet. They are kept, but have no
   link until it is. Leave sites that depend on them on Craft 5 for now.

4. **Find your extensions.** If a plugin or module listens for FreeNav's events, or extends its
   classes, it needs the changes under [Custom code](#custom-code).

## Upgrade

Follow Craft's [Craft 6 upgrade guide](https://craftcms.com/docs/6.x/upgrade.html), requiring
FreeNav 6 in the same step:

```bash
composer require "craftcms/cms:^6.0@beta" "justinholtweb/craft-free-nav:^6.0@beta" -W
php artisan craft:up
```

FreeNav's upgrade migration runs as part of `craft:up`. It:

- renames FreeNav's class names wherever Craft stores them — element types, field types, field
  layouts and their elements, stored conditions, and project config;
- converts category nodes as described above;
- gives each menu a node field layout (FreeNav 5 had no layout designer), with the settings the
  FreeNav 5 builder showed.

It only changes what still needs changing, so running it again does nothing.

Drop the `@beta` flags once Craft 6 and FreeNav 6 have stable releases.

## What carries over

- **Menus.** Definitions stay in project config under `freeNav.menus`, with the same structures,
  sites, propagation methods and limits.
- **Nodes.** The `freenav_nodes` table and every node setting are unchanged, nesting included.
- **Templates.** `craft.freenav.render()`, `nodes()`, `tree()`, `breadcrumbs()`,
  `getActiveNode()` and the menu getters, the six presets and their options, `{% nav %}` over
  `craft.freenav.nodes(…)`, and every node property and method the README lists.
- **The REST API.** The same `actions/free-nav/api/*` URLs and responses, still public and still
  applying visibility rules. Breadcrumb elements are now `{id, title, url}`.
- **GraphQL.** `freeNavNodes`, `freeNavNodeCount`, `freeNavNode`, the `{menuHandle}_FreeNavNode`
  types and the per-menu schema permissions.
- **The Menu field.** Existing fields and their values.
- **Settings and permissions.** Settings stay in project config. Permissions keep their names,
  per-menu ones included, so existing grants still apply.
- **Export files.** Files exported from FreeNav 5 import into FreeNav 6, category links included.

## What changes

### The control panel

The FreeNav 5 builder is gone. Each menu's tree is now Craft's own element index in structure
view: drag nodes to reorder and nest them, and use the status and delete actions as you would for
entries. Click a node to edit it in Craft's element editor. **Add node** opens a form for the
things the editor can't change afterwards: the node type, what it links to, and where it goes.

What the editor shows comes from the menu's **Node Fields** layout, in the menu's settings. Every
menu starts with the settings the FreeNav 5 builder had; you can reorder them, drop the ones your
editors don't need, and add custom fields.

Settings are on **Settings > Plugins > FreeNav**. JSON import and the Verbb Navigation migration
are under **FreeNav > Import**, and **Export JSON** is in the menus list and in each menu's
settings.

Editors need Craft's permission to edit the site a node is on, as before.

### Console commands

| FreeNav 5 | FreeNav 6 |
| --- | --- |
| `php craft free-nav/menus` | `php artisan free-nav:menus:index` |
| `php craft free-nav/menus/resave-nodes [handle]` | `php artisan free-nav:menus:resave-nodes [handle]` |
| `php craft free-nav/menus/clear-cache [handle]` | `php artisan free-nav:menus:clear-cache [handle]` |
| `php craft free-nav/menus/export <handle> --file=…` | `php artisan free-nav:menus:export <handle> --file=…` |
| `php craft free-nav/menus/import [handle] --file=… --replace` | `php artisan free-nav:menus:import [handle] --file=… --replace` |
| `php craft free-nav/migrate` | `php artisan free-nav:migrate:from-navigation` |

The old names still work as aliases (`php craft free-nav/menus/clear-cache` too), so deploy
scripts don't have to change on day one.

### Templates

Nothing to change, unless a template relies on one of these:

- `render()`, `getLink()`, `getIconHtml()` and `getBadgeHtml()` return Laravel's `HtmlString`
  instead of `Twig\Markup`. Twig prints both without escaping.
- Category nodes are entry nodes now. `nodeType('category')` finds nothing.
- `overrideTemplate` names a site template, as before. To change a built-in preset without
  passing `overrideTemplate` everywhere, copy it to `templates/_freenav/_presets/`.

## Custom code

### Namespaces

`justinholt\freenav\…` is now `JustinHoltWeb\FreeNav\…`, and the sub-namespaces are capitalised:
`elements` → `Elements`, `elements\db` → `Elements\Queries`, `services` → `Services`, `models` →
`Models`, `enums` → `Enums`, `events` → `Events`, `fields` → `Fields`, `fieldlayoutelements` →
`FieldLayoutElements`, `helpers` → `Helpers`. The `records` classes are gone; FreeNav reads its
tables with Laravel's query builder.

### Events

FreeNav's events are Laravel events now. Register listeners in a service provider's or plugin's
`boot()`:

```php
// FreeNav 5
Event::on(Node::class, Node::EVENT_NODE_ACTIVE, function (NodeActiveEvent $event) { … });

// FreeNav 6
use Illuminate\Support\Facades\Event;
use JustinHoltWeb\FreeNav\Events\ResolvingNodeActive;

Event::listen(ResolvingNodeActive::class, function (ResolvingNodeActive $event) { … });
```

| FreeNav 5 | FreeNav 6 |
| --- | --- |
| `Menus::EVENT_BEFORE_SAVE_MENU` + `MenuEvent` | `MenuSaving` (`menu`, `isNew`) |
| `Menus::EVENT_AFTER_SAVE_MENU` + `MenuEvent` | `MenuSaved` (`menu`, `isNew`) |
| `Menus::EVENT_BEFORE_DELETE_MENU` + `MenuEvent` | `MenuDeleting` (`menu`) |
| `Menus::EVENT_AFTER_DELETE_MENU` + `MenuEvent` | `MenuDeleted` (`menu`) |
| `Node::EVENT_NODE_ACTIVE` + `NodeActiveEvent` | `ResolvingNodeActive` (`node`, `isActive`) |
| `NodeTypes::EVENT_REGISTER_NODE_TYPES` + `RegisterNodeTypeEvent` | `RegisterNodeTypes` (`types`) |
| `NodeTypes::EVENT_REGISTER_LINKABLE_ELEMENTS` + `RegisterLinkableElementEvent` | `RegisterLinkableElements` (`elementTypes`) |

### Services

Services are container singletons. `FreeNav::getInstance()->getMenus()` and the other getters
still work; in new code, use `app(\JustinHoltWeb\FreeNav\Services\Menus::class)` or inject the
class.

### Node queries

`Node::find()` returns a Craft 6 element query. The FreeNav params — `menuId()`, `menuHandle()`,
`nodeType()`, `hasUrl()`, `linkedElementId()`, `visibleOnly()` — are the same. Craft 6 changed
some query methods: `orderBy(['lft' => SORT_ASC])` still works but is deprecated in favour of
`orderBy('lft')`, and `batch()` is gone — use `->lazy(100)` or `->chunk()`.

## Rolling back

Restore your Craft 5 site and database from the backup you took before upgrading, and require
`justinholtweb/craft-free-nav:^5.2.1`.

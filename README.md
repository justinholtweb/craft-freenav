# FreeNav for Craft CMS 6

A free, full-featured navigation menu builder for Craft CMS 6. Build complex navigation menus with a drag-and-drop tree, conditional visibility, caching, accessibility, and more — all without paying a dime.

> **Beta.** FreeNav 6 is in beta, like Craft 6 itself. Test it on a copy of your site before relying on it in production.
>
> **Using Craft 5?** Install FreeNav 5: `composer require justinholtweb/craft-free-nav:^5.2.1`. To move a Craft 5 site to Craft 6, see [UPGRADING.md](UPGRADING.md).

**Developer:** Justin Holt
**Website:** [craft-freenav.com](https://craft-freenav.com)
**License:** [Craft License](https://craftcms.github.io/license/)

---

## Why FreeNav?

FreeNav provides everything you need to manage navigation in Craft CMS, for free:

| Feature | FreeNav | Verbb Navigation |
|---------|---------|------------------|
| **Price** | Free | $19 |
| **Conditional Visibility** | Per-node rules (user group, login state, URL, entry type) | None |
| **Built-in Cache** | Tagged cache with auto-invalidation | None |
| **Icon & Badge Fields** | Native on every node | None |
| **Template Presets** | 6 presets (dropdown, sidebar, breadcrumb, footer, mega) | None |
| **JSON Import/Export** | Full menu structure portability | None |
| **ARIA Accessibility** | Automatic `aria-current`, `aria-expanded`, `role` | Manual |
| **REST API** | 3 built-in endpoints | None |
| **Mega Menu Columns** | First-class column layout | None |

---

## Features

- **Menu Builder** — Each menu is a drag-and-drop tree in Craft's own element index, and nodes are edited in Craft's element editor
- **Multiple Node Types** — Entry, asset, custom URL, passive (no-link) and site nodes (Commerce product nodes return with Commerce 6)
- **Conditional Visibility** — Show/hide nodes based on user group, logged-in state, URL segments, or entry type
- **Built-in Cache** — Intelligent per-menu cache with automatic invalidation via tagged dependencies
- **Icon & Badge Support** — Native icon class and badge text fields on every node
- **Template Presets** — 6 ready-to-use render presets: default, dropdown, sidebar, breadcrumb, footer, mega menu
- **JSON Import/Export** — Export and import full menu structures with element UID portability
- **ARIA Accessibility** — Built-in accessible markup with `aria-current`, `aria-expanded`, `aria-haspopup`, and `role` attributes
- **REST API** — Simple REST endpoints for headless/decoupled architectures
- **GraphQL** — Full schema with per-menu types and scoped permissions
- **Multi-site** — Configurable propagation methods (none, site group, language, all)
- **Breadcrumbs** — URL-segment breadcrumb generation with automatic Craft element resolution
- **Project Config** — Menu definitions stored in Project Config for environment portability
- **Permissions** — Granular user permissions (manage menus, edit nodes, delete nodes — per-menu)
- **Per-menu Field Layouts** — Choose which node settings editors see, and add your own custom fields to nodes
- **Element Syncing** — Node titles follow their linked entry or asset, and a deleted entry turns its nodes into custom URL nodes
- **Menu Field Type** — Drop a menu selector into any entry type
- **Extensible** — Event hooks for custom node types, visibility rules, active state overrides, and render modification

---

## Requirements

- **Craft CMS** 6.0.0-beta.1+
- **PHP** 8.5+

---

## Installation

```bash
composer require "justinholtweb/craft-free-nav:^6.0@beta"
```

Then install from **Settings > Plugins** in the Control Panel, or via CLI:

```bash
php artisan craft:plugin:install free-nav
```

---

## Quick Start

### 1. Create a Menu

Go to **FreeNav > Menus > New menu**. Set a name (`Main Menu`) and handle (`mainMenu`), choose your site settings, and save. The **Node Fields** designer at the bottom decides what editors see when they edit a node; it starts with every FreeNav node setting, and you can add custom fields.

### 2. Build Your Navigation

Click the menu's name to open its tree, then **Add node**:

- **Entry/Asset** — Pick a Craft element. Leave the title blank to use the element's.
- **Custom URL** — A web address, path, anchor, or `mailto:`/`tel:`/`sms:` link. An environment variable or alias (`$PRIMARY_SITE_URL/about`, `@web/about`) works when it resolves to an `http(s)` address.
- **Passive** — A non-linking label (great for dropdown group headings)
- **Site** — A link to a site, given as a URL

You can only link an element you're allowed to view. A node's URL suffix (`#team`, `?ref=nav`) has to start with `#`, `?`, `&` or `/`, and the finished link is checked again, so no combination of URL and suffix can make a `javascript:` link.

Drag nodes in the tree to reorder them and nest them for submenus. Click a node to edit it — CSS classes, new window, icon, badge, custom attributes, visibility rules and any custom fields — and use the tree's status and delete actions as with entries.

### 3. Render in Templates

```twig
{{ craft.freenav.render('mainMenu') }}
```

That's it. FreeNav outputs a fully accessible `<nav>` with proper ARIA attributes.

---

## Template Usage

### Auto-Render with Presets

```twig
{# Default list #}
{{ craft.freenav.render('mainMenu') }}

{# Dropdown with custom active class #}
{{ craft.freenav.render('mainMenu', {
    preset: 'dropdown',
    activeClass: 'is-active',
}) }}

{# Mega menu #}
{{ craft.freenav.render('mainMenu', { preset: 'mega' }) }}

{# Sidebar navigation #}
{{ craft.freenav.render('sidebarMenu', { preset: 'sidebar' }) }}

{# Footer columns (level 1 = column headers, level 2+ = links) #}
{{ craft.freenav.render('footerMenu', { preset: 'footer' }) }}
```

### Manual Iteration

Use Craft's `{% nav %}` tag for full control:

```twig
{% set nodes = craft.freenav.nodes('mainMenu').visibleOnly(true).all() %}

<nav aria-label="Main">
    <ul>
        {% nav node in nodes %}
            <li class="{{ node.isActive() ? 'active' }}">
                {% if node.getUrl() %}
                    <a href="{{ node.getUrl() }}"
                       {{ node.isCurrent() ? 'aria-current="page"' }}
                       {{ node.newWindow ? 'target="_blank" rel="noopener noreferrer"' }}>
                        {{ node.getIconHtml() }}
                        {{ node.title }}
                        {{ node.getBadgeHtml() }}
                    </a>
                {% else %}
                    <span>{{ node.title }}</span>
                {% endif %}
                {% ifchildren %}<ul>{% children %}</ul>{% endifchildren %}
            </li>
        {% endnav %}
    </ul>
</nav>
```

### Breadcrumbs

```twig
{% set crumbs = craft.freenav.breadcrumbs({
    homeLabel: 'Home',
    includeHome: true,
    includeCurrent: true,
}) %}

<nav aria-label="Breadcrumb">
    <ol>
        {% for crumb in crumbs %}
            <li>
                {% if crumb.isCurrent %}
                    <span aria-current="page">{{ crumb.title }}</span>
                {% else %}
                    <a href="{{ crumb.url }}">{{ crumb.title }}</a>
                {% endif %}
            </li>
        {% endfor %}
    </ol>
</nav>
```

### Querying Nodes

```twig
{# Get a node query #}
{% set nodes = craft.freenav.nodes('mainMenu')
    .nodeType('entry')
    .visibleOnly(true)
    .all() %}

{# Get the tree structure #}
{% set tree = craft.freenav.tree('mainMenu') %}

{# Find the currently active node #}
{% set active = craft.freenav.getActiveNode('mainMenu') %}

{# Get menu metadata #}
{% set menu = craft.freenav.getMenuByHandle('mainMenu') %}
```

---

## Twig API Reference

All methods are accessed via `craft.freenav`:

| Method | Returns | Description |
|--------|---------|-------------|
| `render(handle, options)` | `HtmlString` | Render a menu as HTML using a preset |
| `nodes(handle, criteria)` | `NodeQuery` | Get an element query for menu nodes |
| `tree(handle, criteria)` | `array` | Get a hierarchical tree of nodes |
| `breadcrumbs(options)` | `array` | Generate breadcrumbs from the current URL |
| `getActiveNode(handle)` | `?Node` | Get the currently active node |
| `getMenuByHandle(handle)` | `?Menu` | Get a menu model by handle |
| `getMenuById(id)` | `?Menu` | Get a menu model by ID |
| `getAllMenus()` | `Menu[]` | Get all menus |

Twig templates also get a `freenav` global with the same methods (`{{ freenav.render('mainMenu') }}`).

`visibleOnly(true)` on a node query leaves out nodes whose visibility rules hide them from the current visitor. It filters the results after the query runs, so `count()` and `limit()` are not affected.

---

## Render Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `preset` | `string` | `'default'` | Render preset: `default`, `dropdown`, `sidebar`, `breadcrumb`, `footer`, `mega` |
| `id` | `string` | `null` | `<nav>` id attribute |
| `class` | `string` | `null` | `<nav>` CSS class |
| `ulClass` | `string` | `null` | `<ul>` CSS class |
| `liClass` | `string` | `null` | `<li>` CSS class |
| `aClass` | `string` | `null` | `<a>` CSS class |
| `activeClass` | `string` | `'active'` | CSS class for active items |
| `hasChildrenClass` | `string` | `'has-children'` | CSS class for items with children |
| `maxLevel` | `int` | `null` | Limit rendering depth |
| `overrideTemplate` | `string` | `null` | Custom site template path (bypasses presets) |
| `cache` | `bool` | `true` | Enable/disable caching for this render call |
| `cacheDuration` | `int` | `3600` | Cache TTL in seconds |
| `aria` | `bool` | `true` | Enable automatic ARIA attributes |
| `visibilityCheck` | `bool` | `true` | Apply conditional visibility rules |

### Overriding a Preset

Presets are site templates under `_freenav/_presets/`. To change one for your site, copy it from `vendor/justinholtweb/craft-free-nav/resources/site/_presets/` to `templates/_freenav/_presets/` and edit it there. FreeNav finds your copy first.

---

## Node Properties

Each `Node` element provides:

| Property/Method | Type | Description |
|----------------|------|-------------|
| `title` | `string` | Node title (synced from element or custom) |
| `getUrl()` | `?string` | Resolved URL (element URL, custom URL, or null for passive) |
| `customUrl` | `?string` | The raw URL typed into the node — use `getUrl()` unless you specifically want this |
| `getLink()` | `HtmlString` | Full `<a>` tag with all attributes |
| `nodeType` | `string` | Type: `entry`, `asset`, `product`, `custom`, `passive`, `site` |
| `getNodeType()` | `NodeType` | Enum instance |
| `classes` | `?string` | CSS classes |
| `urlSuffix` | `?string` | URL suffix (e.g., `#section`) |
| `newWindow` | `bool` | Opens in new tab |
| `icon` | `?string` | Icon CSS class |
| `badge` | `?string` | Badge text |
| `getIconHtml()` | `?HtmlString` | Rendered `<i>` icon tag |
| `getBadgeHtml()` | `?HtmlString` | Rendered badge `<span>` |
| `isActive()` | `bool` | Current page or has active descendant |
| `isCurrent()` | `bool` | Exact URL match with current page |
| `hasActiveDescendant()` | `bool` | Any child is current |
| `isVisible()` | `bool` | Passes all visibility rules |
| `isElement()` | `bool` | Linked to a Craft element |
| `isCustom()` | `bool` | Custom URL type |
| `isPassive()` | `bool` | No-link/label type |
| `getLinkedElement()` | `?Element` | The linked Craft element |
| `hasOverriddenTitle()` | `bool` | Title differs from linked element |
| `getLinkAttributes(extra)` | `array` | HTML attributes for the link |
| `getAriaAttributes()` | `array` | Computed ARIA attributes |
| `level` | `int` | Depth in the menu, starting at 1 |
| `getParent()` / `getChildren()` | | Craft's structure methods — node hierarchy lives in the menu's structure |
| `getCustomAttributesArray()` | `array` | Custom `[{key, value}]` attributes, as stored |
| `getMenu()` | `Menu` | Parent menu model |

---

## Custom Attributes

Editors can add HTML attributes to a node's link as a JSON list: `[{"key": "data-track", "value": "nav"}]`. They land on every page that renders the menu, so the names are an allowlist:

- `data-*` and `aria-*`
- plain attributes: `title`, `rel`, `id`, `lang`, `dir`, `role`, `tabindex`, `accesskey`, `translate`, `hreflang`, `download`, `type`, `itemprop`, `itemscope`, `itemtype`, `itemid`, `referrerpolicy`

Event handlers (`onclick`…), URL attributes (`href`, `src`, `formaction`…), `style`, and front-end framework directives that run code — Alpine `x-*`, htmx `hx-*`/`data-hx-*`, Vue `v-*`, Angular `ng-*`/`data-ng-*`, and Datastar's `data-on-*`, `data-text`, `data-show` and its other attribute plugins — are not allowed. Values must be text.

A node with a disallowed attribute won't save. Imports (JSON files, the console and the Verbb Navigation migration) drop disallowed attributes and keep the rest, and rendering, the REST API and GraphQL skip any that were stored before these checks existed. Values are escaped as always. If your site's front end runs a framework that executes other `data-*` attributes, treat menu editors as trusted.

---

## Conditional Visibility

Add visibility rules to any node to control when it appears. Rules are evaluated at render time. Multiple rules use AND logic (all must pass).

### Rule Types

| Type | Operators | Value | Example |
|------|-----------|-------|---------|
| `loggedIn` | `is`, `isNot` | `true`/`false` | Show only to logged-in users |
| `userGroup` | `is`, `isNot` | Group handle or `"guests"` | Show only to "members" group |
| `urlSegment` | `is`, `isNot`, `contains`, `startsWith` | URL string | Show when URL contains "blog" |
| `entryType` | `is`, `isNot` | Entry type handle | Show on "article" entries |

Rules are stored as JSON on the node:
```json
[
    {"type": "loggedIn", "operator": "is", "value": true},
    {"type": "userGroup", "operator": "is", "value": "members"}
]
```

Disable visibility checks for a render call:
```twig
{{ craft.freenav.render('mainMenu', { visibilityCheck: false }) }}
```

---

## Caching

FreeNav caches rendered HTML per menu, site, page, visitor (logged in or not, and their user groups) and render options, using Laravel's cache with tag dependencies. Pages with a query string are rendered fresh rather than cached.

The cache is **automatically invalidated** when:
- A menu is saved or deleted
- A node is saved, deleted, or moved in the tree
- A linked element's title changes (its nodes are re-saved)

### Configuration

Global defaults in **FreeNav > Settings**. Per-render override:

```twig
{# Disable cache for this call #}
{{ craft.freenav.render('mainMenu', { cache: false }) }}

{# Custom TTL #}
{{ craft.freenav.render('mainMenu', { cacheDuration: 7200 }) }}
```

### Clear Cache Manually

```bash
php artisan free-nav:menus:clear-cache            # All menus
php artisan free-nav:menus:clear-cache mainMenu    # Specific menu
```

---

## REST API

Enable/disable in **FreeNav > Settings > Enable REST API** (on by default).

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/actions/free-nav/api/get-menus` | GET | List all menus |
| `/actions/free-nav/api/get-menu?handle={handle}` | GET | Get a menu with all its nodes |
| `/actions/free-nav/api/get-breadcrumbs?uri={uri}` | GET | Get breadcrumbs for a URI |

The endpoints are public. They list enabled nodes only and apply each node's visibility rules, so a "logged in only" node is left out for an anonymous caller. Breadcrumb elements are given as `{id, title, url}`.

---

## GraphQL

FreeNav registers per-menu GQL types and scoped read permissions.

```graphql
{
    freeNavNodes(menuHandle: "mainMenu") {
        id
        title
        url
        nodeType
        icon
        badge
        active
        children {
            id
            title
            url
        }
        ... on mainMenu_FreeNavNode {
            myCustomField
        }
    }
}
```

Enable per-menu access in **Settings > GraphQL > Schemas** under the **FreeNav** section. A query only ever returns nodes from the menus its schema may read. Each menu has its own type, `{menuHandle}_FreeNavNode`, carrying the custom fields of the menu's field layout. Besides `menuHandle`, the queries take `menuId`, `nodeType`, `hasUrl`, `linkedElementId` and Craft's element and structure arguments (`level`, `descendantOf`…).

---

## Import / Export

### Export
Click **Export JSON** in the **FreeNav > Menus** list, or in the save menu of a menu's settings.

### Import
Go to **FreeNav > Import**, choose the file under **Import from JSON**, and click **Import**. If a menu with the file's handle already exists, the import becomes a copy beside it (`mainMenu_imported`, "Main Menu (Imported)") — the existing menu is never touched.

Element-linked nodes are exported with their element's UID, so they find the same entry, asset or product in another environment. A link whose element doesn't exist there imports as a node with no link, and the import tells you which. Files exported by FreeNav 5 import too: category links are looked up as entries, since Craft's conversion of categories to entries keeps their UIDs.

An import is all-or-nothing. Every node is checked first — custom URLs through the same rule the node editor uses, so `javascript:` links and environment variables that don't resolve to an `http(s)` address are refused — along with the menu's node and level limits. If anything fails, nothing is created or changed.

### From the console
The same export and import run from the command line, for deploy scripts and for moving menu content between environments without the control panel:

```bash
# Export a menu (prints the JSON without --file)
php artisan free-nav:menus:export mainMenu --file=storage/mainMenu.json

# Import: creates the menu when no menu has the file's handle
php artisan free-nav:menus:import --file=storage/mainMenu.json

# Replace an existing menu's nodes with the file's
php artisan free-nav:menus:import --file=storage/mainMenu.json --replace

# Import into a different handle, reading the file from stdin
cat mainMenu.json | php artisan free-nav:menus:import footerNav --file=- --replace
```

A menu's definition is project config but its nodes are content, so the two cases differ:

- **`--replace` into a menu that exists** works in any environment, `allowAdminChanges` or not. It replaces the nodes and leaves the menu's settings (name, sites, limits, field layout) alone. Replaced nodes go to Craft's trash.
- **Creating a menu** needs `allowAdminChanges`, like creating one in the control panel. In production, create the menu locally and let project config deploy it, then import its nodes there with `--replace`.
- Without `--replace`, importing over an existing menu does nothing and exits non-zero.

Every failure exits non-zero and changes nothing, so a deploy script can stop on it.

### Format
```json
{
    "freeNav": "1.2.0",
    "menu": {
        "name": "Main Menu",
        "handle": "mainMenu",
        "propagationMethod": "all",
        "maxNodes": null,
        "maxLevels": 5
    },
    "nodes": [
        {
            "title": "Home",
            "nodeType": "custom",
            "customUrl": "/",
            "level": 1,
            "children": []
        }
    ]
}
```

---

## Console Commands

```bash
# List all menus
php artisan free-nav:menus:index

# Resave all nodes (useful after migrations or bulk changes)
php artisan free-nav:menus:resave-nodes

# Resave nodes for a specific menu
php artisan free-nav:menus:resave-nodes mainMenu

# Clear all FreeNav caches
php artisan free-nav:menus:clear-cache

# Clear cache for a specific menu
php artisan free-nav:menus:clear-cache mainMenu

# Export a menu as JSON, and import one (see Import / Export)
php artisan free-nav:menus:export mainMenu --file=mainMenu.json
php artisan free-nav:menus:import --file=mainMenu.json --replace

# Copy Verbb Navigation's navs into FreeNav menus (also under FreeNav > Import)
php artisan free-nav:migrate:from-navigation
```

The FreeNav 5 names still work as aliases — `php craft free-nav/menus/clear-cache`, `php craft free-nav/migrate` and so on.

---

## Events

FreeNav's events are Laravel events in `JustinHoltWeb\FreeNav\Events`. Listen with `Event::listen()` in a service provider's or plugin's `boot()`:

| Event | Payload | Purpose |
|-------|---------|---------|
| `MenuSaving` | `menu`, `isNew` | Before a menu is saved |
| `MenuSaved` | `menu`, `isNew` | After a menu is saved |
| `MenuDeleting` | `menu` | Before a menu is deleted |
| `MenuDeleted` | `menu` | After a menu and its nodes are deleted |
| `ResolvingNodeActive` | `node`, `isActive` | Override a node's active state |
| `RegisterNodeTypes` | `types` | Change the node types offered for new nodes |
| `RegisterLinkableElements` | `elementTypes` | Change the element types nodes can link to |

### Example: Override Active State

```php
use Illuminate\Support\Facades\Event;
use JustinHoltWeb\FreeNav\Events\ResolvingNodeActive;

Event::listen(ResolvingNodeActive::class, function (ResolvingNodeActive $event) {
    // Force a node active based on custom logic
    if ($event->node->getUrl() === '/special') {
        $event->isActive = true;
    }
});
```

---

## Permissions

Every CP action needs **Access FreeNav** and then one of these. Menu definitions and plugin settings
are project config, so changing them also needs `allowAdminChanges`; plugin settings are admin-only.

| Permission | Description |
|------------|-------------|
| `freeNav-manageMenus` | Create, edit, duplicate, reorder, import and delete menus |
| `freeNav-manageMenu:{uid}` | Edit one menu's settings and export it |
| `freeNav-editNodes` | Add, edit, move and toggle nodes in any menu |
| ↳ `freeNav-deleteNodes` | Delete nodes from any menu |
| `freeNav-editNodes:{uid}` | Add, edit, move and toggle nodes in one menu |
| ↳ `freeNav-deleteNodes:{uid}` | Delete nodes from one menu |

The per-menu permissions stand on their own — they are not nested under the all-menus ones, so you
can grant one menu without granting every menu. Editing nodes also needs Craft's permission to edit the
site they're on.

---

## Support

- [GitHub Issues](https://github.com/justinholtweb/craft-freenav/issues)
- [Documentation](https://craft-freenav.com/docs)

---

Made by [Justin Holt](https://craft-freenav.com)

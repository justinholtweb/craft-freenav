# FreeNav for Craft CMS 5

A free, full-featured navigation menu builder for Craft CMS 5. Build complex navigation menus with a drag-and-drop interface, conditional visibility, caching, accessibility, and more — all without paying a dime.

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

- **Menu Builder** — Drag-and-drop node builder in the Control Panel
- **Multiple Node Types** — Entry, category, asset, Commerce product, custom URL, passive (no-link), and site nodes
- **Conditional Visibility** — Show/hide nodes based on user group, logged-in state, URL segments, or entry type
- **Built-in Cache** — Intelligent per-menu cache with automatic invalidation via tagged dependencies
- **Icon & Badge Support** — Pick an icon from Craft's icon picker (printed as inline SVG) or give an icon class, plus badge text, on every node
- **Template Presets** — 6 ready-to-use render presets: default, dropdown, sidebar, breadcrumb, footer, mega menu
- **JSON Import/Export** — Export and import full menu structures with element UID portability
- **ARIA Accessibility** — Built-in accessible markup with `aria-current`, `aria-expanded`, `aria-haspopup`, and `role` attributes
- **REST API** — Simple REST endpoints for headless/decoupled architectures
- **GraphQL** — Full schema with per-menu types and scoped permissions
- **Multi-site** — Configurable propagation methods (none, site group, language, all)
- **Breadcrumbs** — URL-segment breadcrumb generation with automatic Craft element resolution
- **Project Config** — Menu definitions stored in Project Config for environment portability
- **Permissions** — Granular user permissions (manage menus, edit nodes, delete nodes — per-menu)
- **Element Syncing** — Node titles and URLs auto-update when linked entries/categories change
- **Menu Field Type** — Drop a menu selector into any entry type
- **Extensible** — Event hooks for custom node types, visibility rules, active state overrides, and render modification

---

## Requirements

- **Craft CMS** 5.0.0+
- **PHP** 8.2+

---

## Installation

```bash
composer require justinholtweb/craft-free-nav
```

Then install from **Settings > Plugins** in the Control Panel, or via CLI:

```bash
php craft plugin/install free-nav
```

---

## Quick Start

### 1. Create a Menu

Go to **FreeNav > Menus > New menu**. Set a name (`Main Menu`) and handle (`mainMenu`), choose your site settings, and save.

### 2. Build Your Navigation

Click **Build** on your menu. Use the slide-out panel to add nodes:

- **Entry/Category/Asset** — Select a Craft element (title and URL sync automatically)
- **Custom URL** — A web address, path, anchor, or `mailto:`/`tel:`/`sms:` link, including environment variables and aliases (`$BASE_URL/about`) as long as they resolve to an `http(s)` address
- **Passive** — A non-linking label (great for dropdown group headings)

Drag nodes to reorder. Nest them for submenus.

Click **Edit** on a node for the rest of its settings: CSS classes, URL suffix, new window, badge, and

- **Icon** — Craft's icon picker, limited to the free Font Awesome icons (Craft's Pro icons are licensed for the control panel only). It prints as an inline SVG sized to the text; the older **Icon Class** field still works and is used when no icon is picked.
- **Custom Attributes** — a Key / Value table of HTML attributes for the link. Only safe names are accepted: `data-*`, `aria-*`, `title`, `rel`, `id`, `lang`, `role`, `itemprop` and similar.
- **Visibility Rules** — Craft's condition builder (see [Conditional Visibility](#conditional-visibility)).

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
| `render(handle, options)` | `Markup` | Render a menu as HTML using a preset |
| `nodes(handle, criteria)` | `NodeQuery` | Get an element query for menu nodes |
| `tree(handle, criteria)` | `array` | Get a hierarchical tree of nodes |
| `breadcrumbs(options)` | `array` | Generate breadcrumbs from the current URL |
| `getActiveNode(handle)` | `?Node` | Get the currently active node |
| `getMenuByHandle(handle)` | `?Menu` | Get a menu model by handle |
| `getMenuById(id)` | `?Menu` | Get a menu model by ID |
| `getAllMenus()` | `Menu[]` | Get all menus |

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
| `overrideTemplate` | `string` | `null` | Custom Twig template path (bypasses presets) |
| `cache` | `bool` | `true` | Enable/disable caching for this render call |
| `cacheDuration` | `int` | `3600` | Cache TTL in seconds |
| `aria` | `bool` | `true` | Enable automatic ARIA attributes |
| `visibilityCheck` | `bool` | `true` | Apply conditional visibility rules |

---

## Node Properties

Each `Node` element provides:

| Property/Method | Type | Description |
|----------------|------|-------------|
| `title` | `string` | Node title (synced from element or custom) |
| `getUrl()` | `?string` | Resolved URL (element URL, custom URL, or null for passive) |
| `customUrl` | `?string` | The raw URL typed into the node — use `getUrl()` unless you specifically want this |
| `getLink()` | `Markup` | Full `<a>` tag with all attributes |
| `nodeType` | `string` | Type: `entry`, `category`, `asset`, `product`, `custom`, `passive`, `site` |
| `getNodeType()` | `NodeType` | Enum instance |
| `classes` | `?string` | CSS classes |
| `urlSuffix` | `?string` | URL suffix (e.g., `#section`) |
| `newWindow` | `bool` | Opens in new tab |
| `icon` | `?string` | Icon CSS class |
| `craftIcon` | `?string` | Name of the icon chosen in Craft's icon picker |
| `badge` | `?string` | Badge text |
| `hasIcon()` | `bool` | Has a Craft icon or an icon class |
| `getIconHtml()` | `?Markup` | The Craft icon as inline SVG (`<span class="freenav-icon">`), otherwise an `<i>` with the icon class |
| `getBadgeHtml()` | `?Markup` | Rendered badge `<span>` |
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
| `getCustomAttributesHtml(except)` | `Markup` | The safe custom attributes as HTML, leaving out the names in `except` — for templates that write the other link attributes themselves, like the presets |
| `getAriaAttributes()` | `array` | Computed ARIA attributes |
| `level` | `int` | Depth in the menu, starting at 1 |
| `getParent()` / `getChildren()` | | Craft's structure methods — node hierarchy lives in the menu's structure |
| `getCustomAttributesArray()` | `array` | Custom `[{key, value}]` attributes |
| `getMenu()` | `Menu` | Parent menu model |

---

## Conditional Visibility

Add visibility rules to any node to control when it appears. Rules are evaluated at render time. Multiple rules use AND logic (all must pass).

In the node's edit panel, **Visibility Rules** is Craft's condition builder: add a rule, pick **Logged In**, **User Group**, **URL Path** or **Page Entry Type**, and set it. A rule left unfinished (no groups chosen, an empty path) is ignored.

### Rule Types

| Type | Builder rule | Operators | Value | Example |
|------|--------------|-----------|-------|---------|
| `loggedIn` | Logged In | `is`, `isNot` | `true`/`false` | Show only to logged-in users |
| `userGroup` | User Group | `is`, `isNot` | Group handle or `"guests"`, or a list of them | Show only to "members" or "staff" |
| `urlSegment` | URL Path | `is`, `isNot`, `contains`, `startsWith` | The page's path, no leading slash | Show when the path starts with "blog" |
| `entryType` | Page Entry Type | `is`, `isNot` | Entry type handle, or a list of them | Show on "article" entries |

With a list, `is` passes when any of them matches and `isNot` when none does.

Rules are stored as JSON on the node, which is also what export files, the REST API and the node `save` action use:
```json
[
    {"type": "loggedIn", "operator": "is", "value": true},
    {"type": "userGroup", "operator": "is", "value": ["members", "staff"]}
]
```

Disable visibility checks for a render call:
```twig
{{ craft.freenav.render('mainMenu', { visibilityCheck: false }) }}
```

---

## Caching

FreeNav caches rendered HTML per menu + site + render options using Craft's cache component with `TagDependency`.

Cache is **automatically invalidated** when:
- A menu is saved or deleted
- A node is saved, deleted, or reordered
- A linked element's title, URL, or status changes
- Site settings change

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
php craft free-nav/menus/clear-cache            # All menus
php craft free-nav/menus/clear-cache mainMenu    # Specific menu
```

---

## REST API

Enable/disable in **FreeNav > Settings > Enable REST API**.

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/actions/free-nav/api/get-menus` | GET | List all menus |
| `/actions/free-nav/api/get-menu?handle={handle}` | GET | Get a menu with all its nodes |
| `/actions/free-nav/api/get-breadcrumbs?uri={uri}` | GET | Get breadcrumbs for a URI |

Authenticated via Craft's standard action URL auth (session or token).

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
        craftIcon
        iconHtml
        badge
        active
        children {
            id
            title
            url
        }
    }
}
```

Enable per-menu access in **Settings > GraphQL > Schemas** under the **FreeNav** section.

---

## Import / Export

### Export
Go to **FreeNav > Menus > [menu] > Build**, click the menu button next to "Add node", and select **Export JSON**.

### Import
Go to **FreeNav > Settings**, choose the file under **Import from JSON**, and click **Import**. If a menu with the file's handle already exists, the import becomes a copy beside it (`mainMenu_imported`, "Main Menu (Imported)") — the existing menu is never touched.

Element-linked nodes are exported with their element's UID, so they find the same entry, category, asset or product in another environment. A link whose element doesn't exist there imports as a node with no link, and the import tells you which.

An import is all-or-nothing. Every node is checked first — custom URLs through the same rule the node editor uses, so `javascript:` links and environment variables that don't resolve to an `http(s)` address are refused — along with the menu's node and level limits. If anything fails, nothing is created or changed.

### From the console
The same export and import run from the command line, for deploy scripts and for moving menu content between environments without the control panel:

```bash
# Export a menu (prints the JSON without --file)
php craft free-nav/menus/export mainMenu --file=storage/mainMenu.json

# Import: creates the menu when no menu has the file's handle
php craft free-nav/menus/import --file=storage/mainMenu.json

# Replace an existing menu's nodes with the file's
php craft free-nav/menus/import --file=storage/mainMenu.json --replace

# Import into a different handle, reading the file from stdin
cat mainMenu.json | php craft free-nav/menus/import footerNav --file=- --replace
```

A menu's definition is project config but its nodes are content, so the two cases differ:

- **`--replace` into a menu that exists** works in any environment, `allowAdminChanges` or not. It replaces the nodes and leaves the menu's settings (name, sites, limits, field layout) alone. Replaced nodes go to Craft's trash.
- **Creating a menu** needs `allowAdminChanges`, like creating one in the control panel. In production, create the menu locally and let project config deploy it, then import its nodes there with `--replace`.
- Without `--replace`, importing over an existing menu does nothing and exits non-zero.

Every failure exits non-zero and changes nothing, so a deploy script can stop on it.

### Format
```json
{
    "freeNav": "1.3.0",
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

## Import from a Structure Section

Sites often build their menus as a Structure section, with a link field on each entry. One command turns that into FreeNav menus:

```bash
# Each top-level entry becomes a menu (handle from its slug); its descendants become the nodes
php craft free-nav/migrate/from-section --section=menus --link-field=itemLink

# The whole section as one menu
php craft free-nav/migrate/from-section --section=mainNav --link-field=itemLink --menu=mainNav

# A section of pages: without --link-field each entry links to itself
php craft free-nav/migrate/from-section --section=pages --menu=pages
```

- A link to an entry or asset becomes an element node (an anchor or query string on Craft's Link field is kept as the URL suffix). Any other link becomes a custom URL, and an entry with no link a passive node. A link set to open in a new window keeps that.
- Craft's Link field, relation fields (Entries, Assets) and link plugins that offer `getElement()`/`getUrl()` all work.
- Nesting and order follow the section's structure. Disabled entries become disabled nodes.
- Entries are read from the primary site; pass `--site=<handle>` for another.
- Each menu goes through the same import as a JSON file, so links are checked first and a menu that fails changes nothing. A menu whose handle already exists is skipped unless you pass `--replace`, which replaces its nodes. Creating menus needs `allowAdminChanges`.

---

## Console Commands

```bash
# List all menus
php craft free-nav/menus

# Resave all nodes (useful after migrations or bulk changes)
php craft free-nav/menus/resave-nodes

# Resave nodes for a specific menu
php craft free-nav/menus/resave-nodes mainMenu

# Clear all FreeNav caches
php craft free-nav/menus/clear-cache

# Clear cache for a specific menu
php craft free-nav/menus/clear-cache mainMenu

# Export a menu as JSON, and import one (see Import / Export)
php craft free-nav/menus/export mainMenu --file=mainMenu.json
php craft free-nav/menus/import --file=mainMenu.json --replace

# Turn a Structure section into menus (see Import from a Structure Section)
php craft free-nav/migrate/from-section --section=menus --link-field=itemLink
```

---

## Events

FreeNav fires events for extensibility:

| Event | Constant | Service | Purpose |
|-------|----------|---------|---------|
| `beforeSaveMenu` | `EVENT_BEFORE_SAVE_MENU` | `Menus` | Before a menu is saved |
| `afterSaveMenu` | `EVENT_AFTER_SAVE_MENU` | `Menus` | After a menu is saved |
| `beforeDeleteMenu` | `EVENT_BEFORE_DELETE_MENU` | `Menus` | Before a menu is deleted |
| `afterDeleteMenu` | `EVENT_AFTER_DELETE_MENU` | `Menus` | After a menu is deleted |
| `nodeActive` | `EVENT_NODE_ACTIVE` | `Node` | Override a node's active state |
| `registerNodeTypes` | `EVENT_REGISTER_NODE_TYPES` | `NodeTypes` | Register additional node types |
| `registerLinkableElements` | `EVENT_REGISTER_LINKABLE_ELEMENTS` | `NodeTypes` | Register linkable element types |

### Example: Override Active State

```php
use justinholt\freenav\elements\Node;
use justinholt\freenav\events\NodeActiveEvent;

Event::on(
    Node::class,
    Node::EVENT_NODE_ACTIVE,
    function (NodeActiveEvent $event) {
        // Force a node active based on custom logic
        if ($event->node->getUrl() === '/special') {
            $event->isActive = true;
        }
    }
);
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
can grant one menu without granting every menu. The builder also needs Craft's access to the site
being edited.

---

## Support

- [GitHub Issues](https://github.com/justinholtweb/craft-freenav/issues)
- [Documentation](https://craft-freenav.com/docs)

---

Made by [Justin Holt](https://craft-freenav.com)

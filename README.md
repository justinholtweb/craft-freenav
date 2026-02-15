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
- **Custom URL** — Any URL, including environment variables (`$BASE_URL/about`)
- **Passive** — A non-linking label (great for dropdown group headings)

Drag nodes to reorder. Nest them for submenus.

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
| `getLink()` | `Markup` | Full `<a>` tag with all attributes |
| `nodeType` | `string` | Type: `entry`, `category`, `asset`, `product`, `custom`, `passive`, `site` |
| `getNodeType()` | `NodeType` | Enum instance |
| `classes` | `?string` | CSS classes |
| `urlSuffix` | `?string` | URL suffix (e.g., `#section`) |
| `newWindow` | `bool` | Opens in new tab |
| `icon` | `?string` | Icon CSS class |
| `badge` | `?string` | Badge text |
| `getIconHtml()` | `?Markup` | Rendered `<i>` icon tag |
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
| `getAriaAttributes()` | `array` | Computed ARIA attributes |
| `getCustomAttributesArray()` | `array` | Custom `[{key, value}]` attributes |
| `getMenu()` | `Menu` | Parent menu model |

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
POST a JSON file to `free-nav/import-export/import` from the CP. Element-linked nodes are exported with UIDs for cross-environment portability.

### Format
```json
{
    "freeNav": "1.0.0",
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
            "url": "/",
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
php craft free-nav/menus

# Resave all nodes (useful after migrations or bulk changes)
php craft free-nav/menus/resave-nodes

# Resave nodes for a specific menu
php craft free-nav/menus/resave-nodes mainMenu

# Clear all FreeNav caches
php craft free-nav/menus/clear-cache

# Clear cache for a specific menu
php craft free-nav/menus/clear-cache mainMenu
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
        if ($event->node->url === '/special') {
            $event->isActive = true;
        }
    }
);
```

---

## Permissions

| Permission | Description |
|------------|-------------|
| `freeNav-manageMenus` | Create, edit, delete menus |
| `freeNav-manageMenu:{uid}` | Manage a specific menu |
| `freeNav-editNodes` | Edit nodes in any menu |
| `freeNav-editNodes:{uid}` | Edit nodes in a specific menu |
| `freeNav-deleteNodes` | Delete nodes from any menu |
| `freeNav-deleteNodes:{uid}` | Delete nodes from a specific menu |

---

## Support

- [GitHub Issues](https://github.com/justinholtweb/craft-free-nav/issues)
- [Documentation](https://craft-freenav.com/docs)

---

Made by [Justin Holt](https://craft-freenav.com)

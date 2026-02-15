# FreeNav for Craft CMS 5

A free, full-featured navigation menu builder for Craft CMS 5.

## Features

- **Menu Builder** - Drag-and-drop node builder in the Control Panel
- **Multiple Node Types** - Entry, category, asset, product, custom URL, passive, and site nodes
- **Conditional Visibility** - Show/hide nodes based on user group, logged-in state, URL segments, or entry type
- **Built-in Cache** - Intelligent per-menu cache with automatic invalidation
- **Icon & Badge Support** - Native icon class and badge text fields on every node
- **Template Presets** - Ready-to-use render presets: dropdown, sidebar, breadcrumb, footer, mega menu
- **JSON Import/Export** - Export and import full menu structures
- **ARIA Accessibility** - Built-in accessible markup with proper `aria-current`, `aria-expanded`, `role` attributes
- **REST API** - Simple REST endpoints for headless architectures
- **GraphQL** - Full GraphQL schema with per-menu types
- **Multi-site** - Configurable propagation methods across sites
- **Breadcrumbs** - URL-segment breadcrumb generation with element resolution

## Requirements

- Craft CMS 5.0.0 or later
- PHP 8.2 or later

## Installation

```bash
composer require justinholtweb/craft-free-nav
```

Then install the plugin from the Craft Control Panel or run:

```bash
php craft plugin/install free-nav
```

## Usage

### Simple Render

```twig
{{ craft.freenav.render('mainMenu') }}
```

### With Preset

```twig
{{ craft.freenav.render('mainMenu', { preset: 'dropdown', activeClass: 'is-active' }) }}
```

### Manual Iteration

```twig
{% set nodes = craft.freenav.nodes('mainMenu').visibleOnly(true).all() %}
{% nav node in nodes %}
  <li>
    <a href="{{ node.url }}" {{ node.isActive ? 'aria-current="page"' }}>
      {{ node.getIconHtml() }}
      {{ node.title }}
      {{ node.getBadgeHtml() }}
    </a>
    {% ifchildren %}<ul>{% children %}</ul>{% endifchildren %}
  </li>
{% endnav %}
```

### Breadcrumbs

```twig
{% set crumbs = craft.freenav.breadcrumbs() %}
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

## Render Options

| Option | Default | Description |
|--------|---------|-------------|
| `preset` | `'default'` | Render preset (default, dropdown, sidebar, breadcrumb, footer, mega) |
| `id` | `null` | `<nav>` id attribute |
| `class` | `null` | `<nav>` class |
| `ulClass` | `null` | `<ul>` class |
| `liClass` | `null` | `<li>` class |
| `aClass` | `null` | `<a>` class |
| `activeClass` | `'active'` | CSS class for active items |
| `hasChildrenClass` | `'has-children'` | CSS class for items with children |
| `maxLevel` | `null` | Limit rendering depth |
| `overrideTemplate` | `null` | Custom Twig template path |
| `cache` | `true` | Enable/disable caching |
| `cacheDuration` | `3600` | Cache TTL in seconds |
| `aria` | `true` | Enable ARIA attributes |
| `visibilityCheck` | `true` | Apply visibility rules |

## REST API

- `GET /actions/free-nav/api/get-menus` - List all menus
- `GET /actions/free-nav/api/get-menu?handle={handle}` - Get menu with nodes
- `GET /actions/free-nav/api/get-breadcrumbs?uri={uri}` - Get breadcrumbs

## Console Commands

```bash
# Resave all nodes
php craft free-nav/menus/resave-nodes

# Resave nodes for a specific menu
php craft free-nav/menus/resave-nodes mainMenu

# Clear all FreeNav caches
php craft free-nav/menus/clear-cache

# Clear cache for a specific menu
php craft free-nav/menus/clear-cache mainMenu
```

## Support

- [GitHub Issues](https://github.com/justinholtweb/craft-free-nav/issues)
- [Documentation](https://craft-freenav.com/docs)

## License

This plugin is licensed under the [Craft License](https://craftcms.github.io/license/).

Made by [Justin Holt](https://craft-freenav.com)

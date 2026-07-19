# Changelog

## 1.0.1 - 2026-07-19

### Fixed
- Fixed the element index columns for nodes: the `Node Type` and `New Window` columns now render their styled/custom HTML again (the Craft 3/4 `tableAttributeHtml()` hook was renamed to `attributeHtml()` in Craft 5, so the custom rendering was silently ignored).
- Corrected the return type of `Node::getLinkedElement()` and its backing property to `ElementInterface`, matching what Craft's element lookup returns.

### Dev
- Added static analysis (PHPStan level 5, clean), code-style enforcement (ECS with Craft's rule set), and a PHPUnit unit-test suite for the plugin's enums and visibility rules.
- Removed unused imports and redundant code flagged by the new tooling.

## 1.0.0 - 2026-02-15

### Added
- Initial release
- Full-featured navigation menu builder for Craft CMS 5
- "Menus" terminology for clear naming
- Drag-and-drop node builder in the Control Panel
- Support for entry, category, asset, product, custom URL, passive, and site node types
- Conditional visibility rules (user group, logged-in state, URL segments, entry type)
- Built-in per-menu cache with automatic invalidation via tagged dependencies
- Icon class and badge text fields on nodes
- 6 template render presets: default, dropdown, sidebar, breadcrumb, footer, mega menu
- JSON import/export of full menu structures
- Built-in ARIA accessibility attributes (aria-current, aria-expanded, aria-haspopup, role)
- REST API endpoints for headless/decoupled architectures
- GraphQL schema with per-menu types and scoped permissions
- Mega menu column layout support
- Breadcrumb generation from URL segments with element resolution
- Multi-site support with configurable propagation methods
- Project Config support for menu definitions
- Granular user permissions (manage menus, edit nodes, delete nodes)
- MenuField field type for selecting menus in entries
- Element syncing (title/URL updates when linked elements change)
- Console commands for resaving nodes and clearing caches
- Full English translation file

# Changelog

## 5.1.4 - 2026-08-26

### Fixed

- **A caller-supplied `id` option produced a corrupted DOM id.** Twig's `??` binds tighter than `~`, so `options.id ?? 'freenav-' ~ menu.handle` parsed as `(options.id ?? 'freenav-') ~ menu.handle` — passing `{ id: 'main-nav' }` rendered `main-navprimary`. Fixed in all five navigation presets.

## 5.1.3 - 2026-08-18

### Fixed
- Fixed the menu listing reporting "0 nodes" for menus that have nodes. The count ran `craft.freenav.nodes(handle).count()` with the element query's defaults, which meant enabled nodes on the request's current site — so a menu whose nodes live on another site counted nothing, and disabled nodes never counted anywhere. It now counts what the builder lists: every node in the menu, disabled ones included, on the site the "Nodes" link goes to.
- The listing's "Nodes" and "Build" links now carry the site the count was taken on, so the page they open matches the number next to them.
- Fixed the GitHub URLs in `composer.json` and the README, which pointed at `craft-free-nav` and 404'd. The support links render in the Craft Plugin Store listing.

### Added
- `Nodes::getNodeCount()` — a menu's node count for one site, disabled nodes included.

## 5.1.2 - 2026-08-13

### Fixed
- Fixed the add/edit node panel closing when you dismissed a Craft element selector opened from inside it. The panel hand-rolled its own stacking and dismissal: its overlay sat above Craft's modal shade and swallowed the click, and its Escape handler was bound to the document, so both fired for interactions that belonged to the modal.
- Fixed Craft's element selector rendering *behind* the panel, which covered the modal's Select button. Panels now sit on Craft's z-index for modals and slideouts and order themselves by position in `<body>`, so a modal opened from a panel stacks above it.
- Panels now register as Garnish UI layers, so Escape closes the topmost thing — the element selector first, then the panel — the way the rest of the control panel behaves.

## 5.1.1 - 2026-08-13

### Fixed
- Fixed the node builder always operating on the primary site, whatever the site selector said. Craft doesn't apply `?site=` to the current site on control panel requests — `Cp::requestedSite()` does — so on a menu that isn't enabled for the primary site every save failed with "Attempting to save an element in an unsupported site", and menus whose nodes live on another site showed up empty.
- The builder now redirects to a site the menu is actually enabled for, and shows a site selector when a menu is enabled for more than one.
- Fixed `propagationMethod` doing nothing. It was stored, validated, exposed in the CP, exported and migrated, but `Node::getSupportedSites()` never read it, so nodes propagated to every enabled site no matter the setting. All four methods (`none`, `siteGroup`, `language`, `all`) now apply. See **Changed** below.
- Fixed management operations silently doing nothing for menus whose nodes live outside the current site: JSON export wrote an empty node list, `resave-nodes` and the console menu listing reported 0 nodes, the max-nodes limit went unenforced, and deleting a menu left its nodes behind as orphans.
- Fixed node title syncing following the request's site instead of the saved element's, so only one site's nodes picked up a retitled element.
- Adding a node to a site its menu isn't enabled for now fails with a message naming the menu and site, instead of an uncaught exception.

### Changed
- Menus set to a propagation method other than "all" will stop propagating *new* saves to sites the method excludes. Existing rows are left as they are — nothing is deleted — so a menu that had been propagating everywhere keeps the nodes it already has.
- `Nodes::getNodesByMenuId()` and `Nodes::getParentOptions()` take an optional `$siteId`.

### Added
- `Nodes::findNodesInMenu()` — every node in a menu, once each, across all sites. Use it for management operations; rendering stays scoped to the current site.
- `Menu::getEnabledSiteIds()` and `Menu::isEnabledForSite()`.

## 5.1.0 - 2026-08-13

### Fixed
- Fixed nested menus rendering flat through `craft.freenav.tree()`. Node hierarchy lives in the menu's structure, but the tree builder grouped nodes by a `parentId` column that nothing ever wrote, so every node came back as a root. Trees are now built from the structure's `level`.
- Fixed `Nodes::addNodes()` silently flattening menus on multi-site installs. The parent node was looked up against the current site only, so a parent living on another site resolved to `null` and the child was appended to the root instead. Parent lookups now search all sites and prefer the new node's site.
- Fixed `node.url` in Twig and GraphQL returning the raw custom URL — empty for element-linked nodes — instead of the resolved one. The `url` property shadowed `getUrl()`; it is now `customUrl`, leaving `getUrl()` as the only accessor. See **Changed** below.
- Fixed duplicated menus losing their nesting: `Menus::duplicateMenu()` copied nodes but never added them to the new menu's structure, so the copy came out flat (and invisible to structure-ordered queries).
- Fixed nodes whose parent is hidden (disabled or failing a visibility rule) being promoted to the top level when rendering. They are now dropped along with their parent.
- Fixed reordering a node — in the builder or the element index — leaving a stale cached menu on the front end.
- Fixed the CP builder failing to find nodes that don't exist on the current CP site.
- `Nodes::moveNode()` no longer throws when the requested parent or sibling can't be resolved; the node falls back to the menu root and a warning is logged.

### Changed
- **Breaking:** `Node::$url` is now `Node::$customUrl`, and the `freenav_nodes.url` column is now `customUrl`. Templates should call `node.getUrl()` (they always should have — `node.url` returned the wrong value for element-linked nodes). GraphQL's `url` field is unchanged and now resolves correctly. Menu exports emit `customUrl` and are stamped `"freeNav": "1.1.0"`; imports still accept the old `url` key.
- **Breaking:** the unused `freenav_nodes.parentId` column has been dropped, along with `Node::$parentId`, which shadowed Craft's own `Element::getParentId()`. `node.parentId`, `node.getParent()`, and `node.getChildren()` now all read from the menu's structure.
- Node queries now join structure data even when the query isn't scoped to a single menu, so `level`, `lft`, and `rgt` are populated on any `Node::find()` result.

### Added
- `Nodes::findNodeInMenu()`, `Nodes::placeNode()`, and `Nodes::moveNode()` — one place that resolves and positions nodes within a menu's structure.
- `NodeHelper::buildParentMap()` — node ID => parent ID map derived from structure order.

## 5.0.2 - 2026-07-19

### Fixed
- Fixed the element node types (Entry, Category, Asset, Product) being impossible to create in the node builder: the "Select Element" field now renders Craft's element picker so you can choose which element the node links to. Previously the field row appeared but its container stayed empty, and the selected element was never sent to the server. ([#reported](https://github.com/justinholtweb/craft-free-nav/issues))

### Added
- `nodes/element-select-html` controller action that renders the element selector for a given linkable node type (used by the builder UI).

## 5.0.1 - 2026-07-19

### Fixed
- Fixed the element index columns for nodes: the `Node Type` and `New Window` columns now render their styled/custom HTML again (the Craft 3/4 `tableAttributeHtml()` hook was renamed to `attributeHtml()` in Craft 5, so the custom rendering was silently ignored).
- Corrected the return type of `Node::getLinkedElement()` and its backing property to `ElementInterface`, matching what Craft's element lookup returns.

### Dev
- Added static analysis (PHPStan level 5, clean), code-style enforcement (ECS with Craft's rule set), and a PHPUnit unit-test suite for the plugin's enums and visibility rules.
- Removed unused imports and redundant code flagged by the new tooling.

## 5.0.0 - 2026-02-15

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

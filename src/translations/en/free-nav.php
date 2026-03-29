<?php

return [
    // General
    'FreeNav' => 'FreeNav',
    'Menus' => 'Menus',
    'Settings' => 'Settings',
    'Navigation Node' => 'Navigation Node',
    'Navigation Nodes' => 'Navigation Nodes',
    'navigation node' => 'navigation node',
    'navigation nodes' => 'navigation nodes',

    // Menu management
    'New menu' => 'New menu',
    'Create a new menu' => 'Create a new menu',
    'Menu saved.' => 'Menu saved.',
    'Couldn\'t save menu.' => 'Couldn\'t save menu.',
    'Menu deleted.' => 'Menu deleted.',
    'Menu duplicated.' => 'Menu duplicated.',
    'No menus exist yet.' => 'No menus exist yet.',
    '{count} nodes' => '{count} nodes',
    'Build' => 'Build',

    // Node management
    'Add node' => 'Add node',
    'Add Node' => 'Add Node',
    'Edit Node' => 'Edit Node',
    'Node added.' => 'Node added.',
    'Node saved.' => 'Node saved.',
    'Node deleted.' => 'Node deleted.',
    'Couldn\'t add node.' => 'Couldn\'t add node.',
    'Couldn\'t save node.' => 'Couldn\'t save node.',
    'Could not add node.' => 'Could not add node.',
    'Could not save node.' => 'Could not save node.',
    'Could not delete node.' => 'Could not delete node.',
    'Could not toggle node.' => 'Could not toggle node.',
    'Could not move node.' => 'Could not move node.',
    'Are you sure you want to delete this node?' => 'Are you sure you want to delete this node?',
    'Maximum number of nodes ({max}) reached.' => 'Maximum number of nodes ({max}) reached.',
    'No nodes yet. Click "Add node" to get started.' => 'No nodes yet. Click "Add node" to get started.',
    'Drag to reorder' => 'Drag to reorder',
    'Disable' => 'Disable',
    'Enable' => 'Enable',
    'Nodes' => 'Nodes',
    'Node Type' => 'Node Type',

    // Node types
    'Type' => 'Type',
    'Select Element' => 'Select Element',
    'Custom URL' => 'Custom URL',
    'Passive' => 'Passive',

    // Node fields
    'CSS Classes' => 'CSS Classes',
    'Additional CSS classes for this node.' => 'Additional CSS classes for this node.',
    'Custom Attributes' => 'Custom Attributes',
    'Custom HTML attributes as JSON array of {key, value} objects.' => 'Custom HTML attributes as JSON array of {key, value} objects.',
    'Icon Class' => 'Icon Class',
    'CSS icon class (e.g., "fa-home", "icon-menu").' => 'CSS icon class (e.g., "fa-home", "icon-menu").',
    'Badge Text' => 'Badge Text',
    'Optional badge text displayed next to the node title (e.g., "New", "Sale").' => 'Optional badge text displayed next to the node title (e.g., "New", "Sale").',
    'Open in New Window' => 'Open in New Window',
    'Open in new window' => 'Open in new window',
    'New Window' => 'New Window',
    'URL Suffix' => 'URL Suffix',
    'Appended to the URL (e.g., "#section" or "?ref=nav").' => 'Appended to the URL (e.g., "#section" or "?ref=nav").',
    'Visibility Rules' => 'Visibility Rules',
    'JSON rules controlling when this node is visible. Types: userGroup, loggedIn, urlSegment, entryType.' => 'JSON rules controlling when this node is visible. Types: userGroup, loggedIn, urlSegment, entryType.',
    'Classes' => 'Classes',

    // Menu settings
    'What this menu will be called in the CP.' => 'What this menu will be called in the CP.',
    'How you\'ll refer to this menu in the templates.' => 'How you\'ll refer to this menu in the templates.',
    'Helper text to guide the author.' => 'Helper text to guide the author.',
    'Propagation Method' => 'Propagation Method',
    'Which sites should nodes be saved to?' => 'Which sites should nodes be saved to?',
    'Max Levels' => 'Max Levels',
    'The maximum number of levels this menu can have.' => 'The maximum number of levels this menu can have.',
    'Max Nodes' => 'Max Nodes',
    'The maximum number of nodes allowed in this menu.' => 'The maximum number of nodes allowed in this menu.',
    'Default Placement' => 'Default Placement',
    'Where should new nodes be placed by default?' => 'Where should new nodes be placed by default?',
    'Beginning' => 'Beginning',
    'End' => 'End',

    // Settings page
    'Enable Caching' => 'Enable Caching',
    'Cache rendered menu HTML for better performance.' => 'Cache rendered menu HTML for better performance.',
    'Cache Duration' => 'Cache Duration',
    'How long to cache menu HTML (in seconds).' => 'How long to cache menu HTML (in seconds).',
    'Enable ARIA Attributes' => 'Enable ARIA Attributes',
    'Automatically add ARIA attributes to rendered menus for accessibility.' => 'Automatically add ARIA attributes to rendered menus for accessibility.',
    'Default Preset' => 'Default Preset',
    'The default rendering preset when none is specified.' => 'The default rendering preset when none is specified.',
    'Active Class' => 'Active Class',
    'CSS class applied to active menu items.' => 'CSS class applied to active menu items.',
    'Has Children Class' => 'Has Children Class',
    'CSS class applied to items with children.' => 'CSS class applied to items with children.',
    'Enable REST API' => 'Enable REST API',
    'Enable the built-in REST API endpoints.' => 'Enable the built-in REST API endpoints.',
    'Settings saved.' => 'Settings saved.',
    'Couldn\'t save settings.' => 'Couldn\'t save settings.',

    // Permissions
    'Manage menus' => 'Manage menus',
    'Manage "{name}"' => 'Manage "{name}"',
    'Edit nodes' => 'Edit nodes',
    'Delete nodes' => 'Delete nodes',

    // GraphQL
    'View "{name}" menu nodes' => 'View "{name}" menu nodes',

    // Import/Export
    'Export JSON' => 'Export JSON',
    'No file uploaded.' => 'No file uploaded.',
    'Invalid import file format.' => 'Invalid import file format.',
    'Couldn\'t create menu from import.' => 'Couldn\'t create menu from import.',
    'Menu imported successfully.' => 'Menu imported successfully.',

    // Migration
    'Migration' => 'Migration',
    'Migrate from Verbb Navigation' => 'Migrate from Verbb Navigation',
    'Import all navigations and nodes from the Verbb Navigation plugin. Existing menus with matching handles will be skipped. The Navigation plugin must still be installed.' => 'Import all navigations and nodes from the Verbb Navigation plugin. Existing menus with matching handles will be skipped. The Navigation plugin must still be installed.',
    'Migrate from Navigation' => 'Migrate from Navigation',
    'Import from JSON' => 'Import from JSON',
    'Import a menu from a FreeNav JSON export file.' => 'Import a menu from a FreeNav JSON export file.',
    'Verbb Navigation tables not found.' => 'Verbb Navigation tables not found.',
    'No navigations found to migrate.' => 'No navigations found to migrate.',
    '{count} navigation(s) migrated.' => '{count} navigation(s) migrated.',
    '{count} skipped (handle already exists).' => '{count} skipped (handle already exists).',

    // REST API
    'REST API is disabled.' => 'REST API is disabled.',

    // Field type
    'FreeNav Menu' => 'FreeNav Menu',
    'Select a menu…' => 'Select a menu…',
    'This field lets editors select a FreeNav menu.' => 'This field lets editors select a FreeNav menu.',

    // Breadcrumbs
    'Breadcrumb' => 'Breadcrumb',
];

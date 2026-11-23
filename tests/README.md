# FreeNav tests & static analysis

## Tooling

Run in the plugin-testing-craft6 harness container (PHP 8.5), from the plugin's directory:

```bash
docker exec -w /var/www/ports/craft-freenav ddev-plugin-testing-craft6-web vendor/bin/pest
docker exec -w /var/www/ports/craft-freenav ddev-plugin-testing-craft6-web vendor/bin/pint --test
docker exec -w /var/www/ports/craft-freenav ddev-plugin-testing-craft6-web vendor/bin/phpstan analyse --memory-limit=1G
```

Config lives in `phpunit.xml`, `phpstan.neon` (Larastan, level 5) and Pint's Laravel preset.

## Unit suite (`tests/Unit`)

Plain PHP, no Craft application (`tests/bootstrap.php` gives the container just enough for
facades and validators):

- `CustomAttributeGuardTest` — the 5.2.1 custom attribute allowlist, values-must-be-text, the
  import filter and the render loop.
- `CustomUrlTest` — the custom URL gate, the env/alias rules and the URL suffix re-check.
- `LegacyClassMapTest` — the Craft 6 upgrade migration's class map and its walker over stored
  values (project config, keys, `__assoc__` pairs, JSON in strings).
- `NodeVisibilityConditionTest` — the condition builder ↔ stored rules conversion: only FreeNav's
  rule classes are read from a posted config, unfinished rules are dropped, OR and groups refused.
- `SectionImporterTest` — the section importer's tree building and its duck-typed link reading.
- `VisibilityRuleTest` (including handle lists), `NodeHelperTest`, `EnumsTest`, `PropagationTest`, `BreadcrumbsTest`.

## Live checks

Everything that needs a database or a request is checked in the harness: menu CRUD through the
Ui screens, adding nodes, moving them in Craft's structure view, editing them in the element
editor, rendering every preset from a page under `resources/views/fn/`, the REST API anonymously,
GraphQL against an in-memory schema, import/export, the Verbb migration, permissions with a
non-admin user, and the upgrade migration against data rewritten to FreeNav 5's class names.
Create your own content for these and remove it afterwards — the harness is shared.

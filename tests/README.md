# FreeNav tests & static analysis

This directory holds the plugin's automated checks. Everything runs against the
plugin in isolation — no full Craft install or database is required.

## Tooling

| Command | What it does |
|---------|--------------|
| `composer phpstan` | PHPStan static analysis (level 5, Craft ruleset) |
| `composer ecs`     | Code-style check (Craft's ECS ruleset) |
| `composer ecs-fix` | Auto-fix code-style issues |
| `composer test`    | PHPUnit unit tests (`tests/unit`) |
| `composer check`   | Runs phpstan, ecs, and the tests in sequence |

Config lives in `phpstan.neon`, `ecs.php`, and `phpunit.xml` at the repo root.

## Running with DDEV

There is no PHP CLI required on the host — a DDEV config (`.ddev/`, untracked)
provides PHP 8.2 and Composer in a container:

```bash
ddev start
ddev composer install
ddev exec composer check
```

## Test scope

The unit suite covers the pure-logic core that doesn't need a running Craft
application:

- `NodeTypeTest` — the `NodeType` backed enum (labels, colors, URL/element
  semantics, element-class mapping).
- `PresetTest` — the `Preset` enum, including that every preset resolves to a
  template that actually ships in `src/templates/_presets/`.
- `PropagationTest` — the `Propagation` enum.
- `VisibilityRuleTest` — `VisibilityRule` validation ranges and the
  `evaluate()` fall-through.

Behavior that requires a live request, user session, or database (element
syncing, rendering, multi-site propagation) is covered by the manual testing
checklist in `CLAUDE.md`.

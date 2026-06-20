# Contributing

Thanks for considering a contribution! This package guards real production data,
so correctness and tests matter more than features.

## Getting started

```bash
git clone https://github.com/shaxzodbek-uzb/laravel-model-mcp
cd laravel-model-mcp
composer install
```

## Before you open a PR

Run the full quality gate locally — CI runs the same:

```bash
composer test         # Pest test suite
composer analyse      # PHPStan / Larastan (level 6)
composer lint         # Laravel Pint (apply)
# or check without changing files:
vendor/bin/pint --test
```

## Guidelines

- **Security first.** Any change touching authorization, tenant scoping, or what
  data leaves the model must come with a test proving the boundary holds. Default
  to fail-closed.
- **Match the verified `laravel/mcp` API.** This package builds on `laravel/mcp`'s
  real `Tool` / `JsonSchema` / `Response` surface — don't invent methods; pin
  behavior with a test against the installed version.
- **Keep it tenancy-agnostic.** Reads and writes go through `Model::query()` so the
  host app's global scopes apply. Never call `withoutGlobalScopes()`.
- **Add a CHANGELOG entry** under `## [Unreleased]`.
- Follow the existing code style (`declare(strict_types=1)`, typed signatures).

## Reporting bugs

Open an issue with the Laravel + `laravel/mcp` versions, your relevant
`model-mcp` config, and a minimal reproduction. For security issues, see
[SECURITY.md](SECURITY.md) instead.

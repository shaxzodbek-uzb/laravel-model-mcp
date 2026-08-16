# Changelog

All notable changes to `laravel-model-mcp` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-08-16

### Added

- **A `describe` operation.** Until now the only way for an agent to learn a
  model's shape was to call `list` and read whatever came back — which needs at
  least one row to exist, needs the caller to be allowed to see it, and still
  says nothing about which fields are writable, which are required on create, or
  what a date column expects. Each of those unknowns became a failed `create` and
  a retry.
- `describe` is **metadata only**: it reads the model's schema and this package's
  configuration, and never queries a row. So it cannot leak record data and needs
  no tenant scope.
- It is still policy-gated, on `viewAny`. Which models exist and what columns they
  have is itself information not every caller should have.
- Enabled by default in the `operations` config. Remove it there to turn it off.

### Changed

- **Widened the `laravel/mcp` constraint to `^0.8 || ^0.9.3`** (was `^0.8`).
  Pinned to 0.8 alone, this package could not be installed at all into an
  application already on `laravel/mcp` 0.9. The suite passes at both ends of the
  range — verified against v0.8.0 and v0.9.3 — and nothing forces an application
  on 0.8 to move.

### Not included

- **Relation traversal.** Exposing a model's relations means every related model
  needs its own policy check and tenant scope applied at each hop, or the tool
  becomes a way to read records the caller was denied directly. That is a design
  decision about authorization boundaries, not an addition to this release.

## [0.1.0] - 2026-06-20

### Added

- Initial release.
- Auto-generated MCP tools (`list`, `view`, `create`, `update`, `delete`, `search`)
  for any opted-in Eloquent model, built on top of `laravel/mcp`.
- First-class Laravel Policy enforcement on every tool, mapped to the standard
  `viewAny` / `view` / `create` / `update` / `delete` abilities. Fail-closed by
  default: a model with no policy is denied.
- Optional explicit multi-tenant scoping applied to the query *before* the policy
  check, with fail-closed tenant resolution.
- Pluggable audit log (`ToolAuditor`) recording every allowed, denied and errored
  tool call.
- JSON Schema generation from model casts and database columns, including enum
  casts and per-column required/nullable inference.
- `model-mcp:list` Artisan command to audit exactly what is exposed.
- Attribute-based discovery via `#[McpModel]` (opt-in, off by default).

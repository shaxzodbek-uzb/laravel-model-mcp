# Changelog

All notable changes to `laravel-model-mcp` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

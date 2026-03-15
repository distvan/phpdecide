# Copilot Instructions for PHPDecide

## Project intent
- PHPDecide stores architectural decisions as YAML files in `.decisions/` and makes them queryable, explainable, and enforceable.
- AI output is assistive only. Do not invent project rules that are not recorded in decision files.

## Tech stack and runtime
- Language: PHP 8.2+
- CLI entrypoint: `bin/phpdecide`
- Main dependencies: Symfony Console and Symfony YAML
- Tests: PHPUnit 11

## Coding conventions
- Always use strict types: `declare(strict_types=1);`
- Prefer PSR standards when possible (PSR-1, PSR-12, PSR-4).
- Preserve existing namespace and PSR-4 structure under `src/` and `tests/`.
- Prefer small, explicit methods and typed values.
- Keep method control flow simple and avoid excessive early returns.
- Follow SOLID principles for new code and refactoring.
- Prefer composition over inheritance unless inheritance is clearly the better fit.
- Keep modules loosely coupled with clear boundaries and explicit dependencies.
- Avoid broad behavior changes when fixing a focused issue.
- For AI guard logic:
  - Expected validation/policy failures should use `AiClientException`.
  - In `fail_open`, guard-only internal failures may bypass guard processing.
  - Exceptions thrown by the inner AI client must bubble (no retry/duplicate outbound call).

## Decision file expectations
- Decision files live in `.decisions/` and should use `.yaml` extension.
- Required fields: `id`, `title`, `status`, `date`, `scope`, `decision`.
- Keep IDs stable (for example `DEC-0001`) and dates in ISO form (`YYYY-MM-DD`).

## AI and security expectations
- AI mode is opt-in (`--ai`).
- Guard defaults should remain enterprise-safe unless explicitly requested otherwise.
- Do not weaken TLS behavior or introduce insecure transport shortcuts.
- Do not log raw prompt/response content from the guard audit layer.

## Testing and validation
- For focused changes, run targeted tests first (for example a single PHPUnit file).
- For broader changes, run full test suite via `composer test`.
- When behavior changes, update relevant docs (`README.md` and `docs/*`) in the same change.

## Editing behavior for this repo
- Keep diffs minimal and avoid unrelated refactors.
- Preserve existing formatting and indentation style in touched files.
- Add or update tests when modifying behavior or fixing bugs.

## Review checklist
- PSR alignment: code style and structure should remain consistent with PSR-1, PSR-12, and PSR-4.
- SOLID check: new/changed code should keep responsibilities focused and dependencies explicit.
- Design check: prefer composition over inheritance unless inheritance clearly improves the design.
- Coupling check: changes should preserve clear module boundaries and avoid unnecessary cross-module coupling.
- Behavior check: confirm existing behavior is preserved unless the change explicitly intends to alter it.
- Testing check: add or update targeted tests for behavior changes; run focused tests first.
- Documentation check: update `README.md` and relevant `docs/*` when user-visible behavior or configuration changes.

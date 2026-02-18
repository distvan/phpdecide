# Changelog

All notable changes to **PHPDecide** will be documented in this file.

This project aims to follow [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - Unreleased

### Added
- AI gateway support improvements for OpenAI-compatible proxies (e.g. DIAL / Azure-style gateways):
  - `PHPDECIDE_AI_CHAT_COMPLETIONS_PATH` to override the Chat Completions endpoint path (default: `/v1/chat/completions`).
  - `PHPDECIDE_AI_OMIT_MODEL` to omit the JSON `model` field when the gateway encodes the model/deployment in the URL.
  - `PHPDECIDE_AI_AUTH_HEADER_NAME` and `PHPDECIDE_AI_AUTH_PREFIX` to support non-Bearer authentication (e.g. `Api-Key: <key>`).

### Changed
- AI requests explicitly set `stream=false` and send `Accept: application/json` for better compatibility with gateways.

### Fixed
- AI error reporting now includes HTTP status, target URL, auth header diagnostics (without secrets), and a short response snippet when the gateway returns non-JSON bodies (common for 401/404 proxy errors).

## [1.0.0] - 2026-02-15

### Highlights
- First stable release of PHPDecide: decision files as structured, version-controlled project knowledge.
- CI-friendly linting for `.decisions/*.yaml` (syntax + schema checks).
- “Explain” workflow with optional AI summarization (presentation only; decisions remain the source of truth).

### Added
- CLI command `decisions:lint`
  - Validates `.yaml` decision files in a directory (default: `.decisions/`).
  - `--require-any` option to fail if no `.yaml` files exist.
  - Detects unsupported `.yml` extension and reports it explicitly.
  - Detects duplicate decision IDs across multiple files.
- CLI command `explain <question>`
  - Loads recorded decisions from `.decisions/`.
  - `--path <path>` option to only consider decisions applicable to a specific file path.
  - Optional `--ai` mode to summarize recorded decisions.
  - `--ai-strict` option to fail if AI is enabled but unavailable/errors (default: falls back to plain output).

### AI (Environment Configuration)
- Environment variables supported for AI mode:
  - `PHPDECIDE_AI_API_KEY` (required to enable AI)
  - `PHPDECIDE_AI_MODEL` (default: `gpt-4o-mini`)
  - `PHPDECIDE_AI_BASE_URL` (default: `https://api.openai.com`)
  - `PHPDECIDE_AI_TIMEOUT` (seconds)
  - `PHPDECIDE_AI_ORG`, `PHPDECIDE_AI_PROJECT` (optional headers)
  - `PHPDECIDE_AI_SYSTEM_PROMPT` (optional override)
  - `PHPDECIDE_AI_CAINFO` (CA bundle path; Windows TLS help)
  - `CURL_CA_BUNDLE` (fallback for CA bundle path)
- Safety defaults:
  - TLS verification is enforced; insecure skip-verify is intentionally not supported.
  - Non-HTTPS base URLs are rejected, except `http://localhost` for local testing.

### Notes
- Dependencies: Symfony Console/YAML `^7.4`, PHPUnit `^11` (dev).
- Decision loader reads `.yaml` files (not `.yml`).

[1.0.0]: https://github.com/distvan/phpdecide/releases/tag/v1.0.0
[1.1.0]: https://github.com/distvan/phpdecide/releases/tag/v1.1.0

# Decision file anatomy (guidance)

A decision file is the **source of truth** for “what we decided and why”.
The goal is to make decisions:

- readable by humans in Git
- stable over time (diff-friendly)
- rich enough for high-quality explanation (including AI assistance)
- strict enough for automated checks and enforcement

This document describes a **recommended structure** that also matches what PHPDecide currently parses.

## File placement and naming

- Put decisions under `.decisions/` in the repo root.
- Use the `.yaml` extension (the loader currently loads only `.yaml`, not `.yml`).
- Keep filenames stable and sortable, e.g.:
	- `DEC-0001-no-orms.yaml`
	- `DEC-0020-api-error-format.yaml`

## Minimal schema (what PHPDecide loads today)

Required top-level fields:

- `id` (string) – stable identifier like `DEC-0001`
- `title` (string) – short, human-friendly
- `status` (enum string) – one of: `active`, `deprecated`, `superseded`
- `date` (string) – ISO date (`YYYY-MM-DD`) recommended
- `scope` (object) – where it applies
- `decision` (object) – the actual decision content

Optional top-level fields (currently supported by the parser/model):

- `examples` (object) – allowed/forbidden examples
- `rules` (object) – allow/forbid rule keywords (future: enforcement)
- `references` (object) – links to issues/commits/ADRs
- `ai` (object) – AI-friendly metadata for explanations

Extra fields are allowed in YAML, but are currently ignored by the loader.

## Field-by-field guidance

### `id`

- Choose something that never changes (renaming breaks links and search).
- Prefer a fixed-width number: `DEC-0001` over `DEC-1`.

### `title`

- Start with the decision, not the backstory.
- Good: `No ORM in Order domain`
- Avoid: `Discussion about persistence tools`

### `status`

- `active`: enforce / follow by default
- `deprecated`: still exists, but is being phased out
- `superseded`: replaced by a newer decision (record the replacement in `references`)

### `date`

- Use the decision date (not the last edit date).
- Keep it as an unambiguous string: `'2026-02-03'`.

### `scope`

```yaml
scope:
	type: global|path|module
	paths:
		- 'src/Order/*'
```

- `type: global` means “applies everywhere”; omit `paths`.
- `type: path` uses glob matching (`fnmatch`) against repo-relative paths.
	- Prefer forward slashes: `src/Order/*`
	- Use patterns that are hard to misinterpret.
- If you want to scope by module later, keep module boundaries reflected in the path patterns.

### `decision.summary`

- A short paragraph that can be quoted in reviews.
- Use YAML folded style (`>`) for readability.

### `decision.rationale`

- A list of bullets, one idea per line.
- Focus on “why”, tradeoffs, and constraints (not implementation details).
- Make it future-proof: avoid names of specific people or temporary incidents.

### `decision.alternatives` (optional)

- A list of plausible options you considered.
- Mention why they were rejected briefly (you can add this as part of the item).

### `examples` (optional)

```yaml
examples:
	allowed:
		- 'src/Infrastructure/Persistence/Doctrine/*'
	forbidden:
		- 'src/Order/*'
```

- Use real paths from your repo; examples help onboarding.
- Keep examples small and representative.

### `rules` (optional)

```yaml
rules:
	forbid:
		- 'doctrine/orm'
	allow: []
```

- Rules should be **machine-oriented**: stable tokens that can be checked automatically.
- Prefer a small number of strong rules over many weak ones.

### `references` (optional)

```yaml
references:
	issues:
		- 'https://…'
	commits:
		- 'abc1234'
	adr: 'docs/adr/0007-persistence.md'
```

- Put the long backstory here (tickets, PRs, ADRs).
- Keep `decision.rationale` readable without needing the references.

### `ai` (optional)

```yaml
ai:
	explain_style: 'Explain as an architecture mentor…'
	keywords:
		- 'boundary'
		- 'coupling'
```

- Treat this as a “prompt hint”, not as a decision itself.
- Keywords should be nouns/phrases people actually search for.

## Templates

### Minimal template

```yaml
id: DEC-0001
title: <short title>
status: active
date: '2026-02-03'

scope:
	type: global

decision:
	summary: >
		<one-paragraph summary>
	rationale:
		- <reason 1>
		- <reason 2>
```

### Full template (recommended)

```yaml
id: DEC-0001
title: <short title>
status: active
date: '2026-02-03'

scope:
	type: path
	paths:
		- 'src/*'

decision:
	summary: >
		<one-paragraph summary>
	rationale:
		- <reason 1>
		- <reason 2>
	alternatives:
		- <alternative 1>
		- <alternative 2>

examples:
	allowed: []
	forbidden: []

rules:
	forbid: []
	allow: []

references:
	issues: []
	commits: []
	adr: null

ai:
	explain_style: 'Explain it briefly with tradeoffs and boundaries.'
	keywords: []
```

## Example

- See [DEC-0003.no-orm-in-order-domain.yaml](DEC-0003.no-orm-in-order-domain.yaml) for a complete example that matches the current schema.

![Status](https://sonarcloud.io/api/project_badges/quality_gate?project=distvan_phpdecide) \
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=coverage)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Lines of Code](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=ncloc)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=sqale_index)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=vulnerabilities)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide)
## PHPDecide

A decision memory and enforcement for PHP projects. \
Stop re-deciding the same things. Make architectural decisions executable.

### The problem

Every long lived software project suffers from decision decay:

- Architectural decisions are made, then forgotten
- New developers ask the same "why?" questions again and again
- Rules exist only in people's heads or scattered documents
- Code slowly drifts away from original intent
- Reviews become subjective instead of factual

Traditional solutions don't scale:

- Wikis go stale
- ADRs are passive and unenforced
- Chatbots have no project memory
- CI tools enforce syntax, not intent

### The Idea

**PHPDecide** turns architectural and technical decisions into **first class**, **executable artifacts**.

Instead of being a passive documentation, decisions become:

- queryable
- explainable
- enforceable
- version-controlled
- CI-friendly

It is a kind of institutional memory for your codebase, backed by rules and explained by AI.

### What is a Decision?

A decision is a structured, explicit statement that answers:

- What was decided?
- Why was it decided?
- What alternatives were rejected?
- Where does it apply?
- What rules enforce it?

Decisions live inside the repository, next to the code they govern.

Example topics:

- "Why we don't use ORMs"
- "Security headers must be enabled"
- "No business logic in views"
- "Why this module avoids async processing"

### How it works?

Decisions are stored as files
```
.decisions/
    DEC-0001-no-orm.yaml
    DEC-0002-controller-responsibilities.yaml
```
These files are human readable (YAML), diff friendly and stable over time.

Decisions ae loaded into a domain model, each decision becomes a strongly-typed Decision value object:

- ID
- status (active/deprecated/superseded)
- scope (global, path, module)
- rationale
- examples
- enforcement rules
- references (issues, ADRs, commits)

### Enforcement

Decisions can optionally define rules.

Example:

- forbidden dependencies
- forbidden namespaces
- forbidden file patterns

When enforced:

- violations are detected automatically
- failures reference the exact decision
- explanations include why, not just what

This turns architecture from "guideline" into living constraints.

### First enforcement-ready workflow (`v1.3.0`)

The first enforcement slice does not build a new analyzer.
Instead, PHPDecide maps external analyzer findings back to decision IDs.

Run it with a generic JSON report:

`php ./bin/phpdecide enforce --report build/phpdecide-findings.json`

Emit a machine-readable result for CI or bots:

`php ./bin/phpdecide enforce --report build/phpdecide-findings.json --format json`

Or feed Semgrep output directly:

`semgrep scan --config semgrep/rules --json --output build/semgrep.json`

`php ./bin/phpdecide enforce --semgrep-report build/semgrep.json`

Or feed PHPStan output directly:

`phpstan analyse --error-format=json > build/phpstan.json`

`php ./bin/phpdecide enforce --phpstan-report build/phpstan.json`

Checked-in example assets:

- Example decision: [examples/decisions/DEC-0003.no-orm-in-order-domain.yaml](examples/decisions/DEC-0003.no-orm-in-order-domain.yaml)
- Example decision: [examples/decisions/DEC-0004.no-business-logic-in-templates.yaml](examples/decisions/DEC-0004.no-business-logic-in-templates.yaml)
- Example decision: [examples/decisions/DEC-0005.no-orm-in-order-domain-via-phpstan.yaml](examples/decisions/DEC-0005.no-orm-in-order-domain-via-phpstan.yaml)
- Semgrep rule example: [semgrep/rules/no-orm-in-order-domain.yaml](semgrep/rules/no-orm-in-order-domain.yaml)
- Semgrep rule example: [semgrep/rules/no-business-logic-in-templates.yaml](semgrep/rules/no-business-logic-in-templates.yaml)
- PHPStan report example: [examples/phpstan/no-orm-in-order-domain-report.json](examples/phpstan/no-orm-in-order-domain-report.json)
- PHPStan example notes: [examples/phpstan/README.md](examples/phpstan/README.md)
- GitHub Actions example: [examples/github-actions/phpdecide-semgrep-enforce.yaml](examples/github-actions/phpdecide-semgrep-enforce.yaml)
- GitHub Actions example: [examples/github-actions/phpdecide-phpstan-enforce.yaml](examples/github-actions/phpdecide-phpstan-enforce.yaml)
- PR comment renderer: [examples/github-actions/render-phpdecide-pr-comment.php](examples/github-actions/render-phpdecide-pr-comment.php)
- Annotation renderer: [examples/github-actions/render-phpdecide-annotations.php](examples/github-actions/render-phpdecide-annotations.php)

Matching decision tokens for your own project decisions:

- `doctrine/orm`
- `twig/business-logic`
- `phpstan.doctrine.orm`

Record those tokens in decision files under your own project's `.decisions/` directory.

Sample fixture files used by the second rule:

- Allowed fixture: [examples/fixtures/templates/order/show.html.twig](examples/fixtures/templates/order/show.html.twig)
- Violating fixture: [examples/fixtures/templates/order/calculate_total.html.twig](examples/fixtures/templates/order/calculate_total.html.twig)

Sample fixture files used by the PHPStan example:

- Allowed fixture: [examples/fixtures/phpstan/src/Infrastructure/Persistence/Doctrine/OrderRecord.php](examples/fixtures/phpstan/src/Infrastructure/Persistence/Doctrine/OrderRecord.php)
- Violating fixture: [examples/fixtures/phpstan/src/Order/OrderService.php](examples/fixtures/phpstan/src/Order/OrderService.php)

Supported JSON shapes:

```json
{
    "findings": [
        {
            "tool": "semgrep",
            "rule_id": "doctrine/orm",
            "path": "src/Order/OrderService.php",
            "line": 12,
            "severity": "error",
            "message": "Doctrine ORM import detected."
        }
    ]
}
```

Or a top-level JSON array of the same finding objects.

Initial mapping behavior:

- active decisions only
- scope must match the finding path
- the finding `rule_id` must equal a token listed in `rules.forbid`

Analyzer report paths are normalized for scope matching, so Windows-style separators and leading `./` do not prevent a finding from matching path-based decision scopes.

This gives CI a stable way to print violations grouped by decision ID without forcing PHPDecide to own static analysis itself.
For Semgrep, PHPDecide reads native `results[*].check_id`, `path`, `start.line`, and `extra.message` / `extra.severity` fields and converts them internally.
For PHPStan, PHPDecide reads native `files[*].messages[*]` entries and maps the message `identifier` field to the decision token in `rules.forbid`.
The repository includes a checked-in native example report at [examples/phpstan/no-orm-in-order-domain-report.json](examples/phpstan/no-orm-in-order-domain-report.json) so teams can copy the JSON shape and token contract directly.
There is also a short authoring note in [examples/phpstan/README.md](examples/phpstan/README.md) showing how a real PHPStan custom rule should emit the same identifier token.

When `--format json` is used, the command emits a structured payload with:

- `ok`
- `summary`
- `violations_by_decision`
- `unmapped_findings`

The `summary` object contains:

- `violating_decision_count`: number of decision IDs that have at least one matched violation
- `violation_count`: total number of matched violations across all decisions
- `unmapped_finding_count`: number of analyzer findings that did not map to an active scoped decision rule

The exit code behavior does not change: matched decision violations still produce a non-zero exit code.

The checked-in GitHub Actions example now demonstrates the full structured path:

- run Semgrep
- call `phpdecide enforce --format json`
- render PR-comment markdown from the JSON payload
- render GitHub annotation commands from the same JSON payload
- write a human-readable step summary from that markdown
- upload the JSON artifact
- upload the markdown artifact
- upload the annotation command artifact
- fail the job if the enforcement exit code was non-zero

There is also a parallel PHPStan-flavored workflow example:

- stage a native PHPStan JSON report
- call `phpdecide enforce --phpstan-report ... --format json`
- reuse the same markdown and annotation renderers
- upload the JSON, markdown, and annotation artifacts
- fail the job if the enforcement exit code was non-zero

See [examples/github-actions/phpdecide-phpstan-enforce.yaml](examples/github-actions/phpdecide-phpstan-enforce.yaml).

The renderer can also be used locally:

`php examples/github-actions/render-phpdecide-pr-comment.php build/phpdecide-enforce.json`

The annotation renderer can also be used locally:

`php examples/github-actions/render-phpdecide-annotations.php build/phpdecide-enforce.json`

Use the PR-comment renderer when you want one grouped markdown summary. Use the annotation renderer when you want file-level warnings/errors that show up inline in GitHub Actions logs and checks.

### Philosophy of AI Usage in PHPDecide

- AI is an assistant, not an Authority
- PHPDecide does not treat AI as a source of truth.
- Decisions come from people.
- Rules come from teams.
- AI exists to help humans understand and apply those decisions - nothing more.
- If a rule is not recorded, AI has no authority to invent one.
- AI support is a presentation layer: it summarizes recorded rationale and context; it does not create new decisions or rules.

### Who this is for

PHPDecide is ideal for:

- PHP teams with long-lived codebases
- Senior developers tired of repeating explanations
- Teams onboarding new developers frequently
- Projects with architectural or security constraints
- Organizations that value consistency and clarity

### Quickstart (Phase 1: explain adoption)

1) Create a `.decisions/` folder in your project root and add your first decision file.

2) Validate decisions locally (fast feedback):

`php ./bin/phpdecide decisions:lint --require-any`

3) Ask questions during reviews / onboarding:

- Explain a topic:
    - `php ./bin/phpdecide explain "Why no ORMs?"`
- Explain with scope filtering (does it apply to this code path?):
    - `php ./bin/phpdecide explain "Why no ORMs?" --path src/Order/OrderService.php`

Tip: use [docs/decision-file-anatomy.md](docs/decision-file-anatomy.md) as the schema guide.

### Quickstart (Phase 2: enforcement-ready mapping)

1) Add stable machine-oriented tokens in decision `rules.forbid`.

2) Configure your analyzer to emit matching `rule_id` values.

3) Feed the analyzer report into PHPDecide:

`php ./bin/phpdecide enforce --report build/phpdecide-findings.json`

Or use Semgrep directly:

`php ./bin/phpdecide enforce --semgrep-report build/semgrep.json`

Or use PHPStan directly:

`php ./bin/phpdecide enforce --phpstan-report build/phpstan.json`

For CI integration, prefer:

`php ./bin/phpdecide enforce --semgrep-report build/semgrep.json --format json`

If you want a starting point, this repository includes visible example decision files under [examples/decisions](examples/decisions), plus Semgrep and PHPStan example assets that use the same stable tokens. The GitHub Actions examples stage those decision files into a temporary directory before running `decisions:lint --require-any` and `enforce`, so the workflow stays self-contained. The second Semgrep sample intentionally targets example fixture files under `examples/fixtures/templates/` rather than a real application template directory.

The command fails when it finds decision-linked violations, which makes it suitable for CI.

By default, `enforce` uses the same decision cache as `explain` and may create or refresh `.decisions/.phpdecide-decisions.cache` during a run. Disable cache writes for read-only CI jobs or debugging with either `--no-cache` or `PHPDECIDE_DECISIONS_CACHE=0`.

### Decision loading cache (optional)

By default, `explain` and `enforce` cache parsed decisions in `.decisions/.phpdecide-decisions.cache` to speed up repeated runs.

Disable cache if you are debugging loader behavior:

- CLI: `php ./bin/phpdecide explain "Why no ORMs?" --no-cache`
- CLI: `php ./bin/phpdecide enforce --report build/phpdecide-findings.json --no-cache`
- Env: set `PHPDECIDE_DECISIONS_CACHE=0`

### AI configuration (optional)

AI mode is off by default and only activates when you pass `--ai`.

Environment variables:

- `PHPDECIDE_AI_API_KEY` (required)
- `PHPDECIDE_AI_MODEL` (optional, default: `gpt-4o-mini`)
- `PHPDECIDE_AI_OMIT_MODEL` (optional, default: `false`) - set to `true` if your gateway encodes the model in the URL path and rejects a `model` field in JSON (common in some DIAL deployments)
- `PHPDECIDE_AI_BASE_URL` (optional, default: `https://api.openai.com`)
- `PHPDECIDE_AI_CHAT_COMPLETIONS_PATH` (optional, default: `/v1/chat/completions`) - override if your gateway uses a different OpenAI-compatible path
- `PHPDECIDE_AI_TIMEOUT` (optional, seconds)
- `PHPDECIDE_AI_AUTH_HEADER_NAME` (optional, default: `Authorization`) - header name used for authentication (some gateways require `api-key`)
- `PHPDECIDE_AI_AUTH_PREFIX` (optional, default: `Bearer `) - prefix placed before the API key in the auth header value (set to empty for `api-key: <key>` style)
- `PHPDECIDE_AI_ORG`, `PHPDECIDE_AI_PROJECT` (optional) - extra headers for some OpenAI-compatible providers
- `PHPDECIDE_AI_SYSTEM_PROMPT` (optional) - override the system prompt
- `PHPDECIDE_AI_CAINFO` (optional) - path to a CA bundle (`cacert.pem`) if you get cURL SSL errors (e.g. cURL error 60)

### AI egress guard (recommended for enterprise/CI)

If you run PHPDecide in regulated environments or CI, enable the **LLM egress guard**.
It adds deterministic checks (size limits + secret detection) before any outbound AI call, and can also redact secrets from AI output.

Enable:

- `PHPDECIDE_AI_GUARD=1`

Optional configuration:

- `PHPDECIDE_AI_GUARD_FAILURE_MODE` (`fail_closed`|`fail_open`, default: `fail_closed`)
- `PHPDECIDE_AI_GUARD_INPUT_MAX_CHARS` (default: `8000`)
- `PHPDECIDE_AI_GUARD_DLP_ENABLED` (`true`|`false`, default: `true`)
- `PHPDECIDE_AI_GUARD_INPUT_DLP_ACTION` (`block`|`monitor`|`sanitize`, default: `block`)
- `PHPDECIDE_AI_GUARD_OUTPUT_DLP_ACTION` (`sanitize`|`monitor`|`block`, default: `sanitize`)
- `PHPDECIDE_AI_GUARD_AUDIT_LOG_PROMPT` (`none`|`hash`|`redact`, default: `redact`)
- `PHPDECIDE_AI_GUARD_AUDIT_LOG_RESPONSE` (`none`|`hash`|`redact`, default: `redact`)
- `PHPDECIDE_AI_GUARD_AUDIT_ENABLED` (`true`|`false`, default: `true`) - writes structured JSON audit events to stderr

Notes:

- If the guard detects secrets in the **recorded decisions payload**, it blocks the AI call (to avoid leaking decision content).
- If it detects secrets in the **question**, behavior depends on `PHPDECIDE_AI_GUARD_INPUT_DLP_ACTION`.
- Audit events never include raw prompt/response fields from this layer.
- `PHPDECIDE_AI_GUARD_AUDIT_LOG_PROMPT` and `PHPDECIDE_AI_GUARD_AUDIT_LOG_RESPONSE` are currently parsed by config but not yet applied to event-shaping behavior in this guard layer (reserved for future behavior).

TLS note: if you enable `--ai` and hit TLS/certificate issues, configure `PHPDECIDE_AI_CAINFO` (CA bundle). TLS verification is enforced; there is no insecure/skip-verify mode.

Gateway / DIAL note: if your gateway puts the model into the URL (instead of JSON), set `PHPDECIDE_AI_CHAT_COMPLETIONS_PATH` accordingly and enable `PHPDECIDE_AI_OMIT_MODEL=true`.

Example (DIAL / Azure-style OpenAI proxy):

- `PHPDECIDE_AI_BASE_URL=https://ai-proxy.example.com`
- `PHPDECIDE_AI_CHAT_COMPLETIONS_PATH=/openai/deployments/<deployment>/chat/completions`
- `PHPDECIDE_AI_OMIT_MODEL=true`
- `PHPDECIDE_AI_AUTH_HEADER_NAME=Api-Key`
- `PHPDECIDE_AI_AUTH_PREFIX=`

### Security model (threat model + supported/unsupported configs)

Threat model (what we try to prevent):

- Accidental sensitive-data egress to LLM providers (secrets in questions or recorded decisions).
- Misconfiguration that weakens transport security (e.g. non-HTTPS endpoints, insecure TLS).
- “Operational surprises” (e.g. redirects to unexpected hosts) when calling OpenAI-compatible gateways.

Out of scope (what PHPDecide does NOT attempt to solve):

- A compromised developer machine or CI runner.
- A malicious or already-compromised LLM gateway/provider.
- Full DLP coverage. The guard uses deterministic regex-based checks; it reduces risk but cannot guarantee detection.

Supported configs / guarantees:

- TLS verification is always enabled for AI calls.
- AI base URL must be `https://...` (exception: `http://localhost` for local testing).
- HTTP redirect following is disabled in the cURL transport.
- The AI egress guard avoids logging raw prompt/response content; audit events are structured and written to stderr.

Unsupported configs (intentional):

- Disabling TLS verification (there is no “insecure skip verify” mode).
- Using non-loopback `http://` AI endpoints.

![Status](https://sonarcloud.io/api/project_badges/quality_gate?project=distvan_phpdecide) \
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=coverage)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
[![Duplicated Lines (%)](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
[![Lines of Code](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=ncloc)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
[![Technical Debt](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=sqale_index)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=distvan_phpdecide&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=distvan_phpdecide) \
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

1) Create a `.decisions/` folder in your repo root and add your first decision file.

2) Validate decisions locally (fast feedback):

`php ./bin/phpdecide decisions:lint --require-any`

3) Ask questions during reviews / onboarding:

- Explain a topic:
    - `php ./bin/phpdecide explain "Why no ORMs?"`
- Explain with scope filtering (does it apply to this code path?):
    - `php ./bin/phpdecide explain "Why no ORMs?" --path src/Order/OrderService.php`

Tip: use [docs/decision-file-anatomy.md](docs/decision-file-anatomy.md) as the schema guide.

AI note: if you enable `--ai` and hit TLS/certificate issues, configure `PHPDECIDE_AI_CAINFO` (CA bundle). TLS verification is enforced; there is no insecure/skip-verify mode.

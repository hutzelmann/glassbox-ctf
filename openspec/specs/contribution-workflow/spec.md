# contribution-workflow Specification

## Purpose
Establishes OpenSpec as the required drafting step for non-trivial changes, so
structure, behavior, and rationale are captured before code is written.
## Requirements
### Requirement: Non-trivial changes are drafted in OpenSpec first

A non-trivial change SHALL be drafted as an OpenSpec change (proposal, specs,
design, tasks) under `openspec/changes/` before implementation. `AGENTS.md` SHALL
record this rule and define the threshold.

#### Scenario: Structural change requires a proposal

- **WHEN** a contributor adds a challenge, adds a challenge type or base image,
  changes the folder or CI structure, changes the glass-box contract, or touches
  multiple challenges
- **THEN** they first create an OpenSpec change and get it drafted before editing
  code

### Requirement: Trivial changes are exempt

Trivial changes SHALL be allowed without an OpenSpec change. The exempt set SHALL
include: typo and copy fixes, a single-file bugfix within one challenge,
dependency version bumps, and documentation wording.

#### Scenario: Typo fix needs no proposal

- **WHEN** a contributor fixes a typo in one file
- **THEN** they may commit it directly without an OpenSpec change

### Requirement: Tool availability is decided openly, never excluded silently

The repository SHALL NOT silently forbid a tool from its images or contributor
toolchain. When a tool is deliberately left out or brought in, `AGENTS.md` SHALL
state the choice together with its rationale. A contributor proposing to add or
remove a tool SHALL surface the trade-offs (image size, offline/build cost,
security, didactic value) to the maintainer for a decision, rather than dropping
or admitting the tool by fiat.

#### Scenario: A blanket tool ban is not recorded as policy

- **WHEN** `AGENTS.md` or a base image would otherwise state only that a named
  tool is excluded
- **THEN** the exclusion is replaced by an explicit rationale, or the tool is
  admitted, so no tool is banned without a stated reason

#### Scenario: Proposing a tool change weighs the trade-offs

- **WHEN** a contributor proposes adding or removing a tool from an image or the
  toolchain
- **THEN** they present the pros and cons to the maintainer and get a decision,
  rather than excluding it silently


## Purpose

Establishes OpenSpec as the required drafting step for non-trivial changes, so
structure, behavior, and rationale are captured before code is written.

## ADDED Requirements

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

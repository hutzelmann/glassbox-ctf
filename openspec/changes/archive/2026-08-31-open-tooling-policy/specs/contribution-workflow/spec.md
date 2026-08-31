## ADDED Requirements

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

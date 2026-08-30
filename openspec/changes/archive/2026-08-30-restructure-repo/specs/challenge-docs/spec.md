## Purpose

Defines the two-file documentation contract that lets one repo serve both
learners (who must not spoil themselves by accident) and teachers (who need the
complete walkthrough and flags).

## ADDED Requirements

### Requirement: Every challenge ships a spoiler-free README

Each challenge folder SHALL contain a `README.md` aimed at the learner: the
challenge premise, its position in its ladder, the tasks to accomplish, how to
run that container, and what the Fix editor and `?debug=1` view do for it. The
`README.md` SHALL NOT contain flags, payloads, injection strings, or the fix.

#### Scenario: README omits the answer

- **WHEN** a learner reads a challenge `README.md`
- **THEN** they see the tasks and how to run the container, but no flag, no
  payload, and no fix

### Requirement: Every challenge ships a complete SOLUTION

Each challenge folder SHALL contain a `solution.md` aimed at teachers and stuck
learners: a walkthrough of every task, the exact tools and payloads, the flags,
and the remediation to write into `critical.<ext>`. `solution.md` SHALL open with
a spoiler banner. For a container with no vulnerability (e.g. `runtime-check`),
`solution.md` SHALL exist and state that no solution is needed.

#### Scenario: Solution lists flags and fix

- **WHEN** a teacher reads a challenge `solution.md`
- **THEN** it contains the flag(s), the payload(s), and the remediation

#### Scenario: Consistent placeholder for setup containers

- **WHEN** a container has no vulnerability to solve
- **THEN** its `solution.md` still exists and says no solution is needed

### Requirement: Solutions show the professional-tool path

Where an industry-standard tool can solve the challenge, `solution.md` SHALL
include a "Professional tools" section that redoes the exploit with that tool
(e.g. `sqlmap` for SQL injection; browser DevTools and an intercepting proxy for
XSS), after the manual walkthrough. The manual path SHALL come first (the
challenge exists to build understanding); the tool path SHALL be presented as
what the learner reaches for once they understand the bug.

#### Scenario: SQLi solution demonstrates sqlmap

- **WHEN** a teacher reads a SQL-injection `solution.md`
- **THEN** after the manual injection walkthrough it shows a runnable `sqlmap`
  invocation that automates the same result against the container

#### Scenario: No tool is forced where none fits

- **WHEN** a challenge has no meaningful industry-standard tool
- **THEN** the "Professional tools" section may be omitted rather than naming an
  irrelevant tool

### Requirement: Documentation is never shipped in the image

A challenge's `README.md` and `solution.md` SHALL NOT be copied into its
container image. The build SHALL copy only the files the running challenge needs.

#### Scenario: SOLUTION absent from the image

- **WHEN** a challenge image is built and inspected
- **THEN** neither `README.md` nor `solution.md` is present in the served web root

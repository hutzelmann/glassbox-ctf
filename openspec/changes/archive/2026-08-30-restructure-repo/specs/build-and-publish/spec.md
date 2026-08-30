## Purpose

Defines how CI discovers, builds, and publishes images so that adding a challenge
requires no edit to the pipeline and the base-image chain is always built in the
right order.

## ADDED Requirements

### Requirement: Challenges are auto-discovered

CI SHALL discover challenges by scanning the `challenges/` tree for `Dockerfile`s
rather than from a hand-maintained list. Adding a challenge SHALL require only
adding its folder.

#### Scenario: New challenge builds with no pipeline edit

- **WHEN** a new challenge folder containing a `Dockerfile` is pushed to `main`
- **THEN** CI builds and publishes it without any change to the workflow file

### Requirement: The base chain is built before challenges

CI SHALL build and push the `platform/` images before challenge images, in
dependency order: `harness` first, then each family image (e.g. `php`), then the
challenges. A challenge build SHALL be able to pull its family base image.

#### Scenario: Harness precedes php precedes challenges

- **WHEN** a CI run starts
- **THEN** `harness` is published, then `php`, then the challenges that depend on
  `php`

### Requirement: Family is resolved per challenge, tolerating standalone images

CI SHALL determine each challenge's base image from that challenge's own
`Dockerfile` (its `ARG BASE_IMAGE` default). A challenge that does not descend
from a glass-box base image (e.g. `runtime-check`) SHALL still build and publish
standalone.

#### Scenario: Standalone smoke-test still publishes

- **WHEN** `runtime-check`, which builds `FROM alpine`, is discovered
- **THEN** it is built and published as `glassbox-ctf-runtime-check` without
  requiring a glass-box family base

### Requirement: Multi-architecture publishing to GHCR

Every image SHALL be published to `ghcr.io/<owner>/glassbox-ctf-<name>` for both
`linux/amd64` and `linux/arm64` on push to `main`.

#### Scenario: Two-arch tags on main

- **WHEN** a push to `main` completes CI
- **THEN** each image is available for `linux/amd64` and `linux/arm64` under its
  `glassbox-ctf-<name>` tag

## MODIFIED Requirements

### Requirement: The base chain is built before challenges

CI SHALL build and push the `platform/` images before challenge images, in
dependency order: `harness` first, then each family image that builds `FROM` the
harness (`php`, `native`), then the challenges. A challenge build SHALL be able to
pull its family base image.

#### Scenario: Harness precedes php precedes challenges

- **WHEN** a CI run starts
- **THEN** `harness` is published, then `php`, then the challenges that depend on
  `php`

#### Scenario: Native family built in the base chain

- **WHEN** a CI run starts
- **THEN** the `native` family image is built `FROM` the published `harness` and
  pushed as `glassbox-ctf-native` before the challenges that depend on it

### Requirement: Multi-architecture publishing to GHCR

Every image SHALL be published to `ghcr.io/<owner>/glassbox-ctf-<name>` on push to
`main`, for `linux/amd64` and `linux/arm64` by default. A challenge MAY declare a
restricted publish-platform set; when it does, CI SHALL publish only the declared
platforms for that challenge. The declaration SHALL be discoverable by CI from the
challenge itself, and its absence SHALL leave the default two-architecture
behavior unchanged.

#### Scenario: Two-arch tags on main

- **WHEN** a push to `main` completes CI for a challenge that declares no platform
  restriction
- **THEN** its image is available for both `linux/amd64` and `linux/arm64` under
  its `glassbox-ctf-<name>` tag

#### Scenario: Challenge restricts its publish platforms

- **WHEN** a challenge declares a restricted platform set (e.g. the binary rungs
  declare `linux/amd64` only, so their x86-64 addresses match the walkthrough)
- **THEN** CI publishes that challenge for only the declared platforms, while
  other challenges continue to publish for both architectures

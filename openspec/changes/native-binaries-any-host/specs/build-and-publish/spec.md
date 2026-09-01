## MODIFIED Requirements

### Requirement: Multi-architecture publishing to GHCR

Every image SHALL be published to `ghcr.io/<owner>/glassbox-ctf-<name>` on push to
`main`, for `linux/amd64` and `linux/arm64`. No image SHALL be restricted to a subset
of architectures: every published image SHALL provide both, so any host selects a
native image with no `--platform` or architecture flag. A challenge whose exploitable
binary targets a single architecture SHALL still publish its container for both host
architectures and run that binary on the non-matching host through the emulation layer
its family image provides, rather than restricting the image's platforms.

#### Scenario: Two-arch tags on main

- **WHEN** a push to `main` completes CI for any challenge
- **THEN** its image is available for both `linux/amd64` and `linux/arm64` under its
  `glassbox-ctf-<name>` tag

#### Scenario: Binary rungs publish for both architectures

- **WHEN** the binary rungs (`ret2win`, `ret2libc`), whose exploitable binary targets
  x86-64, complete CI
- **THEN** each is published for both `linux/amd64` and `linux/arm64`, the same as
  every other image, and neither declares a restricted platform set

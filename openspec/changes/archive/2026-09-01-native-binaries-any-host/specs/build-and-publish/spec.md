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

#### Scenario: Challenge restricts its publish platforms

- **WHEN** any challenge is built, including a binary rung (`ret2win`, `ret2libc`)
  whose exploitable binary targets a single architecture (x86-64)
- **THEN** its image is still published for both `linux/amd64` and `linux/arm64`; the
  per-challenge platform-restriction mechanism has been removed, so no challenge
  restricts its platforms (the binary rungs keep their binary single-arch via
  `nbuild --arch` and run it under `qemu-user` on the non-matching host instead)

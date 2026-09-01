## ADDED Requirements

### Requirement: The binary runs on any host architecture

A native challenge's exploitable binary SHALL declare a single fixed target
architecture, and the challenge SHALL run that binary on any host architecture its
container image supports: natively when the host matches the binary's target, and
through a user-mode emulation layer baked into the image when it does not. The image
SHALL be published for every supported host architecture so the plain run command
selects a native image with no architecture flag. The downloadable binary, the
walkthrough, and the debug internals SHALL correspond to the binary's target
architecture, not the host's, so every learner faces the same exploit against a
byte-identical binary regardless of the host they run on. The emulated path SHALL
apply the same time and resource bounds as the native path, so a pathological payload
cannot wedge the container on either.

#### Scenario: Solvable on a host that does not match the binary

- **WHEN** the container runs on a host whose architecture differs from the binary's
  declared target and a learner submits the intended payload
- **THEN** the binary runs under the emulation layer, the payload reaches the
  objective, and the plain run command needs no `--platform` or architecture flag

#### Scenario: Native when the host matches the target

- **WHEN** the host architecture matches the binary's declared target
- **THEN** the binary runs natively, without the emulation layer

#### Scenario: Same binary and walkthrough on every host

- **WHEN** two learners on different host architectures solve the same rung
- **THEN** they exploit a byte-identical binary and the published walkthrough's
  addresses, offsets, and gadgets apply unchanged to both

## MODIFIED Requirements

### Requirement: Debug captures the real stack frame before and after the overflow

At the Debug level, a native challenge SHALL show the actual runtime contents of
the vulnerable stack frame captured from the running binary: the frame **before**
the overflowing read (the pristine frame, with the real saved return address, the
real saved frame pointer, the real stack canary when the binary has one, and the
buffer's own uninitialized contents) and the frame **after** the read (the same
slots as overwritten by the learner's payload). The two frames SHALL be presented
together in a single view so the change to each slot is visible without navigating
away by comparing the before and after columns, with the saved-return-address
value resolved to the symbol or region it points at on both sides.

The capture SHALL work whether the binary runs natively or under the emulation
layer: when the host matches the binary's target architecture it SHALL use a native
debugger, and when it does not it SHALL drive the emulator's debug stub with a
debugger for the binary's target architecture, so full-fidelity capture does not
depend on the host matching the binary.

The capture SHALL nonetheless remain best-effort: when it cannot run in the current
environment, the view SHALL fall back to the payload-derived stack model and say so,
and the challenge SHALL remain fully solvable. The capture SHALL run only at the
Debug level and only after a payload has been submitted, and SHALL NOT be required to
solve the challenge.

#### Scenario: The real canary is shown being overwritten

- **WHEN** a build with the stack protector enabled runs a learner's overflowing
  payload and the learner opens the Debug stack view
- **THEN** the view shows the canary's real random value in the before column and
  the learner's bytes over it in the after column, alongside the abort outcome

#### Scenario: Before and after are shown together

- **WHEN** a learner submits a payload at the Debug level and capture succeeds
- **THEN** the pristine and clobbered frames appear side by side in one view, so the
  change to the saved return address, saved frame pointer and canary is visible by
  comparing the two columns

#### Scenario: Full-fidelity capture on a host that does not match the binary

- **WHEN** a learner opens the Debug stack view on a host whose architecture differs
  from the binary's target, and capture is supported in that environment
- **THEN** the real before and after frames are captured through the emulator's debug
  stub, with the same fidelity as a native host, rather than falling back to the
  payload-derived model

#### Scenario: Live capture degrades gracefully

- **WHEN** live capture cannot run in the current environment
- **THEN** the stack view falls back to the payload-derived model with a note, and
  the exploit and its solution are unaffected

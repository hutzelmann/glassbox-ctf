## ADDED Requirements

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

The capture SHALL be best-effort: when it cannot run in the current environment,
the view SHALL fall back to the payload-derived stack model and say so, and the
challenge SHALL remain fully solvable. The capture SHALL run only at the Debug
level and only after a payload has been submitted, and SHALL NOT be required to
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

#### Scenario: Live capture degrades gracefully

- **WHEN** live capture cannot run in the current environment
- **THEN** the stack view falls back to the payload-derived model with a note, and
  the exploit and its solution are unaffected

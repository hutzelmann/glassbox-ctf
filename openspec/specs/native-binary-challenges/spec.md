# native-binary-challenges Specification

## Purpose
Defines the behavior of the native binary-exploitation family and its challenges:
how a learner delivers a payload, watches the overflow unfold at the victim,
recompiles their fix, toggles compiler protections, and inspects the binary's
internals, all offline, following the glass-box philosophy for a compiled target.
## Requirements
### Requirement: Native family provides an offline C exploitation toolchain

The native family image SHALL let a challenge compile C to a native executable and
introspect it fully offline. It SHALL provide a C compiler, tools to disassemble
and list the symbols of an executable, a `checksec`-style report of an
executable's protections, an in-browser C editor with linting, and shared helpers
for running and introspecting the built binary. It SHALL NOT require any network
access at run time.

#### Scenario: Native challenge builds and runs offline

- **WHEN** a native challenge container runs with no network access
- **THEN** its binary can be recompiled, executed, disassembled, and reported on
  entirely within the container

#### Scenario: C editor reports compile errors

- **WHEN** a learner writes C in the Fix editor that fails to compile
- **THEN** the editor shows the compiler's errors at the corresponding source lines

### Requirement: Learners exploit the binary through the browser payload runner

A native challenge page SHALL accept an arbitrary byte payload from the learner,
run the vulnerable binary on it server-side, and show the program's output and
whether the objective (e.g. obtaining the flag) was achieved. Payload entry SHALL
allow any byte value (including NUL and newline), SHALL offer an escape-sequence
view and a hexadecimal view kept in sync, and MAY accept an uploaded payload file.
The exact binary the server runs SHALL be downloadable so the learner can
reproduce the attack locally with their own tools. Each run SHALL be bounded in
time and resources so a pathological payload cannot wedge the container.

#### Scenario: Submitting a payload runs the victim binary

- **WHEN** a learner submits a payload and runs it
- **THEN** the page shows the binary's output and reports success when the payload
  reaches the objective

#### Scenario: Arbitrary bytes via synced fields

- **WHEN** the learner edits the escape-sequence field or the hexadecimal field
- **THEN** the other view updates to the same bytes, and bytes such as an address
  containing NUL or newline are delivered to the binary intact

#### Scenario: Binary is downloadable

- **WHEN** a learner downloads the challenge binary
- **THEN** it is byte-for-byte the executable the server runs, suitable for local
  tooling such as pwntools

### Requirement: Saving the fix recompiles the binary and takes effect

For a native challenge, Save SHALL recompile the binary from the edited source and
the current compiler flags before the fix takes effect. On a successful compile
the running binary SHALL be replaced by the freshly built one; on a failed compile
the previously working binary SHALL keep serving and the compiler errors SHALL be
shown to the learner. A correct fix that bounds the vulnerable read SHALL cause the
previously working exploit payload to stop working.

#### Scenario: A correct fix defeats the exploit

- **WHEN** a learner bounds the vulnerable read in `critical.c` and saves
- **THEN** the binary recompiles and the payload that previously reached the
  objective no longer does

#### Scenario: A broken fix keeps the last-good binary

- **WHEN** a learner saves source that fails to compile
- **THEN** the challenge keeps running the last successfully built binary and shows
  the compile errors, and remains fully usable

### Requirement: Compiler protections are a learner-editable remediation lane

A native challenge SHALL expose an editable compiler-flags field restricted to a
curated allowlist of exploit-relevant flags (stack protector, position
independence / PIE, non-executable stack / NX, optimization level, and debug
info). Flags outside the allowlist SHALL be ignored with a visible note rather than
applied. Changing the flags and saving SHALL rebuild the binary and update its
`checksec` report, and enabling an appropriate protection SHALL be able to defeat
the shipped exploit without any change to the source.

#### Scenario: Enabling the stack protector defeats ret2win

- **WHEN** a learner enables the stack protector in the flags field and saves,
  without changing the source
- **THEN** the original overflow aborts on the canary check and no longer reaches
  `win()`

#### Scenario: Non-allowlisted flags are ignored

- **WHEN** a learner enters a flag outside the allowlist
- **THEN** it is not passed to the compiler and the learner is told it was ignored

#### Scenario: checksec reflects the flags

- **WHEN** the flags change the binary's protections
- **THEN** the debug view's `checksec` report shows the new protection state

### Requirement: The debug view exposes binary internals

With `?debug=1`, a native challenge SHALL reveal the internals a learner needs to
understand and exploit the bug: the disassembly of the relevant functions, the
symbol listing including the target function's address, the `checksec`
protections, the process memory map, a hexdump of the bytes the server received,
and a stack-frame view mapping the submitted bytes onto the buffer, the saved
frame pointer, and the saved return address. The stack-frame view SHALL resolve
the value that lands in the saved return address to the symbol or region it points
at, so the learner sees where execution would go. Guidance that interprets these
internals SHALL appear only once debug is enabled.

#### Scenario: Debug reveals the win() address

- **WHEN** a learner enables debug
- **THEN** the symbol listing shows `win()`'s address, which the payload must target

#### Scenario: Stack view maps the learner's bytes

- **WHEN** a learner submits a payload and enables debug
- **THEN** the stack-frame view marks which submitted bytes overwrote the saved
  return address and resolves the value there to the symbol or region it points at

### Requirement: The binary ladder, ret2win then ret2libc

The `binary` domain SHALL ship two rungs. `ret2win` SHALL be solvable by
overflowing a stack buffer to overwrite the saved return address and redirect
execution to a `win()` function that prints a fictional flag. `ret2libc` SHALL be
solvable by a deterministic return-oriented chain that calls `system("/bin/sh")`
to obtain arbitrary command execution, with a flag readable only via that command
execution; it SHALL ship with a non-executable stack so that code injection is not
the intended path. Each rung SHALL be remediable by bounding the vulnerable read in
its own `critical.c`.

#### Scenario: ret2win reaches the win function

- **WHEN** a learner sends padding plus `win()`'s address
- **THEN** `win()` runs and prints the flag

#### Scenario: ret2libc yields command execution

- **WHEN** a learner sends the return-oriented chain
- **THEN** the binary executes a shell command of the learner's choosing and can
  read the `/flag` file

#### Scenario: Bounding the read remediates the rung

- **WHEN** a learner bounds the read in a rung's `critical.c` and saves
- **THEN** that rung's exploit payload no longer works

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


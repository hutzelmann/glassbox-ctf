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
info). Flags outside the allowlist entered in that field SHALL be ignored with a
visible note rather than applied. Changing the flags and saving SHALL rebuild the
binary and update its `checksec` report, and enabling an appropriate protection
SHALL be able to defeat the shipped exploit without any change to the source.

Separately, a challenge MAY pin fixed author-supplied build flags that apply on
every build in addition to the learner's allowlisted flags. These author flags are
trusted, are NOT exposed in the learner-editable field, and are NOT subject to the
learner allowlist. A learner SHALL NOT be able to remove or override an author-fixed
flag through the flags field. Author-fixed flags are for build settings a challenge
depends on structurally (for example, `ret2libc` pinning static linking so its ROP
ingredients exist), not for protections the learner is meant to toggle.

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

#### Scenario: Author-fixed flags always apply

- **WHEN** a challenge pins a fixed author build flag (such as `ret2libc`'s static
  linking) and a learner edits or clears the editable flags field and saves
- **THEN** every rebuild still applies the author-fixed flag, and the learner cannot
  remove it through the flags field

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

Every stack slot's note SHALL describe that slot. A note SHALL NOT attribute to one
slot a consequence that belongs to another: only the saved-return-address row may
state what the CPU does when the function returns. Slots above the frame SHALL be
resolved to the symbol or region they point at — that identification is what lets a
learner see a target address landed one slot off — but SHALL be described as stack
above the frame, reached only if execution returns again, not as the address the
`ret` jumps to.

#### Scenario: Debug reveals the win() address

- **WHEN** a learner enables debug
- **THEN** the symbol listing shows `win()`'s address, which the payload must target

#### Scenario: Stack view maps the learner's bytes

- **WHEN** a learner submits a payload and enables debug
- **THEN** the stack-frame view marks which submitted bytes overwrote the saved
  return address and resolves the value there to the symbol or region it points at

#### Scenario: A slot above the frame is not described as a return target

- **WHEN** a learner submits a payload long enough to fill whole words past the
  saved return address and opens the stack view
- **THEN** each such row is described as stack above the frame, reached only if
  execution returns again, and its value is still resolved to the symbol or region
  it points at
- **AND** no such row claims the CPU faults, or jumps, on returning to that value

### Requirement: The binary ladder, ret2win then ret2libc

The `binary` domain SHALL ship two rungs. `ret2win` SHALL be solvable by
overflowing a stack buffer to overwrite the saved return address and redirect
execution to a `win()` function that prints a fictional flag. `ret2libc` SHALL be
solvable by a deterministic return-oriented chain that calls `system("/bin/sh")`
to obtain arbitrary command execution, with a flag readable only via that command
execution; it SHALL ship with a non-executable stack so that code injection is not
the intended path. Each rung SHALL be remediable by bounding the vulnerable read in
its own `critical.c`.

`ret2libc`'s exploitation primitives — a `pop rdi; ret` gadget, the `"/bin/sh"`
string, and the `system` entry point — SHALL be present in the shipped binary as a
consequence of its ordinary build rather than through hand-authored assembly,
synthetic globals, or dead code whose only purpose is to keep a symbol or string
linked. The learner-facing source SHALL therefore read as a plausible C program.

`ret2libc`'s flag SHALL be a file named `flag.txt` that the exploit reads with a
relative path (e.g. `cat flag.txt`) from the working directory in which the server
runs the binary. The flag file SHALL live outside the web document root so that the
web layer never serves it; it SHALL be obtainable only through the command
execution the exploit achieves.

#### Scenario: ret2win reaches the win function

- **WHEN** a learner sends padding plus `win()`'s address
- **THEN** `win()` runs and prints the flag

#### Scenario: ret2libc yields command execution

- **WHEN** a learner sends the return-oriented chain
- **THEN** the binary executes a shell command of the learner's choosing and can
  read the `flag.txt` file

#### Scenario: ret2libc ingredients arise from the natural build

- **WHEN** the shipped ret2libc binary is inspected for the gadget, the `"/bin/sh"`
  string, and `system`
- **THEN** all three are present as products of the normal (statically linked)
  build, and the source contains no inline assembly, no synthetic `/bin/sh` global,
  and no branch that exists solely to keep `system` linked

#### Scenario: The flag is not reachable from the web layer

- **WHEN** the flag file is requested through the challenge's web server
- **THEN** it is not served, and the flag is disclosed only via a command run by the
  exploited binary

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

### Requirement: The debug view interprets a submitted ROP chain

For a rung solved with a multi-link return-oriented chain (`ret2libc`), the Hints
level (`?debug=1`) SHALL interpret the learner's own submitted bytes as a chain, not
merely display them. It SHALL show the full submitted payload on the stack view
(never silently truncated), mark where the vulnerable read stops so bytes beyond it
are shown as feeding the spawned shell's input rather than the stack, resolve each
filled slot at and above the saved return address to the role it plays in the chain,
and report whether the chain reaches its `system` call with the stack alignment that
call requires. This guidance interprets the learner's attempt, so it SHALL appear
only once Hints (or Debug) is enabled and only after a payload has been submitted.

Chain interpretation is opt-in per rung. Chain vocabulary — naming a slot a chain
link, a gadget, an argument or a call target — SHALL appear only for a rung that has
declared the chain roles being resolved, and SHALL be identical in the
payload-derived stack view and the live captured stack view. A rung that declares no
chain SHALL NOT use that vocabulary anywhere in its debug views, so a learner meets
those terms first on the rung that teaches them. A slot whose value the declaring
rung does not recognise SHALL fall back to the plain past-frame description rather
than being named a chain link.

#### Scenario: Full submitted bytes with the read boundary marked

- **WHEN** a learner submits a payload longer than the vulnerable read and opens the
  stack view
- **THEN** every submitted byte is shown, and the bytes past the read boundary are
  marked as feeding the spawned shell's stdin rather than lying on the stack

#### Scenario: Chain links are resolved to their roles

- **WHEN** a learner submits a chain and opens the stack view or the chain read-out
- **THEN** each filled slot from the saved return address upward is labelled with its
  role (for example an alignment `ret`, a `pop rdi; ret` gadget, the address of the
  `"/bin/sh"` string used as an argument, or the `system` call target), and an
  argument slot is not mislabelled as a return target

#### Scenario: A rung without a chain never says "chain link"

- **WHEN** a learner on a rung that declares no chain roles (`ret2win`) submits an
  overlong payload and opens either the payload-derived or the live stack view at
  any debug level
- **THEN** no row is labelled a chain link, gadget, argument or call target, and no
  row names return-oriented programming

#### Scenario: Misalignment before the system call is reported

- **WHEN** a learner's chain calls `system` but reaches it without the 16-byte stack
  alignment that call requires
- **THEN** the view reports that the call will fault on the alignment-sensitive
  instruction and tells the learner to add a bare `ret` to realign, and when the
  chain is correctly aligned it does not raise that warning

#### Scenario: The chain read-out summarizes the attempt

- **WHEN** a learner submits a chain and opens the chain read-out
- **THEN** it lists the chain step by step — each link's stack offset, value, resolved
  role, and effect — together with the alignment verdict, without the learner having
  to reconstruct it from the raw frame


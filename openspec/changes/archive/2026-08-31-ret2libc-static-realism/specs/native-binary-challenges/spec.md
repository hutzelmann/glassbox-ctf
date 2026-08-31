# native-binary-challenges Specification (delta)

## MODIFIED Requirements

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

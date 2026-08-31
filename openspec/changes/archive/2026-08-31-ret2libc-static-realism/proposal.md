## Why

The ret2libc binary reads as hand-patched rather than as a program a learner could
meet in the wild: a `pop rdi; ret` gadget is written in inline `__asm__`, a
`char *shell = "/bin/sh"` global exists only to keep the string in the binary, and
an `if (argc > 9) system(shell)` branch exists only to import `system`. Learner-facing
comments narrate the exploit ("The learner chains them into a small ROP payload"),
which is odd to read while solving. A learner cannot map the inline assembly to a
real-world program written entirely in C.

Modern glibc (2.41) makes this hard to avoid honestly: a normally-compiled *dynamic*
build of the same C contains **zero** `pop rdi; ret` gadgets and no `/bin/sh`
string, which is exactly why the author planted all three. A **statically linked**
build of the identical C yields all three naturally (108 `pop rdi; ret` byte
sequences, the `/bin/sh` string, a real `system` symbol) with NX still on and
addresses still fixed. That lets us delete every hand-patch and keep the exploit
identical.

## What Changes

- **Rebuild the ret2libc binary as realistic C.** Remove the inline `__asm__`
  gadget, the `char *shell` global, and the `argc > 9` branch. Compile the same
  overflow with `-static` so the gadget, the `/bin/sh` string, and `system` arise
  from the natural build. The exploit shape is unchanged (`pop rdi → &"/bin/sh" →
  system`), the offset stays 24, and NX stays on. **BREAKING** for anyone relying
  on the old fixed addresses / the `pop_rdi_ret` symbol.
- **Keep one honest `system()` reference** behind a plausible `selftest`
  subcommand (runs a fixed benign command) instead of the fake `argc > 9` branch.
- **Remove the 4th-wall / spoiler comments** from the learner-facing C source and
  trim the binary's printed banner to a terse, premise-only prompt.
- **Pin `-static` as a fixed author flag**, not a learner-editable one: extend the
  native family's `nbuild` to apply author-supplied trusted flags on every build in
  addition to the learner's allowlisted protection flags. The editable flags field
  stays protections-only, so a learner cannot accidentally un-static the binary.
- **Rename the flag to `flag.txt` and keep it out of the web root.** The spawned
  shell reads it as `cat flag.txt` from a private working directory the runner sets;
  apache never serves it. Requires an optional working-directory parameter on the
  shared payload runner (`nrun_run`), defaulting to today's behavior.
- **Add a gadget-address finder** to the native family so the Debug "ROP
  ingredients" panel can still show the `pop rdi; ret` address once the
  `pop_rdi_ret` symbol is gone (scan executable sections for the gadget bytes).
- **Trim the ret2libc front page** to ret2win weight (no `/flag`, no
  `system`/`"/bin/sh"` naming on the always-visible level-0 copy); move those
  details into the README tasks and the Hints/Debug panels. Update `README.md` and
  `solution.md` for `flag.txt`, static `system` (not `system@plt`), and the
  gadget-from-static-libc framing.

## Capabilities

### New Capabilities

<!-- none: all changes fall under the existing native-binary-challenges capability -->

### Modified Capabilities

- `native-binary-challenges`: the ret2libc rung's exploitation primitives now arise
  from the binary's natural (static) build rather than hand-authored assembly or
  synthetic globals; its flag is a `flag.txt` read via command execution from a
  private working directory and is not downloadable from the web layer; and a
  native challenge MAY pin fixed author build flags that always apply, outside and
  in addition to the learner-editable protection allowlist.

## Impact

- **Challenge** (`challenges/binary/ret2libc/`): `main.c`, `critical.c` (comments),
  `build.sh`, `Dockerfile`, `glassbox.php`, `index.php`, `README.md`, `solution.md`.
- **Platform** (`platform/native/`): `nbuild` (fixed author flags), `native-run.php`
  (`nrun_gadget_addr()`, optional `$cwd` on `nrun_run()`). Rebuilds the native base
  image; ret2win rebuilds with unchanged behavior.
- **Published artifact:** the ret2libc image grows (~19 KB → ~750 KB binary) and its
  fixed exploit addresses change; `system@plt` becomes the real libc `system`.
- **Out of scope:** info-leak / hands-on PIE+ASLR (the documented next rung),
  ret2syscall, and any change to ret2win or other challenges.

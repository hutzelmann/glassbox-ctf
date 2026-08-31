## Context

See proposal.md — Why. The vulnerable C is shared with ret2win and unchanged in
substance (`struct msg` overflow of `char buf[16]`, offset 24). What is arbitrary is
everything main.c does to *manufacture* the ROP ingredients. An empirical check on
the shipped toolchain (gcc 14.2, glibc 2.41, inside the native image) grounds the
approach:

| build of the identical C | `pop rdi; ret` (`5f c3`) in `.text` | `/bin/sh` string | `system` |
| --- | --- | --- | --- |
| dynamic (`-no-pie`) | **0** | absent | via PLT only |
| static (`-no-pie -static`) | **~106–108** | present | real symbol `0x4048xx` |

Static linking is therefore the only way to source all three from ordinary C on this
toolchain, without an info leak into libc.so. NX stays on (`GNU_STACK` = `RW`),
`-no-pie` keeps addresses fixed, and `vuln`'s frame stays `sub rsp,0x10` → offset 24.

The debug plumbing couples to the old shape in two spots only: `index.php` resolves
the gadget by the `pop_rdi_ret` symbol and lists it in the disassembly, and resolves
`system` by its `@plt` stub. `/bin/sh` is already found by a byte-scan
(`nrun_string_addr`) and needs no change.

## Goals / Non-Goals

**Goals:**
- ret2libc's binary reads as a plausible, fully-C program; the three ROP ingredients
  come from the natural static build.
- The exploit a learner writes is byte-for-byte the same technique and offset as
  before (`pad(24) → ret → pop rdi → &"/bin/sh" → system`, padded to 64, then the
  shell command).
- The protection lessons the README advertises (NX-off via `-Wl,-z,execstack`,
  canary via `-fstack-protector-all`) still work.
- `-static` cannot be removed by a learner through the flags field.
- The flag reads as `cat flag.txt` and is not downloadable from apache.

**Non-Goals:**
- Hands-on PIE/ASLR on this rung (info leak) or ret2syscall — the documented next
  steps, out of scope.
- Any change to ret2win's behavior or to other challenges.
- A general gadget search UI — only the single `pop rdi; ret` the panel already
  showed needs an address.

## Decisions

**Static link, not the alternatives.** Dynamic-plus-tidier-planting still needs
planted bytes (the table above shows why); a real libc-leak ret2libc adds an
info-leak stage the proposal explicitly excludes; static ret2syscall needs four
registers set and abandons the `system("/bin/sh")` framing the ladder is built on.
Static keeps the exact exploit shape while removing every hand-patch.

**Static *glibc*, not a smaller libc.** musl (31 KB, MIT) and dietlibc (24 KB,
GPLv2) were both measured with the identical C: both yield `system`, `/bin/sh`, and
at least one `pop rdi; ret` with NX on, so both *work*. They were rejected because
they do not meet the "simpler setup" bar and cost more than they save:

| | static glibc | musl | dietlibc |
| --- | --- | --- | --- |
| size | 772 KB | 31 KB | 24 KB |
| `pop rdi; ret` gadgets | ~106 | 4 | 1 (fragile across recompiles) |
| license | LGPL (ok) | MIT (ok) | **GPLv2 — conflicts with the MIT repo for a published binary** |
| toolchain | stock `gcc` | +`musl-tools`, dual-compiler | +`dietlibc-dev`, dual-compiler, niche |
| movaps alignment lesson | kept | likely lost | likely lost |

A smaller libc shrinks the *artifact*, not the *files or setup*: the `.c`/`.php`
sources are identical, while musl/dietlibc each add a toolchain dependency and force
`nbuild`/`lint.php` (shared with glibc-based ret2win) to select a per-challenge
compiler, and their minimal `system` almost certainly drops the 16-byte
stack-alignment (`movaps`) teaching beat. dietlibc additionally conflicts on license
(GPLv2 into a downloadable binary of an MIT project) and ships only one gadget. The
772 KB download is cosmetic; stock `gcc` static glibc is the simplest, license-clean
option that preserves the alignment lesson.

**`-static` is a fixed *author* flag, applied by `nbuild`, not by the editable
flags file.** `-static` is not a protection and removing it silently makes the
challenge unsolvable (no gadgets), so it must not sit in the learner's field.
`nbuild` gains a way to receive trusted author flags that are appended to the
learner's allowlisted flags without being allowlist-filtered (the author is
trusted; only the learner-editable file is untrusted input). `build.sh` passes
`-static` this way. Alternative — adding `-static` to the allowlist and to
`build.flags` — was rejected: it lets a learner brick the premise and muddies the
protections field. The editable `build.flags` stays `-fno-stack-protector -no-pie
-O0 -g`, unchanged.

**Gadget address by byte-scan of executable sections, not by symbol or aligned
disassembly.** There is no `pop_rdi_ret` symbol any more, and `pop rdi; ret`
gadgets in libc are overwhelmingly *unaligned* (an aligned `objdump` grep finds
none, a byte-scan finds ~106). A new `nrun_gadget_addr($bin, ...)` finds the first
`5f c3` inside an executable (`X`-flagged) section and maps its file offset to a
vaddr the same way `nrun_string_addr` maps strings. Because `5f` (pop rdi) and `c3`
(ret) are each complete single-byte instructions, any `5f c3` in executable memory
is a valid gadget, and the bare-`ret` address stays gadget+1 (the existing panel
math). Restricting to executable sections avoids returning a `5f c3` that lies in
rodata/data.

**`system` by symbol.** A static binary has no `system@plt`; `nm` lists `system`
(as a weak `W` symbol) at a fixed address. `index.php` switches from
`nrun_plt_addr($BIN,'system')` to `nrun_symbol_addr($BIN,'system')`, which matches
by exact name and returns it. Panel/README/solution wording changes `system@plt` →
`system` (which is more faithful to the name "ret2libc": the chain returns into
libc's real `system`).

**Flag placement: private dir + runner cwd.** To let the spawned shell run `cat
flag.txt` with a clean relative path while keeping apache from serving it, the flag
lives at a non-webroot path (e.g. `/var/challenge/flag.txt`) and the runner sets the
binary's working directory there. `nrun_run()` gains an optional `$cwd` (4th param,
`null` = today's inherited-cwd behavior, so ret2win is untouched) passed straight to
`proc_open`. `index.php` reads the same absolute path for success detection.

**Honest `system()` reference: a `selftest` subcommand.** `main.c` keeps one real
`system(...)` behind `if (argc > 1 && strcmp(argv[1],"selftest")==0)` running a
fixed benign command (e.g. `uname -srm`). This pulls `system` (and its embedded
`/bin/sh`) into the static link, reads as a plausible hidden maintenance feature,
is genuinely reachable (`./ret2libc selftest`) yet is not a shortcut to the flag,
and never fires on the web runner's no-arg invocation. The banner shrinks to a
terse premise (parallel to ret2win), and the 4th-wall comments go with the code
they annotated.

## Risks / Trade-offs

- **static-PIE does not reproduce ASLR** (gcc ignores `-pie` under `-static`, binary
  stays `EXEC`) → the PIE/ASLR angle stays conceptual, exactly as solution.md
  already frames it (the next rung). README advertises only NX-off + canary as
  hands-on toggles, both verified to still work. Mitigation: solution.md notes PIE
  is out of scope here; no advertised toggle is lost.
- **Binary grows ~19 KB → ~750 KB; ROPgadget output is large** → this is realistic
  for a static target and the learner greps it; the Debug panel still shows the one
  gadget address. No mitigation needed.
- **Addresses shift when a learner recompiles** (edits critical.c, or toggles
  protections) → unchanged from today; the panels recompute live and the learner
  works from a freshly downloaded binary. No regression.
- **Stale podman COPY layer can ship the old native-run.php / nbuild** → rebuild the
  native base with `--no-cache` after the platform edits (per AGENTS.md), then
  ret2libc.
- **Success detection reads the flag file server-side** → it must be readable by
  www-data and outside the docroot; both hold for `/var/challenge/flag.txt` (0644,
  not under `/var/www/html`).
- **Static libc breaks symbol-based canary detection** → a statically linked glibc
  always contains `__stack_chk_fail`, so `nrun_checksec` reported `Canary=Yes` for
  the shipped `-fno-stack-protector` build. Fixed by detecting the canary from the
  challenge's own instrumentation (the `fs:0x28` load in `main`/`vuln`) rather than
  symbol presence; this reads identically for dynamic ret2win, and also corrects the
  live gdb frame's canary slot. Discovered and fixed during implementation.
- **Static libc renames the `read` call, breaking the live capture** → the gdb frame
  dumper sets its breakpoints by finding vuln's `call <read@plt>`, but a static libc
  links it as `<__libc_read>`, so breakpoint detection failed and the Debug stack view
  silently fell back to the payload-derived `··` model instead of the real frame.
  Fixed by matching the libc read aliases; dynamic ret2win is unaffected. Discovered
  post-implementation.

## Migration Plan

1. Land platform edits (`nbuild` fixed-flags, `native-run.php` `nrun_gadget_addr` +
   `nrun_run` `$cwd`), then the ret2libc challenge edits and docs.
2. Rebuild locally: `podman build --no-cache -t glassbox-native ./platform/native/`,
   then `podman build -t glassbox-ret2libc ./challenges/binary/ret2libc/`.
3. Verify by hand (no test suite): the shipped chain gets the flag; `checksec` shows
   NX; `-Wl,-z,execstack` flips NX off; `-fstack-protector-all` aborts the chain
   (signal 6); the Debug "ROP ingredients" panel shows `system`, `/bin/sh`, and the
   gadget; `flag.txt` is not served at its URL; a bounding fix defeats the chain.
4. Rollback: revert the change and rebuild the native base + ret2libc; ret2win is
   unaffected throughout.

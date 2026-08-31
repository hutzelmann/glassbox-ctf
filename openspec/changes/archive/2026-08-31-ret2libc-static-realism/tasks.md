## 1. Platform: native family helpers

- [x] 1.1 `platform/native/nbuild`: accept trusted author-fixed flags (e.g. via a
  new arg or an `--` separator) appended to the learner's allowlisted flags without
  allowlist filtering; keep the atomic temp-compile-and-swap behavior. Document that
  author flags are trusted and learner flags stay filtered.
- [x] 1.2 `platform/native/native-run.php`: add `nrun_gadget_addr(string $bin,
  string $bytes = "\x5f\xc3"): ?string` that scans executable (`X`-flagged) sections
  for the byte sequence and maps the file offset to a vaddr (mirror
  `nrun_string_addr`'s section-header mapping; restrict to executable sections).
- [x] 1.3 `platform/native/native-run.php`: add an optional `$cwd = null` param to
  `nrun_run()` and pass it to `proc_open` (null preserves today's inherited cwd, so
  ret2win is unaffected).
- [x] 1.4 `platform/native/native-run.php`: fix `nrun_checksec` canary detection for
  static binaries — a static libc always carries `__stack_chk_fail`, so detect the
  challenge's own instrumentation (the `fs:0x28` canary load in `main`/`vuln`) instead
  of that symbol's presence; dynamic ret2win reads identically. (Discovered during
  5.3: the static switch made checksec always report `Canary=Yes`.)
- [x] 1.5 `platform/native/native-run.php`: fix the live gdb stack capture for static
  binaries — `nrun_vuln_bp_addrs` matched only `<read@plt>`, but a static libc links
  the call as `<__libc_read>`/`<__read>`, so breakpoint detection failed and the Debug
  stack view fell back to the payload-derived model (only `··` for the real
  buffer/RBP). Broaden the read-call match to the libc aliases; dynamic ret2win still
  matches `<read@plt>`. (Discovered post-implementation: static ret2libc showed no real
  bytes at Debug.)

## 2. Challenge: ret2libc binary source

- [x] 2.1 `challenges/binary/ret2libc/main.c`: remove the inline `__asm__`
  `pop_rdi_ret` gadget, the `char *shell` global, the `if (argc > 9) system(shell)`
  branch, and all 4th-wall/spoiler comments.
- [x] 2.2 `main.c`: add the honest `system()` reference behind a `selftest`
  subcommand (`if (argc > 1 && strcmp(argv[1],"selftest")==0) return
  system("uname -srm");`), include `<string.h>`, and shrink the printed banner to a
  terse premise (parallel to ret2win, no naming of system/`"/bin/sh"`/the recipe).
- [x] 2.3 `challenges/binary/ret2libc/critical.c`: confirm it stays the clean,
  learner-facing snippet (no spoiler comments); no functional change.
- [x] 2.4 `challenges/binary/ret2libc/build.sh`: pass `-static` to `nbuild` as a
  fixed author flag (using the mechanism from 1.1); leave `build.flags` as the
  protections-only `-fno-stack-protector -no-pie -O0 -g`.

## 3. Challenge: page and container

- [x] 3.1 `challenges/binary/ret2libc/index.php`: resolve `system` via
  `nrun_symbol_addr($BIN,'system')` and the gadget via `nrun_gadget_addr($BIN)`;
  drop `pop_rdi_ret` from the disassembly symbol list; keep bare-`ret` = gadget+1.
- [x] 3.2 `index.php`: run the payload with cwd at the private flag dir
  (`nrun_run($BIN,$payload,3,'/var/challenge')`) and read the flag for success
  detection from that absolute path (`/var/challenge/flag.txt`).
- [x] 3.3 `index.php`: trim the front copy to ret2win weight — short premise-only
  subtitle, one intro sentence, Download link, form; remove the `/flag` mention and
  the `system`/`"/bin/sh"`/NX exposition from level-0 (they live in README + Debug).
  Reword the "ROP ingredients" panel `system@plt` → `system`.
- [x] 3.4 `challenges/binary/ret2libc/Dockerfile`: write the flag as
  `flag.txt` into a private dir outside the web root (e.g.
  `/var/challenge/flag.txt`, `0644`), instead of `/flag`; keep the build via
  `build.sh`; update the flag comment.
- [x] 3.5 `challenges/binary/ret2libc/glassbox.php`: confirm the compiler-flags field
  label still matches (protections only); no `-static` mention (it is author-fixed).

## 4. Challenge: docs

- [x] 4.1 `challenges/binary/ret2libc/README.md`: task (d) reads `cat flag.txt`;
  reflect static `system` (not `system@plt`) and gadget-from-static-libc; keep NX +
  canary as the advertised hands-on toggles; PIE/ASLR stays the conceptual next rung.
- [x] 4.2 `challenges/binary/ret2libc/solution.md`: update ingredients table and
  prose (`system` not `system@plt`, `/bin/sh` and gadget from static libc), the
  `cat flag.txt` step, the pwntools snippet, and a note that PIE is not toggleable
  here (info-leak is the next rung).

## 5. Build and verify (manual; no test suite)

- [x] 5.1 Rebuild the base: `podman build --no-cache -t glassbox-native
  ./platform/native/`, then `podman build -t glassbox-ret2libc
  ./challenges/binary/ret2libc/`.
- [x] 5.2 Confirm the shipped chain gets the flag end-to-end via the page; `checksec`
  shows NX enabled; the Debug "ROP ingredients" panel shows `system`, `/bin/sh`, and
  the gadget address; `flag.txt` is NOT downloadable at its URL.
- [x] 5.3 Confirm the protection lessons: `-Wl,-z,execstack` flips NX off in
  `checksec`; `-fstack-protector-all` makes the chain abort (signal 6); a bounding
  fix in `critical.c` + Save defeats the chain and a broken edit keeps the last-good
  binary.
- [x] 5.4 Confirm ret2win still builds and behaves unchanged after the native-base
  rebuild.
- [x] 5.5 `openspec validate ret2libc-static-realism --strict` passes.

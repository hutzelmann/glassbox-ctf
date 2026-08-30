## 1. Native platform family (`platform/native/`)

- [x] 1.1 `editor/`: `package.json` (add `@codemirror/lang-cpp`, esbuild), `cm-init.js` (C-mode editable → `codemirror-bundle.js`), `cm-c-view.js` (read-only C view → `codemirror-c-view.js`), `linters.js` (client `lang-cpp` tree linter)
- [x] 1.2 `lint.php`: run `gcc -fsyntax-only` on the assembled unit (main.c `#include`ing the posted snippet), map errors to `critical.c` lines via `#line`, return whitelisted diagnostics as JSON
- [x] 1.3 `crash-reporter.c`: ptrace helper — exec the target, pipe the payload to stdin, on `SIGSEGV` print `RIP`/`RSP`/`RBP` + faulting address as JSON; degrade cleanly when register capture is unavailable
- [x] 1.4 `native-run.php`: shared helpers — run-binary-with-payload (`proc_open` + `timeout`/`ulimit`, capture stdout/stderr/terminating signal), decode hex/escape payload, hexdump, invoke the crash-reporter, `objdump`/`nm`/`checksec`, render the payload-driven stack-frame table (`<mark>` on attacker bytes)
- [x] 1.5 `Dockerfile` `FROM ${BASE_IMAGE:-glassbox-harness}`: node stage esbuilds the C bundles; install `gcc` + binutils; fetch `checksec`; compile `crash-reporter`; copy bundles + `lint.php` + `native-run.php`; no gdb
- [x] 1.6 Build locally `glassbox-harness` then `glassbox-native`; confirm both succeed

## 2. `fix.php` generalization (`platform/harness/`)

- [x] 2.1 Generalize `fix.php`: `require` an optional `glassbox.php` (`target`, `build`, `fields`); default to `critical.php`, no hook, no extra fields; render the target editor plus each declared extra field; Save writes them all and runs the build hook if declared; on hook failure surface its stderr as editor diagnostics (no swap); Restore resets target + fields to `.orig`/defaults and re-runs the hook
- [x] 2.2 Backwards-compat check: rebuild `glassbox-php` + one PHP challenge (e.g. `sqli-login`); confirm the Fix editor Save/Restore and the challenge behave exactly as before

## 3. `challenges/binary/ret2win/`

- [x] 3.1 `main.c` (fixed `main()` + `win()` printing a fictional flag; `#line 1 "critical.c"` + `#include "critical.c"`), `critical.c` (`read(0, buf, <cap>)` into `char buf[16]`), `glassbox.php` (target `critical.c`, build `build.sh`, field `build.flags` labelled "Compiler flags"), `build.flags` (`-fno-stack-protector -no-pie -O0 -g`)
- [x] 3.2 `build.sh`: tokenize `build.flags`, keep only the allowlist (stack-protector / PIE / NX / `-O*` / `-g`) noting dropped tokens, invoke gcc as an argv array with hook-owned `-o`/input/arch to a temp path, atomic `mv` on success, print gcc errors + exit non-zero on failure, `flock`-serialized
- [x] 3.3 `index.php` (thin): challenge framing + payload form (synced escape/hex fields + upload, hex as the wire value) + Run via `native-run.php` (stdout + flag check) + binary download link + Fix/debug controls; debug shows disasm, symbols + `win()` address, `checksec`, received-bytes hexdump, crash report, and the stack table; guidance only under `?debug=1`
- [x] 3.4 `Dockerfile` `FROM ${BASE_IMAGE:-glassbox-native}`: `COPY *.php *.c build.sh build.flags`; snapshot `critical.c` → `critical.orig.c`; build the initial binary; `LABEL org.glassbox.platforms="linux/amd64"`; `chown -R www-data`; start Apache
- [x] 3.5 `README.md` (tasks a–f + a pwntools scripting task + a stretch "beyond win()" note; no spoilers) and `solution.md` (walkthrough, flag, the `sizeof buf` fix, the flags-hardening lane, and a "Professional tools" section)

## 4. `challenges/binary/ret2libc/`

- [x] 4.1 `main.c` (references `system` so `system@plt` exists; a `"/bin/sh"` string in read-only data; ensure a `pop rdi; ret` gadget is present — add one explicitly if the linker yields none; no `win()`), `critical.c` (`read` into `buf[16]` with a cap that fits the ~48-byte chain and leaves the rest of stdin), `glassbox.php`, `build.flags` (NX on: `-fno-stack-protector -no-pie -O0 -g`, executable stack NOT set)
- [x] 4.2 `build.sh` (reuse the family pattern; verify the gadget/PLT survive the build), `Dockerfile` (as ret2win; create the `/flag` file readable only via code-exec; `LABEL … linux/amd64`), `index.php` (thin; debug additionally lists PLT/GOT + candidate gadgets)
- [x] 4.3 `README.md` (ladder position after ret2win; tasks: leak-free ROP to `system("/bin/sh")`, read `/flag`, fix, defenses) and `solution.md` (the ROP chain, the leftover-stdin `cat /flag` demonstration, and a "Professional tools" section featuring `ROPgadget` + pwntools `ROP()`)

## 5. CI (`.github/workflows/docker-publish.yml`)

- [x] 5.1 `base` job: add a `native` step building `FROM` the published `harness` and pushing `glassbox-ctf-native`, after the `php` step
- [x] 5.2 `discover` job: parse `LABEL org.glassbox.platforms` per challenge Dockerfile; pass it to the challenge build's `platforms`, defaulting to `linux/amd64,linux/arm64` when absent
- [x] 5.3 Confirm `ret2win`/`ret2libc` resolve the `native` family base and publish `linux/amd64` only, while other challenges still publish both arches

## 6. Repository documentation

- [x] 6.1 `AGENTS.md`: document the `native` family + `binary` domain, the generalized glass-box contract (`glassbox.php` config, recompile-on-Save, the compiler-flags lane), and the binary `?debug=1` internals; update the layout tree and the base-chain build order
- [x] 6.2 Top `README.md`: add a "Binary exploitation" domain to the catalog (ladder `ret2win` → `ret2libc`) and a quick-start note (amd64-only image, identical command via emulation on arm64)
- [x] 6.3 `.gitignore`: ignore the native build outputs (C bundles, fetched `checksec`, the compiled `crash-reporter`) and each challenge's built binary + `critical.orig.c`
- [x] 6.4 `TODOs.md`: move the native family + `ret2win`/`ret2libc` from "in flight" to done; note the deferred harder rung

## 7. Verify and archive

- [x] 7.1 `openspec validate add-binary-family --strict` passes
- [x] 7.2 Local smoke (podman): build harness → native → `ret2win`, run and solve it; live-patch `critical.c` and confirm the exploit then fails; save a non-compiling edit and confirm the container stays up with errors shown; enable the stack protector via the flags field and confirm ret2win fails with no source change; build/run/solve `ret2libc` (leftover-stdin `cat /flag`); grep both built images to confirm no `*.md` shipped; rebuild a PHP challenge to confirm it still works
- [ ] 7.3 Review the full diff; then archive with `openspec archive add-binary-family`

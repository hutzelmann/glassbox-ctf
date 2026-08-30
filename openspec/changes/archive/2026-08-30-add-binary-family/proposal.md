## Why

The repo restructure deliberately left the door open for a non-web domain, naming
a binary/`ret2win` family as the imminent next step. Today's glass-box contract
(`critical.php` + Fix + `?debug=1`, PHP-only, no build step) cannot host a
compiled-language challenge: the learner's fix must be **recompiled** to take
effect, and "debug internals" here mean disassembly, symbols, addresses, and a
crash — not SQL and rows. This change adds the `native` platform family and the
first binary ladder (`ret2win` → `ret2libc`), and generalizes the glass-box
editing contract just enough to support compiled targets — the piece the
restructure explicitly deferred to "the ret2win change."

## What Changes

- Add **`platform/native/`** (`FROM harness`), a new base family: native `gcc` +
  binutils + `checksec` + a compiled ptrace crash-reporter; a C-mode CodeMirror
  bundle + read-only C view; a native `lint.php` (`gcc -fsyntax-only`); and
  shared PHP runner/debug helpers. Published `glassbox-ctf-native`.
- Add a new **`binary`** challenge domain with a two-rung ladder:
  - `challenges/binary/ret2win` — stack buffer overflow → overwrite the saved
    return address → redirect execution to a `win()` that prints a fictional flag.
  - `challenges/binary/ret2libc` — a minimal, deterministic ROP chain
    (`pop rdi; ret` → `&"/bin/sh"` → `system@plt`, all fixed no-PIE addresses) →
    arbitrary command execution; the flag is a `/flag` file readable only via
    code execution. Motivates ROP by shipping with NX **on**.
- **Generalize `fix.php`** from a hardcoded `critical.php` target to a
  per-challenge `glassbox.php` config (target filename, optional Save-hook,
  optional extra editable fields). Backwards-compatible: an absent config means
  today's PHP behavior, so every existing challenge is untouched.
- Add **recompile-on-Save**: Save runs a per-challenge build hook that compiles to
  a temp path and **atomically swaps** the live binary only on success; a failed
  compile keeps the last-good binary and surfaces `gcc` errors as editor
  diagnostics — the container never bricks. Restore reverts source **and** flags
  and rebuilds.
- Add an **editable compiler-flags surface** (curated allowlist: stack-protector /
  PIE / NX / `-O*` / `-g`) so learners can flip protections and watch the *same*
  exploit succeed or fail without touching the source — a second remediation lane
  beside the source fix. `checksec` in debug reflects each change.
- Add a **browser payload runner**: the vulnerable binary runs server-side on
  learner-submitted bytes (two live-synced escape/hex fields, plus upload); the
  binary is also downloadable for local pwntools.
- Extend **`?debug=1`** for binary bugs: objdump disasm, `nm` symbols + the
  `win()` address, `checksec` (reflecting current flags), a received-bytes
  hexdump, the ptrace crash report (proving RIP control), and a **dynamic
  payload-driven stack-frame table**; PLT/GOT + gadgets for `ret2libc`.
- **CI**: add a `native` step to the base chain (harness → php, native), and a
  per-challenge publish-platform override (default amd64+arm64; the binary rungs
  publish **amd64-only** so x86-64 addresses match the walkthrough — arm64 hosts
  run the same simple command under emulation).
- **Docs**: each rung ships a spoiler-free `README.md` + a spoiler `solution.md`
  (manual walkthrough first, then a "Professional tools" section: pwntools,
  `gdb`+pwndbg/gef, `checksec`, `objdump`/`nm`, `ROPgadget`).

## Capabilities

### New Capabilities
- `native-binary-challenges`: the behavior of the native family and its
  challenges — the browser payload-runner delivery model, recompile-on-Save
  (atomic swap, non-bricking, surfaced compile errors), the editable
  compiler-flags lane (allowlisted), the binary `?debug=1` introspection (disasm,
  symbols, `checksec`, ptrace RIP, dynamic stack table), and the `ret2win` /
  `ret2libc` learning contract.

### Modified Capabilities
- `challenge-structure`: add the `binary` domain and the `native` family to the
  platform chain; generalize the glass-box editing contract so a challenge MAY
  declare a per-challenge config (target `critical.<ext>`, a Save-hook, extra
  editable fields) and so Save MAY trigger a rebuild whose failure never bricks
  the challenge and whose result replaces the running artifact only atomically.
- `build-and-publish`: add `native` to the base-chain build order (after `php`),
  and allow a challenge to declare a restricted publish-platform set — the
  default remains `linux/amd64` + `linux/arm64`.

## Impact

- **New files**: `platform/native/` (`Dockerfile`, `editor/`, `lint.php`,
  `native-run.php` helpers, the ptrace reporter source, the `checksec` fetch);
  `challenges/binary/ret2win/` and `challenges/binary/ret2libc/` (`Dockerfile`,
  `main.c`, `critical.c`, `glassbox.php`, `build.sh`, `index.php`, `README.md`,
  `solution.md`).
- **Modified files**: `platform/harness/fix.php` (config-driven target + Save-hook
  + extra fields; backwards-compatible); `.github/workflows/docker-publish.yml`
  (native base step + platform-override parsing); `.gitignore` (native bundles);
  `AGENTS.md` (native family, `binary` domain, the generalized contract + config
  file); top `README.md` catalog (new domain); `TODOs.md`.
- **Unchanged contracts**: existing PHP challenges and the `php` family (fix.php
  stays backwards-compatible); the `challenge-docs` and `contribution-workflow`
  specs (binary challenges simply follow them).
- **External contract**: three new published images — `glassbox-ctf-native`
  (base), `glassbox-ctf-ret2win`, `glassbox-ctf-ret2libc`; the two rungs are
  published `linux/amd64` only (arm64 hosts run them under transparent emulation).
- **Threat model**: as with the PHP challenges (arbitrary PHP via Fix), the native
  family compiles and runs learner-supplied C as `www-data` in a throwaway,
  fully-offline container. Runs are sandboxed (timeout + resource limits) and the
  intended exploit only prints a flag or spawns an in-container shell — consistent
  with the existing model. No new runtime cloud dependencies.

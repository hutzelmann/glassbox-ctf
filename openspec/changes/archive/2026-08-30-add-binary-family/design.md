## Context

See proposal.md — Why. The restructure built the `harness → php` chain and wrote
the glass-box contract as `critical.<ext>` on purpose, but `fix.php` still
hardcodes `critical.php` and there is no build step: a PHP snippet takes effect
the moment it is written. A compiled target breaks both assumptions — the fix
must be recompiled, and a bad edit must not leave the container serving a broken
or missing binary. The web front stays PHP/Apache (the whole delivery skeleton
lives in the harness); only the *toolchain, editor language, and "debug internals"*
differ, which is exactly what a new family layer is for. All decisions below were
settled in a design interview; this records them with their rationale.

## Goals / Non-Goals

**Goals:**
- One `native` family (`FROM harness`) that any future binary challenge reuses.
- A recompile-on-Save mechanism that is immediate, gives compile feedback, and can
  never brick the container.
- A browser runner that is a faithful "victim" (watch the overflow land) while the
  same binary is downloadable for real tooling.
- One simple `podman run` command that works on every host.
- Two rungs (`ret2win`, `ret2libc`) that share all of the above.

**Non-Goals:**
- Defeating ASLR, info-leak primitives, or a canary-leak rung — a later change.
- Running the binary as a network service (no open TCP port; Apache is the only
  service). Local `remote()`-style play is covered by the downloadable binary +
  an optional `socat` note in `solution.md`.
- 32-bit and aarch64 walkthroughs — the shipped binary is x86-64 (see D3).

## Decisions

### D1 — Delivery: browser runner (victim) + downloadable binary
The challenge page accepts a byte payload and runs the vulnerable binary
server-side on it (bytes on stdin), showing output, a success/flag check, and (in
debug) the internals. The same binary is downloadable for local pwntools
(`process()`). Rejected: a raw TCP/`nc` service (adds a second published port and
breaks the one-Apache model) and download-only (loses the "watch it at the victim"
glass box).

### D2 — Byte input: two synced fields, upload fills them, guidance behind debug
An escape-sequence textarea (`\xNN`, `\n`, literal ASCII) and a hex textarea are
kept in sync client-side; an optional upload decodes into both. **Hex is the
canonical wire value** the form POSTs (trivial, unambiguous server decode); the
escape view is the convenience editor. The offset/address guidance and the stack
view appear only with `?debug=1`, matching the "debug reveals internals" pattern.
Rationale: escapes give continuity with the pwntools notation the course teaches;
hex maps 1:1 to the stack-cell table; syncing keeps both without a server round
trip.

### D3 — Target ISA: x86-64, published amd64-only, one command everywhere
The binary is compiled inside the container, so on an arm64 host gcc would emit
aarch64 with different addresses than an x86-64 walkthrough. To keep a single
deterministic binary that matches `solution.md` and the lecture, the two rungs are
published **`linux/amd64` only** (declared via a Docker `LABEL`, see D13). The run
command is identical on every host: native on x86-64 (all CI/cloud/x86 labs),
automatic emulation on Apple-Silicon Docker Desktop / podman machine, and a
one-line `qemu-user-static` install on the rare bare-arm64-Linux host (documented).
The debug view is computed live from the real binary, so disasm/symbols/checksec/
stack table are always correct; only the ptrace RIP readout is best-effort under
emulation (D10). Rejected: multi-arch with a bundled x86-64 cross-gcc + qemu-user
(fat arm64 image, complex family, ptrace degraded anyway) and native-multi-arch
self-adapting (Apple-Silicon students get an aarch64 binary → one lecture no longer
fits everyone — bad for a course).

### D4 — Vulnerable primitive: a small unbounded `read`
`critical.c` is `read(0, buf, <cap>)` into `char buf[16]`, where `<cap>` exceeds
the distance to the saved return address (offset 24 on x86-64: 16 buffer + 8 saved
RBP). The fix is a one-token change: `read(0, buf, sizeof buf)`. `read` is chosen
over `gets`/`fgets`/`scanf` because it imposes no delimiter, so a payload may
contain NUL/newline (arbitrary little-endian addresses), and because classic
`gets` is removed from modern glibc (Debian's 2.36) and will not even link.
Buffers stay tiny — no 64-byte filler — so payloads are short and the stack table
is legible.

### D5 — File split: `#include "critical.c"` with a `#line` directive
A fixed, uneditable `main.c` defines `main()` and `win()` (and, for `ret2libc`, the
`"/bin/sh"` string and a `system` reference), then does `#line 1 "critical.c"`
followed by `#include "critical.c"`. This mirrors the PHP `require 'critical.php'`
pattern exactly and keeps everything the learner must not touch outside the
editable file. The `#line` directive resets gcc's line counting so compile errors
report correct `critical.c:N` locations for both the Save-time build and the live
`gcc -fsyntax-only` linter. Rejected: separate linked TUs (cleaner C hygiene but a
two-file compile and a required prototype) and one whole-program file (lets the
learner delete `win()` — violates the contract).

### D6 — Recompile-on-Save: temp build, atomic swap, last-good, `flock` (the crux)
Save writes the source and the flags, then runs a per-challenge `build.sh` that
compiles to a **temp path**; on success it `mv`s (atomic rename) over the live
binary; on failure it makes no swap and prints gcc's stderr, which `fix.php`
surfaces as editor diagnostics (like the PHP linter). The runner always executes
the current live binary, so a broken edit simply leaves the previous working
binary in place — the container never bricks. No lock is needed: each build
compiles to its own `mktemp` file and the atomic rename makes concurrent rebuilds
last-writer-wins with no half-written binary ever visible. `critical.orig.c`, the
default flags, and the shipped `ret2win`/`ret2libc` binary are the Restore floor
(Restore resets source **and** flags, then rebuilds).
Rejected: request-time lazy rebuild (feedback at run time, muddier), a watcher
daemon (extra process, lag), and prebuilt-variants-only (kills live editing).

### D7 — Compiler flags: editable field, curated allowlist, argv-invoked
A second editable field in the Fix editor holds the gcc flag string, backed by a
`build.flags` file the hook reads (default `-fno-stack-protector -no-pie -O0 -g`).
`build.sh` tokenizes it and accepts only an allowlist (stack-protector variants,
PIE/`no-pie`, `execstack`/`noexecstack`, `-O0..-O3`/`-Os`, `-g`); unknown tokens
are dropped with a visible note. gcc is invoked as an **argv array** (never a shell
string) and the hook always owns `-o <output>`, the input, and the arch — so no
value in the field can shell-inject, redirect output, swap the input, or
cross-compile. Optimization flags are intentionally allowed (a legit thing to try);
if `-O3` shifts the offset, the glass box shows the new one. `checksec` in debug
reflects the current flags. Rejected: mitigation toggles (hide the real gcc line)
and free-form-with-denylist (invites off-topic breakage).

### D8 — `fix.php` generalization: a small `glassbox.php` config
`fix.php` `require`s an optional per-challenge `glassbox.php` returning
`['target' => 'critical.c', 'build' => 'build.sh', 'fields' => [['file' =>
'build.flags', 'label' => 'Compiler flags']]]`. Absent → `target = critical.php`,
no build hook, no extra fields → existing PHP challenges are byte-for-byte
unchanged. The editor form renders the target plus each declared extra field;
Save writes them all, runs the build hook if declared, and shows its errors on
failure; Restore resets the target and every field to their `.orig`/default and
re-runs the hook. The editor bundle (`codemirror-bundle.js`) and lint endpoint
(`lint.php`) are still referenced by name and supplied by whichever family sits
below — so `fix.php` never picks a language. Rejected: a JSON config (needs a
decode + malformed guard in a PHP app) and pure convention (hardcodes labels).

### D9 — `native` family composition
`platform/native/` (`FROM harness`) adds, all fetched/built at image-build time:
native `gcc` + binutils (objdump/nm/readelf/strings); a `checksec`-style report
**computed from readelf/nm** in `native-run.php` (no external script — leaner and
fully offline); a compiled **ptrace crash-reporter** (D10); a C-mode CodeMirror
bundle from `editor/cm-*.js`
(`@codemirror/lang-cpp`) esbuilt to `codemirror-bundle.js`, plus a read-only C view
for showing the uneditable `main.c` context in debug; a native `lint.php` running
`gcc -fsyntax-only` on the assembled unit (`#line`-mapped) layered with a client
`lang-cpp` tree linter; and `native-run.php` shared helpers (D14). No gdb in the
image (D10).

### D10 — Crash introspection: a ptrace reporter, not gdb
A ~60-line C helper (compiled at image build) runs the target under `ptrace`, pipes
in the payload, and on `SIGSEGV` prints RIP/RSP/RBP + the faulting address as JSON,
so debug can show `RIP=0x4141414141414141` — the proof of return-address control.
This keeps gdb out of the runtime image and stays fully offline. It needs a real
x86-64 kernel; under arm64 emulation cross-process register capture may be
unavailable, so the reporter is **best-effort** — the run still reports the
terminating signal and everything else works; only the live RIP number degrades.
Rejected: gdb batch mode (pulls ~50MB and is what the learner uses locally anyway)
and signal-only (loses the RIP reveal).

### D11 — Stack visualization: a dynamic, payload-driven HTML table
In the debug runner output, a semantic `<table>` renders the stack as 8-byte words
— columns `[offset, hex, ASCII, region: buf / saved-RBP / return-address]` — built
server-side from the actual submitted bytes, with attacker-controlled bytes wrapped
in `<mark>` (the same highlight the SQLi debug view uses) and the return-address row
annotated with the real RIP from D10. Pure pico + `<mark>`, the simplest HTML layer,
no custom CSS. A static baseline diagram goes in the README/solution for first
orientation. Rejected: an SVG diagram (more code, past the simplest layer) and a
static-only view (never reflects the learner's own payload).

### D12 — Rung 2: `ret2libc` via PLT, one gadget, deterministic
`ret2libc`'s `main.c` references `system` (so `system@plt` exists at a fixed
no-PIE address) and holds a `"/bin/sh"` string in read-only data; a `pop rdi; ret`
gadget is present (verified with objdump/ROPgadget, added explicitly if the linker
does not yield one). The intended chain is `padding → pop rdi; ret → &"/bin/sh" →
system@plt` — no libc leak, no ASLR dependency, fully deterministic. NX is **on**
by default here, which is exactly why shellcode-on-stack is not the path (the
teachable contrast with rung 1). Command execution is demonstrated in the browser:
the vulnerable `read` consumes only its capped bytes, so payload bytes after the
chain remain on stdin for the spawned `/bin/sh` to execute (e.g. append
`cat /flag`). The flag is a `/flag` file no function prints, readable only via that
code execution. Debug additionally lists the PLT/GOT and candidate gadgets. Movaps
16-byte alignment is handled by keeping the chain aligned or adding a `ret` gadget.

### D13 — CI: a `native` base step + a platforms `LABEL`
The `base` job gains a `native` step (`build-args BASE_IMAGE=…-harness`) after
`php`; both families are siblings `FROM harness`. The `discover` job additionally
greps each challenge Dockerfile for `LABEL org.glassbox.platforms="…"` (like it
greps `ARG BASE_IMAGE`) and passes it to the challenge build's `platforms`,
defaulting to `linux/amd64,linux/arm64` when absent. `ret2win`/`ret2libc` set
`linux/amd64`. Rejected: platforms in `glassbox.php` (CI would parse PHP; mixes
runtime and build concerns) and a separate metadata file (a new file type to scan).

### D14 — Shared runner code in the family, thin per-challenge `index.php`
`platform/native/native-run.php` holds the reusable pieces: run-binary-with-payload
(`proc_open` + `timeout`/`ulimit`, capture stdout/stderr/signal), decode hex/escape,
hexdump, invoke the ptrace reporter, objdump/nm/checksec, and render the stack
table. Each challenge's `index.php` stays thin — its framing + the payload form +
calls into the helpers + any challenge-specific debug bits (e.g. rung 2's PLT/GOT).
Mirrors how the php family centralizes `lint.php`. Rejected: duplicating the runner
per challenge (drifts) and a single family-owned runner page (no per-challenge
framing).

### D15 — Docs and sandboxing
Each rung ships a spoiler-free `README.md` (tasks a–f: overflow → offset → reach
`win()` → source fix → compiler-flags mitigation lane → defenses; plus a pwntools
scripting task and a stretch "beyond win()" pointer) and a spoiler `solution.md`
(manual walkthrough first, then "Professional tools": pwntools `cyclic`/`p64`/
`ROP()`/`process()`, gdb + pwndbg/gef, `checksec`, `objdump`/`nm`, `ROPgadget`).
Neither `.md` is copied into the image (explicit `COPY` globs). Every run is bounded
by `timeout` + `ulimit`; the family compiles and runs learner C as `www-data` in a
throwaway offline container — the same threat class as arbitrary PHP via Fix — and
the intended exploit only prints a flag or spawns an in-container shell reading the
leftover stdin.

## Risks / Trade-offs

- **ptrace RIP under emulation** → best-effort: fall back to signal-only when
  register capture is unavailable; full fidelity on native x86-64 (CI, cloud, x86
  labs). The challenge is fully solvable either way.
- **Bare arm64 Linux needs qemu-binfmt for an amd64 image** → documented one-line
  `qemu-user-static` install; Apple-Silicon Docker Desktop / podman machine do it
  automatically; the run command itself is unchanged.
- **Arbitrary flags / arbitrary C run as www-data** → argv invocation (no shell),
  hook-owned `-o`/input/arch, an allowlist, and a throwaway offline container;
  atomic-swap + last-good + Restore contain any breakage. No new privilege beyond
  the existing "arbitrary PHP via Fix" model.
- **`-O3`/odd flags shift the taught offset** → the glass box shows the live
  offset and RIP; `solution.md` documents the `-O0` default.
- **`ret2libc` chain must fit the read distance** → size `buf`/read cap so the
  ~48-byte chain fits and the remainder of stdin feeds the shell; handle movaps
  alignment (aligned chain or a `ret` gadget).
- **`system("/bin/sh")` has no interactive TTY** → prove command execution via the
  leftover-stdin trick (`cat /flag`), not an interactive shell.
- **`fix.php` generalization could disturb PHP challenges** → the absent-config
  path is byte-identical to today's behavior; verification rebuilds and clicks
  through a PHP challenge unchanged.
- **Native image is larger than php** → accepted; still lean (no gdb; checksec is a
  single script; the ptrace helper is tiny).

## Migration Plan

1. Build `platform/native/` and generalize `platform/harness/fix.php` (config
   read + Save-hook + extra fields), keeping the absent-config default.
2. Build `challenges/binary/ret2win/`, then `challenges/binary/ret2libc/` on top of
   the family.
3. Rewrite the CI `base` job (add native) and `discover` (parse the platforms
   `LABEL`); add the `LABEL` to both rungs.
4. Docs: per-rung `README.md` + `solution.md`; update `AGENTS.md` (native family,
   `binary` domain, the generalized contract, `glassbox.php`), the top `README.md`
   catalog, `.gitignore`, `TODOs.md`.
5. Verify locally with podman: build harness → native → ret2win; solve it; patch
   `critical.c` live and confirm the exploit then fails; break the compile and
   confirm the container stays up; enable the stack protector via flags and confirm
   ret2win fails without a source change; repeat for ret2libc; grep a built image to
   confirm no `*.md` shipped and the PHP challenges still build unchanged.

Rollback: the change is one reviewable branch; revert it. Existing images are
untouched (fix.php stays backwards-compatible), so nothing already published breaks.

## Open Questions

- Exact fictional flag strings for each rung (cosmetic; chosen at implementation
  time to match the repo's existing playful style, e.g. `H3ll0W0rld`).
- Whether a third, harder rung (canary/PIE leak, ret2syscall) follows — explicitly
  out of scope here; the family is designed to host it later.

## Context

See proposal.md (Why). Two facts about the current code shape the approach:

- The binary rungs pin `LABEL org.glassbox.platforms="linux/amd64"`, so only an amd64
  image is published; arm64 hosts get no native image.
- `platform/native` is already **arch-honest by derivation**: `checksec`, `objdump`,
  `nm`, DWARF buffer size, symbol/gadget addresses, and the payload-derived stack table
  all read the ELF or the payload, never a hardcoded address. The one piece that needs
  a live process is the Debug before/after frame (`gdb-dump.py` via `native-run.php`),
  and it already degrades to the payload-derived model when it cannot run.

The subtlety that dominates this design: **Save recompiles with the container's
`gcc`.** On an arm64 container, a native `gcc` would emit an aarch64 binary, which
would change every address and offset and split the class across two walkthroughs,
the exact thing the maintainer ruled out. So keeping "one binary, one walkthrough for
everyone" on a multi-arch container forces the recompile to target a fixed
architecture regardless of host, i.e. cross-compilation on the non-matching host.

## Goals / Non-Goals

**Goals:**
- Multi-arch container; single, fixed-target-arch exploitable binary; plain `docker
  run` native on every host, no `--platform` flag.
- Fix/Save recompiles to the same target arch on every host, so the glass-box editor
  behaves identically for all learners.
- Full-fidelity live gdb capture on every host, native or emulated.
- One mechanism that a future aarch64-target rung reuses by declaring a different
  target arch, no base-image redesign.

**Non-Goals:**
- Any aarch64 walkthrough, arch-adaptive `objdump` syntax, or aarch64 frame
  *interpretation* (which slot is the return address). Every shipped binary stays
  x86-64; those land only with a future aarch64-target rung. (Exception: the frame-
  *locating* register in `gdb-dump.py` becomes arch-aware now, see D5, so the mismatch
  path is self-testable with an aarch64 test binary.)
- Forcing emulation on hosts that match the binary (native stays native).
- Relying on the host's `binfmt_misc` registration; the container wraps foreign
  binaries with an explicit `qemu` prefix so behavior is deterministic and offline.

## Decisions

### D1: Multi-arch container carrying a single-arch binary, via cross-compile + qemu-user

Chosen over (a) a genuinely native per-host binary (rejected by the maintainer:
splits learners across architectures and walkthroughs) and (b) forcing `qemu` on all
hosts for a bit-identical substrate (rejected: needlessly emulates the amd64 common
case and CI; observable behavior is already identical for a fixed `-no-pie` binary).
The binary's target arch is fixed; the container is native everywhere; only the binary
is emulated, and only on a non-matching host.

### D2: The target architecture is an author-fixed build property

The target arch is declared per challenge as an **author-fixed** build input (like
`ret2libc`'s static-linking pin), not a learner-editable flag. Concretely, `build.sh`
passes it to `nbuild` (e.g. an `--arch x86_64` argument, snapshotted alongside
`build.flags`). `nbuild` maps it to the matching compiler: native `gcc` when it equals
the container arch, else the cross compiler (`x86_64-linux-gnu-gcc`). Save therefore
rebuilds the same-arch binary on any host, and a learner cannot change the target arch
through the flags field. Runtime code does not need to read this config: the built
ELF's `e_machine` is the authoritative source of the binary's arch for the runner and
the debugger.

### D3: The native family ships both cross toolchains + qemu-user + gdb-multiarch, uniformly

The family image is built independently of any challenge, so it cannot condition on a
challenge's target arch; it carries the union of what the repo's target arches need.
**Both image variants ship the same set**: native `gcc`, the x86-64 and aarch64 cross
compilers (`gcc-x86-64-linux-gnu`, `gcc-aarch64-linux-gnu`), `qemu-user-static`
(providing `qemu-x86_64-static` and `qemu-aarch64-static`), and `gdb-multiarch`.

Uniform, rather than `TARGETARCH`-gated to keep amd64 lean, is a deliberate choice to
close the verification gap: because each variant also carries the *other* arch's
toolchain, the host-not-equal-target path is self-testable in an ordinary container on
either host (see D7), and CI proves it on standard amd64 runners. It also makes a future
aarch64-target rung a zero-Dockerfile change. The cost is a fatter image on both
variants, accepted for self-testability and recorded per the open-tooling policy in
AGENTS.md.

### D4: Runner selects native vs qemu by comparing host arch to the ELF's arch

`native-run.php` computes the host arch (`php_uname('m')`) and the binary arch
(`readelf -h` / ELF `e_machine`). On a match it runs as today; on a mismatch it prefixes
the sandboxed command with `qemu-<binarch>-static`. The `timeout`/CPU bound and the
KILL still wrap the whole invocation. The address-space cap (`ulimit -v`) is loosened
or dropped on the emulated path, because `qemu-user` reserves a large guest address
space that a tight `-v` would kill; the CPU-time bound and process-count cap remain the
guardrails. (Exact `-v` handling to confirm in-container, see Risks.)

### D5: gdb capture gains a remote (qemu-gdbstub) mode

`gdb-dump.py` takes a mode from the environment:
- **native** (host == target): today's path, `gdb -batch`, `run < payload`, read
  `$rbp/$rsp`. Unchanged.
- **remote** (host != target): `native-run.php` launches
  `qemu-<binarch>-static -g <port> ./bin < payload` in the background (guest starts
  paused at the gdbstub), then runs `gdb-multiarch -batch` with the script connecting
  via `target remote :<port>`, setting the two `break *(vuln+off)` breakpoints, and
  `continue`-ing to them instead of `run`. `gdb-multiarch` auto-detects the guest arch
  from qemu's target description; the guest is x86-64, so `$rbp/$rsp` and the frame
  math are unchanged.

`gdb-dump.py` selects its **frame-locating register by the arch gdb reports** (`$rbp`
for x86-64, `$x29`/`$sp` for aarch64), so the remote path can read a *foreign* guest's
frame, which is what makes the D7 self-test possible. The window math and slot
*interpretation* stay x86-64-shaped; aarch64 frame interpretation is out of scope until
a real aarch64 rung (Non-Goals).

`native-run.php` owns port selection (a free ephemeral port), readiness wait, an
overall timeout, and killing `qemu` afterward. The existing best-effort contract holds:
any failure prints the `{"ok": false}` JSON and the view falls back to the
payload-derived model.

### D6: CI publishes binary challenges like everything else

Remove the platform-restriction parsing from `.github/workflows/docker-publish.yml` and
the `LABEL org.glassbox.platforms` from both binary challenges; they publish for
`linux/amd64,linux/arm64` by default. buildx builds the arm64 leg under emulation (now
including the cross-gcc/qemu apt install), which is slower but a build-time-only cost.

### D7: A symmetric in-container self-test closes the logic gap without Apple Silicon

The mismatch path is direction-agnostic, so it is exercised on an amd64 host by building
an **aarch64-target** throwaway binary and running it under `qemu-aarch64-static`
(single-level qemu on real hardware, no `binfmt`, no nested emulation). This one test
covers cross-compile-on-Save, the emulated run, the sandbox under qemu, and the
`qemu -g` + `gdb-multiarch` remote frame read, the same code students hit in reverse. It
runs in a plain amd64 container and in CI on standard runners. What it does not prove is
environmental: that the published *arm64 image* assembles/boots and that Docker
Desktop's `qemu-x86_64` behaves on Apple Silicon. That is the one remaining hardware
sign-off.

## Risks / Trade-offs

- **Image size** (both cross-gcc + both target libcs + qemu-user + gdb-multiarch on both
  variants) → accepted deliberately for self-testability and forward-readiness (D3/D7),
  documented in AGENTS.md. If it becomes painful, the fallback is `TARGETARCH`-gating the
  cross toolchains and moving the self-test to a dedicated CI image, at the cost of an
  extra build target.
- **qemu-gdbstub fragility** (port races, stub timing) → best-effort fallback preserved
  end to end; generous readiness wait and overall timeout; qemu always killed in a
  `finally`.
- **`ulimit -v` vs qemu's large guest reservation** → relax/drop `-v` on the emulated
  path and lean on the CPU-time and process-count bounds; confirm in-container that a
  runaway payload is still contained.
- **Memory-map panel under emulation** (`/proc/self/maps` reflects qemu, not a clean
  guest map) → this level-2 panel may need a "shown from the emulator" annotation or a
  static derivation; it is off the critical path, so worst case it degrades like the
  live frame. Confirm behavior in-container.
- **Cannot fully validate on the x86-64 dev host** (no `qemu-user`/`gdb-multiarch`, no
  arm64) → see Migration Plan for the validation ladder.

## Migration Plan

1. Extend `platform/native` (D3) and rebuild the base chain (`--no-cache` per the stale
   COPY-layer caveat).
2. Update `nbuild`/`build.sh` for the target-arch selection (D2), then
   `native-run.php` + `gdb-dump.py` (D4, D5).
3. Drop the LABELs and CI restriction (D6); update AGENTS.md and the two READMEs.
4. **Validation ladder:** (a) in a plain amd64 container, run the D7 symmetric self-test
   (aarch64-target build + `qemu-aarch64-static` run + `gdb-multiarch` remote capture),
   covering the whole mismatch logic path, and wire it into CI; (b) confirm via buildx
   that the arm64 image builds and ships the expected tools; (c) final environmental
   sign-off on real Apple Silicon (maintainer's or a student's machine): plain
   `docker run`, Save/recompile, and the Debug before/after frame over `qemu-x86_64`.
5. **Rollback:** revert the LABEL/CI changes to republish amd64-only; the base-image
   additions are inert on amd64, so a partial rollback leaves amd64 users unaffected.

## Open Questions

- None that change the specs, approach, or task breakdown. The `ulimit -v` value and
  the memory-map panel's exact behavior under emulation are implementation details to
  settle during in-container validation, both with defined fallbacks above.

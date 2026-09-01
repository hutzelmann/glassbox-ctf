## Why

The binary challenges publish for `linux/amd64` only, so on Apple Silicon (and any
arm64 host) `docker run` finds no matching image and either refuses or falls back to
`--platform linux/amd64` emulation, an extra flag and a degraded experience the
teacher must explain. We want every container to run natively on every host, with
full features and no per-arch flag, while all learners keep exploiting the exact same
binary against the exact same walkthrough (binary exploitation is hard enough without
splitting the class across two architectures).

## What Changes

- Binary-challenge **containers become multi-arch** (native apache/php on both
  `linux/amd64` and `linux/arm64`), while each challenge's **exploitable binary stays
  a single, fixed target architecture** (today x86-64). The container runs that binary
  natively when the host matches and transparently under `qemu-user` when it does not,
  so the plain `docker run` works everywhere with no `--platform` flag.
- Each binary challenge **declares its binary's target architecture** as config; the
  runner and the debug introspection resolve host-vs-target from it. A future rung can
  ship an aarch64 binary and it will run on every host by the same mechanism.
- The **live gdb stack capture works cross-architecture with full fidelity**: native
  ptrace when host equals target, and a `qemu-user` gdbstub driven by `gdb-multiarch`
  when it does not. The before/after real frame (real saved return address, real
  random canary, uninitialized buffer) is available on every host, not only amd64. The
  best-effort fallback to the payload-derived model is preserved for any environment
  where capture cannot run.
- The `platform/native` image gains `qemu-user-static` and `gdb-multiarch` (offline,
  baked at build time). This tooling addition is recorded per the open-tooling policy.
- **BREAKING (publish contract): the per-challenge publish-platform restriction is
  retired.** The `LABEL org.glassbox.platforms="linux/amd64"` pins on `ret2win` and
  `ret2libc` are removed and every image publishes for both architectures. The
  "a challenge may restrict its publish platforms" escape hatch is removed from the
  spec; no image is arch-restricted anymore.
- Docs are updated: the READMEs drop the "runs under emulation on arm64" note in favor
  of "runs natively on any host," the walkthrough is stated as matching the binary's
  architecture (not the host's), and AGENTS.md records the multi-arch-container /
  single-arch-binary model and the new tooling.

Non-goals: rewriting the walkthroughs for aarch64, or making the debug register model
arch-adaptive. Those land only if and when an aarch64-target rung is added; every
binary here stays x86-64, so the existing x86-64 walkthrough remains correct for all
learners.

## Capabilities

### New Capabilities
<!-- none: the behavior extends existing binary-family and publish capabilities -->

### Modified Capabilities
- `native-binary-challenges`: the exploitable binary declares a fixed target
  architecture and runs on any host (native or via `qemu-user`) without changing the
  binary or the walkthrough; the live stack capture is required to work
  cross-architecture with full fidelity (not only when the host matches the binary),
  with the existing graceful-degradation fallback retained.
- `build-and-publish`: every image publishes for both `linux/amd64` and `linux/arm64`
  unconditionally; the per-challenge publish-platform restriction (used by the binary
  rungs to pin amd64-only) is removed.

## Impact

- **Base image**: `platform/native/Dockerfile` (adds `qemu-user-static`,
  `gdb-multiarch`); `platform/native/native-run.php` (host-vs-target runner path and
  cross-arch gdb capture path); `platform/native/gdb-dump.py` (remote-gdbstub mode);
  possibly a small target-arch helper.
- **Challenges**: `challenges/binary/ret2win/` and `challenges/binary/ret2libc/`
  (remove the `linux/amd64` LABEL, declare target arch, README copy).
- **CI**: `.github/workflows/docker-publish.yml` (drop the platform-restriction
  parsing; publish binary challenges for both arches like everything else).
- **Docs**: `AGENTS.md` (model + tooling), the two binary READMEs, and the affected
  OpenSpec specs.
- **Dependencies**: `qemu-user-static`, `gdb-multiarch` added to the native family
  image (size cost noted in design).
- **Verification**: the cross-arch run and the qemu-gdbstub capture need validation in
  the built container, with final sign-off on real Apple Silicon; the dev host used to
  author this has no `qemu-user`/`gdb-multiarch` to exercise the emulated path.

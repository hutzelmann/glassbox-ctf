## 1. Native family toolchain (D3)

- [x] 1.1 Add `gdb-multiarch` to `platform/native/Dockerfile` on both image variants.
- [x] 1.2 Install both cross toolchains and the emulators on both variants (no
  `TARGETARCH` gating, D3): `gcc-x86-64-linux-gnu`, `gcc-aarch64-linux-gnu`, and
  `qemu-user-static` (providing `qemu-x86_64-static` and `qemu-aarch64-static`).
- [x] 1.3 Rebuild the base chain with `--no-cache` (harness, then native) and confirm
  both cross-gcc, both `qemu-<arch>-static`, and `gdb-multiarch` are present in both the
  amd64 and arm64 images.

## 2. Fixed-target-arch recompile (D2)

- [x] 2.1 Teach `nbuild` a target-arch input that selects the compiler (native `gcc`
  when it equals the container arch, else the matching cross compiler), keeping the
  atomic temp-file-then-swap and the flags allowlist unchanged.
- [x] 2.2 Pass the target arch from each binary challenge's `build.sh`/`build.flags`
  as an author-fixed value (snapshotted like `build.flags`); ensure it is NOT in the
  learner-editable allowlist and cannot be overridden through the flags field.
- [ ] 2.3 Verify Save on an arm64 container recompiles an x86-64 binary (unchanged
  `e_machine`), and a bad compile still keeps the last-good binary.

## 3. Arch-aware runner (D4)

- [x] 3.1 In `native-run.php`, derive host arch and the built binary's arch (ELF
  `e_machine` via `readelf -h`); run natively on a match, else prefix the sandboxed
  command with `qemu-<binarch>-static`.
- [x] 3.2 Adjust the sandbox for the emulated path: relax/drop `ulimit -v` while
  keeping the CPU-time bound and process-count cap; confirm a runaway payload is still
  killed under emulation.
- [x] 3.3 Confirm stdout/stderr, exit status, and signal detection (segfault 11, abort
  6, timeout) are reported correctly through `qemu-user`.

## 4. Cross-arch live gdb capture (D5)

- [x] 4.1 Add a mode switch to `gdb-dump.py`: keep the native `run < payload` path, add
  a remote path that does `target remote :<port>` then `continue` to the two
  breakpoints (no `run`); select the frame-locating register by the arch gdb reports
  (`$rbp` for x86-64, `$x29`/`$sp` for aarch64) so a foreign guest's frame can be read.
- [x] 4.2 In `native-run.php`, when host != target, launch
  `qemu-<binarch>-static -g <port> ./bin < payload` in the background (free ephemeral
  port, readiness wait), drive it with `gdb-multiarch -batch` + the remote script, then
  kill `qemu` in a `finally`; on any failure fall back to the payload-derived model.
- [ ] 4.3 Confirm the before/after frame (real saved return address, real random canary
  with the stack protector on, uninitialized buffer) matches native output for the same
  payload; confirm the memory-map panel either works or is annotated/degraded under
  emulation.

## 5. Retire the amd64-only publish pins (D6)

- [x] 5.1 Remove `LABEL org.glassbox.platforms="linux/amd64"` from
  `challenges/binary/ret2win/Dockerfile` and `challenges/binary/ret2libc/Dockerfile`.
- [x] 5.2 Remove the platform-restriction parsing from
  `.github/workflows/docker-publish.yml` so the binary challenges publish for both
  architectures like every other image.
- [ ] 5.3 Confirm CI (or a local buildx dry run) produces both `linux/amd64` and
  `linux/arm64` manifests for `ret2win` and `ret2libc`.

## 6. Docs

- [x] 6.1 Update `AGENTS.md`: the multi-arch-container / single-arch-binary model, the
  target-arch author-fixed property, and the new tooling (`qemu-user-static`,
  `gdb-multiarch`, cross-gcc) with its rationale per the open-tooling policy.
- [x] 6.2 Update `challenges/binary/ret2win/README.md` and
  `challenges/binary/ret2libc/README.md`: replace the "runs under emulation on arm64"
  note with "runs natively on any host", and state that the walkthrough matches the
  binary's architecture, not the host's, without steering the learner into debug.

## 7. Verification (D7 + Migration Plan)

- [x] 7.1 Add a throwaway aarch64-target fixture (a tiny C program built with
  `gcc-aarch64-linux-gnu`) and a script that, in a plain amd64 container,
  cross-compiles it, runs it under `qemu-aarch64-static`, and drives a `qemu -g` +
  `gdb-multiarch` remote frame capture, asserting a frame is read. This exercises the
  whole host-not-equal-target logic path on real hardware.
- [x] 7.2 Wire the 7.1 self-test into CI so the mismatch path is proven on standard
  amd64 runners every push.
- [ ] 7.3 Confirm via `buildx` that the arm64 image builds and ships the expected tools
  (both cross-gcc, `qemu-x86_64-static`, `qemu-aarch64-static`, `gdb-multiarch`).
- [ ] 7.4 Final environmental sign-off on real Apple Silicon (maintainer or student):
  plain `docker run` (no `--platform`), solve the rung, Save/recompile, and the Debug
  before/after frame over `qemu-x86_64`.

## 8. OpenSpec close-out

- [x] 8.1 `openspec validate native-binaries-any-host --strict` passes.
- [ ] 8.2 Archive the change with `openspec archive native-binaries-any-host` after
  the work lands.

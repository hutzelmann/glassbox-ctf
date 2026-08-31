## 1. Record the convention

- [x] 1.1 Add a "Tooling is decided in the open" bullet to the **Conventions**
  section of `AGENTS.md`: no tool is excluded from (or added to) the images or the
  toolchain silently; state the choice and its rationale, and surface the
  trade-offs (image size, offline/build cost, security, didactic value) to the
  maintainer when proposing a tool change.
- [x] 1.2 Rewrite the "No gdb, exploitation happens locally against the
  downloadable binary" clause in the `platform/native/` bullet so it no longer
  bans `gdb`/`ptrace`: the image may carry `gdb` for debug-only introspection;
  exploitation still happens locally against the downloadable binary.

## 2. Capture it as spec

- [x] 2.1 Add the `contribution-workflow` requirement "Tool availability is
  decided openly, never excluded silently" with its scenarios.

## 3. Verify

- [x] 3.1 `grep -n "No gdb" AGENTS.md` returns nothing; the new convention bullet
  and the reworded native clause are present.
- [x] 3.2 `openspec validate open-tooling-policy --strict` passes.

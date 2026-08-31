## Why

`AGENTS.md` and the native base image currently ban a tool by fiat: "No gdb, no
ptrace." That blanket exclusion pre-empts a maintainer decision. It already misled
the project once (a `--cap-add=SYS_PTRACE` "register readout" was documented for a
feature that did not exist, later removed as phantom), and it now blocks a
legitimate, high-value teaching feature (a gdb-driven live stack view). The
maintainer's guidance is explicit: do not exclude any tool silently; weigh the
pros and cons in the open and let the maintainer decide.

This change makes that a recorded convention and lifts the specific `gdb`/`ptrace`
ban from `AGENTS.md`, so a follow-up feature change can add `gdb` for debug-only
introspection on its own merits.

## What Changes

- Add a **standing convention** to `AGENTS.md`: tooling is decided in the open. A
  tool is never dropped from (or added to) the images or the contributor toolchain
  by fiat; the choice and its rationale are stated, and a contributor proposing a
  tool change surfaces the trade-offs (image size, offline/build cost, security,
  didactic value) to the maintainer for a decision.
- **Remove the "No gdb, no ptrace" exclusion** from the `platform/native/`
  description in `AGENTS.md` and rewrite that clause: the native image is free to
  carry `gdb` for debug-only introspection, while exploitation still happens
  locally against the downloadable binary.
- Record a matching **contribution-workflow requirement** so the convention is
  captured as spec, not just prose.

This change is documentation and convention only. It ships no code and no image
edit; the actual `gdb` install and the native `Dockerfile` comment fix belong to
the feature change that needs them.

## Impact

- Affected specs: `contribution-workflow` (one added requirement).
- Affected docs: `AGENTS.md` (Conventions section; the `platform/native/` bullet).
- Unblocks: the `add-live-stack-capture` feature change, which adds `gdb`.
- No runtime, CI, or challenge behavior changes.

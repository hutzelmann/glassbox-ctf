# Runtime Check — Solution

> ⚠️ **No solution needed.** This is a setup check, not a challenge. There is no
> vulnerability, no flag, and nothing to exploit.

## What "solved" means

If the run command prints the success message, your runtime works:

```
Congratulations! Your setup is working.
```

That is the entire pass condition. Move on to the [hello](../hello/) challenge.

## If it did not print that

The image failed to pull or run — a runtime problem, not a puzzle. A few things
to check:

- The container runtime (podman or docker) is installed and its service is
  running.
- You have network access to pull `ghcr.io/hutzelmann/glassbox-ctf-runtime-check`
  the first time. After that, the cached image runs offline.
- You typed the image tag exactly.

Once it prints the message, you are done here. Continue to
[hello](../hello/), the first challenge with a real web page.

No flags, no tasks, no professional tools — this rung of the ladder only exists
to isolate "runtime broken" from "web app broken" before you start.

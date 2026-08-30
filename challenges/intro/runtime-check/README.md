# Runtime Check

> Before you attack anything, prove your container runtime can pull and run an
> image. This one just prints a success message and exits.

**Domain:** Intro **Ladder:** none — a setup smoke test, not a challenge.

## The scenario

Every other challenge here ships as a Docker image you pull and run. If your
runtime is broken, you want to find out *now* — with an image that does nothing
but print one line — not while you are three payloads deep into a SQL injection
and can't tell whether the bug is in the app or in your setup.

This image is deliberately minimal (`alpine` + a single `echo`). If it prints
the success message, your runtime works.

## Your tasks

- **a)** Run the image with the command below.
- **b)** Confirm you see the expected output (see **Running it**).
- **c)** Move on to the [hello](../hello/) challenge — the first one with a web
  page.

## Running it

```bash
podman run --rm ghcr.io/hutzelmann/glassbox-ctf-runtime-check
```

There is no web UI and no port to open — the container prints one line and
exits. You should see:

```
Congratulations! Your setup is working.
```

`docker run --rm ghcr.io/hutzelmann/glassbox-ctf-runtime-check` works the same
way if you use Docker instead of podman.

## The glass box

Nothing to open here — there is no `critical.php`, no **Fix** button, and no
debug dial. Those tools show up starting with the [hello](../hello/)
challenge, where there is actually a running page to inspect.

## Stuck?

If the command errors instead of printing the message, your container runtime
can't pull or run images yet — fix that before going further. See
[solution.md](solution.md).

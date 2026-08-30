# glassbox-ctf

**Learn to hack by watching the exploit land, at the victim, and then patch the hole live in your browser.**

Most security exercises are black boxes: you throw payloads at a target and guess
what happened inside. `glassbox-ctf` is the opposite. Every challenge is a small,
deliberately vulnerable app or program that lets you *see its internals as you
attack it*, the exact SQL your injection built and the rows it returned, the page
the victim's browser actually rendered, the stack frame your overflow walks over
and the CPU registers at the moment it crashes, and then hands you an in-browser
editor to fix the one vulnerable line and prove your patch works. Attack,
understand, remediate, all in the same tab.

It is built for classrooms and self-study: textbook, well-understood
vulnerabilities across web (SQL injection, XSS) and binary exploitation
(stack overflows, ret2win, ret2libc), with more domains coming, plus fictional
flags and throwaway containers that run completely offline.

## Why it's different

- **A debug dial with three settings, not an on/off switch.** Every page carries
  a sticky selector — **Challenge**, **Hints**, **Debug** — so you choose how much of
  the answer you want.
  - **Challenge** is the challenge as a real target would give it to you — no glass box.
  - **Hints** (`?debug=1`) hands you *tooling and the symptom your own attempt
    provoked*, never the answer. In the web challenges the input you attack
    through becomes a proper editor (highlighting and live diagnostics for the
    language the server parses it as) and you see the database's own error
    message, the admin browser's JS console errors, the query timing a blind
    attacker measures. In the binary challenges you get a byte/endianness
    calculator and a hexdump plus a live stack-frame table showing where *your*
    bytes landed, so you read the offset off your own crash.
  - **Debug** (`?debug=2`) opens the glass box the rest of the way, to the
    internals a real attacker has to extract for themselves. In the web
    challenges: the literal SQL string your injection produced, the rows it
    returned, the page the victim actually rendered, the raw request, and the
    vulnerable source. In the binary challenges: the disassembly, the symbol and
    gadget addresses your payload jumps to, the `checksec` protections, the
    memory map, and the full program source.

  Stuck on a payload is a different problem from not understanding the target,
  and the dial lets you buy exactly the help you need.
- **Fix it live.** Each challenge isolates its single vulnerable snippet in a
  `critical.<ext>` file, `critical.php` for the web challenges, `critical.c` for
  the binary ones. The built-in **Fix** editor (CodeMirror, linting in the
  challenge's own language) saves your edit straight into the *running* target,
  retry your own exploit against your own patch. Compiled challenges rebuild on
  Save, and a build that fails shows you the compiler errors while the last
  working binary keeps running. **Restore Original** puts the bug back.
- **Disposable and offline.** Each challenge is one self-contained container with
  its own seeded data, no setup, no network, nothing to clean up. Web challenges
  are published for `linux/amd64` and `linux/arm64`, so they run on an Intel
  laptop or an Apple-silicon Mac alike; the binary challenges are x86-64 and run
  under emulation on arm64 hosts.

## Quick start

You need a container runtime, [podman](https://podman.io/) or Docker. Run your
first challenge:

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-hello
```

Open <http://localhost:9000/>, find your first flag, then turn the **debug**
dial in the header up to **Hints** and then **Debug**, and click **Fix**, to meet
the two controls every challenge shares. That's the whole workflow. When you're
done, `Ctrl+C`: the container
leaves nothing behind.

New to containers? Start with the runtime check, which just proves your setup
works:

```bash
podman run --rm ghcr.io/hutzelmann/glassbox-ctf-runtime-check
```

## Challenge catalog

Each challenge links to its own `README.md` (tasks and how to run, no spoilers).
Work each group **top to bottom**: they form a difficulty ladder, each rung
building on the last.

### Intro, learn the setup

| Challenge | You learn | Image |
|-----------|-----------|-------|
| [runtime-check](challenges/intro/runtime-check/) | Your container runtime works | `glassbox-ctf-runtime-check` |
| [hello](challenges/intro/hello/) | The first flag; the debug switch and Fix editor | `glassbox-ctf-hello` |

### Web · SQL Injection

| Challenge | You learn | Image |
|-----------|-----------|-------|
| [sqli-login](challenges/web/sqli-login/) | Login bypass, `UNION` reads, dumping a database | `glassbox-ctf-sqli-login` |
| [sqli-blind](challenges/web/sqli-blind/) | Extracting data with no visible output (boolean + time-based) | `glassbox-ctf-sqli-blind` |
| [sqli-insert](challenges/web/sqli-insert/) | Injection in an `INSERT`: exfiltration and account forgery | `glassbox-ctf-sqli-insert` |

### Web · Cross-Site Scripting

| Challenge | You learn | Image |
|-----------|-----------|-------|
| [xss-light](challenges/web/xss-light/) | Reflected XSS and reading a cookie | `glassbox-ctf-xss-light` |
| [xss-shop](challenges/web/xss-shop/) | Injecting HTML/JS and defeating client-side validation | `glassbox-ctf-xss-shop` |
| [xss-cookie](challenges/web/xss-cookie/) | The full chain: XSS → admin-bot → cookie theft, offline | `glassbox-ctf-xss-cookie` |

### Binary Exploitation

| Challenge | You learn | Image |
|-----------|-----------|-------|
| [ret2win](challenges/binary/ret2win/) | Stack buffer overflow → overwrite the saved return address → redirect execution to `win()` | `glassbox-ctf-ret2win` |
| [ret2libc](challenges/binary/ret2libc/) | Return-oriented programming: chain gadgets to call `system("/bin/sh")` past a non-executable stack | `glassbox-ctf-ret2libc` |

> The binary challenges compile x86-64 and are published for `linux/amd64`; on
> arm64 hosts (e.g. Apple Silicon) the same command runs them under emulation. In
> these, **Fix** recompiles the binary on Save and you can also edit the compiler
> flags to toggle protections (stack canary, PIE, NX) and watch which exploit each
> one kills; add `--cap-add=SYS_PTRACE` for the live register readout in the debug
> view:

```bash
podman run --rm -p 9000:80 --cap-add=SYS_PTRACE ghcr.io/hutzelmann/glassbox-ctf-ret2win
```

More domains are on the way.

## For teachers

- **Self-contained and offline.** Hand students an image name; they run it and
  get to work. No accounts, no shared server, no data to reset, each container is
  its own sealed world with fictional flags.
- **Solutions are in the repo.** Every challenge folder has a `solution.md` with
  the full walkthrough, the flags, and the fix, plus a **Professional tools**
  section that redoes each exploit with the industry-standard tool for its
  domain (`sqlmap` for SQLi, browser DevTools and an intercepting proxy for XSS,
  `pwntools` with `gdb`/`checksec`/`objdump`/`ROPgadget` for the binary rungs) so
  students graduate from manual understanding to the real toolchain.
- **Flags are for participation, not grading.** Because the flags live in this
  public repo, a motivated student can look them up. Collecting flags (e.g. in an
  LMS) works well for self-check and participation, but do **not** base grades on
  flag submission, assess the explanation and the fix instead.
- **Capstone idea.** Once a class has solved these by hand, revisit them with an
  automated, agentic LLM pentest (e.g. HexStrike AI) and compare what the tools
  find to what the students did.

## For contributors

Conventions, architecture, and the build pipeline live in
[AGENTS.md](AGENTS.md), the single source of truth. In short: challenges live
under `challenges/<domain>/<name>/`, share a base-image chain under `platform/`,
and CI discovers them automatically. **Non-trivial changes are drafted in OpenSpec
first** (`openspec/`); see AGENTS.md for the threshold.

### Building from source

Build the base chain, then a challenge (details and the live-reload loop are in
AGENTS.md):

```bash
podman build -t glassbox-harness ./platform/harness/
podman build -t glassbox-php ./platform/php/        # web challenges
podman build -t glassbox-native ./platform/native/  # binary challenges
```

Then the challenge itself, for example:

```bash
cd challenges/web/sqli-login && podman build -t glassbox-ctf . && podman run --rm -p 9000:80 glassbox-ctf
```

```bash
cd challenges/binary/ret2win && podman build -t glassbox-ctf . && podman run --rm -p 9000:80 glassbox-ctf
```

## A note on intent

This is defensive security education. The vulnerabilities are classic, textbook
bugs from the standard curriculum (injection, XSS, memory corruption); the
"secrets" are fictional; the targets are throwaway local containers.
The entire design points at one goal: understand a weakness well enough to fix it.

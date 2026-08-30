# glassbox-ctf

**Learn to hack by watching the exploit land — at the victim — and then patch the hole live in your browser.**

Most security exercises are black boxes: you throw payloads at a server and guess
what happened inside. `glassbox-ctf` is the opposite. Every challenge is a small,
deliberately vulnerable app that lets you *see its internals as you attack it* —
the exact SQL it built from your input, the rows it returned, the page the victim
actually rendered — and then hands you an in-browser editor to fix the one
vulnerable line and prove your patch works. Attack, understand, remediate, all in
the same tab.

It is built for classrooms and self-study: textbook, well-understood
vulnerabilities (the OWASP-class web bugs today, more domains coming), fictional
flags, and throwaway containers that run completely offline.

> _Screenshot slot: `hero.gif` — exploit lands → debug reveals the internals → Fix
> patches `critical.php` → exploit now fails. (see `docs/img/`)_

## Why it's different

- **The debug switch (`?debug=1`).** A sticky toggle on every page reveals the
  server's internals for *this* vulnerability — the literal SQL string your
  injection produced, the returned rows, the admin's rendered page, JS console
  errors. You watch the bug work instead of guessing.
- **Fix it live.** Each challenge isolates its single vulnerable snippet in a
  `critical.php` file. The built-in **Fix** editor (CodeMirror, with PHP linting)
  saves your edit straight into the *running* app — retry your own exploit against
  your own patch. **Restore Original** puts the bug back.
- **Disposable, offline, multi-arch.** Each challenge is one self-contained
  container with its own seeded data — no setup, no network, nothing to clean up.
  Images are published for `linux/amd64` and `linux/arm64`, so they run on an
  Intel laptop or an Apple-silicon Mac alike.

## Quick start

You need a container runtime — [podman](https://podman.io/) or Docker. Run your
first challenge:

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-hello
```

Open <http://localhost:9000/>, find your first flag, then flip the **debug**
toggle in the header and click **Fix** to meet the two controls every challenge
shares. That's the whole workflow. When you're done, `Ctrl+C` — the container
leaves nothing behind.

New to containers? Start with the runtime check, which just proves your setup
works:

```bash
podman run --rm ghcr.io/hutzelmann/glassbox-ctf-runtime-check
```

## Challenge catalog

Each challenge links to its own `README.md` (tasks and how to run — no spoilers).
Work each group **top to bottom**: they form a difficulty ladder, each rung
building on the last.

### Intro — learn the setup

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

More domains (binary exploitation and beyond) are on the way.

## For teachers

- **Self-contained and offline.** Hand students an image name; they run it and
  get to work. No accounts, no shared server, no data to reset — each container is
  its own sealed world with fictional flags.
- **Solutions are in the repo.** Every challenge folder has a `solution.md` with
  the full walkthrough, the flags, and the fix — plus a **Professional tools**
  section that redoes each exploit with the industry-standard tool
  (`sqlmap`, browser DevTools, …) so students graduate from manual understanding
  to the real toolchain.
- **Flags are for participation, not grading.** Because the flags live in this
  public repo, a motivated student can look them up. Collecting flags (e.g. in an
  LMS) works well for self-check and participation, but do **not** base grades on
  flag submission — assess the explanation and the fix instead.
- **Capstone idea.** Once a class has solved these by hand, revisit them with an
  automated, agentic LLM pentest (e.g. HexStrike AI) and compare what the tools
  find to what the students did.

## For contributors

Conventions, architecture, and the build pipeline live in
[AGENTS.md](AGENTS.md) — the single source of truth. In short: challenges live
under `challenges/<domain>/<name>/`, share a base-image chain under `platform/`,
and CI discovers them automatically. **Non-trivial changes are drafted in OpenSpec
first** (`openspec/`); see AGENTS.md for the threshold.

### Building from source

Build the base chain, then a challenge (details and the live-reload loop are in
AGENTS.md):

```bash
podman build -t glassbox-harness ./platform/harness/
podman build -t glassbox-php ./platform/php/
cd challenges/web/sqli-login && podman build -t glassbox-ctf . && podman run --rm -p 9000:80 glassbox-ctf
```

## A note on intent

This is defensive security education. The vulnerabilities are classic, textbook
bugs; the "secrets" are fictional; the targets are throwaway local containers.
The entire design points at one goal: understand a weakness well enough to fix it.

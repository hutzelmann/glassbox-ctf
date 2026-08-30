# AGENTS.md

Guidance for AI coding agents (Claude Code and others) working in this repository. This is the single source of truth; `CLAUDE.md` just `@`-imports it.

## Purpose and scope

This is an **educational security project for defensive learning**. It teaches standard, well-documented security vulnerabilities so learners can recognize and *fix* them. Today the challenges are web-based (SQL injection, XSS); the project deliberately aims to grow beyond web to other classes of weakness (a binary/`ret2win` family is planned next). Every part is scoped to that didactic goal:

- **Sandboxed and self-contained.** Each challenge is a throwaway Docker container that runs fully offline against its own seeded data. There are no real users, no real secrets, and no third-party targets — the "flags" and credentials are fictional teaching content.
- **Well-known weaknesses only.** Textbook, well-understood bugs used in classrooms and CTFs (OWASP-class web flaws today, other domains later) — not novel exploits or attack tooling aimed at real systems.
- **The point is the fix.** The whole design (the live `critical.<ext>` editor, the debug view exposing internals) exists so learners understand a vulnerability well enough to remediate it. Offensive detail here serves comprehension and defense.

Work in this repo — writing challenges, exploit examples, admin-bot simulations, remediation notes — is legitimate security education. Keep that framing when describing tasks.

## Change workflow: OpenSpec is mandatory for non-trivial changes

Non-trivial changes **must** be drafted as an OpenSpec change under `openspec/changes/<id>/` (proposal → specs → design → tasks) **before** implementation. The tooling is set up (`openspec/`, plus `.claude/` commands and skills); use `openspec new change <id>`, fill the artifacts, and `openspec validate <id> --strict` before coding. Archive with `openspec archive <id>` when done.

**Non-trivial (OpenSpec required):** adding a challenge; adding a challenge type or base image; changing the folder or CI structure; changing the glass-box contract (the `critical.<ext>` / Fix / `?debug=1` pattern); anything that touches multiple challenges.

**Trivial (skip OpenSpec):** typo and copy fixes; a single-file bugfix within one challenge; dependency version bumps; documentation wording.

## What this is

`glassbox-ctf` is a set of self-contained, Dockerized security teaching challenges. Each challenge is a deliberately vulnerable app. The "glass box" idea: the learner can watch the exploit unfold **at the victim server** and then patch the hole live in the browser.

Two learner-facing mechanisms drive almost every design decision:

- **The `critical.<ext>` pattern.** The single vulnerable snippet lives in `critical.php` (the extension tracks the challenge language; all current challenges are PHP), `require`d by a page (e.g. `index.php`, `search.php`). At build time it is snapshotted to `critical.orig.php`. `fix.php` (shipped by the harness image) is an in-browser CodeMirror editor that saves back to `critical.php` (Restore reverts to the `.orig`). So the learner edits the *real running code* of just the vulnerable part. Everything the learner should not touch stays outside `critical.php`.
- **The debug switch.** `?debug=1` toggles extra output that exposes server internals — the exact SQL sent, returned rows, the admin's rendered page, JS console errors, etc. It is sticky: every page computes `$debugSuffix` and appends it to internal links, and an inline `onchange` handler rewrites the URL's `debug` param. Debug-only CodeMirror bundles are `<script>`-included only when `debug=1`.

## Repository layout

```
challenges/
  intro/            on-ramp containers (not vuln lessons)
    hello/          the first flag + a tour of the glass box
    runtime-check/  a minimal alpine smoke test: "does my container runtime work?"
  web/              web-delivered vulnerability challenges
    sqli-login/  sqli-blind/  sqli-insert/     (SQLi ladder)
    xss-light/   xss-shop/    xss-cookie/      (XSS ladder)
platform/           shared base images (the family chain)
  harness/          FROM php:8.5-apache: pico.css + fix.php — the web-delivery + Fix skeleton
  php/              FROM harness: CodeMirror bundles + Psalm + lint.php — the PHP family
openspec/           OpenSpec changes and specs
docs/img/           screenshots/media for the top-level README
```

A challenge's **published image name is its folder basename**, flat and path-independent: `challenges/web/sqli-login` → `ghcr.io/<owner>/glassbox-ctf-sqli-login`. The `<domain>` segment (`web`, `intro`) never enters a tag.

## Build and run

There is **no test suite and no CLI build/lint task** — a challenge is exercised by building its container and clicking through it in a browser. PHP is only ever run inside the container.

Local dev uses **podman** (CI uses docker buildx). Build the base chain first — `harness`, then `php` — then the challenge:

```sh
podman build -t glassbox-harness ./platform/harness/
podman build -t glassbox-php ./platform/php/
```

```sh
cd challenges/web/sqli-login && podman build -t glassbox-ctf . && podman run -it --rm -p 9000:80 glassbox-ctf
```

Then open `http://localhost:9000/` (add `?debug=1`, or use the header toggle, to see internals). Live-reload loop while editing a challenge:

```sh
while true; do find . -maxdepth 1 -type f | entr -d -r sh -c 'podman build -t glassbox-ctf . && exec podman run --rm -p 9000:80 glassbox-ctf'; done
```

Rebuild the base chain whenever you touch anything in `platform/` (editor sources, linters, `fix.php`, `lint.php`, `psalm.xml`, either Dockerfile) — challenge images layer `FROM ${BASE_IMAGE}` (default `glassbox-php`) on top of it.

CI (`.github/workflows/docker-publish.yml`) **auto-discovers** challenges: a `discover` job scans `challenges/**/Dockerfile`, then a `base` job builds+pushes `harness` then `php`, then a `challenges` matrix builds+pushes every `glassbox-ctf-<name>` for linux/amd64+arm64 on push to `main`. **Adding a challenge is just adding its folder** — there is no matrix to edit. Each challenge's family base is parsed from its own Dockerfile's `ARG BASE_IMAGE` default (a folder that does not descend from a glass-box image, like `runtime-check`, builds standalone).

## Architecture

**Platform base chain (`platform/`) — the shared runtime.**
- `platform/harness/` — `FROM php:8.5-apache`; fetches `pico.min.css`; ships `fix.php`. This is the universal web-delivery + Fix-button skeleton. `fix.php` references `codemirror-bundle.js` **by name**, so the concrete editor bundle is supplied by whichever family sits on top — a future non-PHP family can swap the editor's language without touching the harness.
- `platform/php/` — `FROM` harness; a `node:alpine` stage esbuilds `editor/cm-*.js` into the four CodeMirror bundles, installs the Psalm phar, and ships `psalm.xml` + `lint.php`. This is everything PHP-specific.

Everything under `platform/` is fetched/built at image-build time (pico.css, the Psalm phar, the esbuilt bundles) — never committed, never fetched at runtime.

**The in-browser editor + linting stack.** CodeMirror sources are `platform/php/editor/cm-*.js`, bundled by esbuild. Each attaches to a differently-marked `<textarea>`:
- `cm-init.js` → `codemirror-bundle.js` → `textarea[name='content']`, the editable PHP editor used by `fix.php`.
- `cm-sql-edit.js` / `cm-html-edit.js` → `[data-codemirror="sql-edit"|"html-edit"]`, editable views shown in debug mode (the SQL editor re-highlights strings using MySQL escaping rules, not CM's).
- `cm-php-view.js` → `[data-codemirror="php-view"]`, read-only.

Linters live in `platform/php/editor/linters.js`. The PHP linter POSTs to `lint.php`, which runs `php -l` first (fast syntax gate) then Psalm, and returns only a whitelisted set of issue types (undefined function/class/method/constant, too-few-args) as JSON diagnostics. `psalm.xml` points `projectFiles` at `/tmp` (where `lint.php` writes the snippet) and suppresses undefined-variable errors because `critical.php` snippets reference variables injected by the including page (`$db`, `$user`, `$sql`, …).

**Per-challenge anatomy.** A challenge folder is: a `Dockerfile` that is `FROM ${BASE_IMAGE}` (ARG defaults to `glassbox-php`), copies `*.php` (and `*.py` where needed), snapshots `critical.php` → `critical.orig.php`, and `chown`s to `www-data`; a page that `require`s `critical.php`; the `critical.php` vuln itself; a learner-facing `README.md` and a teacher-facing `solution.md` (see below); and, for some types, extra infrastructure:
- **SQLi challenges** additionally install `mariadb-server` + the `mysqli` extension, load `db.sql` at build, and start mariadb alongside apache. DB is `hacky`/`hacky`/`Ju5TRE4D1t`. `db.sql` seeds the "flags" (e.g. an admin password, a `hidden` table) — these baked-in secrets are intentional challenge content.
- **XSS challenges** additionally install chromium + selenium and ship `adminclicks.py`: a headless bot that sets the admin `session` cookie then visits a learner-supplied URL, simulating the admin clicking a malicious link (this is how cookie theft is demonstrated, offline, via the in-container `log.php` sink). The vulnerable sink echoes user input unescaped.

**Documentation contract (per challenge).** Every challenge folder ships:
- `README.md` — learner-facing: premise, ladder position, tasks, how to run, what the Fix/debug controls do. **No flags, payloads, or fix.**
- `solution.md` — teacher/stuck-learner-facing: a spoiler banner, the full walkthrough with payloads and flags, the fix to write into `critical.php`, and a **Professional tools** section that redoes the exploit with the industry-standard tool the course teaches (`sqlmap` for SQLi; browser DevTools + an intercepting proxy for XSS). Manual understanding first, tool second. A setup container with no vulnerability still ships a `solution.md` that says so.

Neither `.md` file is copied into the image — challenge Dockerfiles copy only what the running app needs (`*.php`/`*.py`), so keep those explicit `COPY` globs rather than `COPY . .`.

## Conventions

- **No custom CSS.** Use pico.css classes/semantics only.
- **Prefer the simplest layer:** modern HTML5-only > CSS > JavaScript > server-side. Reach for the higher layer only when the lower one can't do it.
- **No runtime cloud dependencies.** Everything a container needs must be baked in at build time; a built image must work fully offline.
- **Never commit external JS/CSS.** Download/build it during the image build instead (`pico.min.css` and the CodeMirror bundles are gitignored for this reason).
- **New challenge = own folder under `challenges/<domain>/` + Dockerfile** that starts the service when run, follows the `critical.<ext>` + debug-switch pattern, ships `README.md` + `solution.md`, and is picked up by CI automatically (no matrix edit). Draft it as an OpenSpec change first.

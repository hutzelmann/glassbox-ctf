# AGENTS.md

Guidance for AI coding agents (Claude Code and others) working in this repository. This is the single source of truth; `CLAUDE.md` just `@`-imports it.

## Purpose and scope

This is an **educational security project for defensive learning**. It teaches standard, well-documented security vulnerabilities so learners can recognize and *fix* them. The challenges span web (SQL injection, XSS) and binary exploitation (a stack-overflow `ret2win` → `ret2libc` ladder), and the project deliberately aims to keep growing to other classes of weakness. Every part is scoped to that didactic goal:

- **Sandboxed and self-contained.** Each challenge is a throwaway Docker container that runs fully offline against its own seeded data. There are no real users, no real secrets, and no third-party targets, the "flags" and credentials are fictional teaching content.
- **Well-known weaknesses only.** Textbook, well-understood bugs used in classrooms and CTFs (OWASP-class web flaws today, other domains later), not novel exploits or attack tooling aimed at real systems.
- **The point is the fix.** The whole design (the live `critical.<ext>` editor, the debug view exposing internals) exists so learners understand a vulnerability well enough to remediate it. Offensive detail here serves comprehension and defense.

Work in this repo, writing challenges, exploit examples, admin-bot simulations, remediation notes, is legitimate security education. Keep that framing when describing tasks.

## Change workflow: OpenSpec is mandatory for non-trivial changes

Non-trivial changes **must** be drafted as an OpenSpec change under `openspec/changes/<id>/` (proposal → specs → design → tasks) **before** implementation. The tooling is set up (`openspec/`, plus `.claude/` commands and skills); use `openspec new change <id>`, fill the artifacts, and `openspec validate <id> --strict` before coding. Archive with `openspec archive <id>` when done.

**Non-trivial (OpenSpec required):** adding a challenge; adding a challenge type or base image; changing the folder or CI structure; changing the glass-box contract (the `critical.<ext>` / Fix / debug-level pattern); anything that touches multiple challenges.

**Trivial (skip OpenSpec):** typo and copy fixes; a single-file bugfix within one challenge; dependency version bumps; documentation wording.

## What this is

`glassbox-ctf` is a set of self-contained, Dockerized security teaching challenges. Each challenge is a deliberately vulnerable app. The "glass box" idea: the learner can watch the exploit unfold **at the victim server** and then patch the hole live in the browser.

Two learner-facing mechanisms drive almost every design decision:

- **The `critical.<ext>` pattern.** The single vulnerable snippet lives in `critical.<ext>` (the extension tracks the challenge language, `critical.php` for web, `critical.c` for the binary family), `require`d/`#include`d by a page or program. At build time it is snapshotted to `critical.orig.<ext>`. `fix.php` (shipped by the harness image) is an in-browser CodeMirror editor that saves back to it (Restore reverts to the `.orig`). So the learner edits the *real running code* of just the vulnerable part. Everything the learner should not touch stays outside `critical.<ext>`. **`fix.php` is config-driven:** a per-challenge `glassbox.php` (optional) names the target file, an optional **Save-hook** to run after writing, and optional extra editable **fields**; absent it, the target defaults to `critical.php` with no hook (today's PHP behavior). The Save-hook is how a compiled target **recompiles on Save** (see the native family), and it is safe-by-construction: the hook compiles to a temp file and swaps the live artifact only on success, so a failed build surfaces the compiler errors and leaves the last working artifact running, the container never bricks.
- **The debug dial.** Three cumulative levels selected by a sticky `?debug=<n>`: **0 Challenge** (the challenge as shipped), **1 Hints**, **2 Debug**. The placement rule is *symptom versus cause*: level 1 tells the learner **how their own attempt failed** (a language-appropriate CodeMirror editor with linting on the input; the raw `$db->error`; the admin bot's JS console errors; `sqli-blind`'s timing panel; on the binary rungs the byte/endianness calculator and the live stack-frame table of *their* bytes), level 2 discloses **what the target is doing** (the assembled `$sql`, returned rows, the admin's rendered page, the raw request, the vulnerable source; on the binary rungs the disassembly, the symbol/gadget addresses the payload jumps to, `checksec`, and the memory map). Nothing a learner could already read in their own browser is held back to level 2, and no challenge may offer a level with nothing behind it.

  The plumbing is `platform/harness/debug.php`, which every page `require`s: it exports `$debugLevel` (clamped int, so `?debug=banana`/`7`/`-1` land on a valid level), `$debugSuffix` (appended to internal links, unchanged in role), and `debug_switch()` (the pico `<select>` in the header). Gate panels with `if ($debugLevel >= 1)` / `>= 2`; level-specific CodeMirror bundles are `<script>`-included the same way. Never re-derive the level from `$_GET['debug']` in a page.

## Repository layout

```
challenges/
  intro/            on-ramp containers (not vuln lessons)
    hello/          the first flag + a tour of the glass box
    runtime-check/  a minimal alpine smoke test: "does my container runtime work?"
  web/              web-delivered vulnerability challenges
    sqli-login/  sqli-blind/  sqli-insert/     (SQLi ladder)
    xss-light/   xss-shop/    xss-cookie/      (XSS ladder)
  binary/           binary-exploitation challenges (x86-64, published amd64-only)
    ret2win/     ret2libc/                     (binary ladder)
platform/           shared base images (the family chain)
  harness/          FROM php:8.5-apache: pico.css + fix.php + debug.php (web delivery, Fix, debug dial)
  php/              FROM harness: CodeMirror bundles + Psalm + lint.php (the PHP family)
  native/           FROM harness: gcc + C editor + gcc -fsyntax-only lint + native-run.php (the C family)
openspec/           OpenSpec changes and specs
```

A challenge's **published image name is its folder basename**, flat and path-independent: `challenges/web/sqli-login` → `ghcr.io/<owner>/glassbox-ctf-sqli-login`. The `<domain>` segment (`web`, `intro`) never enters a tag.

## Build and run

There is **no test suite and no CLI build/lint task**: a challenge is exercised by building its container and clicking through it in a browser. PHP is only ever run inside the container.

Local dev uses **podman** (CI uses docker buildx). Build the base chain first, `harness`, then the family the challenge needs (`php` and/or `native`), then the challenge:

```sh
podman build -t glassbox-harness ./platform/harness/
podman build -t glassbox-php ./platform/php/
podman build -t glassbox-native ./platform/native/
```

```sh
cd challenges/web/sqli-login && podman build -t glassbox-ctf . && podman run -it --rm -p 9000:80 glassbox-ctf
```

Binary challenges are x86-64 and published amd64-only; on an arm64 host they run under emulation. The runner executes the binary as a subprocess (`proc_open`, sandboxed with `timeout`/`ulimit`); all debug introspection is static (`objdump`/`nm`/readelf) or payload-derived, so the plain `run` command needs no extra capabilities.

Then open `http://localhost:9000/` (add `?debug=1` for Hints or `?debug=2` for full internals, or use the header dial). Live-reload loop while editing a challenge:

```sh
while true; do find . -maxdepth 1 -type f | entr -d -r sh -c 'podman build -t glassbox-ctf . && exec podman run --rm -p 9000:80 glassbox-ctf'; done
```

Rebuild the base chain whenever you touch anything in `platform/` (editor sources, linters, `fix.php`, `debug.php`, `lint.php`, `psalm.xml`, `nbuild`, `native-run.php`, any Dockerfile), challenge images layer `FROM ${BASE_IMAGE}` (default `glassbox-php` or `glassbox-native`) on top of it. **A stale podman COPY layer can silently ship the old `fix.php`/bundle;** if a `platform/` edit doesn't seem to take effect, rebuild the affected base image with `--no-cache`.

CI (`.github/workflows/docker-publish.yml`) **auto-discovers** challenges: a `discover` job scans `challenges/**/Dockerfile`, then a `base` job builds+pushes `harness`, then the families `php` and `native`, then a `challenges` matrix builds+pushes every `glassbox-ctf-<name>` on push to `main`. **Adding a challenge is just adding its folder**: there is no matrix to edit. Each challenge's family base is parsed from its own Dockerfile's `ARG BASE_IMAGE` default (a folder that does not descend from a glass-box image, like `runtime-check`, builds standalone). Publish platforms default to `linux/amd64,linux/arm64`; a challenge may pin its own set with `LABEL org.glassbox.platforms="…"` (the binary rungs pin `linux/amd64` so their x86-64 addresses match the walkthrough).

## Architecture

**Platform base chain (`platform/`), the shared runtime.**
- `platform/harness/`: `FROM php:8.5-apache`; fetches `pico.min.css`; ships `fix.php`, `debug.php` and `remember-form-input.js`. This is the universal web-delivery + Fix-button + debug-dial skeleton (`debug.php` is language-agnostic, so a non-PHP family inherits the dial unchanged). `fix.php` references `codemirror-bundle.js` **by name**, so the concrete editor bundle is supplied by whichever family sits on top; a future non-PHP family can swap the editor's language without touching the harness.
- `platform/php/`: `FROM` harness; a `node:alpine` stage esbuilds `editor/cm-*.js` into the five CodeMirror bundles, installs the Psalm phar, and ships `psalm.xml` + `lint.php`. This is everything PHP-specific.
- `platform/native/`: `FROM` harness; a `node:alpine` stage esbuilds a C-mode editor bundle (`@codemirror/lang-cpp` → `codemirror-bundle.js`) + a read-only `codemirror-c-view.js`; installs `gcc` + binutils; and ships `nbuild` (the shared recompile helper: allowlist the flags, `gcc` as an argv array with hook-owned `-o`/input, compile to a temp file, atomic `mv` on success), a C `lint.php` (`gcc -fsyntax-only` on the assembled unit, `#line`-mapped), and `native-run.php` (shared runner/introspection: sandboxed run, payload decode, hexdump, `objdump`/`nm`/readelf-`checksec`, the stack-table renderer). No gdb, exploitation happens locally against the downloadable binary. `checksec` is computed from readelf/nm (no external script). The debug dial applies here too: level-1 panels (byte calculator, your-bytes stack table) versus level-2 panels (disassembly, symbols/gadgets, `checksec`, memory map, program source).

Everything under `platform/` is fetched/built at image-build time (pico.css, the Psalm phar, the esbuilt bundles), never committed, never fetched at runtime.

**The in-browser editor + linting stack.** CodeMirror sources are `platform/php/editor/cm-*.js`, bundled by esbuild. Each attaches to a differently-marked `<textarea>`:
- `cm-init.js` → `codemirror-bundle.js` → `textarea[name='content']`, the editable PHP editor used by `fix.php`.
- `cm-sql-input.js` → `[data-codemirror="sql-input"]`, the **level-1** editor for SQLi payload fields: form-wired, MySQL highlighting, and `sqlBadCommentLinter` only. `sqlUnterminatedStringLinter` is deliberately absent, because an unterminated string is what a breakout payload produces.
- `cm-sql-edit.js` / `cm-html-edit.js` → `[data-codemirror="sql-edit"|"html-edit"]`, editable views (`sql-edit` is the **level-2** view of the server's assembled query; `html-edit` is the level-1 payload editor on XSS pages and the level-2 admin-page dump).
- `cm-php-view.js` → `[data-codemirror="php-view"]`, read-only.
- `mysql-strings.js`: MySQL string-range parsing and the highlighter shared by both SQL bundles.
- `cm-theme.js`: the colour theme **every** bundle in the family installs, right after `basicSetup`. CodeMirror ships no theme of its own beyond a hardcoded light one, so without this the gutter stays `#f5f5f5` on a dark page. Chrome colours are pico variables (so pico stays the only palette); the token colours, which pico has none, use CSS `light-dark()`, which resolves against the `color-scheme` pico sets on `:root`. Nothing reads `matchMedia`, so a scheme change needs no reload. The palette is also published as `--gb-cm-*` custom properties, which is where `mysql-strings.js` gets its string colour.

Every bundle mounts with `querySelectorAll`, not `querySelector`: a page can have more than one editor (`sqli-login` upgrades both username and password).

Linters live in `platform/php/editor/linters.js`. The PHP linter POSTs to `lint.php`, which runs `php -l` first (fast syntax gate) then Psalm, and returns only a whitelisted set of issue types (undefined function/class/method/constant, too-few-args) as JSON diagnostics. `psalm.xml` points `projectFiles` at `/tmp` (where `lint.php` writes the snippet) and suppresses undefined-variable errors because `critical.php` snippets reference variables injected by the including page (`$db`, `$user`, `$sql`, …).

The **native family** mirrors this with its own `platform/native/editor/` (`cm-init.js` → the C `codemirror-bundle.js`, `cm-c-view.js` → the read-only `[data-codemirror="c-view"]`, `linters.js` → a client `@codemirror/lang-cpp` tree linter + a server `cLinter`, `cm-theme.js` → its copy of the shared theme). Its `lint.php` assembles the posted snippet into the challenge's real `main.c` and runs `gcc -fsyntax-only`, mapping diagnostics back to `critical.c` via the `#line` directive. Because the harness references `codemirror-bundle.js`/`lint.php` **by name**, `fix.php` is language-agnostic, the family underneath supplies the C versions.

**Per-challenge anatomy.** A challenge folder is: a `Dockerfile` that is `FROM ${BASE_IMAGE}` (ARG defaults to `glassbox-php`), copies only the files the running app needs, snapshots `critical.<ext>` → `critical.orig.<ext>`, and `chown`s to `www-data`; a page (or program) that loads `critical.<ext>`; the `critical.<ext>` vuln itself; a learner-facing `README.md` and a teacher-facing `solution.md` (see below); and, for some types, extra infrastructure:
- **SQLi challenges** additionally install `mariadb-server` + the `mysqli` extension, load `db.sql` at build, and start mariadb alongside apache. DB is `hacky`/`hacky`/`Ju5TRE4D1t`. `db.sql` seeds the "flags" (e.g. an admin password, a `hidden` table), these baked-in secrets are intentional challenge content.
- **XSS challenges** additionally install chromium + selenium and ship `adminclicks.py`: a headless bot that sets the admin `session` cookie then visits a learner-supplied URL, simulating the admin clicking a malicious link (this is how cookie theft is demonstrated, offline, via the in-container `log.php` sink). The vulnerable sink echoes user input unescaped.
- **Binary (native) challenges** are `FROM ${BASE_IMAGE:-glassbox-native}`. They ship a fixed, uneditable `main.c` (holds `main()`, the goal, a `win()` or `system@plt`/`"/bin/sh"`/gadget, and `#include`s `critical.c` behind a `#line 1 "critical.c"` directive so compile errors map to the snippet), the `critical.c` vuln (a too-large `read` into a small stack buffer), a `build.sh` (`exec nbuild <out> main.c`), a `build.flags` (default `-fno-stack-protector -no-pie -O0 -g`), a `glassbox.php` pointing `fix.php` at `critical.c` with an allowlisted compiler-flags field, and an `index.php` runner that feeds a raw byte payload (synced escape/hex fields; hex is the wire value) to the binary via `native-run.php` and shows output + debug internals. The Dockerfile snapshots `critical.c`/`build.flags`, builds the initial binary, `LABEL`s `org.glassbox.platforms="linux/amd64"`, and (ret2libc) writes a `/flag` readable only via code execution. Save recompiles; a failed compile keeps the last-good binary. The intended exploit only prints a flag or spawns an in-container shell that reads the payload's leftover stdin, same throwaway-container threat model as arbitrary PHP via Fix.

**Documentation contract (per challenge).** Every challenge folder ships:
- `README.md`: learner-facing: premise, ladder position, tasks, how to run, what the Fix/debug controls do. **No flags, payloads, or fix.**
- `solution.md`: teacher/stuck-learner-facing: a spoiler banner, the full walkthrough with payloads and flags, the fix to write into `critical.<ext>`, and a **Professional tools** section that redoes the exploit with the industry-standard tool the course teaches (`sqlmap` for SQLi; browser DevTools + an intercepting proxy for XSS; `pwntools` + `gdb`/pwndbg/gef + `checksec` + `objdump`/`nm` + `ROPgadget` for binary). Manual understanding first, tool second. A setup container with no vulnerability still ships a `solution.md` that says so.

Neither `.md` file is copied into the image, challenge Dockerfiles copy only what the running app needs (`*.php`/`*.py`, or `*.c`/`build.*` for the native family), so keep those explicit `COPY` globs rather than `COPY . .`.

## Conventions

- **Debug is optional, never on the critical path.** `?debug=1` is a teaching aid for *understanding* a bug, not a step in exploiting it. Every challenge MUST be fully solvable without it, and nothing in the normal flow, page header/intro text, result banners, `README.md`, or `solution.md`: may require or nudge the learner to turn it on (no "turn on debug to…", no placeholder that hands over the payload, no header that states the key facts like a buffer size). Solutions derive their facts from the challenge itself (the running app, or the downloadable binary + standard tools like `nm`/`objdump`/Ghidra/`pwntools`) and mention the debug view only as an optional aside. Debug may reveal as much as it likes *once opened*, the rule is about not steering the learner into it.
- **Linear git history, no merge commits.** Land work by rebasing the branch onto `main` (`git rebase main` in the worktree) and then fast-forwarding: `git merge --ff-only <branch>`. Never merge `main` into a feature branch to catch up, rebase instead; that is where this repo's one historical merge commit came from. The repo config enforces it (`merge.ff = only` refuses a non-fast-forward merge, `pull.rebase = true` makes `git pull` rebase), and a pull request should land through GitHub's rebase or squash option rather than its merge-commit one. `git merge --no-ff` still overrides the config, so use it only when a merge commit is genuinely wanted.
- **Plain punctuation in prose.** Do not use em dashes (Unicode U+2014) or a bare `---` anywhere in human text (READMEs, solutions, page copy, code comments). Use commas, colons, parentheses, or separate sentences instead. A `--` that belongs to a command-line flag or a shell command is fine, the rule is about punctuation in prose.
- **No custom CSS.** Use pico.css classes/semantics only. The one thing pico cannot reach is the inside of a CodeMirror view, which is styled by editor configuration rather than by page CSS: that lives in each family's `cm-theme.js` and is written from pico's own variables, so it is not a second palette.
- **Prefer the simplest layer:** modern HTML5-only > CSS > JavaScript > server-side. Reach for the higher layer only when the lower one can't do it.
- **No runtime cloud dependencies.** Everything a container needs must be baked in at build time; a built image must work fully offline.
- **Never commit external JS/CSS.** Download/build it during the image build instead (`pico.min.css` and the CodeMirror bundles are gitignored for this reason).
- **Forms remember what was typed.** `remember-form-input.js` (shipped by the harness) refills every form on the page from `sessionStorage`, with no per-field configuration, writing only into fields the server left blank, so server-rendered state always wins. Add the tag to the `<head>` of any page with a `<form>`; skip pages without one, and never add it to `fix.php`, whose fields always show the file currently on disk, including when that file is legitimately empty (an emptied snippet, or an empty `build.flags`, which `nbuild` accepts): refilling one of those would make the editor disagree with what is actually running, and the next Save would write the stale text back. Load it **without `defer`** so its `DOMContentLoaded` listener is registered before the deferred CodeMirror bundles run; otherwise an editor mounts from the still-empty textarea and the restore is silently lost. Any editor bundle whose textarea sits inside a form must sync its document into that textarea on every change, not only on submit, or leaving the page without submitting (the debug dial does exactly that) stores an empty value. `data-no-restore` on a form or field opts out.
- **New challenge = own folder under `challenges/<domain>/` + Dockerfile** that starts the service when run, follows the `critical.<ext>` + debug-level pattern, ships `README.md` + `solution.md`, and is picked up by CI automatically (no matrix edit). Draft it as an OpenSpec change first.

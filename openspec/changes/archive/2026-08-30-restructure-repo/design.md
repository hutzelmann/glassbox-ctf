## Context

See proposal.md — Why. Today all challenges are flat top-level folders, `base/`
is a single `php:8.5-apache` image mixing shipped files (`fix.php`, `lint.php`,
`psalm.xml`) with build inputs (`cm-*.js`, `linters.js`, `package.json`), and
`.github/workflows/docker-publish.yml` carries a hand-maintained matrix. A
web-fronted binary family (ret2win) is imminent and needs the same Apache/PHP
delivery + Fix editor but a different toolchain (gcc, a C editor, recompile on
Save). The current single base cannot host it without duplication.

## Goals / Non-Goals

**Goals:**
- One domain-grouped tree for challenges; one `platform/` tree for base images.
- A base chain whose shared seam (`harness`) is reusable by unrelated families.
- CI that needs no edit when a challenge is added.
- Published tags stay `glassbox-ctf-<name>`, stable and path-independent.
- Per-challenge docs with a clean learner/teacher split.

**Non-Goals:**
- The ret2win challenge and the `platform/native/` family — deferred to their own
  OpenSpec change. This change only leaves the door open.
- Generalizing `fix.php` to arbitrary `critical.<ext>` targets and Save-triggered
  recompilation — that mechanism is designed and proven in the ret2win change.
- Producing real screenshots/GIFs for the README — placeholder slots only.

## Decisions

### D1 — Two-level tree, tag decoupled from path
`challenges/<domain>/<challenge>/`; the published image name is the challenge
folder's basename (`glassbox-ctf-<basename>`), never the path. The `<domain>`
grouping is for humans and the catalog; it never enters a tag. Alternative
(path-derived tags like `glassbox-ctf-web-sqli-login`) was rejected: it adds a
redundant segment (the vuln class is already in the `sqli-`/`xss-` prefix) and
couples tags to folder moves.

### D2 — `harness` / `php` split
- `platform/harness/` — `FROM php:8.5-apache`; fetches `pico.min.css`; ships
  `fix.php`. This is the universal web-delivery + Fix-button skeleton. `fix.php`
  references `codemirror-bundle.js` and `pico.min.css` **by name**; the concrete
  bundle is supplied by whichever family sits on top, so a C-mode editor can
  replace the PHP-mode one without touching the harness.
- `platform/php/` — `FROM` harness; a `node:alpine` build stage esbuilds
  `editor/cm-*.js` → the four CodeMirror bundles; installs the Psalm phar; ships
  `psalm.xml` and `lint.php`. This is everything PHP-specific: the PHP/HTML/SQL
  editor modes and PHP static analysis.

Rationale: every family is web-delivered through PHP/Apache (even the binary
family's front end is a PHP page), so PHP/Apache belongs in the shared harness;
what differs between families is the *analysis toolchain and editor language*,
which is exactly the `php`/`native` layer. A pure "assets image consumed via
`COPY --from=`" was rejected because it would force `fix.php` to be duplicated
into every family; a `FROM` chain shares it once.

### D3 — CI auto-discovery
A first job enumerates `challenges/**/Dockerfile`, and for each emits
`{name: <basename>, path: <dir>}` plus the base image parsed from that
Dockerfile's `ARG BASE_IMAGE=<default>` line (empty ⇒ standalone). It outputs two
JSON matrices via `GITHUB_OUTPUT`: the base chain (`harness`, then `php`) and the
challenges. Jobs: `discover` → `base` (harness then php, ordered by `needs`) →
`challenges` (matrix, `needs: base`). Family base images are passed to challenge
builds via `build-args: BASE_IMAGE=ghcr.io/<owner>/glassbox-ctf-<family>:latest`,
overriding the local default. Standalone challenges ignore the arg.

### D4 — `fix.php` stays `critical.php`-targeted this change
All current challenges are PHP, so `fix.php` continues to read/write
`critical.php` / `critical.orig.php`. The spec is written as `critical.<ext>` so
the contract is already general; the code generalization ships with ret2win,
which is the first non-`.php` target. No current behavior changes.

### D5 — Docs excluded from images by construction
Every challenge Dockerfile keeps an explicit `COPY *.php` (and `*.py` where
needed) rather than `COPY . .`, so `README.md`/`solution.md` are never copied into
the served root. This is asserted, not incidental — the docs spec depends on it.

### D6a — Professional-tool section sourced from the taught toolchain
`solution.md` files present the industry-standard tool the lecture actually
teaches, so the repo matches the course: `sqlmap` for the SQLi challenges (the
handout gives the exact `--data … --dump` invocation), and browser DevTools +
an intercepting proxy for XSS (the handout teaches DevTools-level inspection and
`htmlspecialchars`/CSP/HttpOnly defenses, not a named offensive tool). The
agentic `HexStrike AI` capstone (revisit every challenge with an automated
LLM pentest) is mentioned once at the repo level, not per challenge. A tool is
never invented where none genuinely fits (e.g. the intro `hello`).

### D6 — Moves preserve history
All relocations use `git mv` so `git log --follow` still works across the
restructure; `base/` is split by moving its files into `platform/harness` and
`platform/php` and rewriting the two Dockerfiles.

## Risks / Trade-offs

- [Auto-discovery sweeps a folder that isn't meant to publish] → discovery keys
  on `challenges/**/Dockerfile` only; `platform/` and `openspec/` are outside the
  scanned root, so only real challenges match.
- [Parsing `ARG BASE_IMAGE` is brittle] → the default is a fixed one-line form in
  every glass-box Dockerfile; the parser treats a missing/foreign value as
  standalone and simply builds with no `BASE_IMAGE` override, which is safe.
- [Renaming/removing published tags breaks the old class sheets] → accepted; the
  semester is over and the reshape targets a clean future. The catalog and
  quick-start use the new tags.
- [Harness publishes an image nobody runs directly] → accepted; it is the shared
  `FROM` target and keeps CI jobs able to pull it across steps.

## Migration Plan

1. `git mv` challenges into `challenges/<domain>/<challenge>/`; `rm -r` xss-chat.
2. Split `base/` → `platform/harness/` + `platform/php/{editor,…}`; rewrite both
   Dockerfiles; update challenge `ARG BASE_IMAGE` defaults to `glassbox-php`.
3. Rewrite the CI workflow to discover + build the chain + challenges.
4. Write per-challenge `README.md` + `solution.md`; rewrite top `README.md`;
   rewrite `AGENTS.md`; add `docs/img/` placeholders; update `.gitignore`.
5. Verify: `podman build` harness → php → one web challenge → run → click through
   with `?debug=1` and the Fix button; grep built image to confirm no `*.md`.

Rollback: the change is a single reviewable diff on a branch; revert the branch.

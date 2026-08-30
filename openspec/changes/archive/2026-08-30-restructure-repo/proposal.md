## Why

The repo grew by accretion: every challenge is a flat top-level folder, the CI
matrix is hand-maintained (a footgun already flagged in AGENTS.md), the shared
`base/` image mixes shipped files with build inputs, one challenge (`xss-chat`)
is a half-migrated legacy outlier, and the top `README.md` is a bare developer
note with no overview for the learners and teachers who are the actual audience.
Nothing documents how to *solve* a challenge, and the scope is about to widen
beyond web (a binary/ret2win family is imminent), which the current single
`php:8.5-apache` base cannot host. This change lays a clean, domain-agnostic
foundation before that growth, and adopts OpenSpec so future non-trivial changes
are drafted before they are built.

## What Changes

- **BREAKING** Move every challenge into a two-level tree
  `challenges/<domain>/<challenge>/` (`intro/`, `web/`). Published image names
  stay flat (`glassbox-ctf-<name>`), decoupled from folder paths.
- **BREAKING** Replace the single `base/` image with a `platform/` chain:
  `harness/` (php:8.5-apache + pico + `fix.php`, the shared web-delivery + Fix
  skeleton) → `php/` (FROM harness: CodeMirror bundles, Psalm, `lint.php`). The
  `native/` sibling for the binary family is deliberately deferred to its own
  change; the door is left open.
- **BREAKING** Rename the `debug/` smoke-test to `challenges/intro/runtime-check`
  (published `glassbox-ctf-runtime-check`) to end the "debug" name collision with
  the `?debug=1` learner feature.
- Remove `xss-chat` (superseded by `xss-cookie`, which is its correct
  implementation; it also shipped an unintended command-injection bug).
- Rewrite CI to **auto-discover** challenges by scanning for `Dockerfile`s and to
  build the base chain (`harness` → `php`) before challenges; family is parsed
  from each Dockerfile's `ARG BASE_IMAGE` default, standalone-tolerant.
- Add per-challenge docs: `README.md` (learner-facing tasks, no spoilers) and
  `solution.md` (payloads, flags, and the fix; never shipped in the image). Each
  `solution.md` also shows the professional-tool path (e.g. `sqlmap`, DevTools)
  the learner graduates to once they understand the bug manually.
- Rewrite the top `README.md` as an advertising overview + quick-start + a
  domain-grouped challenge catalog (ladder order within each group), plus a
  teacher note and a contributor pointer. Move all build/architecture detail to
  `AGENTS.md`.
- Adopt OpenSpec and make it mandatory for non-trivial changes; record the
  workflow and its threshold in `AGENTS.md`.

## Capabilities

### New Capabilities
- `challenge-structure`: the repository layout contract — where challenges live,
  the `platform/` base-image family chain, image-naming rules, and the
  generalized glass-box conventions (`critical.<ext>` + Fix editor + `?debug=1`)
  every challenge must follow.
- `challenge-docs`: the documentation contract — every challenge folder ships a
  spoiler-free `README.md` of tasks and a complete `solution.md`; neither is
  copied into the container image.
- `build-and-publish`: the CI contract — auto-discover challenges, build the base
  chain first, and publish every image as `glassbox-ctf-<name>` for
  linux/amd64+arm64 on push to `main`.
- `contribution-workflow`: the process contract — non-trivial changes MUST be
  drafted as an OpenSpec change before implementation; the threshold is defined.

### Modified Capabilities
<!-- None: no prior openspec/specs/ existed before this change. -->

## Impact

- **Affected files**: every challenge folder (moved), `base/` (split into
  `platform/harness` + `platform/php`), `.github/workflows/docker-publish.yml`
  (rewritten), `README.md` (rewritten), `AGENTS.md` (rewritten), `CLAUDE.md`
  (import unchanged), `.gitignore` (path updates), `TODOs.md` (reconciled).
- **Removed**: `xss-chat/`, top-level `debug/`, top-level `base/`.
- **External contract**: published `ghcr.io` image tags change for the renamed
  smoke-test and the new base images; the old class exercise sheets reference
  some old tags and will need reprinting (accepted — the semester is over).
- **Tooling**: OpenSpec added under `openspec/` with generated `.claude/`
  commands and skills.
- **No runtime code inside `critical.php` snippets changes**; the vulnerabilities
  themselves are preserved verbatim.

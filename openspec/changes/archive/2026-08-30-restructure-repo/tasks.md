## 1. Platform base chain

- [x] 1.1 Create `platform/harness/` with a Dockerfile `FROM php:8.5-apache` that fetches `pico.min.css` and copies `fix.php`
- [x] 1.2 Move `base/fix.php` into `platform/harness/`
- [x] 1.3 Create `platform/php/` with `editor/` holding `package.json` + `cm-init.js` + `cm-php-view.js` + `cm-html-edit.js` + `cm-sql-edit.js` + `linters.js`, and top-level `lint.php` + `psalm.xml`
- [x] 1.4 Write `platform/php/Dockerfile` `FROM ${BASE_IMAGE:-glassbox-harness}`: node build stage esbuilds the CodeMirror bundles, install Psalm, copy bundles + `lint.php` + `psalm.xml`
- [x] 1.5 Remove the old `base/` folder
- [x] 1.6 Build locally: `glassbox-harness` then `glassbox-php`; confirm both succeed

## 2. Challenge tree moves

- [x] 2.1 `git mv` `hello` → `challenges/intro/hello`; `debug` → `challenges/intro/runtime-check`
- [x] 2.2 `git mv` `sqli-login` `sqli-blind` `sqli-insert` → `challenges/web/`
- [x] 2.3 `git mv` `xss-light` `xss-shop` `xss-cookie` → `challenges/web/`
- [x] 2.4 `git rm -r` `xss-chat`
- [x] 2.5 Update every moved challenge Dockerfile: `ARG BASE_IMAGE=glassbox-php` (runtime-check keeps `FROM alpine`); confirm `COPY *.php`/`*.py` excludes markdown
- [x] 2.6 Build one web challenge locally against `glassbox-php`, run it, click through with `?debug=1` and the Fix button

## 3. CI pipeline

- [x] 3.1 Rewrite `.github/workflows/docker-publish.yml`: `discover` job emits base + challenge JSON matrices from `challenges/**/Dockerfile` and parsed `ARG BASE_IMAGE`
- [x] 3.2 `base` job builds+pushes `harness` then `php` (ordered)
- [x] 3.3 `challenges` job (`needs: base`) builds+pushes each `glassbox-ctf-<name>` for amd64+arm64, passing the family `BASE_IMAGE`
- [x] 3.4 Confirm `runtime-check` (standalone) builds without a family base

## 4. Per-challenge documentation

- [x] 4.1 Mine the solutions PDF (chapter 5) and the lecture handout (tool references: `sqlmap`, DevTools, HexStrike) and cross-check each walkthrough against the actual container code; note any drift
- [x] 4.2 `challenges/intro/hello`: `README.md` + `solution.md`
- [x] 4.3 `challenges/intro/runtime-check`: `README.md` + `solution.md` ("no solution needed")
- [x] 4.4 `challenges/web/sqli-login`: `README.md` + `solution.md`
- [x] 4.5 `challenges/web/sqli-blind`: `README.md` + `solution.md`
- [x] 4.6 `challenges/web/sqli-insert`: `README.md` + `solution.md`
- [x] 4.7 `challenges/web/xss-light`: `README.md` + `solution.md`
- [x] 4.8 `challenges/web/xss-shop`: `README.md` + `solution.md`
- [x] 4.9 `challenges/web/xss-cookie`: `README.md` + `solution.md`
- [x] 4.10 Consistency pass: identical section structure, tone, and spoiler banners across all challenge docs

## 5. Top-level documentation

- [x] 5.1 Rewrite `README.md`: hero + "why it's different" + quick-start + domain-grouped catalog (ladder order, no difficulty column) + teacher note (flags in repo → LMS = participation, not grading) + contributor pointer
- [x] 5.2 Add `docs/img/` with labelled placeholder slots referenced by the README
- [x] 5.3 Rewrite `AGENTS.md`: new build/run paths + base chain order, new architecture (harness/php, `critical.<ext>`, README+SOLUTION contract), CI auto-discovery, OpenSpec mandate + threshold; drop the xss-chat legacy note
- [x] 5.4 Reconcile `TODOs.md` with the new state (mark done / restate remaining)
- [x] 5.5 Update `.gitignore` for new paths (`pico.min.css`, `node_modules`, bundles)

## 6. Verify and archive

- [x] 6.1 `openspec validate restructure-repo --strict` passes
- [x] 6.2 Final local smoke: harness → php → one sqli + one xss challenge build & run; grep a built image to confirm no `*.md` shipped
- [ ] 6.3 Review the full diff; then archive the change with `openspec archive restructure-repo`

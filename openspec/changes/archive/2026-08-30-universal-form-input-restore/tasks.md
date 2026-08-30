## 1. Harness helper

- [x] 1.1 Write `platform/harness/remember-form-input.js` with the "not part of any challenge" header comment
- [x] 1.2 Implement save on `pagehide` and restore on `DOMContentLoaded`, keyed by `form-input:<pathname>|<sorted editable names>`
- [x] 1.3 Restore only blank fields; support `data-no-restore` on form or field; skip forms with an empty signature
- [x] 1.4 Dispatch the `input`/`change` event a refill would have raised, so page-level field mirrors stay in step
- [x] 1.5 Wrap everything in `try/catch` so a storage failure can never break a page
- [x] 1.6 Add `remember-form-input.js` to the `COPY` line in `platform/harness/Dockerfile`

## 2. Editor bundles

- [x] 2.1 `platform/php/editor/cm-html-edit.js`: sync the document into the textarea on every change
- [x] 2.2 `platform/php/editor/cm-sql-input.js`: same, for the level-1 SQLi payload editors
- [x] 2.3 Confirm no other bundle needs it (`sql-edit`, `php-view`, `c-view` are outside forms; `cm-init.js` serves `fix.php`)

## 3. Challenge pages

- [x] 3.1 `sqli-login/index.php`: add the `<head>` tag, delete the inline restore script
- [x] 3.2 `sqli-blind/index.php`: add the `<head>` tag, delete the inline restore script
- [x] 3.3 `xss-shop/index.php`: add the `<head>` tag, delete the inline restore script, drop `value="0"` from the quantity inputs
- [x] 3.4 `sqli-insert/index.php`: add the `<head>` tag
- [x] 3.5 `xss-light/index.php`: add the `<head>` tag
- [x] 3.6 `xss-cookie/chat.php`: add the `<head>` tag
- [x] 3.7 `xss-cookie/search.php`: add the `<head>` tag
- [x] 3.8 `binary/ret2win/index.php`: add the `<head>` tag
- [x] 3.9 `binary/ret2libc/index.php`: add the `<head>` tag
- [x] 3.10 Confirm `hello/index.php`, `xss-cookie/index.php`, `log.php` and `fix.php` are left without the tag

## 4. Documentation

- [x] 4.1 `AGENTS.md`: list the helper in the harness contents under Platform base chain
- [x] 4.2 `AGENTS.md`: mention the `<head>` tag in Per-challenge anatomy
- [x] 4.3 `AGENTS.md`: add a Conventions bullet covering the missing `defer` and the in-form editor sync rule
- [x] 4.4 `README.md`: one sentence in Quick start describing remembered input
- [x] 4.5 Keep every added line free of em dashes, per the Plain punctuation convention

## 5. Verification

- [x] 5.1 Build `glassbox-harness`, then `glassbox-php` and `glassbox-native`, then every challenge image
- [x] 5.2 Each form page, at levels 0, 1 and 2: submit, return, values are back
- [x] 5.3 Fill a form, move the debug dial, values survive across the level change
- [x] 5.4 `xss-shop` at level 1: the comment restores into the CodeMirror editor and submits correctly
- [x] 5.5 `sqli-login` at level 1: both payload editors restore, and survive the dial without submitting
- [x] 5.6 `ret2win`: enter a payload, visit Fix, come back, the hex field and the escape view are both repopulated
- [x] 5.7 `fix.php`: Save a broken snippet, then Restore Original yields the pristine snippet
- [x] 5.8 `search.php?q=foo`: the field shows `foo`, never a stale stored value
- [x] 5.9 The debug dial itself is never restored or altered

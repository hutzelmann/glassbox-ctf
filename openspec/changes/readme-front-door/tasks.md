## 1. Screenshots

- [x] 1.1 Run the `sqli-login` image locally and drive it to debug level 2 with a payload that breaks the query without solving it
- [x] 1.2 Capture the debug view (assembled SQL plus the database error) at ~1200px wide, no browser chrome, in light and dark colour schemes
- [x] 1.3 Capture the Fix editor holding the shipped `critical.php`, same width and both schemes
- [x] 1.4 Verify no capture contains a working payload, a flag, or a patched `critical.php`
- [x] 1.5 Crop and optimise the four PNGs, commit them under `assets/`
- [x] 1.6 Ship the capture as `assets/shots.mjs`: own chromium with a throwaway profile, both colour schemes, padding and compression, so a re-shoot is one command

## 2. README rewrite

- [x] 2.1 Title, existing tagline, badge row (CI, MIT, platforms, GHCR)
- [x] 2.2 Two thumbnails side by side, each a `<picture>` pair wrapped in a link to the full-size file, with alt text
- [x] 2.3 Hook cut to four or five lines in the current voice
- [x] 2.4 `## Quick start`: both routes as equals (Podman Desktop click path and the terminal command), the runtime-check pointer as half a sentence, no second code block
- [x] 2.5 `## See the internals, then fix them`: three-row dial table, the Fix editor, one line on toggling compiler protections on the binary rungs
- [x] 2.6 `## Challenges`: one table (Challenge, Track, You learn), image-name rule stated once above it
- [x] 2.7 `## For teachers`: three bullets, keeping the flags-are-public grading warning and the capstone idea
- [x] 2.8 State that every challenge folder ships a `solution.md`, with a link, where a stuck learner will see it
- [x] 2.9 `## Contributing`: four lines pointing at `AGENTS.md` and OpenSpec, build code blocks deleted
- [x] 2.10 `## A note on intent`: two sentences
- [x] 2.11 Delete the form-memory paragraph, the binary blockquote and its duplicate run command, and the per-challenge build blocks

## 3. Conventions

- [x] 3.1 Add one `AGENTS.md` Conventions bullet: README images live in `assets/`, committed, light and dark pairs, spoiler-free, re-shot when the pictured UI changes

## 4. Verification

- [x] 4.1 No em dash or bare `---` in the new prose
- [x] 4.2 Every catalog row links to an existing challenge folder, and every challenge folder has a row
- [x] 4.3 Every image path resolves and every `<picture>` pair names both schemes
- [x] 4.4 Render the README (GitHub preview or an equivalent renderer) and confirm the thumbnails sit side by side and click through
- [x] 4.5 `openspec validate readme-front-door --strict` passes

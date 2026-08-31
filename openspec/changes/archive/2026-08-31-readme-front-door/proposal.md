## Why

The root `README.md` is the project's front door and it currently reads like an
internal design document: 182 lines, no images, no badges, and a run command that
does not appear until line 62. Its longest block (22 lines) enumerates every
debug panel of every level for both domains, which is inventory a stranger cannot
use and which each challenge `README.md` already carries. Its build section
repeats three code blocks verbatim from `AGENTS.md`, so the repository now has
two places to update when the base chain changes.

Nothing on the page shows what "glass box" actually looks like. The pitch is a
visual claim (watch the exploit land *at the victim*, then patch the line) and it
is currently made entirely in prose, so a reader has to take it on faith.

There is also a discoverability gap for self-study learners. Every challenge
folder ships a `solution.md` and every challenge `README.md` already links it
from a `## Stuck?` section, but the root README mentions solutions only inside
**For teachers**, with no link. A stuck learner reading the front page has no
reason to believe a walkthrough exists.

Finally, none of this is written down anywhere. `AGENTS.md` governs challenge
folders, the platform chain, and CI, but says nothing about what the front page
must do or where its images live, so the next agent to touch it will invent a
convention (`docs/img/`, an uncommitted asset, a screenshot containing a working
payload) with nothing to check itself against.

## What Changes

- **Rewrite `README.md` around the self-study learner**, roughly 95 lines instead
  of 182, ordered pitch, screenshots, run command, depth. The author's voice and
  the existing tagline stay; the four-example pileup, the panel inventory, the
  duplicated build blocks, the form-memory aside, and the `Image` column of the
  catalog go.
- **Add two screenshots**, shown as small side-by-side thumbnails that link to
  the full-size file: `sqli-login` at debug level 2 (the assembled SQL and the
  database's own error, produced by a payload that does **not** solve the
  challenge) and the Fix editor holding `critical.php`. Each is captured in both
  colour schemes and shipped as a `<picture>` pair, so the images do not fight
  the viewer's GitHub theme.
- **Add an `assets/` folder** at the repository root for those PNGs, the script
  that produces them, and a future social-preview image. These are committed,
  unlike the built CSS/JS bundles that `.gitignore` excludes.
- **Add `assets/shots.mjs`**, a dependency-free CDP driver that rebuilds all four
  screenshots from a running challenge container, so the re-shoot obligation is a
  command rather than a memory of how it was done once.
- **Add a badge row**: CI status, MIT licence, supported platforms, GHCR images.
- **Compress the debug dial into a three-row table** (Challenge, Hints, Debug)
  with two or three examples per level rather than the full panel list.
- **Collapse the catalog into one table** (Challenge, Track, You learn), stating
  the `ghcr.io/hutzelmann/glassbox-ctf-<name>` rule once instead of repeating the
  image name on every row.
- **Link the solutions from the front page**, so a stuck self-study learner sees
  that every challenge folder ships a full walkthrough. The per-challenge
  `## Stuck?` links already exist and are unchanged; this change makes that
  contract explicit in the `challenge-docs` spec, which previously said what a
  challenge `README.md` must *not* contain but never required the link.
- **Add a `project-readme` capability** covering the front door: what it must do,
  that its catalog tracks `challenges/`, that its images are spoiler-free, and
  where those images live.
- **Add one `AGENTS.md` conventions bullet** for the `assets/` rule, since
  `AGENTS.md` is what an agent reads first.

## Impact

- `README.md`: rewritten (content preserved or deliberately dropped, nothing
  moved to a new document).
- New `assets/` folder with four committed PNGs (two screenshots, light and dark)
  and the `shots.mjs` capture script.
- `AGENTS.md`: one bullet added under Conventions.
- New spec `openspec/specs/project-readme/`; `challenge-docs` gains one
  requirement about the solution link.
- No challenge, platform, CI, or container change. No `.gitignore` change: the
  existing entries name specific build outputs, none of which live in `assets/`.
- Risk accepted: screenshots go stale when the pictured UI changes. The spec
  names the obligation to re-shoot them; nothing enforces it automatically.

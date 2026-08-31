## Context

The rewrite is a documentation change, but two of its decisions set precedents
that outlive it: the repository starts committing binary assets, and the front
page starts making claims (a catalog, a screenshot of a debug panel) that can
drift out of step with the challenges themselves. Both need a written rule, which
is why this went through OpenSpec rather than being treated as copy editing.

The audience decision drives everything else: the front page optimises for a
self-study learner who arrived from a search result and has a container runtime
installed. Teachers and contributors are served by short sections lower down that
link out, not by depth on the front page.

## Goals / Non-Goals

**Goals**

- A stranger understands the idea and has a container running without scrolling
  past the first screen and a half.
- The page shows the glass box rather than only asserting it.
- Every fact lives in exactly one place: challenge detail in challenge READMEs,
  build and architecture in `AGENTS.md`, front page links to both.
- A stuck self-study learner can find the walkthroughs from the front page.

**Non-Goals**

- No new documentation files (no `TEACHERS.md`, no `docs/`). Material is either
  compressed in place or dropped.
- No change to any challenge, page, image, or CI job.
- No animated demo (GIF or video). Two still images carry the pitch; a recording
  is heavier to store and worse to keep current.
- Not a rewrite of the per-challenge READMEs. Their `## Stuck?` sections already
  link `solution.md`; this change only writes that existing behaviour into the
  spec.

## Decisions

### 1. Screenshots show a failing payload, not a working one

The most compelling screenshot of `sqli-login` at debug level 2 would show a real
login bypass next to the SQL it assembled. That is the answer to the first web
challenge, printed on the front page of the repository, and `challenge-docs`
already forbids payloads in learner-facing docs.

So the pictured input is a payload that breaks the query without solving it (an
unbalanced quote). The debug panel then shows the assembled `$sql` and the
database's own error message, which demonstrates the glass box exactly: the
learner sees their own input reshaping a query at the victim. The Fix editor shot
shows `critical.php` as shipped, which is the vulnerability, not the remediation.

Rejected: screenshotting `hello` instead. It is structurally spoiler-free but has
no interesting internals, so the images would undersell the product.

Rejected: showing the real payload on the grounds that every `solution.md` in the
repo already contains it. A learner choosing to open `solution.md` is different
from a learner having the answer pushed at them by the front page.

### 2. Light and dark pairs, via `<picture>`

pico.css follows the viewer's colour scheme, so a screenshot freezes one of them.
A light screenshot on GitHub's dark theme is a bright slab; a dark screenshot on
the light theme is a hole in the page. GitHub honours `<picture>` with
`media="(prefers-color-scheme: dark)"`, so each screenshot ships as a pair and
the viewer's theme picks one.

Cost: four PNGs instead of two, and four files to re-shoot when the UI changes.
Accepted, because the alternative degrades for roughly half of all readers.

### 3. Thumbnails that link to the full image

Full-width screenshots push the run command below the fold, which is exactly what
this rewrite is trying to fix. The two images are therefore rendered side by side
at about 420px inside a two-cell table, each wrapped in a link to the full-size
file, so a reader who wants detail clicks through. The images set the scene; they
are not the content.

### 4. `assets/` at the repository root

Rejected `docs/img/`: there is no `docs/` tree and no plan for one, so the folder
would exist to hold a single sibling. Rejected `misc/`: a name that invites
everything. `assets/` is the conventional home for exactly this (README imagery
plus a social-preview image later) and reads as intentional.

The capture script lives there too, next to what it produces, rather than under a
new `platform/tools/`: `platform/` is the base-image chain that ships inside
containers, and this script never enters an image.

These files are committed, which is a deliberate exception to the repository's
habit of never committing fetched or built front-end assets. That rule exists so
that image builds stay reproducible and offline; a screenshot is neither fetched
nor built, and a README on GitHub cannot reference a file that is not in the
repository.

### 5. The debug dial becomes a table, the inventory goes

The dial is the differentiator, so it keeps a prominent place, but the prose form
(22 lines listing every panel per level per domain) is inventory. A three-row
table with two or three examples per level makes the *structure* obvious, which
is what a stranger needs; the complete list stays where it is actionable, in each
challenge's own README and in `AGENTS.md`.

Rejected: folding the full inventory into a `<details>` block. It would preserve
every word at the cost of a collapsed section nobody opens.

### 6. The catalog is one table with a Track column

Four sub-headings and four table headers cost about 20 lines of structure to
group ten rows, and the `Image` column repeated `glassbox-ctf-` ten times when the
image name is mechanically derivable from the folder name. One table with a Track
column keeps the ladders visible as blocks (rows stay in ladder order) and states
the image-name rule once, above the table.

### 7. Front page links to the solutions, spec records the per-challenge link

The front page gains an explicit statement, with a link into a challenge folder,
that every challenge ships a `solution.md`. This is currently only implied inside
**For teachers**, which a self-study learner has no reason to read.

The per-challenge links already exist in all ten `## Stuck?` sections, so no
challenge README changes. What is missing is the *rule*: `challenge-docs` says a
challenge README must not contain the answer, but never said it must point at
where the answer lives. That requirement is added, which turns ten instances of
existing good behaviour into a contract a new challenge has to meet.

### 8. Build instructions leave the README entirely

`AGENTS.md` is declared the single source of truth for the build, and the README
was carrying a verbatim copy of three of its code blocks plus two per-challenge
build commands. Duplication in a README is how documentation rots: the copy is
never the one that gets updated. Contributors get four lines and a link.

Accepted cost: a contributor now needs one extra click to find the base-chain
build command.

## Risks / Trade-offs

- **Screenshots go stale.** The pictured panels are stable (the debug view and
  the Fix editor are the project's contract, not incidental UI), but a pico.css
  bump or a layout change will eventually make them wrong. Mitigation: the spec
  states the re-shoot obligation and the images are cheap to regenerate from a
  locally built image. Nothing automated checks this.
- **The catalog can drift** from `challenges/`. Mitigation: a spec requirement
  that it lists every challenge folder. CI does not verify it; adding a check
  would be a build-and-publish change, out of scope here.
- **Detail is genuinely lost** from the front page, above all the per-level panel
  inventory. This is the point of the change rather than a side effect, and every
  dropped fact survives in a challenge README or `AGENTS.md`.
- **Badges depend on shields.io and GitHub** at view time. They degrade to alt
  text, and the repository's offline guarantee is about container images, not
  about a web page rendered by GitHub.

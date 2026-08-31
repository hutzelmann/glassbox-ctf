## ADDED Requirements

### Requirement: The root README is the learner's front door

The root `README.md` SHALL be written for a self-study learner meeting the
project for the first time. It SHALL open with what the project is, and SHALL
show how to run one challenge before any section that explains the architecture,
the teaching model, or the contribution workflow. That first run SHALL be offered
by both routes a learner may have chosen, a desktop container client (clicking)
and the command line (a copy-pasteable command), presented as equals rather than
one as the fallback of the other, because a classroom contains both. It
SHALL link to `AGENTS.md` for build, architecture, and contribution detail rather
than restating it, and SHALL NOT duplicate build commands that `AGENTS.md`
already carries.

#### Scenario: Running before reading

- **WHEN** a first-time visitor opens the repository page
- **THEN** they reach a runnable container command in the first sections, before
  any explanation of the platform chain, the base images, or the build

#### Scenario: The clicking learner is served too

- **WHEN** a learner who runs containers through a desktop client reads Quick
  start
- **THEN** they find the click path (image name, port mapping, start) next to the
  terminal command, not a terminal command alone

#### Scenario: Build detail is linked, not copied

- **WHEN** the base-image build commands change in `AGENTS.md`
- **THEN** no command in `README.md` has to change, because the README points at
  `AGENTS.md` instead of repeating it

### Requirement: The README catalog covers every challenge

The `README.md` SHALL contain a catalog listing every challenge folder under
`challenges/`, grouped by family (the `<domain>` segment of its path) under its
own heading, and within a family in the order a learner should attempt them
(ladder order). Each entry SHALL link to that challenge's own folder and SHALL say
what the learner takes away from it. The catalog SHALL state once how a challenge
folder name maps to its published image name, rather than repeating the image
name per entry. Family grouping SHALL survive the catalog growing: a reader SHALL
be able to tell which class of target a challenge belongs to, and which ladder
within it, from the structure around the entry rather than from the entry itself.

#### Scenario: A new challenge appears in the catalog

- **WHEN** a challenge folder is added under `challenges/<domain>/`
- **THEN** the README catalog gains an entry for it, under that family's heading,
  in its ladder position

#### Scenario: A new family appears in the catalog

- **WHEN** a challenge family is added under `challenges/`
- **THEN** the catalog gains a heading for it, carrying whatever is true of every
  challenge in that family (such as its published platforms)

#### Scenario: Image names are derived, not listed

- **WHEN** a reader wants the published image for a catalog entry
- **THEN** they apply the single stated `glassbox-ctf-<folder>` rule, and the
  catalog does not repeat an image column

### Requirement: README imagery gives nothing away

Screenshots and other images in the root `README.md` SHALL NOT show a working
exploit payload, a flag, or a remediation. An image MAY show the glass-box
machinery itself (a debug panel, the Fix editor, the debug dial), including
server internals produced by an input that does not solve the pictured
challenge.

#### Scenario: A debug panel screenshot stays spoiler-free

- **WHEN** the README pictures a challenge's debug view
- **THEN** the input shown is one that fails to solve the challenge, and the
  panel's contents therefore reveal the mechanism without revealing the answer

#### Scenario: The Fix editor screenshot shows the bug

- **WHEN** the README pictures the Fix editor
- **THEN** it shows the vulnerable `critical.<ext>` as shipped, not a patched
  version

### Requirement: README images live in assets/ as theme pairs

Images referenced by the root `README.md` SHALL be committed under `assets/` at
the repository root. Any screenshot of a challenge UI SHALL be captured in both
colour schemes and referenced through a `<picture>` element so the viewer's
GitHub theme selects one. Screenshots SHALL be re-captured when the interface
they picture changes.

#### Scenario: A dark-theme reader sees a dark screenshot

- **WHEN** the repository page is viewed with GitHub's dark theme
- **THEN** the dark capture of each screenshot is displayed

#### Scenario: A UI change invalidates a screenshot

- **WHEN** a change alters a panel, control, or layout that a README screenshot
  pictures
- **THEN** that screenshot is re-captured in both colour schemes as part of the
  same change

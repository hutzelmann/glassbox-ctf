## ADDED Requirements

### Requirement: Learners can find the solution from where they are stuck

Each challenge `README.md` SHALL link to that challenge's `solution.md`, in a
clearly marked section, together with a warning that it contains the answer. The
root `README.md` SHALL state that every challenge folder ships a full
walkthrough, so a learner who never opens a challenge folder still knows the
walkthroughs exist. Providing the link SHALL NOT be treated as putting the answer
in the `README.md`: the link is a signpost, the spoilers stay in `solution.md`.

#### Scenario: A stuck learner finds the walkthrough

- **WHEN** a learner reading a challenge `README.md` cannot make progress
- **THEN** the README offers a marked link to `solution.md` and says plainly that
  it gives the answer away

#### Scenario: The front page advertises that solutions exist

- **WHEN** a learner reads only the root `README.md`
- **THEN** they learn that every challenge folder contains a complete
  walkthrough, with a link to at least one

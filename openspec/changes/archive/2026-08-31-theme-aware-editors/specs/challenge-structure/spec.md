## ADDED Requirements

### Requirement: Editors render in the page's colour scheme

Every in-browser code editor the platform ships, the Fix editor and every debug
or payload editor a challenge mounts, SHALL render in the same colour scheme as
the page around it, in both directions. Editor chrome (the text, the editing
surface, the line-number gutter and its separator, the caret, the selection, the
active line, and any panel or tooltip the editor opens) SHALL take its colours
from the page's own stylesheet variables rather than from a palette the editor
carries, so a change to the page's theme moves the editor with it and cannot
leave the two disagreeing.

Syntax highlighting SHALL stay legible against the editing surface in both
schemes. Where the page's stylesheet offers no variable for a colour the editor
needs, that colour SHALL be expressed so that it resolves from the colour scheme
the page has declared, not from an independent reading of the browser preference
which could disagree with the page.

#### Scenario: The gutter follows the page into dark

- **WHEN** a learner opens the Fix editor with the browser set to a dark colour
  scheme
- **THEN** the line-number gutter, its separator, and the editing surface are
  dark like the page, with no light strip beside the code

#### Scenario: The light scheme is unchanged in character

- **WHEN** the same page is opened with the browser set to a light colour scheme
- **THEN** the editor is light, with the gutter still distinguishable from the
  editing surface

#### Scenario: Highlighting stays readable in both schemes

- **WHEN** a snippet containing comments, strings, keywords, and a PHP open tag
  is displayed in either colour scheme
- **THEN** every token is readable against the editing surface behind it, rather
  than being a dark colour on a dark background

#### Scenario: Every editor is themed, not only the editable one

- **WHEN** a challenge page renders a read-only source view, a level-1 payload
  editor, or a level-2 view of the assembled query in a dark colour scheme
- **THEN** each of them is themed exactly like the Fix editor, because the theme
  is shared by every bundle a family ships

#### Scenario: The scheme changes while a page is open

- **WHEN** the browser's colour scheme changes while a page with an editor is
  open
- **THEN** the editor follows immediately, without a reload

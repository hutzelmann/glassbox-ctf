## MODIFIED Requirements

### Requirement: The debug view exposes binary internals

With `?debug=1`, a native challenge SHALL reveal the internals a learner needs to
understand and exploit the bug: the disassembly of the relevant functions, the
symbol listing including the target function's address, the `checksec`
protections, the process memory map, a hexdump of the bytes the server received,
and a stack-frame view mapping the submitted bytes onto the buffer, the saved
frame pointer, and the saved return address. The stack-frame view SHALL resolve
the value that lands in the saved return address to the symbol or region it points
at, so the learner sees where execution would go. Where the view shows the frame
both before and after the overflowing read, only the value present **after** the
read is resolved this way; the pristine **before** value is the slot's original
contents and SHALL NOT be resolved as a return target. Guidance that interprets
these internals SHALL appear only once debug is enabled.

Every stack slot's note SHALL describe that slot. A note SHALL NOT attribute to one
slot a consequence that belongs to another: only the saved-return-address row may
state what the CPU does when the function returns. Slots above the frame SHALL be
resolved to the symbol or region they point at — that identification is what lets a
learner see a target address landed one slot off — but SHALL be described as stack
above the frame, reached only if execution returns again, not as the address the
`ret` jumps to.

A saved-return-address row that the payload did not overwrite SHALL be described as
returning to the caller normally. Its pristine value SHALL NOT be resolved against
the binary's static symbols nor described as unmapped or faulting: for a
position-independent (PIE) binary that original return into the caller is relocated
to a random base and is therefore absent from the binary's static symbols, yet it
is the genuine return the CPU takes, and it does not fault.

#### Scenario: Debug reveals the win() address

- **WHEN** a learner enables debug
- **THEN** the symbol listing shows `win()`'s address, which the payload must target

#### Scenario: Stack view maps the learner's bytes

- **WHEN** a learner submits a payload and enables debug
- **THEN** the stack-frame view marks which submitted bytes overwrote the saved
  return address and resolves the value there to the symbol or region it points at

#### Scenario: A slot above the frame is not described as a return target

- **WHEN** a learner submits a payload long enough to fill whole words past the
  saved return address and opens the stack view
- **THEN** each such row is described as stack above the frame, reached only if
  execution returns again, and its value is still resolved to the symbol or region
  it points at
- **AND** no such row claims the CPU faults, or jumps, on returning to that value

#### Scenario: An unchanged saved return is not described as faulting

- **WHEN** a learner opens the stack view on a build whose saved return address the
  payload did not overwrite — including a PIE build whose original return into the
  caller is relocated and so is not one of the binary's static symbols
- **THEN** the saved-return-address row states that execution returns to the caller
  normally
- **AND** the row does not resolve the pristine return value against the static
  symbols, and does not claim it is unmapped or that the CPU faults on returning to it

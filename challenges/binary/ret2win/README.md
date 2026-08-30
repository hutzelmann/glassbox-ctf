# Binary Exploitation: ret2win

> A program reads your bytes onto a tiny stack buffer and then returns. It has a
> `win()` function it never calls. Make it call `win()` anyway, by overwriting the
> return address the CPU trusts.

**Domain:** Binary · Stack overflow **Ladder:** Binary 1 of 2, **ret2win** → [ret2libc](../ret2libc/)

## The scenario

This is the "hello world" of memory-corruption exploitation. A function copies
your input into a 16-byte buffer on the stack using a read that is far too big.
Just above that buffer sit two values the function will use when it returns: the
saved frame pointer and the **saved return address**: the exact spot in the code
the CPU will jump to next. Run off the end of the buffer and you get to choose
that address.

Somewhere in the binary is a `win()` function that prints the flag. Nothing ever
calls it. Your job is to *return into it*.

## Your tasks

- **a)** Make the program crash. What is the smallest input that does it?
- **b)** Work out the size of the stack buffer your input is read into (analyse the
  binary with Ghidra / `objdump`, or narrow it down by experiment).
- **c)** Find the memory address of the `win()` function in the binary
  (Ghidra / `nm` / `objdump`).
- **d)** Redirect execution to `win()` and read the flag.
- **e)** Fix `critical.c` so the overflow can no longer reach the return address,
  then confirm your exploit fails. (Hint: the bug is one number.)
- **f)** *Without changing the source*, use the **compiler-flags** field in the Fix
  editor to enable a protection that stops your original exploit. Explain what a
  stack canary, PIE/ASLR, and NX each do, and which one defeats ret2win and why.
- **g)** Explain the layered defenses and their limits, what does each stop, and
  what does it *not* stop?
- **h)** Script the whole exploit with **pwntools** against the downloaded binary
  (`cyclic` for the offset, `p64()` for the address, `process()` to run it).

> **Stretch:** once `win()` works, you've only redirected to a function that was
> already there. The next rung, [ret2libc](../ret2libc/), chains several addresses
> together to call a function of *your* choosing, the start of return-oriented
> programming.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-ret2win
```

Then open <http://localhost:9000/>. The binary is x86-64; the image is published
for `linux/amd64`, so on Apple Silicon or other arm64 hosts it runs under
emulation automatically (same command).

## The glass box

- **Fix button**: opens the one vulnerable function (`critical.c`) in an
  in-browser editor. **Save recompiles the binary** and the page runs your version
  immediately. A version that does not compile is rejected with the compiler's
  errors, and the previous working binary keeps running, you cannot brick it.
  **Restore Original** puts the bug (and the default flags) back. The editor also
  has a **compiler-flags** field: change the protections the binary is built with
  and watch the exploit succeed or fail without touching the code.
- **Debug dial** (optional, the selector in the header), the "glass box". You
  never need it to solve the challenge; it is there when you want to *understand*
  what happened, and it comes in two settings:
  - **Hints** (`?debug=1`) gives you tooling and your own attempt back: a
    byte/endianness calculator, a hexdump of exactly what the server received, and
    a **live stack table** of your bytes laid onto the frame, so you read the
    overflow **offset** off your own crash. It does not name the target address.
  - **Debug** (`?debug=2`) opens the rest: the disassembly, the **Symbols** panel
    with `win`'s address (the value you jump to), the `checksec` protections, and
    the memory map, the internals a real attacker digs out with `objdump` or
    Ghidra.

## Stuck?

The full walkthrough, the offset, the payload, the flag, the fix, and the
professional-tool version with `pwntools` / `gdb` / Ghidra, is in
[solution.md](solution.md). Spoilers ahead.

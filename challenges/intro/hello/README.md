# Hello, Hacker

> Your first flag is on the page, but it is not printed anywhere you can see.
> Find it, and learn to tell the difference between what a page *shows* and what
> it *sends*.

**Domain:** Intro **Ladder:** none. Start here, then move on to the [web challenges](../../web/) (begin with [SQLi: Login Bypass](../../web/sqli-login/)).

## The scenario

This is the on-ramp. There is nothing to break in here: no injection, no bypass,
no admin to impersonate. The page hands your browser a flag and then quietly
declines to display it. Your only job is to notice that the browser knows more
than the screen tells you, and to get comfortable with the two controls every
other challenge in this set uses.

## Your tasks

- **a)** Run the container and open it in your browser (<http://localhost:9000/>).
- **b)** Find your first flag. It is not printed on the page.
- **c)** If you are in a class, submit the flag where your instructor asks.
- **d)** Try the two glass-box controls (the **debug** switch and the **Fix**
  button) so you know what they do before the real challenges.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-hello
```

Then open <http://localhost:9000/>.

## The glass box

- **Fix button**: opens the one snippet each challenge is built around
  (`critical.php`) in an in-browser editor. On the real challenges you patch the
  bug here and **Save** to run your version immediately; **Restore Original**
  puts it back. Here there is no bug, this is just the tour, so have a look.
- **Debug dial** (the selector in the header): three settings, and every
  challenge has them. On the real challenges **Hints** gives you a proper editor
  for the input you attack through plus the error your attempt provoked, while
  **Debug** shows the exact query the server built, the rows it returned, and
  more. Here:
  - **Hints** (`?debug=1`) swaps the page for a read-only view of `critical.php`
    itself, the whole point of the tour.
  - **Debug** (`?debug=2`) adds the request as PHP parsed it (`$_GET`, `$_POST`,
    headers), a small preview of the kind of internals the later challenges
    expose.

## Stuck?

The full walkthrough (where the flag hides and how to reveal it) is in
[solution.md](solution.md). It contains the flag; try **View Source** first.

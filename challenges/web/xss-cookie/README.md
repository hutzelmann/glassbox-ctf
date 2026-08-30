# Cross-Site Scripting: Cookie Theft

> A search page prints your query straight back into the HTML. Turn that echo
> into a script, mail the link to the admin, and walk away with their session.

**Domain:** Web · Cross-Site Scripting **Ladder:** XSS 3 of 3 — [light](../xss-light/) → [shop](../xss-shop/) → **cookie**

## The scenario

You are looking at a plain search page. Type a term, hit search, and if nothing
matches it repeats your term back to you in the page. That echo is the whole
game: the server trusts your input enough to render it.

You are not the interesting victim, though. There is an **admin** who will click
a link you send them — and the admin's browser carries a session cookie you very
much want. Two other pages help you close the loop: a **chat** where you hand the
admin a link, and a **Web Analytics** log that records every request that hits
the server. This is the full chain, start to finish, and it all runs offline.

## Your tasks

- **a)** Explore the three pages and find where the search page reflects your
  input without escaping it.
- **b)** Use that reflection to run a script that reads *your own* session cookie.
- **c)** Log in with a stolen cookie you have been handed: `0123456789abcdef`.
- **d)** Use the same reflection to load another page silently in the background.
- **e)** Dry-run the real attack: make a script send *your own* cookie to the
  Web Analytics log, then go read it back out of the log.
- **f)** Send the admin a link that fires your script in *their* browser and
  drops *their* cookie into the log. Read it. That is the flag.
- **g)** Convince yourself the admin saw nothing suspicious while it happened.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-xss-cookie
```

Then open <http://localhost:9000/> (the port is whatever you mapped with `-p`).

## The glass box

- **Fix button** — opens the one vulnerable snippet (`critical.php`) in an
  in-browser editor. Patch it, **Save**, and the running page uses your version
  immediately. **Restore Original** puts the bug back.
- **Debug dial** (the selector in each page's header) — three settings:
  - **Off** — the site as it ships.
  - **Hints** (`?debug=1`) — the search box becomes a multi-line HTML editor so
    you can write a real script; your own session cookie is shown (it has no
    `HttpOnly` flag, so `document.cookie` can read it anyway); and in the chat,
    the admin bot's **JavaScript console errors** come back. Those errors are the
    heart of this rung: a payload that never runs in the victim's browser leaves
    a `SyntaxError` behind, and now you can read it.
  - **Debug** (`?debug=2`) — the exact HTML page the admin's browser rendered,
    what the server received in `$_GET["q"]`, and the vulnerable snippet itself.
    This is the victim's side of the wire; reach for it once your payload runs
    but you cannot see why the result is wrong.

## Stuck?

The full walkthrough — payloads, the flag, and the fix — is in
[solution.md](solution.md). It contains spoilers; turn the debug dial up first.

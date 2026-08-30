# Cross-Site Scripting: Reflected

> A search page repeats your search term back to you, dropped straight into the
> HTML. Feed it something that isn't just text — and make the browser run it.

**Domain:** Web · Cross-Site Scripting **Ladder:** XSS 1 of 3 — **light** → [shop](../xss-shop/) → [cookie](../xss-cookie/)

## The scenario

You are on a simple content search page. Type a term, hit search, and if there
are no hits the page tells you so — echoing your term back inside the sentence
"Unfortunately, the search for *…* returned no results." That echo is the whole
game: the server hands your input back to the browser without asking what it is.

## Your tasks

- **a)** Get some JavaScript of your own to run from the search box.
- **b)** Pop an `alert()` dialog.
- **c)** Read the page's `session` cookie through your injected script.
- **d)** Find the mistake in the code and fix it with the **Fix** button.
- **e)** Explain what further defenses you would add.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-xss-light
```

Then open <http://localhost:9000/>.

## The glass box

- **Fix button** — opens the one vulnerable snippet (`critical.php`) in an
  in-browser editor. Patch it, **Save**, and the running page uses your version
  immediately. **Restore Original** puts the bug back.
- **Debug dial** (the selector in the header) — three settings:
  - **Challenge** — a plain one-line search box, as the site ships.
  - **Hints** (`?debug=1`) — the search box becomes a roomy CodeMirror editor
    with HTML highlighting and live diagnostics, so you compose a multi-character
    payload in the open instead of fighting a text input. It flags malformed
    markup and unknown tags; it does not tell you what to write.
  - **Debug** (`?debug=2`) — what the server actually received in `$_GET["q"]`
    after URL-decoding, plus the vulnerable snippet itself, so you can see the
    unescaped `echo` that lets your markup through. That last part is the answer,
    so save it for after you have tried.

## Stuck?

The full walkthrough — payloads, the flag, and the fix — is in
[solution.md](solution.md). It contains spoilers; turn the debug dial up first.

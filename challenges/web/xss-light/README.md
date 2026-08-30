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
- **Debug switch** (the toggle in the header, or `?debug=1`) — turns the search
  box into a roomy CodeMirror editor so you can type multi-character payloads
  comfortably instead of fighting a one-line text input. This is the "glass box":
  compose your injection in the open and watch it land in the response.

## Stuck?

The full walkthrough — payloads, the flag, and the fix — is in
[solution.md](solution.md). It contains spoilers; try the debug switch first.

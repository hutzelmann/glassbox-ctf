# SQL Injection: Blind Extraction

> The same login form is vulnerable — but this time it never prints what it
> found. Make the database confess one bit at a time.

**Domain:** Web · SQL Injection **Ladder:** SQLi 2 of 3 — [login](../sqli-login/) → **blind** → [insert](../sqli-insert/)

## The scenario

Behind the form sits the same query-built-from-a-string as the previous
challenge, so your injections still reshape the SQL. But the page has gone quiet:
a correct login shows a bare `Welcome!` with no name, and every failure shows one
generic error. Nothing you smuggle into a column ever gets echoed back. You can
no longer *read* the answer — you can only ask the database yes/no questions and
watch how it reacts.

## Your tasks

- **a)** Read the password of the `admin` user — blind, without the page ever
  printing it.
- **b)** Take a copy of the entire database (all users, and anything else hiding
  in there) — again, blind.
- **c)** Find the mistake in the code and fix it with the **Fix** button.
- **d)** Explain what further defenses you would add.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-sqli-blind
```

Then open <http://localhost:9000/>.

## The glass box

- **Fix button** — opens the one vulnerable snippet (`critical.php`) in an
  in-browser editor. Patch it, **Save**, and the running page uses your version
  immediately. **Restore Original** puts the bug back.
- **Debug dial** (the selector in the header) — three settings, and here the
  split matters more than anywhere else on the ladder:
  - **Off** — total silence, exactly like a real blind target.
  - **Hints** (`?debug=1`) — MySQL editors on the login fields, the database's
    own error message, and the **timing panel** (*query runtime*, *CPU
    user/sys*, *block I/O*). The timing panel is your **instrument**: this rung
    is about reading the answer from timing and yes/no behavior. Note it is a
    friendlier instrument than reality — the server measures its own query, so
    you get a cleaner number than the wall-clock a real attacker times over the
    network.
  - **Debug** (`?debug=2`) — the *exact SQL string* your input built and the rows
    the query returned. These are the **check**, not the instrument: use them to
    confirm reasoning you already did at **Hints**.

  Solving this rung at **Debug** teaches you nothing blind injection has to
  teach. Stay at **Hints** as long as you can bear it.

## Stuck?

The full walkthrough — payloads, flags, and the fix — is in
[solution.md](solution.md). It contains spoilers; turn the debug dial up first.

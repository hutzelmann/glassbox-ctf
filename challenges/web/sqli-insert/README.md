# SQL Injection: INSERT Break-Out

> The login is done right, but the registration form pastes your new username
> straight into an `INSERT`. Break out of it, and write rows the app never meant
> to write.

**Domain:** Web · SQL Injection **Ladder:** SQLi 3 of 3: [login](../sqli-login/) → [blind](../sqli-blind/) → **insert**

## The scenario

You are looking at an ordinary account page: a **Login** form and a **Register**
form, side by side. Someone learned their lesson on the login path: it uses a
prepared statement and verifies a hashed password, so the classic
login-injection tricks bounce right off. The registration form, though, still
builds its query the old way: it drops your chosen username into an `INSERT`
statement as text. One form is safe; the other is not. Find the difference.

## Your tasks

- **a)** Find the injectable field. (Hint: only one of the two forms builds SQL
  from your input; the login is done right, so look at the *other* one.)
- **b)** Confirm you can break out of the `INSERT`, that your input becomes SQL
  syntax, not just a stored value.
- **c)** Use the break-out to read the administrator's stored secret.
- **d)** Forge an account whose password *you* choose, bypassing the app's own
  password hashing, and log in with it.
- **e)** Find the mistake in the code and fix it with the **Fix** button.
- **f)** Explain what further defenses you would add.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-sqli-insert
```

Then open <http://localhost:9000/> (the port is whatever you mapped with `-p`).

## The glass box

- **Fix button**: opens the one vulnerable snippet (`critical.php`) in an
  in-browser editor. Patch it, **Save**, and the running page uses your version
  immediately. **Restore Original** puts the bug back.
- **Debug dial** (the selector in the header): three settings:
  - **Challenge**: the challenge as shipped.
  - **Hints** (`?debug=1`): the registration username becomes a MySQL editor,
    and the *database's own error message* appears instead of the generic
    "registration failed". On this rung the error is unusually informative: a
    malformed `INSERT` tells you a lot about the statement you are injecting
    into.
  - **Debug** (`?debug=2`): the *exact `INSERT` string* the server built from
    your input **and** the full `users` table after the write. Register once,
    then watch which rows actually landed.
- **Reset button**: deletes every row you injected (everything except the
  original admin) and resets the counter. Use it to clean up between attempts.

## Stuck?

The full walkthrough (payloads, the flag, and the fix) is in
[solution.md](solution.md). It contains spoilers; turn the debug dial up first.

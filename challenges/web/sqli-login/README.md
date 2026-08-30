# SQL Injection: Login Bypass

> An admin login form builds its SQL query by pasting your input straight into a
> string. Make the database let you in — and then say more than it should.

**Domain:** Web · SQL Injection **Ladder:** SQLi 1 of 3 — **login** → [blind](../sqli-blind/) → [insert](../sqli-insert/)

## The scenario

You are standing in front of an admin login page. You do not have an account, and
you do not know any password. Somewhere behind the form, a single SQL query
decides whether you get in. Your job is to talk to that query directly.

## Your tasks

- **a)** Guess the password for the user account `test`.
- **b)** Log in as the administrator without knowing the correct password.
- **c)** Read the password of the `admin` user.
- **d)** Take a copy of the entire database (all users, and anything else hiding
  in there).
- **e)** Carefully cause a denial-of-service against the database.
- **f)** Find the mistake in the code and fix it with the **Fix** button.
- **g)** Explain what further defenses you would add.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-sqli-login
```

Then open <http://localhost:9000/>.

## The glass box

- **Fix button** — opens the one vulnerable snippet (`critical.php`) in an
  in-browser editor. Patch it, **Save**, and the running page uses your version
  immediately. **Restore Original** puts the bug back.
- **Debug switch** (the toggle in the header, or `?debug=1`) — shows you the
  *exact SQL string* the server built from your input and the *rows it returned*.
  This is the "glass box": watch your injection reshape the query at the victim.

## Stuck?

The full walkthrough — payloads, flags, and the fix — is in
[solution.md](solution.md). It contains spoilers; try the debug switch first.

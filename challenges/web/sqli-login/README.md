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
- **Debug dial** (the selector in the header) — three settings:
  - **Off** — the challenge as a real target would give it to you.
  - **Hints** (`?debug=1`) — the username and password fields become MySQL
    editors, with the quote boundaries coloured the way MySQL really parses them,
    and the *database's own error message* replaces the polite "something went
    wrong". Enough to see why a payload broke; not enough to hand you the answer.
  - **Debug** (`?debug=2`) — the "glass box" proper: the *exact SQL string* the
    server built from your input and the *rows it returned*. Watch your injection
    reshape the query at the victim.

  Try **Hints** first — reaching **Debug** before you have a theory just tells
  you the answer.

## Stuck?

The full walkthrough — payloads, flags, and the fix — is in
[solution.md](solution.md). It contains spoilers; turn the debug dial up first.

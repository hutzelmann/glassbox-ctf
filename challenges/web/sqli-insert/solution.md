# SQL Injection: INSERT Break-Out, Solution

> ⚠️ **Spoilers below.** The flag, the payloads, and the fix. If you want to
> solve it yourself, close this file and turn the debug dial up instead.

## The vulnerability

The **login** path is safe: it uses a prepared statement and `password_verify`,
so the input can never be parsed as SQL. Don't waste time on it.

The hole is the **registration** path. `critical.php` builds the `INSERT` by
interpolating the raw registration username into a string:

```php
$sql = "INSERT INTO `users` (username, password) VALUES ('$regUser', '$regPassHash')";
$insertOk = $db->query($sql);
```

`$regUser` comes straight from the `reg_username` POST field. Anything you type
there becomes *SQL syntax*, not just a stored value, which means a single
quote lets you close the `VALUES` string and keep writing the statement
yourself. Set the debug dial to **Debug** (`?debug=2`) to watch the `INSERT`
change as you register, and to see the whole `users` table afterward. That is
the point of this challenge. (**Hints**, `?debug=1`, gives you the MySQL editor
on the registration username and the raw database error, but not the statement.)

The `users` table is `users(uid, username UNIQUE, password)`. The administrator
is `uid 1`, and its `password` column stores the flag.

## Walkthrough

Payloads go in the **Register** form's **username** field (`reg_username`); the
registration password can be anything. Set the debug dial to **Debug**
(`?debug=2`) first, and use the **Reset** button to clear injected rows between
attempts.

### a) & b) Break out of the `INSERT`

The username is dropped between single quotes inside `VALUES ('…', '…')`. A
single quote closes that string; a `),(` starts a second row; and a trailing
`-- ` (dash, dash, **space**) comments out the rest of the statement, including
the app's own dangling `'$regPassHash')`. So a payload shaped like
`x', 'y'),( … ) -- ` turns the app's single-row `INSERT` into a *two-row* one
whose second row you fully control. That extra row appearing in the **Debug**
`users` table is your proof the break-out worked.

### c) Read the admin's stored secret

The app never prints the secret, but you can *write it into a column you can
see*. Make the forged second row's **username** be a subquery that reads the
admin's stored password:

```
reg_username:  pwn', 'x'),((SELECT password FROM users WHERE uid=1), 'y') -- 
reg_password:  whatever
```

(Note the trailing space after `-- `.) The `INSERT` becomes a two-row insert:
the first row is `('pwn', 'x')`; the second row's username is the result of
`SELECT password FROM users WHERE uid=1`. After you submit, the **Debug**
`users` table shows a fresh row (`uid 3`) whose **username** is the admin's
secret:

- **Flag (admin's stored secret):** `N0tAHa5Hbu1y0urFLA9`

That is the exfiltration: you smuggled a value out of a column the app would
never display into one it does.

### d) Forge an account whose password you control

Registration normally hashes the password *for* you, so you can never choose
what gets stored. But if you inject the whole row, you write the `password`
column yourself, so store a bcrypt hash of a password *you* already know.

First, generate a bcrypt hash of a password of your choice (any bcrypt hash
works; the app's login uses `password_verify`, so it must be a *real* hash):

```bash
php -r 'echo password_hash("letmein", PASSWORD_DEFAULT), "\n";'
```

Then break out and plant a row with your username and that hash as the password:

```
reg_username:  hacker', '$2y$10$<your-own-bcrypt-of-a-known-password>') -- 
reg_password:  whatever
```

The `INSERT` becomes `... VALUES ('hacker', '$2y$10$…') -- ', '<app-hash>')`,
so a `hacker` row lands with *your* hash in the password column. Now switch to
the **Login** form and sign in as `hacker` with the password you hashed
(`letmein` above). You never let the app hash anything for you, so you planted a
credential entirely of your choosing, a self-service backdoor account.

Unlike the exfil in (c), this variant *must* use a real bcrypt hash, because the
safe login path verifies it with `password_verify`.

### e) The fix

See **The fix** below.

### f) Further defenses

See **Further defenses** below.

## Flags

| Task | What | Value |
|------|------|-------|
| c | admin's stored secret | `N0tAHa5Hbu1y0urFLA9` |

Task (d) has no fixed flag; the "prize" is a working `hacker` login with a
password you chose.

## The fix

Separate code from data with a prepared statement, exactly as the login path
already does, so the username can never be parsed as SQL. Open the **Fix**
editor and replace the body of `critical.php`:

```php
<?php
$stmt = $db->prepare("INSERT INTO `users` (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $regUser, $regPassHash);
$insertOk = $stmt->execute();
?>
```

Save, then retry every payload above. The whole `pwn', 'x'),(…` string is now
stored as a *literal username* (and rejected by the `UNIQUE` constraint on a
retry, or simply stored verbatim): it can no longer start a second row or a
comment. **Restore Original** brings the bug back for the next learner.

## Professional tools

Once you understand the break-out by hand, `sqlmap` is what you point at the
registration POST to confirm and fingerprint it:

```bash
sqlmap -u "http://localhost:9000/" \
  --data="reg_username=test&reg_password=test&register_submit=1" -p reg_username \
  --purge --batch --dump -v 3
```

- `--data` marks this as a POST; `-p reg_username` tells sqlmap which parameter
  to attack (the injectable one you found in task a).
- `--dump` tries to pull the tables; `--batch` accepts the defaults; `-v 3`
  prints the payloads so you can compare them to what you wrote by hand.

Be honest about the limits, though: **`INSERT`-context injection is finicky for
automated tools**. There is no result set to read back the way a `SELECT` gives
one, so sqlmap is most useful here to *confirm the parameter is injectable* and
to fingerprint the DB, while the hand-written subquery from task (c) is what
does the clean exfil. This is exactly the case where the manual skill earns its
keep.

## Further defenses

- **Parameterize everywhere.** The login path shows the pattern; the fix applies
  it to the `INSERT`. Every query built from input should use bound parameters,
  reads *and* writes.
- **Least privilege.** The app's DB user has `INSERT`/`DELETE`/`ALTER`; a
  registration flow needs far less. Narrower grants shrink the blast radius when
  a bug slips through.
- **Validate and constrain input** (allowed username characters, length) as
  defense in depth, but never *instead* of parameterization, only alongside it.
- **Don't store secrets in a stealable shape.** Password columns should hold
  slow, salted hashes only; a plausible-looking placeholder that is actually a
  cleartext flag is precisely what task (c) walks out with.

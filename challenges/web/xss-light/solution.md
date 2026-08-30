# Cross-Site Scripting: Reflected — Solution

> ⚠️ **Spoilers below.** The flag, payloads, and the fix. If you want to solve it
> yourself, close this file and turn the debug dial up instead.

## The vulnerability

`critical.php` prints the `q` search parameter straight into the page, with no
escaping:

```php
<?php if (!empty($_GET["q"])):?>
  <p>Unfortunately, the search for <i><?php echo $_GET["q"];?></i> returned no results.</p>
<?php endif;?>
```

Whatever you put in `q` becomes part of the HTML the browser parses. Plain text
renders as text — but markup renders as markup. This is **reflected** (or
*non-persistent*) XSS: the server takes input from your request and reflects it
straight back into the same response, so anything you can talk a victim into
requesting runs in their browser.

## Walkthrough

Payloads go in the **search field** (the `q` parameter). At **Off** it is a
one-line text input; set the debug dial to **Hints** (`?debug=1`) to type them
into a comfortable multi-line editor instead. **Debug** (`?debug=2`) additionally
shows the raw `$_GET["q"]` and the vulnerable snippet.

### a) Get JavaScript to run

Break out of the sentence with a `<script>` tag. Because `q` is echoed unescaped,
the tag is parsed as a real element and its contents execute:

```
<script>alert(1)</script>
```

### b) Pop an alert

That is exactly what the payload above does — the browser runs `alert(1)` and a
dialog pops up. If you searched it and saw the box, task (a) and (b) are done in
one shot.

### c) Read the session cookie

The page sets a cookie (`session=5uper5ecret5ession5trin9`) when it loads. Your
injected script runs in the page's own context, so it can read `document.cookie`:

```
<script>alert(document.cookie)</script>
```

The dialog shows `session=5uper5ecret5ession5trin9`. On a real site this is the
crux of the attack: script running in the victim's page can read their session
cookie and ship it off to an attacker — which is precisely how the
[cookie](../xss-cookie/) challenge later in this ladder steals an admin session.

- **Flag (session cookie):** `5uper5ecret5ession5trin9`

### d) The fix

See **The fix** below.

### e) Further defenses

See **Further defenses** below.

## Flags

| Task | What | Value |
|------|------|-------|
| c | `session` cookie value | `5uper5ecret5ession5trin9` |

## The fix

Escape on output. Run the reflected value through `htmlspecialchars()` so that
`<`, `>`, `&`, and quotes become HTML entities — the browser then shows them as
literal characters instead of parsing them as markup. Open the **Fix** editor and
replace the body of `critical.php`:

```php
<?php if (!empty($_GET["q"])):?>
  <p>Unfortunately, the search for <i><?php echo htmlspecialchars($_GET["q"]);?></i> returned no results.</p>
<?php endif;?>
```

Save, then retry the payloads above: `<script>alert(1)</script>` now appears as
visible text inside the sentence, tags and all, and nothing runs. **Restore
Original** brings the bug back for the next learner.

## Professional tools

You do not need anything special to see this bug — the browser's own developer
tools are the whole kit:

- **Console** — run `document.cookie` yourself to confirm what an injected script
  could read.
- **Elements** — inspect the live DOM and find your injected `<script>` sitting
  right there inside the `<i>` element, proof that your input became markup.
- **Network** — watch the request carry your `q` value and the response echo it
  back.

You can also confirm the raw reflection from the command line, with no browser at
all (adjust the port to whatever you mapped with `-p`):

```bash
curl "http://localhost:9000/?q=<script>alert(1)</script>"
```

Your `<script>` tag comes back verbatim in the HTML — the server never escaped
it. On a real engagement you would find and replay reflected XSS with an
intercepting proxy such as **Burp Suite** (its *Repeater* lets you tweak the `q`
parameter and resend the request over and over). There is no `sqlmap` here — this
is an HTML/JavaScript flaw, not a SQL one.

## Further defenses

Escaping on output is the fix, but defense in depth adds more:

- **`htmlspecialchars()` on every reflected value**, not just this one — audit
  each place user input reaches the page.
- **A Content-Security-Policy** that blocks inline scripts, so even an injected
  `<script>` refuses to run.
- **Limit input length and characters** where the field allows it, shrinking the
  room an attacker has to work in.

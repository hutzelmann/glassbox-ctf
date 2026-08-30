# Cross-Site Scripting: Stored, Solution

> ⚠️ **Spoilers below.** Flags, payloads, and the fix. If you want to solve it
> yourself, close this file and turn the debug dial up instead.

## The vulnerability

`critical.php` writes the customer comment straight into the packaging page's
HTML with no escaping:

```php
<h6>Customer Comment</h6>

<?php if (!empty($_POST["comment"])):?>
  <blockquote><?php echo $_POST["comment"]; ?></blockquote>
<?php else:?>
  <blockquote><em>No comment provided.</em></blockquote>
<?php endif;?>
```

Whatever you type in the comment field is not treated as text: it is parsed and
rendered as markup. That is a cross-site scripting hole. But this challenge has a
second, deeper bug: the **Packaged and Shipped** button runs `checkOrder()` in
the *worker's browser* (`index.php`), pure client-side JavaScript. It scans the
page for elements with class `item` (flagging any whose text is not
Apple/Banana/Cherry) and class `quantity` (flagging any over 3). Because your
injected comment becomes real DOM on the page that check inspects, you can forge
exactly the elements it is looking for. The server blocks bad orders; the check
that matters lives where you control it.

## Walkthrough

To reach the packaging page at all, place a **normal, valid order**: quantity
1–3 of a real fruit (Apple, Banana, or Cherry), with your payload in the
**Comment** box. The server accepts the order, renders *Package Instructions for
Order 1337*, and drops your comment into it verbatim. Each payload below goes in
the comment field; inject one at a time so you can see each flag on its own.

### a) Get your HTML onto the page

Any markup in the comment renders. Order 1 Apple and comment:

```
<b>hello from the comment</b>
```

Submit. The text appears **bold** inside the *Customer Comment* blockquote,
proof your input is parsed as HTML, not printed as characters.

### b) Forge an over-limit quantity

The server caps quantities at 3, but `checkOrder()` reads whatever `.quantity`
elements sit on the page. Inject one it will never like:

```
<span class="quantity">10</span>
```

Order 1 Apple, put that in the comment, submit, then click **Packaged and
Shipped**. The check finds a quantity over 3 and prints the flag.

- **Flag (quantity manipulated):** `G1mmeM0re`

### c) Add an imaginary item

`checkOrder()` flags any `.item` whose text is not one of the three fruits. Give
it one the shop does not sell:

```
<span class="item">Diamond</span>
```

Order 1 Apple, comment as above, submit, click **Packaged and Shipped**.

- **Flag (new item added):** `8lackFr1day1984`

(The new-item check runs *before* the quantity check inside `checkOrder()`, so an
`item` payload wins if both are present on the page. Inject one payload at a time
to see each flag.)

### d) Hide it from the warehouse worker

The forged elements only have to *exist* in the DOM for `checkOrder()` to see
them; they do not have to be visible. Make them invisible:

```
<span class="item" style="display:none">Diamond</span>
```

The packaging sheet now looks like a plain 1-Apple order to the worker, but the
check still finds the hidden item and prints `8lackFr1day1984`. The same
`display:none` trick hides the `quantity` span from task b.

### Proving it another way

You can skip the comment entirely and edit the live page: open DevTools →
**Elements**, add a `<span class="item">Diamond</span>` (or bump a `.quantity`
to `10`), then call `checkOrder()` from the **Console**. Same result, which is
the whole point. A check that runs in the victim's browser can be rewritten by
anyone who reaches that browser.

### e) The fix

See **The fix** below.

### f) Further defenses

See **Further defenses** below.

## Flags

| Task | What | Value |
|------|------|-------|
| b | Over-limit quantity | `G1mmeM0re` |
| c | Item not sold by the shop | `8lackFr1day1984` |

## The fix

Escape the comment so it renders as text, never as markup. Open the **Fix**
editor and change `critical.php`:

```php
<blockquote><?php echo htmlspecialchars($_POST["comment"]); ?></blockquote>
```

Save, then retry the payloads: `<span class="quantity">10</span>` now shows up
literally inside the blockquote instead of becoming an element, `checkOrder()`
finds nothing, and the page reports *No manipulation detected*. **Restore
Original** brings the bug back for the next learner.

## Professional tools

There is no `sqlmap` here (XSS lives in the browser, not the database), so the
tools are the browser's own and an HTTP replay proxy.

- **Browser DevTools.** The **Elements** panel is where you craft and inject
  nodes; the **Console** invokes the page logic (`checkOrder()`) and lets you
  edit the live DOM. This is the fastest way to prove the client-side check is
  the real weakness.
- **`curl`** confirms the raw reflection at the server, with no browser in the
  way. Adjust the port to whatever you mapped with `-p`:

  ```bash
  curl -s http://localhost:9000/ \
    --data-urlencode 'qty[Apple]=1' \
    --data-urlencode 'comment=<b>injected</b>' | grep -A2 'Customer Comment'
  ```

  You will see `<b>injected</b>` sitting inside the blockquote unescaped, the
  server never touched it. After the fix, the same request returns
  `&lt;b&gt;injected&lt;/b&gt;`.
- **Burp Suite Repeater.** Intercept the order POST, send it to Repeater, and
  mutate the comment field to iterate payloads without re-typing in the browser.
  That is the real-world loop for discovering and confirming an XSS sink.

## Further defenses

Escaping stops this particular injection, but the deeper lesson is: **never trust
client-side validation.** `checkOrder()` being the only gate is the real bug:
enforce the item allow-list and the quantity cap on the **server**, where the
attacker cannot rewrite them. Then add a **Content-Security-Policy** that blocks
inline scripts, so an injected `<script>` cannot run even if some other sink is
missed. Output escaping, server-side enforcement, and a CSP together are defense
in depth.

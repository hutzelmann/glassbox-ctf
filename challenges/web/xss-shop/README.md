# Cross-Site Scripting: Stored

> A fruit shop lets you leave a comment with your order, and prints it straight
> onto the warehouse's packaging sheet. Turn that trust into extra goods the shop
> never sold you.

**Domain:** Web · Cross-Site Scripting **Ladder:** XSS 2 of 3: [light](../xss-light/) → **shop** → [cookie](../xss-cookie/)

## The scenario

You are a customer at a small fruit shop. Every order you place is turned into a
*Package Instructions* sheet that a warehouse worker reads before boxing your
goods and clicking **Packaged and Shipped**. The sheet includes a free-text
**comment** you wrote at checkout, printed onto the page exactly as you typed it.

The shop is careful about the order itself: the server refuses any quantity above
three and only knows about the three fruits it sells. But the check that decides
whether an order looks *legitimate* runs in the worker's browser, on the page
built from your input. That is the seam you are going to pull on.

## Your tasks

- **a)** Get your own HTML (not just plain text) to render on the packaging page.
- **b)** Make the shipping check report a quantity larger than the shop lets you order.
- **c)** Slip an item into the delivered package that the shop does not sell.
- **d)** Do it so the warehouse worker reading the sheet notices nothing wrong.
- **e)** Find the mistake in the code and fix it with the **Fix** button.
- **f)** Explain what further defenses you would add.

## Running it

```bash
podman run --rm -p 9000:80 ghcr.io/hutzelmann/glassbox-ctf-xss-shop
```

Then open <http://localhost:9000/>.

## The glass box

- **Fix button**: opens the one vulnerable snippet (`critical.php`) in an
  in-browser editor. Patch it, **Save**, and the running page uses your version
  immediately. **Restore Original** puts the bug back.
- **Debug dial** (the selector in the header): three settings:
  - **Challenge**: a plain comment box.
  - **Hints** (`?debug=1`): the **Comment** box becomes a syntax-highlighting
    HTML editor with live diagnostics. Watch the editor treat your comment as
    *markup* rather than plain text, the same mistake the shop makes.
  - **Debug** (`?debug=2`): what the server received in `$_POST["comment"]`,
    plus the vulnerable snippet that prints it into the packing slip. That shows
    you the bug outright, so try the exploit first.

## Stuck?

The full walkthrough (payloads, flags, and the fix) is in
[solution.md](solution.md). It contains spoilers; turn the debug dial up first.

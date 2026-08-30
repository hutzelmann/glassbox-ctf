# Hello, Hacker: Solution

> ⚠️ **Spoilers below.** This gives away the flag and where it hides. If you want
> to find it yourself, close this file and press **Ctrl+U** (View Source) instead.

## The vulnerability

There is no injectable bug here, this is the intro. But there *is* a mistake,
and it is the most common one in the book: the flag is written into the page's
HTML as a **comment**. `critical.php` is just:

```php
<h6>Solution</h6>
Your first flag is: <!-- H3ll0W0rld -->
```

The browser strips the comment before it paints the page, so nothing shows on
screen. But the comment was still *delivered*: it sits in the source your
browser received. Absent from the rendered page, present in the response. That
gap is the whole lesson.

## Walkthrough

### a) Run and open it

Start the container and open <http://localhost:9000/> (the port is whatever you
mapped with `-p`). The page greets you and says your flag is here, but shows
nothing after the colon. It was not printed; that is intentional.

### b) Find the flag

The flag is inside an HTML comment, so pick any way to look at what the server
actually sent:

- **View Source**: press **Ctrl+U** (or right-click → *View Page Source*) and
  read the raw HTML.
- **Inspector**: open DevTools (**F12**), find the *Elements* panel, and expand
  the node; comments are shown greyed out.
- **Debug dial**: set it to **Hints** (`?debug=1`) to read `critical.php`
  directly in the editor.

Any of the three reveals the comment:

```html
Your first flag is: <!-- H3ll0W0rld -->
```

### c) Submit the flag

If you are in a class, hand `H3ll0W0rld` in wherever your instructor collects
flags.

### d) Try the controls

Turn the **debug** dial from **Challenge** to **Hints** and watch the page swap to
the read-only `critical.php` view, then to **Debug** for the request as PHP
parsed it. The same dial exposes real server internals on the later
challenges. Open the **Fix** button to see the in-browser editor; there is
nothing to patch here, but this is the editor you will use to fix every real
bug that follows.

## Flags

| Task | What | Value |
|------|------|-------|
| b | First flag | `H3ll0W0rld` |

## The fix

There is no code bug to patch, so do not go looking for one. The takeaway is the
rule itself: **never put a secret in content you deliver to the client.** HTML
comments, hidden `<input>` fields, JavaScript variables, data attributes: every
byte the server sends reaches the user, and "hidden" in client-side source is not
hidden at all. Keep secrets on the server and send only what the user is allowed
to have.

## Professional tools

No `sqlmap` here, there is nothing to inject. The professional habit this
teaches is reading the raw response instead of the rendered page. From a
terminal:

```bash
curl "http://localhost:9000/"
```

That prints the exact HTML the server sent, comment and all, no browser to hide
it. Same idea inside the browser: **View Source** (Ctrl+U) and the DevTools
*Elements* panel show you the delivered source rather than the painted page.

**Defenses in one line:** treat everything the client receives as public, and
never rely on it staying unseen, because it never does.

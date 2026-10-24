# LadyQR roadmap

LadyQR is a privacy-oriented QR code generator and URL shortener, built to run on a minimal shared hosting plan (Apache with `.htaccess`, PHP and SSL, about 100 MB) and to be easy for anyone to set up.

## Principles

- Minimal dependencies: ideally none at runtime. No build step.
- Stage 1, the QR code generator, runs entirely in the browser. Nothing is sent to the server, and a strict CSP guarantees it.
- Stage 2, the URL shortener, is private and single-user. It is a small PHP admin that writes a delimited block of redirects into `.htaccess`, so redirects are served by Apache without running PHP. When no redirect matches, the QR code generator loads.
- Anyone must be able to deploy the same thing: one configuration file, our own authentication and bundled documentation.
- **The best the host allows, and never break by asking for too much.** Harden what can be hardened (secure cookies, HTTPS rules), but if the hosting does not allow something, carry on with what it does allow. A server without SSL must still work.
- Use what PHP gives natively. Do not make the user depend on things many shared hosts forbid, such as choosing where sessions are stored.
- License: GPL-2.0-or-later (see `LICENSE`).

## Design decisions

- **Outputs:** PNG and SVG.
- **Configuration file:** a PHP file that returns an array. It is looked up in the web root first and, if missing, one level above, like `wp-config.php`. The file is called `ladyqr-config.php`; a `.gitignore` entry keeps the real one out of the repository and `ladyqr-config.sample.php` documents it. Reading a file above the web root works on the target hosting. As in WordPress plugins, the file starts with `defined( 'LADYQR' ) || exit;`: `index.php` defines `LADYQR` before reading it, so a direct request prints nothing and the file is safe in the web root. `.htaccess` also blocks it.
- **Authentication:** a single user, written in-house.
  - **The password is written in plain text in the configuration file, and secured by the first login.** The file has `user` and `password`. The first time someone logs in with the right user and password, the plain text is replaced in the file by `password_hash()`'s result, which holds its own random salt, the algorithm and the cost (bcrypt by default, so `$2y$…`). From then on the password is checked with `password_verify()`. To change it, write the new one in plain text in the file; it is secured again at the next login. This needs no command line and no hash generator.
  - **Telling a plain password from a hash is reliable:** a hash is what `password_get_info()` recognizes (it starts with `$2y$`, or `$argon2…`), and a plain password never does. `password_needs_rehash()` updates the hash when PHP raises its default cost.
  - **Why not `hash( salt . hash( password ) )`:** with a fast algorithm such as SHA-256, anyone who reads the file can try billions of passwords a second, and a salt does not prevent that. `password_hash()` is slow on purpose, adds the salt itself, is in PHP core since 5.5 without extensions, and took about 195 ms on the target hosting. No separate `salt` field is needed. If a host has no `password_hash()` the admin shows a clear error; there is no weaker fallback.
  - **Rewriting the file must never break it:** only the value of `password` is replaced, keeping comments and the rest of the file. The new version is written to a temporary file, loaded to check that it is valid and gives the same settings except the password, and only then it is renamed over the original. **No `.bak` copy is kept**, unlike the `.htaccess` writes: the backup would hold the plain password, which is what is being removed, and it is not needed because the original is untouched until the rename. If the file cannot be written, the login still works with the plain password and the admin shows a notice that it could not be secured.
  - Until the first login the password sits in plain text in the file, which is blocked from the web and guarded, but should be said in the documentation. `password_hash()` only uses the first 72 bytes of a password.
  - Comparisons of the user and the password are constant-time (`hash_equals`, `password_verify`), and forms carry a CSRF token.
  - **Native PHP sessions, with nothing configured by us:** no custom storage path and no cookie of our own, because many shared hosts do not allow it. PHP's session cookie is the only cookie, and it is a session cookie.
  - **Best-effort hardening that can never break the login:** `HttpOnly` and `SameSite=Lax` through the standard `session_set_cookie_params()`, and `Secure` only when the request came over HTTPS (detected with `$_SERVER['HTTPS']`, port 443 or `X-Forwarded-Proto`). On a server without SSL the login still works.
  - **Short sessions:** the session closes after about 15 minutes without activity, tracked inside the session itself. Having to log in again is fine: the idea is to log in, make the changes and leave.
  - A failed login gets a short delay. Real rate limiting is in the backlog.
- **HTTPS notice:** the login screen shows a small warning under the password field when the connection is not HTTPS, saying that the password would travel unencrypted.
- **No password: locked by default, open only on purpose.** The admin has three states:
  1. **No `user` or `password` set (or either is empty): locked.** Nobody gets in, and the screen explains how to set them. This is a fresh install, so anyone scanning for `/admin/` cannot create redirects on the domain before the owner sets a password.
  2. **`'require_login' => false` in the configuration file: open.** Anyone who can reach the page can use the panel, and every admin page shows a permanent, prominent warning: anyone who can reach this page can change your links, and how to set a user and a password. This is for using it without a password on purpose, for example locally with `php -S`.
  3. **A user and a password set: normal login.**
  - The switch is a setting of its own, deliberately **not** an empty password: the sample file ships with no empty password field to copy by accident, and an empty or missing password never opens the panel by itself. If `require_login` is `false` and there are credentials as well, the panel is still open and still warns.
  - The credentials and `require_login` are only ever set by editing the configuration file, never through the web.
- **`.htaccess` writes:** only inside a `# BEGIN` / `# END` block, validated, atomic (temporary file plus rename), with a `.bak` copy, followed by a request from the server to itself to check that the site still answers, and an automatic rollback if it does not. This works on the target hosting (see `docs/hosting.md`). A syntax error must never take the site down. If a host cannot request itself, there is no rollback and the validation must be stricter.
- **No lock for now.** `flock` does not work across requests on the target hosting, and with a single-user personal tool two simultaneous writes are very unlikely, so writes are done carefully and a lock is left in the backlog.
- **Redirects:** plain `Redirect` lines inside the block. Apache answers them without PHP, and a change takes effect in well under a second on the target hosting.
- **Force HTTPS:** the target hosting does not redirect `http://` to `https://`, so a rule using `%{HTTPS}` in the fixed part of `.htaccess` does it. It was tested and it does not loop.
- **Distribution:** clonable repository first, and later a release `.zip` ready for FTP.
- **PHP is required anyway:** the project needs PHP regardless (the shortener), so the editor page is generated by PHP and what reaches the user is static HTML.
- **Multilingual:** the interface is translatable the WordPress way, so standard tools (`wp i18n make-pot`, Poedit, Loco, GlotPress) work on it.
  - All text is written in English in the code with gettext-style functions, `__( 'Download', 'ladyqr' )` and `_e()`, using the `ladyqr` text domain. A string with no translation stays in English.
  - Translations live in `languages/`: `ladyqr.pot`, and `.po` and `.mo` files named like `ladyqr-es_ES.po`. The compiled `.mo` is committed, because the hosting has no tools.
  - A small in-house `.mo` reader loads the file. It does not use PHP's `gettext` extension, which is not reliable on shared hosting. With the default language, English, no file is read.
  - The language is a single site-wide setting in the configuration file (`'language' => 'es_ES'`), English by default; visitors do not choose it.
  - No gettext plurals. A small `__plural( __( 'singular', 'ladyqr' ), __( 'plural', 'ladyqr' ), $number )` helper picks the singular for exactly 1 and the plural otherwise, and the caller applies `sprintf`. Translation tools see only the inner `__()` calls, so each form is its own string, with a `translators:` comment to say they go together. It supports two forms only, which is enough for English and Spanish; languages with more forms would need real gettext plurals.
  - Strings needed by the browser-side JS are translated in PHP and written into the page as a `<script type="application/json">` block, which a strict CSP does not block, and read with `JSON.parse`.

## Milestones

Work is done one milestone at a time, each in its own branch, and a milestone is only started when explicitly authorized.

### M0. First publishable QR code generator — done

The base of the project, not a throwaway: it lives at the repository root.

- A page generated by PHP (`index.php`) that delivers static HTML, CSS and JS: no dependencies, no build step.
- In-house QR encoder (`assets/qr.js`): byte mode (text and URLs), versions 1 to 40, the four error correction levels, automatic version and mask.
- Rendering helpers (`assets/render.js`): SVG and PNG output, conversion of the module matrix to and from an array of `'0'/'1'` strings, and color contrast helpers.
- The editor: collapsible settings as the card header (error correction, margin, colors), one text field, the QR code, and a download button for PNG 500 px with PNG 1000 px and SVG in its menu. Help dialog and footer included.
- The settings are remembered in the browser's `localStorage`, never the text, and nothing is sent anywhere. A button in the settings resets them.
- A small warning under the QR code when the module and background colors have a contrast ratio of 4:1 or less, when the colors are inverted (modules lighter than the background), or both, each with its own message.
- Translations as described above, with English and Spanish (`es_ES`).
- No external requests, enforced by a CSP sent by PHP.

Built, verified and merged into `main`. Publishing it on the author's own `1qr.es` is milestone M2.

**Decision on the encoder:** writing it ourselves was worth it. It is about 400 lines with no dependencies, and it was verified against an independent implementation, so there is no reason to vendor a library.

**Verification** (done with throwaway scripts outside the repository, with `qrcode`, `jsqr` and `puppeteer-core` as test-only tools):

- The module matrix is identical to the `qrcode` npm package's for 328 inputs that cover all 40 versions at the four error correction levels, forcing byte mode and the same version and mask.
- `jsqr` decodes 346 of 348 symbols back to the original text. The two it fails on (level L, version 23) fail in the same way on the reference implementation's own symbol, so the decoder is at fault, not the encoder. ZXing was also tried; on the inputs where it was compared directly, it behaved the same with our symbols and the reference's.
- In headless Chrome, the 500 px PNG, the 1000 px PNG and the SVG that the editor downloads decode to the exact text, with accents and emoji, also with custom colors, margin and error correction.
- With the CSP active there are no console errors and no requests outside the page's own origin.
- Not tested: Firefox, Safari, touch devices, screen readers, and PHP versions before 8.4.

**Findings worth keeping:**

- Borders were 1.31:1 against the card, far below the 3:1 that WCAG asks for the edges of controls. Measuring them, and measuring text with the same contrast function the editor uses, also found small button labels at 3.89:1 because of an 85% opacity. See `DESIGN.md`.
- The JSON block for translated strings avoids the need for CSP hashes or nonces for inline scripts.
- GitHub Pages cannot send HTTP headers, so the demo carries its policy in a `<meta>` tag, which cannot use `frame-ancestors`.

### Public website — done (first version)

A landing page and a live demo of the editor are published at https://ladyqr.nilovelez.com/ with GitHub Pages.

- **Source:** the landing page is in `site/` (static HTML and CSS, no JavaScript, with its own `<meta>` CSP). It uses the same tokens as the editor.
- **Demo:** `tools/build-site.php` renders `index.php` in English to static HTML under `/try/`, because GitHub Pages cannot run PHP.
- **Deployment:** `.github/workflows/pages.yml` builds `_site/` and deploys it on every push to `main`. Pages is set to the "GitHub Actions" source with `ladyqr.nilovelez.com` as the custom domain; the DNS record is a `CNAME` to `nilovelez.github.io`. No `CNAME` file is needed with this kind of deployment.
- **The QR code** on the landing page and in the README (`site/qr.svg`) is made with the project's own encoder: `node tools/make-qr.mjs https://ladyqr.nilovelez.com/ site/qr.svg L 4`.
- The demo is in English only for now.

### M1. Hosting spike — done

Verified what the target hosting allows before building the shortener. The full results are in [`hosting.md`](hosting.md).

- A self-contained package of 14 checks, run against the real OVH plan from a browser. The package was removed from the repository once its findings were recorded; it is in the Git history (branch `feat-spike`, commit `22ea142`) if it is ever needed again.
- Result: 11 pass and 1 fail. Everything the shortener's design needs works, and the redirects are answered by Apache alone.
- The one failure, `flock`, and two surprises (no automatic HTTP to HTTPS redirect, and no SSL certificate for the bare domain yet) are covered by the decisions above and by M2.
- Also found: the hosting's FTP server does not support TLS, so uploads go over SFTP.

### M2. Publish the editor on `1qr.es` — done

Take what exists to the author's domain. Details and measurements are in [`hosting.md`](hosting.md).

- The author set up the SSL certificate for the bare domain in the OVH panel. **The bare domain `1qr.es` is the canonical address**, to keep short links short, and `www` redirects to it.
- Uploaded over SFTP: `index.php`, `includes/`, `assets/`, `languages/` and `.htaccess`. `www/old` holds the previous site and is not touched.
- `.htaccess` is now part of the repository. It blocks direct access to the configuration file, and has the HTTPS rule **commented out**, because on a server without SSL it would make the site unreachable. The server's copy has that block enabled, plus the `www` redirect.
- **Folder listings:** every folder has a blank `index.html` (the WordPress way), and `DirectoryIndex` includes it, so a folder never shows its contents.
- The sample configuration file carries the `defined( 'LADYQR' ) || exit;` guard.
- Checked in production: `http://` and `www` both reach `https://1qr.es/` in one hop; the CSP and security headers; the PNG and SVG downloads decode to the exact text; no console errors and no requests outside the site; the configuration file is found above the web root, takes precedence when it is in the web root, and is blocked from direct access.
- No configuration file is on the server, so the language is English. A file with `'language' => 'es_ES'` above the web root switches it to Spanish.
- **Found:** files uploaded or deleted over SFTP take between 3 and 16 seconds to be seen by PHP, because of the shared network storage. It does not affect files that PHP writes itself.
- Done: `https://1qr.es/` shows the editor and `http://1qr.es/` redirects to it.

### M3. Configuration and authentication — done

The admin's front door, as decided above. Built and tested locally with `php -S`; **nothing has been uploaded to `1qr.es`.**

- `/admin/` with a single-user login, native PHP sessions, a CSRF token, a 15 minute idle timeout (setting `session_timeout`), logout, and a one second pause after a wrong attempt.
- The user and password are written in plain text in the configuration file and secured by the first login, as described above, including the case where the file cannot be written.
- The locked screen for an install with no credentials, the permanent warning when `require_login` is `false`, and the notice under the password field when the connection is not HTTPS.
- The login follows `DESIGN.md` and is translatable, with Spanish included.
- **The admin is not linked from the editor** and carries `noindex` in an `X-Robots-Tag` header and a `<meta>` tag on every state. There is no `robots.txt` entry for it on purpose: it would keep crawlers from reading the `noindex` and would publish the path.
- `.htaccess` now blocks every file whose name starts with `ladyqr-config`, not just the two known ones.
- New files: `admin/index.php`, `includes/auth.php`, `includes/config-writer.php`, `includes/functions.php` and `assets/admin.css`.
- **`assets/` is now flat:** the stylesheets and scripts that were in `assets/css/` and `assets/js/` sit next to the icons, with a single blank `index.html`. The live server still has the old layout until the next deployment, which must also delete the two old folders there.

**Verification** (local, PHP 8.5 built-in server, Chrome and curl):

- The password writer was run on 15 shapes of configuration file: comments that mention the key, double quotes, short array syntax, CRLF line endings, passwords with quotes, backslashes, `$`, accents and emoji, a value that is already a hash, a concatenation, a variable, a missing key, a key only in a comment, a second `password` key nested in another array, and a read-only file. It replaced the value when it could, kept comments and everything else, left no temporary files, and in every other case changed nothing. The nested key case is caught by the check and reported as failed.
- The login flow: the page and its cookie (`HttpOnly`, `SameSite=Lax`, and `Secure` only behind `X-Forwarded-Proto: https`), a form without a valid token, a wrong password (one second, file untouched), the first login (hash written, comments and user kept, file valid, no leftovers), logout, a second login with the hash (file not rewritten), a wrong user with the right password, and special characters in the password.
- The three states: locked with no credentials or no file, without leaking a server path; open only with a real `false` (not `0`, not `'false'`), still open and warning if there are credentials too; and a session that cannot work, which shows a clear error.
- The idle timeout, with `session_timeout` at 1 minute: still in after 40 s and after another 40 s of activity, expired after 63 s idle, and able to log in again at once.
- A read-only file and an unsupported file both log in, say why the password could not be secured, and are left unchanged.
- Screenshots of every state in light, dark and phone widths, with no console errors and no horizontal scrolling.
- The editor and the website build still work.
- Not tested: PHP 7.4, a real hosting plan (this was local only, by decision), Firefox and Safari, and screen readers.

**Found:** OPcache keeps a compiled copy of the configuration file and rechecks it every 2 seconds, so a change made by hand can take that long to show. When LadyQR rewrites the file itself it invalidates the copy, so its own change is seen at once. A test script that rewrote the file twice within 2 seconds was fooled by this, which is how it was found.

Done when the admin cannot be entered without the password unless `require_login` is `false`, with and without HTTPS, the plain password is replaced by its hash at the first login and the file stays valid, and the session expires. All of it holds locally.

### M4. Redirect manager — built, waiting for approval

The short links, managed in the admin. Built and tested locally on branch `feat/m4-redirect-manager`; **nothing has been uploaded to `1qr.es`.**

- **List, add, edit and delete** in `/admin/`. Without JavaScript: editing and deleting are their own pages, and deleting asks for confirmation.
- **The managed block is the only store.** It sits between `# BEGIN LadyQR short links` and `# END LadyQR short links` in the root `.htaccess`, one line per link: `RedirectMatch 302 ^/slug$ https://target`. It is `RedirectMatch` with an exact pattern because a plain `Redirect` would also catch everything under the name (`/slug/anything`). The path includes the folder when LadyQR lives in a subfolder. There is no database and no extra data file.
- **Each link is temporary (302) or permanent (301).** Temporary is the default, and the form explains that browsers remember permanent ones.
- **Validation:** names of letters, digits and hyphens up to 64 characters, compared without regard to case when looking for duplicates, and not reserved. The reserved names are a fixed list (`admin`, `assets`, `includes`, `languages`, `old`, `index`, `docs`, `site`, `tools`) plus anything that exists in the web root, since a redirect is applied before the file is looked for and would hide it. Targets must be `http` or `https` with a host, in plain ASCII, with no spaces, control characters, quotes, backslashes or angle brackets, so nothing a person types can end the line or start another directive. A dollar sign is written as `%24`, because it is special in a `RedirectMatch` target. A link to itself is refused.
- **Safe writes:** a copy of the current file is saved as `.htaccess.bak` (Apache never serves `.ht*` files, and it holds nothing secret); the new file is built aside and put in place with one rename (or written in place if the folder is not writable); it is read back and compared; and then the site is requested over HTTP (the blank page of `assets/`). If it answers with an error, the old file is put back and the admin says so. If the request cannot be made (no cURL and no `allow_url_fopen`, or PHP's built-in server), the change is kept and the admin warns that it was not checked.
- **What is not ours is respected:** anything else in the `.htaccess` is left alone, and a line inside the block that is not a link is kept. If the markers are damaged (one missing or repeated), the admin refuses to touch the file and says so. If there is no `.htaccess`, the forms are hidden and it asks for the one that comes with LadyQR.
- **Not found:** anything that matches no link and no file answers with a real 404 and a plain page, `404.php` (`ErrorDocument 404 /404.php` in `.htaccess`), with a link to the editor. This closes the open question: the answer is 404, not the editor with status 200. In a subfolder install the path in that line needs the folder in front, and the file says so.
- **Left to the backlog:** a lock around the write (`flock` does not work on the target host), so two simultaneous saves could lose one.
- **Also fixed:** at 900 px and wider, the admin card inherited the editor's two-column grid and left a blank column. The admin now has its own single-column card.

**Verification** (local Apache 2.4 with PHP-CGI and `AllowOverride All`, plus the built-in server): 41 checks end to end with a logged-in session. The link redirects with the right status and `Location`, Apache alone answers it (no `X-Powered-By`), `/slug/extra` is a 404, the dollar sign survives, and the list reads the file back. Fourteen kinds of invalid input are refused without touching the file. Rename and type change work, and delete confirms and removes. A wrong or missing CSRF token changes nothing, and neither does an anonymous request. A foreign line inside the block and the rest of the file survive. An invalid directive is rolled back byte for byte and the site keeps answering. Damaged markers and a read-only file are reported. The same flow works from a subfolder (`/sub/hi`). Screenshots in light, dark and phone widths.

Done when a link created in the admin redirects without PHP and a mistake can never leave the site returning errors.

### M5. Documentation and packaging

Installation guide, troubleshooting based on `hosting.md`, a complete `ladyqr-config.sample.php`, and a release `.zip` ready for FTP.

### M6. Automated tests and encoder consolidation

Automated tests with Node's built-in runner, kept apart from the runtime code (the test-only tools would be development dependencies), including the checks that today live in throwaway scripts. Optionally, numeric and alphanumeric modes for smaller codes.

## Backlog

Things that are wanted but not urgent, in no particular order.

- **A lock for `.htaccess` writes.** `mkdir` is atomic on the target hosting (1 success out of 6 simultaneous attempts), unlike `flock`. Use it with a timeout for stale locks.
- **Import the old short links** the author had on `1qr.es`, configured by the author.
- **Automate the deployment** to the hosting over SFTP (for example from GitHub Actions), instead of uploading by hand.
- **A QR code for each short link** in the admin, using the editor.
- **Real login rate limiting.** It needs somewhere to store attempts, which the "native only" principle avoids; the current answer is a short delay.
- **More testing:** Firefox, Safari, touch devices, screen readers, and PHP 7.4, which is the stated target but has not been run.
- **The website's demo in Spanish.**
- **A manual light/dark switch.**
- **Confirm the real disk quota** (100 MB) in the OVH panel: PHP only sees the disk of the whole cluster.
- **A script for the SFTP deployment**, which M2 did by hand.

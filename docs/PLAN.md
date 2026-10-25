# LadyQR roadmap

LadyQR is a privacy-oriented QR code generator and URL shortener, built to run on a minimal shared hosting plan (Apache with `.htaccess`, PHP and SSL, about 100 MB) and to be easy for anyone to set up.

## Principles

- Minimal dependencies: ideally none at runtime. No build step.
- Stage 1, the QR code generator, runs entirely in the browser. Nothing is sent to the server, and a strict CSP guarantees it.
- Stage 2, the URL shortener, is private and single-user. It is a small PHP admin that keeps the links in a data file; `index.php` answers them, and `.htaccess` only sends the paths that do not exist to it (see M5). Without a working `.htaccess` the shortener is off and the generator still works.
- **Secure by design without `.htaccess`.** The application works and is secure if there is no `.htaccess`; it adds nothing that security depends on.
- Anyone must be able to deploy the same thing: an installer in the style of WordPress's, one configuration file, our own authentication and bundled documentation. Updating is uploading the new files over the old ones: the zip never contains a file that the user or the application changes.
- **The best the host allows, and never break by asking for too much.** Harden what can be hardened (secure cookies, HTTPS rules), but if the hosting does not allow something, carry on with what it does allow. A server without SSL must still work.
- Use what PHP gives natively. Do not make the user depend on things many shared hosts forbid, such as choosing where sessions are stored.
- License: GPL-2.0-or-later (see `LICENSE`).

## Design decisions

- **Outputs:** PNG and SVG.
- **Configuration file:** a PHP file of constants with the `LADYQR_` prefix, as `wp-config.php` does (`define( 'LADYQR_LANGUAGE', 'es_ES' );`). Until M5 it returned an array. It is looked up in the web root first and, if missing, one level above, like `wp-config.php`. The file is called `ladyqr-config.php`; a `.gitignore` entry keeps the real one out of the repository and `ladyqr-config.sample.php` documents it. Reading a file above the web root works on the target hosting. As in WordPress plugins, the file starts with `defined( 'LADYQR' ) || exit;`: `index.php` defines `LADYQR` before reading it, so a direct request prints nothing and the file is safe in the web root, with or without `.htaccess`.
- **Authentication:** a single user, written in-house.
  - **The password is written in plain text in the configuration file, and secured by the first login.** The file has `LADYQR_USER` and `LADYQR_PASSWORD`. The first time someone logs in with the right user and password, the plain text is replaced in the file by `password_hash()`'s result, which holds its own random salt, the algorithm and the cost (bcrypt by default, so `$2y$…`). From then on the password is checked with `password_verify()`. To change it, write the new one in plain text in the file; it is secured again at the next login. This needs no command line and no hash generator.
  - **Telling a plain password from a hash is reliable:** a hash is what `password_get_info()` recognizes (it starts with `$2y$`, or `$argon2…`), and a plain password never does. `password_needs_rehash()` updates the hash when PHP raises its default cost.
  - **Why not `hash( salt . hash( password ) )`:** with a fast algorithm such as SHA-256, anyone who reads the file can try billions of passwords a second, and a salt does not prevent that. `password_hash()` is slow on purpose, adds the salt itself, is in PHP core since 5.5 without extensions, and took about 195 ms on the target hosting. No separate `salt` field is needed. If a host has no `password_hash()` the admin shows a clear error; there is no weaker fallback.
  - **Rewriting the file must never break it:** only the single-quoted value of `LADYQR_PASSWORD` is replaced by another single-quoted string, keeping comments and the rest of the file. The new text is checked to hold the hash where the password was, written to a temporary file, renamed over the original and read back. **No `.bak` copy is kept**, unlike the `.htaccess` writes: the backup would hold the plain password, which is what is being removed, and it is not needed because the original is untouched until the rename. If the file cannot be written, the login still works with the plain password and the admin shows a notice that it could not be secured.
  - Until the first login the password sits in plain text in the file, which is blocked from the web and guarded, but should be said in the documentation. `password_hash()` only uses the first 72 bytes of a password.
  - Comparisons of the user and the password are constant-time (`hash_equals`, `password_verify`), and forms carry a CSRF token.
  - **Native PHP sessions, with nothing configured by us:** no custom storage path and no cookie of our own, because many shared hosts do not allow it. PHP's session cookie is the only cookie, and it is a session cookie.
  - **Best-effort hardening that can never break the login:** `HttpOnly` and `SameSite=Lax` through the standard `session_set_cookie_params()`, and `Secure` only when the request came over HTTPS (detected with `$_SERVER['HTTPS']`, port 443 or `X-Forwarded-Proto`). On a server without SSL the login still works.
  - **Short sessions:** the session closes after about 15 minutes without activity, tracked inside the session itself. Having to log in again is fine: the idea is to log in, make the changes and leave.
  - A failed login gets a short delay. Real rate limiting is in the backlog.
- **HTTPS notice:** the login screen shows a small warning under the password field when the connection is not HTTPS, saying that the password would travel unencrypted.
- **No password: locked by default, open only on purpose.** The admin has three states:
  1. **No `user` or `password` set (or either is empty): locked.** Nobody gets in, and the screen explains how to set them. This is a fresh install, so anyone scanning for `/admin/` cannot create redirects on the domain before the owner sets a password.
  2. **`define( 'LADYQR_REQUIRE_LOGIN', false );` in the configuration file: open.** Anyone who can reach the page can use the panel, and every admin page shows a permanent, prominent warning: anyone who can reach this page can change your links, and how to set a user and a password. This is for using it without a password on purpose, for example locally with `php -S`.
  3. **A user and a password set: normal login.**
  - The switch is a setting of its own, deliberately **not** an empty password: the sample file ships with no empty password field to copy by accident, and an empty or missing password never opens the panel by itself. If `require_login` is `false` and there are credentials as well, the panel is still open and still warns.
  - The credentials and `require_login` are only ever set by editing the configuration file, never through the web.
- **`.htaccess` writes:** only inside a `# BEGIN` / `# END` block (from M5 it holds the routing rule, not the links), validated, atomic (temporary file plus rename), with a `.bak` copy, followed by a request from the server to itself to check that the site still answers, and an automatic rollback if it does not. This works on the target hosting (see `docs/hosting.md`). A syntax error must never take the site down. If a host cannot request itself, there is no rollback and the validation must be stricter.
- **No lock for now.** `flock` does not work across requests on the target hosting, and with a single-user personal tool two simultaneous writes are very unlikely, so writes are done carefully and a lock is left in the backlog.
- **Redirects:** until M4, `RedirectMatch` lines inside the block, answered by Apache without PHP. From M5 they live in `ladyqr-data.php` and `index.php` answers them, so a lost or replaced `.htaccess` can never lose the links.
- **Force HTTPS:** the target hosting does not redirect `http://` to `https://`, so a rule using `%{HTTPS}` does it. It was tested and it does not loop. From M5 the installer writes it, with the chosen host (with or without `www`), at the top of a `.htaccess` it creates.
- **Distribution:** clonable repository first, and later a release `.zip` ready for FTP.
- **PHP is required anyway:** the project needs PHP regardless (the shortener), so the editor page is generated by PHP and what reaches the user is static HTML.
- **Multilingual:** the interface is translatable the WordPress way, so standard tools (`wp i18n make-pot`, Poedit, Loco, GlotPress) work on it.
  - All text is written in English in the code with gettext-style functions, `__( 'Download', 'ladyqr' )` and `_e()`, using the `ladyqr` text domain. A string with no translation stays in English.
  - Translations live in `languages/`: `ladyqr.pot`, and `.po` and `.mo` files named like `ladyqr-es_ES.po`. The compiled `.mo` is committed, because the hosting has no tools.
  - A small in-house `.mo` reader loads the file. It does not use PHP's `gettext` extension, which is not reliable on shared hosting. With the default language, English, no file is read.
  - The language is a single site-wide setting in the configuration file (`define( 'LADYQR_LANGUAGE', 'es_ES' );`), English by default; visitors do not choose it.
  - No gettext plurals. A small `__plural( __( 'singular', 'ladyqr' ), __( 'plural', 'ladyqr' ), $number )` helper picks the singular for exactly 1 and the plural otherwise, and the caller applies `sprintf`. Translation tools see only the inner `__()` calls, so each form is its own string, with a `translators:` comment to say they go together. It supports two forms only, which is enough for English and Spanish; languages with more forms would need real gettext plurals.
  - Strings needed by the browser-side JS are translated in PHP and written into the page as a `<script type="application/json">` block, which a strict CSP does not block, and read with `JSON.parse`.

## Milestones

Work is done one milestone at a time, and a milestone is only started when explicitly authorized.

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

### M4. Redirect manager — done

The short links, managed in the admin. Built and tested locally; **nothing has been uploaded to `1qr.es`.**

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

### Design revision — done

A round of design and UX corrections from the author, before M5.

- **Light only.** The dark theme is gone from the editor, the admin, the not-found page and the website. The "manual light/dark switch" leaves the backlog.
- **A simpler, cleaner look.** Neutral grays, 6 px radius on controls and 10 px on cards, a near-flat card, 44 px controls with labels above them in sentence case, buttons as wide as their text, and fields with an add-on (the site's address before a short name) that line up with the rest.
- **The short links are a table** with alternating white and soft gray rows, and the code (`301` or `302`) in its own column instead of "Permanent" or "Temporary". The type select reads "301 – Permanent redirect" and "302 – Temporary redirect". Edit and delete have their own headings.
- **Logging out moved** from a button in the card to a line above it, on the right: "Signed in as admin – Log out", where "Log out" is a link carrying the session's token.
- **DESIGN.md is now a design system**: tokens with measured contrast, shape and spacing scales, the shared components, each screen, and a consistency checklist. The shared components moved to `assets/app.css`.
- The website's editor screenshot was retaken, and its roadmap no longer says the admin and the shortener are only planned.
- **The editor's settings** are a folded "Customize" box under the QR code on narrow screens, showing the current values, and the side column on wide ones. Inside: error correction, the two colors side by side, the margin and the reset button.
- **The short link forms** put the labels beside the fields from 720 px up.

### M5. Installer, and a site that works without `.htaccess` — done

Reviewed with a user persona: Alfonso, a web implementer used to WordPress, PrestaShop and Joomla. He can create a hosting, unzip a file, edit a configuration file and upload by FTP or with the host's file manager, but he does not read code and knows that an application's code files are not to be touched, only its configuration. What M4 left him: a zip with an `.htaccess` that mixes fixed rules, settings he has to edit by hand (HTTPS, the subfolder) and all his short links, so uploading a new version over the old one would wipe them.

**Principles**

- **Secure by design without `.htaccess`.** The application must work and be secure if there is no `.htaccess`. Every PHP file that is not a page stops when requested directly, and no copy of the configuration or the data is ever written with another extension.
- **The zip never contains a file that the user or the application changes.** No `ladyqr-config.php`, no `ladyqr-data.php`, no `.htaccess`. Updating is "upload everything over the old one".
- **The release zip is clean** (runtime files only), with an `INSTALL.txt` (not a `README.txt`) that covers installing and updating. Not built yet.

**Short links**

- Stored in `ladyqr-data.php`, a PHP file that returns an array of the links (written with `var_export()`, guarded like the configuration), and always answered by PHP. `.htaccess` only sends the paths that do not exist to `index.php`.
- `index.php` is the single entry point: the root is the editor, a known name is a short link, anything else is a 404.
- Without a working `.htaccess` the shortener is turned off and only the editor works.
- Whether the shortener is on is a setting in `ladyqr-config.php`. The installer tests once that `.htaccess` really reaches PHP (a request to a test path) and writes the result; the application does not test it again.

**Installer, in the style of WordPress's five minute install**

- The zip has no `.htaccess`. `/admin/` sends to `/admin/install.php` when there is no `ladyqr-config.php`.
- **Not installed means not usable, so nobody else can install it first.** Reviewed with a second persona, Antonio: he does not read, uploads the files, opens the site, sees the QR code creator working and never visits `/admin/`. If the site worked without being installed, the first bot to find `/admin/install.php` would install it with its own user and use his domain for phishing redirects (or, through the site address, redirect the whole site). So, without `ladyqr-config.php`, **every visit to the site goes to the installer** while `admin/install.php` exists; the owner, who opens the site right after uploading it, is the one who installs it. If `admin/install.php` does not exist, the site is the QR code creator alone, with no notice and no redirect: deleting the `admin` folder is how to use only the creator, with nothing to attack. The address the installer uses to test `.htaccess` is answered before this redirect.
- `install.php` stops if `ladyqr-config.php` already exists, and says so.
- **Four screens**, and nothing is written until the third is sent, so going back is always free:
  1. **The installer's language**, English by default. The installer is translatable like the rest. Its language travels in the address (`?lang=es_ES`), not in the session, which has not been checked yet.
  2. **Welcome**: two or three lines in the first person ("Hi! I'm LadyQR…") and "How will you use LadyQR?", with two big buttons side by side (one under the other on a phone): **QR code generator + link shortener** and **QR code generator only**.
  3. **The server checks for that choice, and its settings.** The site's language comes first, preselected with the installer's but separate from it: the installer can be in Spanish and the site in English.
     - Generator only: checks PHP 7.4 or later (critical) and writing in the web root (a warning); asks only for the site's language.
     - Generator and shortener: also password hashing and random numbers, and sessions (critical), `.htaccess` reaching PHP and HTTPS (warnings); asks for the site's address, a user and a password.
     - If PHP cannot write to the web root, it still goes on, and screen 4 shows the contents of each file to create and upload by hand, as WordPress does with `wp-config.php`.
  4. **The result**: the files to create by hand if needed; for the shortener, a summary, the user and the password once (warning that the password is now encrypted and will not be shown again), and buttons to the generator and to the shortener; for the generator alone, that changing your mind means editing `ladyqr-config.php` or deleting it to use the installer again, and a button to the generator.
- **The settings of screen 3**:
  - Base address of the short links: the path is always where LadyQR is installed. The scheme and the host are proposed from the current request and can be corrected (with or without `www`, `http` or `https`), since the visit may have come in through the wrong one. Once installed, `.htaccess` forces that form.
  - An admin with no password is not offered; it stays as a manual setting in the configuration file, with its permanent warning.
  - Password: a suggested one is filled in, visible, 20 random characters from `random_bytes()`, letters and digits without the ambiguous ones (`0 O o 1 l I`). Alfonso can change it: at least 12 characters, at most 72 bytes (the `password_hash()` limit), with a message instead of a silent cut. No confirmation field, since it is visible. No JavaScript; reloading gives another suggestion. Only the hash is written.
- **On saving**, the shortener writes `.htaccess`, `ladyqr-data.php` and, last, `ladyqr-config.php`; the generator alone writes only `ladyqr-config.php`, with its language and the shortener off. Always in the web root next to `index.php`; they still work if moved one level up by hand.

**`.htaccess`**

- If one exists, only the LadyQR block is added, between `# BEGIN LadyQR` and `# END LadyQR`, with the rule that sends the paths that do not exist to `index.php`. Nothing else is touched.
- If it is created from scratch, the generic rules go at the top (force HTTPS, and the host with or without `www`), and then the LadyQR block.
- The block carries a version. When entering the admin, a block from another version is rewritten automatically, and a missing block (or a missing file) is put back, with the existing check and rollback, and the admin says what it did. If it cannot write, it shows the text to paste.
- This also covers a manual install: copy the sample, set the user, the password and the shortener on, and the admin writes the block on the first visit.

**Generator only mode**

- `/admin/` shows a notice that the shortener is turned off in the configuration, without saying how to change it.

**Consequences**

- The M4 shortener is rebuilt: the links move from `RedirectMatch` lines in `.htaccess` to `ladyqr-data.php`, served by `index.php`.
- The removal of the empty block when the last link is deleted (commit `12ac1d7`) no longer applies.
- The configuration file became constants with the `LADYQR_` prefix, and the links file a PHP array.

**Also in M5**

- The admin's name and the ladybug sit above the card, on the left, with the account line on the right. The footer link reads "Back to the QR code creator".
- Ordinary buttons are gray (primary) or white (secondary); red is kept for very specific actions, such as the download. A step back comes before the step forward. See DESIGN.md.

**Verification** (a local Apache 2.4 with PHP-CGI, on its own port and folder, apart from the author's test site): 76 checks end to end. The four screens in both languages and going back; nothing written before confirming; the shortener install at the root and in a subfolder, with the route test, the address rules and the redirect of another host; the generator alone; a manual install from the sample, with the password hashed at the first login; adding, renaming, changing the code and deleting links; a lost or outdated block put back by the admin; a foreign `.htaccess` kept; direct requests to the configuration, the data and `includes/` printing nothing; every visit sent to the installer until it is installed, and the creator alone when the `admin` folder is deleted. Separately, a folder PHP cannot write to: the installer shows the three files and writes nothing.

### M6. Documentation and packaging — done (1.0.0)

- **A clean release zip, built by a GitHub Action** when a release is published, with the runtime files only. `assets/icon-full.png`, the full-size icon, is for development and stays out.
- **A permanent download link:** each release gets the zip attached as `ladyqr-latest.zip`, so https://github.com/nilovelez/ladyqr/releases/latest/download/ladyqr-latest.zip always gives the latest version. A short link on `1qr.es` can point to it later.
- **`INSTALL.txt`** (not a `README.txt`): installing and updating, written for someone like Alfonso.
- **The README brought up to date** with M5: no `.htaccess` in the repository, the installer, the links in `ladyqr-data.php`, the configuration as constants.
- **Troubleshooting** based on `docs/hosting.md`, and a complete `ladyqr-config.sample.php`.
- **Installer changes after the first real install** on `1qr.es`:
  - "Generator only" also asks for the site's address. A new `.htaccess` gets only the address rules (with or without `www`, and https when the server reports it), with the same check and rollback; an existing one is left alone; if it cannot be written, its text is shown to create by hand.
  - With the shortener, the address hint says it is also the start of the short links, with an example.
  - The final summary shows the admin panel's address on its second line.
  - The two final buttons are both secondary, the shortener first: nothing is left to decide.
- **Short links ignore case:** `/pepe`, `/PEPE` and `/Pepe` are the same link. The slug is stored as typed, and the admin already refused a slug that differs from an existing one only in case ("There is already a short link with that name"). Done before 1.0.0, so no install ever has two links that would clash.
- **The new icons:** the ladybug fitted to its drawing in the pages, the square ones as favicon and Apple touch icon. See DESIGN.md.
- **`CLAUDE.md` and `docs/HANDOFF.md`**, so a new session can pick the project up from the repository alone. The test tools moved out of a temporary folder to `D:\Claude\ladyqr-tools\`.

### M7. Automated tests and encoder consolidation

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
- **Confirm the real disk quota** (100 MB) in the OVH panel: PHP only sees the disk of the whole cluster.
- **A script for the SFTP deployment**, which M2 did by hand.
- **A new icon that works at small sizes** (a 32 and 16 px favicon). Today the 128 px icon is used everywhere. Nilo will draw it; 1.0.0 ships with the current one.
- **QR codes from the shortener.** Each link gets a QR icon that opens the editor with the short link filled in. Pieces:
  - The editor takes its initial text from the address fragment (`#text=…`), so it never reaches the server.
  - An optional `LADYQR_QR_BASE` (such as `1QR.ES/`) for the short links in QR codes: no scheme and in uppercase, to use the alphanumeric mode. The admin still copies links with `https://`.
  - The encoder's alphanumeric mode (now in M7). A version 1-M code holds 14 characters in byte mode (`1QR.ES/1234567`) and 20 in alphanumeric mode (`HTTP://1QR.ES/123456`).
  - Slugs and the alphanumeric set: `_` is not in it, and short links already ignore case (done in 1.0.0, see M6).
- **Customize the generator page.** How is to be decided; probably an optional `header.html` and `footer.html` in the install folder, included before and after the generator when they exist.

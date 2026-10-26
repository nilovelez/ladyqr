<p align="center">
  <img src="assets/ladyqr@2X.png" alt="LadyQR, a ladybug with a QR code on its back" width="128" height="96">
</p>

<h1 align="center">LadyQR</h1>

<p align="center"><strong>QR codes that never leave your browser.</strong></p>

<p align="center">
  <a href="https://github.com/nilovelez/ladyqr/releases/latest/download/ladyqr-latest.zip">Download</a> ·
  <a href="https://ladyqr.nilovelez.com/">Website and live demo</a> ·
  <a href="docs/PLAN.md">Roadmap</a> ·
  <a href="DESIGN.md">Design</a>
</p>

LadyQR is a privacy-oriented QR code generator, with an optional private link shortener for your own domain, made to run on a minimal shared hosting plan.

You type a link and the QR code appears as you type. You download it as a PNG or a vector SVG. What you type is never sent to a server, and the page loads nothing from outside.

<p align="center">
  <img src="site/qr.svg" alt="QR code that opens ladyqr.nilovelez.com" width="160" height="160"><br>
  <sub>Scan me. This code was made with LadyQR.</sub>
</p>

## Features

- **Instant.** The code updates on every keystroke. There is no generate button.
- **Private.** The code is computed in the browser. A strict Content Security Policy forbids any network request from the page.
- **PNG and SVG.** A 500 px PNG with one click, a 1000 px PNG or a vector SVG from the menu next to it.
- **Made to be scanned.** Error correction levels L, M, Q and H, a margin from 0 to 8 modules, and custom colors. A warning appears for low contrast (4:1 or less), for inverted colors (modules lighter than the background), or for both.
- **Remembers your settings, nothing else.** Error correction, margin and colors are kept in your browser's `localStorage`. The text you type is never stored. A button resets the settings.
- **Optional link shortener.** Short links on your own domain, such as `https://example.com/menu`, managed from a private single-user admin.
- **Installs like WordPress.** Upload the files, open your site, and a short web installer asks what it needs. No database.
- **Own encoder.** About 450 lines of JavaScript with no dependencies and no build step. Versions 1 to 40, in the most compact mode for the text: numeric, alphanumeric, or byte mode (UTF-8, so accents and emoji work, up to 2953 bytes at level L).
- **Made to be customized.** A `theme` folder with your own CSS, header, footer and head tags, without touching LadyQR's files, so updates keep it. See [Customizing the generator page](#customizing-the-generator-page).
- **Accessible.** High-contrast borders, full keyboard use and screen reader labels. See [DESIGN.md](DESIGN.md).
- **Translatable.** Translations work like WordPress, with standard `.po` files. English and Spanish are included.

## Privacy and security

- The page is served with this policy, sent as an HTTP header by `index.php`:

  ```
  default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self';
  connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'
  ```

  `connect-src 'none'` makes the browser refuse any connection from the page, whatever the code tries to do. There are no inline scripts or styles, and no fonts, images or scripts from other sites. You can check it yourself: open the developer tools on the Network tab and type something.
- Only the settings (error correction, margin and colors) are remembered, under the `ladyqr-settings` key in `localStorage`. They never leave your browser.
- The live editor on the website has no HTTP headers to send, because GitHub Pages does not allow them. It carries the same policy in a `<meta>` tag instead.
- **Secure without `.htaccess`.** LadyQR does not rely on `.htaccess` to protect anything. Every PHP file that is not a page stops when it is requested directly, and the configuration and the links are PHP files that print nothing. `.htaccess` only adds the routing for short links and the rules that force your site's address.
- **Not installed means not usable.** Until LadyQR is installed, every visit to the site goes to the installer, so a bot cannot find a working site with an open installer and take it over. Install it right after uploading it.

## Requirements

- PHP 7.4 or later is the target, with no extensions beyond the default ones. The code has been tested on PHP 8.4 and 8.5.
- A web server that can run PHP. Apache is the target.
- For the link shortener: Apache with `mod_rewrite` and `.htaccess` files allowed. Most shared hostings have both. The QR code generator works without them.
- HTTPS is recommended, so the admin's password travels encrypted.
- No database.

The QR code itself needs a modern browser: ES modules, `<dialog>`, and `localStorage` (the editor works without it).

## Installation

1. Download [ladyqr-latest.zip](https://github.com/nilovelez/ladyqr/releases/latest/download/ladyqr-latest.zip), the latest release. Unzip it on your computer: you get a folder called `ladyqr`.
2. Upload what is **inside** the `ladyqr` folder to your site, by FTP or with your hosting's file manager. It can go in the root of your domain or in a folder of it, such as `/qr/`.
3. Open your site in a browser. The installer starts by itself and asks for the language of the installer, which components you want (the QR code generator alone, or with the link shortener) and the settings for that choice: the site's language and address, an optional title and description for the QR code page, and for the shortener a user and a password. If you choose the shortener, write down the user and the password it shows at the end; the password is not shown again.

The installer writes `ladyqr-config.php` (the settings), `ladyqr-data.php` (the short links) and, if there is none, an `.htaccess` with the rules that force your site's address. If PHP cannot write in the site's folder, it shows the content of each file for you to create and upload.

**Only the QR code generator, with nothing to attack.** Instead of using the installer, you can delete the `admin` folder. Without it there is no installer and no admin: the site is the QR code generator alone.

**Updating.** Upload the contents of the new zip over your current files. Your settings, your short links and your `.htaccess` are not in the zip, so they are kept. If you had deleted the `admin` folder, delete it again. When you next open the admin, it updates LadyQR's rules in `.htaccess` if the new version needs it.

**Installing again.** Delete `ladyqr-config.php` and open your site: the installer starts again. Your short links, in `ladyqr-data.php`, are kept.

The zip also has an `INSTALL.txt` with these steps.

## Configuration

The installer writes the configuration for you. It is `ladyqr-config.php`, a PHP file of constants with the `LADYQR_` prefix. It is looked up in the web root first and, if it is not there, one level above, as WordPress does with `wp-config.php`. If both exist, the one in the web root wins; they are never merged. Putting it above the web root keeps it out of reach of the web server.

To write it by hand, copy `ladyqr-config.sample.php` to `ladyqr-config.php` and change what you need:

| Constant | Default | Meaning |
|---|---|---|
| `LADYQR_LANGUAGE` | `'en_US'` | Interface language, as a WordPress-style locale such as `es_ES`. It is one setting for the whole site; visitors do not choose it. |
| `LADYQR_URL` | the address of each visit | The address of the site, without a final slash, such as `https://example.com` or `https://example.com/qr`. Short links are built on it. |
| `LADYQR_SHORTENER` | `false` | Turns the link shortener on. It needs a user and a password, and `.htaccess` (see [Admin](#admin)). |
| `LADYQR_USER` | none | The admin's user. |
| `LADYQR_PASSWORD` | none | The admin's password. Written by hand in plain text, it is replaced with a hash at the first login. |
| `LADYQR_SESSION_TIMEOUT` | `15` | Minutes without activity after which the admin asks for the password again. |
| `LADYQR_REQUIRE_LOGIN` | `true` | Only an explicit `false` opens the admin without a login, with a permanent warning. |
| `LADYQR_QR_BASE` | the site's address | Advanced, and not needed. What the QR codes of short links start with, such as `1QR.ES/` (see [Admin](#admin)). |
| `LADYQR_SITE_TITLE` | `LadyQR` | The title of the page with the QR code generator. |
| `LADYQR_SITE_DESCRIPTION` | none | The meta description of the page with the QR code generator. Without it, the page has none. |

Keep the line `defined( 'LADYQR' ) || exit;` at the top: it makes the file print nothing when requested directly. `ladyqr-config.php` and `ladyqr-data.php` are ignored by Git, so your settings and links never end up in the repository.

## Admin

The admin is at `/admin/`. It has the login and the short links. Nothing on the editor page links to it, and its pages tell search engines not to index them (a `noindex` header and meta tag). There is deliberately no `robots.txt` entry for it: it would stop crawlers from reading the `noindex`, and it would publish the path. If the shortener is off, the admin only says so.

**The password.** The installer stores only a hash, made with `password_hash()` (bcrypt, with its own random salt inside), and checks it with `password_verify()`. To change the password, write the new one in plain text in the configuration file:

```php
define( 'LADYQR_PASSWORD', 'your-new-password' );
```

The next time you log in, LadyQR replaces it in the file with its hash, keeping the rest of the file as it was. Until then the password sits in plain text in a file that prints nothing, but do not leave it that way for long. `password_hash()` only uses the first 72 bytes of a password.

The file has to be writable by PHP for this. If it is not, or if the password is written in a form LadyQR cannot edit safely (for example built from several strings), the login still works with the plain password and the admin tells you why it could not secure it. LadyQR checks the new file before putting it in place, and if the check fails it changes nothing.

**Short links.** Once logged in, the admin lists your short links and lets you add, edit and delete them. Each link has a short name (letters, digits and hyphens, up to 64), an `http` or `https` address it goes to, and a type: temporary (302, the default) or permanent (301, which browsers remember). Names ignore case: `/menu`, `/MENU` and `/Menu` are the same link, so the admin refuses a name that differs from an existing one only in case. The names of LadyQR's own files and folders are reserved.

**A QR code for each short link.** The small QR icon before each link opens the generator in a new tab with the short link filled in, such as `https://example.com/menu`. The link travels in the address fragment (`#text=`), which the browser never sends to the server.

For smaller codes, set `LADYQR_QR_BASE` to your domain in uppercase and without `https://`, such as `define( 'LADYQR_QR_BASE', '1QR.ES/' );`. The QR icon then fills in `1QR.ES/MENU`: names ignore case, so it still finds the link, and a text of digits, uppercase letters and a few symbols fits the encoder's alphanumeric mode, which needs fewer modules. At level M, the smallest code, version 1, holds 20 characters in alphanumeric mode and 14 in byte mode. Most phone cameras open it as a web address, but test a code before you print it.

The links are stored in `ladyqr-data.php`, a PHP file that returns an array, and `index.php` answers them. Every save is made carefully: the new file is written aside, put in place in one move and read back.

**`.htaccess`.** For a short link such as `/menu` to reach `index.php`, `.htaccess` has to send every address that is not a real file or folder to it. LadyQR keeps those rules in a block of its own:

```apache
# BEGIN LadyQR
# Version 1. Written by LadyQR, which rewrites this block when it needs to.
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ /index.php [L]
</IfModule>
# END LadyQR
```

The rest of the file is yours and is left alone. When you open the admin, LadyQR puts the block back if it is missing and rewrites it if it comes from another version. Every change keeps a copy of the file as `.htaccess.bak`, puts the new file in place in one move, and then requests your site to check that it still answers. If it does not, the previous file is put back and the admin tells you, so a mistake cannot leave the site broken. If the check cannot be made on your host, the change is kept and the admin warns you. If `.htaccess` cannot be written, the admin shows the text to paste.

Anything that is not a short link answers with a real 404 and a plain page (`404.php`).

**Three states**

| State | When | What happens |
|---|---|---|
| Locked | No user or no password (or either is empty) | Nobody gets in. The page explains how to set them. |
| Login | A user and a password are set | A login form. Sessions are PHP's own, last 15 minutes without activity by default, and a wrong attempt waits one second. |
| Open | `define( 'LADYQR_REQUIRE_LOGIN', false );` | No login. Every page shows a permanent red warning that anyone who can reach it can change your links. For trying it locally, never for a public site. |

An empty or missing password never opens the panel by itself; only the explicit `LADYQR_REQUIRE_LOGIN` setting does.

**Cookies and HTTPS.** The only cookie is PHP's session cookie, marked `HttpOnly` and `SameSite=Lax`, and `Secure` when the request came over HTTPS (also behind a proxy that says so with `X-Forwarded-Proto`). On a server without SSL everything still works, and the login form shows a small warning that the password would travel unencrypted.

## Troubleshooting

**Every visit goes to the installer.** LadyQR is not installed yet: there is no `ladyqr-config.php`. Finish the installer. If you only want the QR code generator, you can delete the `admin` folder instead.

**The installer shows files to create by hand.** PHP cannot write in your site's folder. Create each file it shows next to `index.php`, with exactly that content, and upload it. `.htaccess` is a hidden file: many FTP clients only show it if you ask them to show hidden files. Alternatively, ask your host to let PHP write in the folder and run the installer again.

**Short links are off, or they answer with the server's own "Not Found" page.** Your server is not sending the addresses that are not files to LadyQR. Check that:

- the `.htaccess` file is there, next to `index.php`, with LadyQR's block in it (opening the admin puts it back);
- your host allows `.htaccess` files and has `mod_rewrite` (on your own Apache, `AllowOverride All` for the folder).

If the installer turned the shortener off because the server did not allow it, fix the server and then change `LADYQR_SHORTENER` to `true` in `ladyqr-config.php`.

**The admin says the short links are turned off in the configuration.** LadyQR was installed as "QR code generator only". To add the shortener, delete `ladyqr-config.php` and run the installer again, or add `LADYQR_SHORTENER`, `LADYQR_USER` and `LADYQR_PASSWORD` to the file by hand (see `ladyqr-config.sample.php`).

**The site answers with an error 500 after a change to `.htaccess`.** LadyQR checks every change it makes and undoes it if the site stops answering, but a hand edit, or a host that answers the check differently, can still break it. Put back the copy in `.htaccess.bak`, or delete `.htaccess`: the QR code generator works without it, and opening the admin writes LadyQR's block again.

**The site keeps redirecting in a loop.** A rule that forces HTTPS loops when the server sits behind a proxy that ends the secure connection, because the server always sees plain http. The installer only writes that rule when the server itself reports HTTPS. If you added it by hand, remove it, or use your host's option to force HTTPS instead.

**I forgot the admin's password.** Write a new one in plain text in `ladyqr-config.php`: `define( 'LADYQR_PASSWORD', 'your-new-password' );`. Log in with it, and LadyQR replaces it with its hash.

**The admin cannot secure the password.** It says why: usually `ladyqr-config.php` is not writable by PHP. The login works anyway. Make the file writable, or write the hash yourself.

**A change to `ladyqr-config.php` takes a while to show.** On some shared hostings, files uploaded by FTP take a few seconds to be seen by PHP, and PHP's cache can keep the old configuration for a couple of seconds more. Wait a moment and reload.

**A folder such as `/assets/` shows an empty page.** That is on purpose: `assets`, `includes` and `languages` each have a blank `index.html`, so a server that lists folders shows nothing.

What LadyQR needs from a host, and what was found on the author's own, is in [docs/hosting.md](docs/hosting.md).

## Customizing the generator page

For developers. Create a folder called `theme` next to `index.php` and put in it any of these files, all optional:

- `custom.css` is linked after LadyQR's own styles, on the generator, the admin pages and the not-found page.
- `head.html` goes right before `</head>`, on the generator.
- `header.html` goes inside a `<header>` right after `<body>`, on the generator.
- `footer.html` replaces what is inside the `<footer>`, on the generator.

The installer is never themed. The Content-Security-Policy is not relaxed, so a theme cannot make the page talk to another server: no inline styles or scripts, and no images, scripts or fonts from other sites (your own fonts are fine). [docs/THEME.md](docs/THEME.md) has the full guide, with what you can and cannot include, the CSS variables and class names that are safe to rely on, and examples. The release has an example folder, [theme.zip](https://github.com/nilovelez/ladyqr/releases/latest/download/theme.zip), and an update never touches your `theme` folder. The title and the description of the page are the two settings above.

## Translations

All text in the code is written in English with WordPress-style functions: `__()`, `_e()`, `esc_html__()`, `esc_html_e()` and `esc_attr__()`, always with the `ladyqr` text domain. A string without a translation stays in English.

Translations live in `languages/`:

- `ladyqr.pot` is the template.
- `ladyqr-<locale>.po` is a translation, for example `ladyqr-es_ES.po`.
- `ladyqr-<locale>.mo` is the compiled file the page reads. It is committed, because the hosting has no tools to compile it.

With the default language, English, no file is read. A small in-house reader loads the `.mo` file; it does not use PHP's `gettext` extension, which is not reliable on shared hosting.

Because it is standard gettext, any WordPress translation tool works: Poedit, Loco Translate, GlotPress, and WP-CLI.

**Update the template** after changing any string:

```
wp i18n make-pot . languages/ladyqr.pot --slug=ladyqr --domain=ladyqr --skip-js --exclude=docs,guppy,assets,.github,_site,_release
```

**Add a language:** copy `ladyqr.pot` to `ladyqr-<locale>.po`, translate it, compile it to `ladyqr-<locale>.mo` (Poedit does it when you save, or run `wp i18n make-mo languages`), and set `LADYQR_LANGUAGE` in the configuration file. The installer offers every language that has a `.mo` file.

**Plurals** are not done with gettext plural forms. A helper, `__plural( $singular, $plural, $number )`, takes two already translated strings and picks the singular for exactly 1 and the plural for anything else. Translation tools see only the inner `__()` calls, so each form is its own string. It supports two forms only, which is enough for English and Spanish.

Strings the browser-side JavaScript needs are translated in PHP and written into the page as a `<script type="application/json">` block, which a strict CSP does not block.

## How it works

PHP generates the page, in the configured language, and what reaches the browser is plain HTML, CSS and JavaScript. The QR code is made entirely by the JavaScript.

`index.php` is the single entry point. It sends every visit to the installer while LadyQR is not installed; after that, the root is the QR code editor, a known name is a short link, and anything else is the 404 page.

```
index.php                  The entry point: the editor page and its CSP header, short links, 404
404.php                    The page for anything that does not exist
admin/index.php            The admin: locked, open, login and the short links
admin/install.php          The installer
includes/config.php        Configuration loader
includes/auth.php          Sessions, login check, CSRF token
includes/config-writer.php Safely replaces the password in the configuration file
includes/data.php          Reads and writes the short links in ladyqr-data.php
includes/links.php         Short link validation
includes/htaccess.php      LadyQR's block in .htaccess, with safe writes, a check and rollback
includes/functions.php     Small shared helpers, including the theme's
includes/i18n.php          Translation functions and the .mo reader
assets/qr.js               The QR code encoder
assets/render.js           SVG and PNG output, matrix and color helpers
assets/app.js              The editor's behavior
assets/app.css             Styles
assets/admin.css           Styles for the admin and the installer
assets/icon-64.png         Square icon, 64 px: favicon
assets/icon-128.png        Square icon, 128 px: Apple touch icon
assets/ladyqr.png          Icon, 64 x 48 px, and @2X and @4X versions
assets/icon-full.png       Full size icon, for development only
assets/icon-vector.ai      Vector original, for development only
languages/                 Translation template and translations
ladyqr-config.sample.php   Configuration sample
INSTALL.txt                Installing and updating, for the release zip
site/                      The website's landing page
tools/build-release.php    Builds the release zips
tools/build-site.php       Builds the website
tools/make-qr.mjs          Makes a QR code SVG with the encoder
tests/                     Automated tests, for development only
CHANGELOG.md               What each version changed
docs/hosting.md            What the target hosting allows, from the M1 tests
docs/PLAN.md               Roadmap and design decisions
docs/THEME.md              Customizing the generator page, for developers
docs/theme/                The example theme that goes in theme.zip
docs/HANDOFF.md            Where the work stands, for whoever picks it up
DESIGN.md                  Look, behavior and accessibility
guppy/                     Project status published to Guppy (the author's project manager)
```

### Using the encoder

`assets/qr.js` and `assets/render.js` are plain ES modules with no dependencies. They also run in Node.js.

```js
import { encode } from './assets/qr.js';
import { toSvg, toPngBlob } from './assets/render.js';

const { modules, version, size, mask, mode } = encode('https://example.com/', 'M');
// modules[y][x] is true for a dark module.

const svg = toSvg(modules, { margin: 4, fg: '#1d1a1b', bg: '#ffffff', size: 512 });
const png = await toPngBlob(modules, { size: 500 }); // browser only; resolves to a Blob
```

- `encode( text, level = 'M' )` takes any text and returns the module matrix. `level` is `'L'`, `'M'`, `'Q'` or `'H'`. It throws a `RangeError` when the text does not fit in version 40 and a `TypeError` for an unknown level. The mode, the version and the mask are chosen automatically, and `mode` in the result says which mode was used: `'numeric'` for digits only, `'alphanumeric'` for digits, uppercase letters, space and `$%*+-./:`, and `'byte'` (UTF-8) for anything else.
- `toSvg( modules, { margin, fg, bg, size } )` returns SVG markup. Colors must look like `#rrggbb`. Without `size` the SVG scales to its container.
- `toPngBlob( modules, { size, margin, fg, bg } )` returns a `Promise` of a PNG exactly `size` by `size` pixels. Module edges are rounded to whole pixels, so modules differ in width by at most one pixel but stay sharp.
- `matrixToRows( modules )` and `rowsToMatrix( rows )` convert the matrix to and from an array of strings of `'0'` and `'1'`, one per row.
- `contrastRatio( colorA, colorB )` returns the WCAG contrast ratio of two colors, and `isInverted( moduleColor, backgroundColor )` tells whether the modules are lighter than the background.

Limits: the whole text goes in one mode, so a text that mixes, for example, a long run of digits with lowercase letters uses byte mode for all of it. There is no ECI, Kanji or structured append support.

## Development

There is no build step. To try the editor locally:

```
php -S localhost:8080
```

and open http://localhost:8080/. PHP's built-in server ignores `.htaccess`, so short links need Apache.

**Run the tests.** They use Node's built-in test runner and live in `tests/`, with development dependencies of their own that are never part of the release. They need Node.js 22 or later, PHP on the `PATH`, and an installed Chrome for the browser tests (set `CHROME_PATH` if it is not found; without it those tests are skipped).

```
cd tests
npm install
npm test
```

The browser and admin tests run on a throwaway copy of the site served by PHP's built-in server, with a small router (`tests/lib/router.php`) that stands in for Apache and LadyQR's `.htaccess` block.

**Make a QR code** as an SVG with the project's own encoder, for example the one on the website:

```
node tools/make-qr.mjs LADYQR.NILOVELEZ.COM site/qr.svg M 4
```

**Build the website** into `_site/`, which is ignored by Git:

```
php tools/build-site.php
```

**Build the release zip** into `_release/ladyqr-latest.zip`, also ignored by Git. It needs PHP's zip extension. A GitHub Action does the same when a release is published, and attaches the zip to it.

```
php tools/build-release.php
```

**How the encoder is verified.** The tests in `tests/encoder.test.mjs` compare its module matrix with the one made by an independent implementation, the `qrcode` package on npm, in each of the three modes, at all 40 versions and the four error correction levels (with the same mask), and check that one more character takes the next version. The codes decode back to the original text with the jsQR decoder; the few large symbols it cannot read fail in the same way on the reference implementation's own symbol. In a real Chrome, the 500 px PNG, the 1000 px PNG and the SVG that the editor downloads also decoded to the exact text, with accents and emoji. It has been tested in Chrome only.

## Roadmap

See [docs/PLAN.md](docs/PLAN.md). 1.1.0 brought automated tests, the encoder's numeric and alphanumeric modes, and a QR code for each short link, straight from the admin. The backlog has what may come later, and [CHANGELOG.md](CHANGELOG.md) what each version changed.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

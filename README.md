<p align="center">
  <img src="assets/icon-128.png" alt="LadyQR, a ladybug with a QR code on its back" width="128" height="128">
</p>

<h1 align="center">LadyQR</h1>

<p align="center"><strong>QR codes that never leave your browser.</strong></p>

<p align="center">
  <a href="https://ladyqr.nilovelez.com/">Website</a> ·
  <a href="https://ladyqr.nilovelez.com/try/">Try it</a> ·
  <a href="docs/PLAN.md">Roadmap</a> ·
  <a href="DESIGN.md">Design</a>
</p>

LadyQR is a privacy-oriented QR code generator, and soon a private URL shortener, made to run on a minimal shared hosting plan.

You type a link and the QR code appears as you type. You download it as a PNG or a vector SVG. What you type is never sent to a server, and the page loads nothing from outside.

<p align="center">
  <img src="site/qr.svg" alt="QR code that opens ladyqr.nilovelez.com" width="160" height="160"><br>
  <sub>Scan me. This code was made with LadyQR.</sub>
</p>

## Status

The QR code generator (milestone M0) is done, and the target hosting has been tested (milestone M1, see [docs/hosting.md](docs/hosting.md)). The URL shortener is planned. See the [roadmap](docs/PLAN.md) for what comes next.

## Features

- **Instant.** The code updates on every keystroke. There is no generate button.
- **Private.** The code is computed in the browser. A strict Content Security Policy forbids any network request from the page.
- **PNG and SVG.** A 500 px PNG with one click, a 1000 px PNG or a vector SVG from the menu next to it.
- **Made to be scanned.** Error correction levels L, M, Q and H, a margin from 0 to 8 modules, and custom colors. A warning appears for low contrast (4:1 or less), for inverted colors (modules lighter than the background), or for both.
- **Remembers your settings, nothing else.** Error correction, margin and colors are kept in your browser's `localStorage`. The text you type is never stored. A button resets the settings.
- **Own encoder.** About 400 lines of JavaScript with no dependencies and no build step. Byte mode (UTF-8, so accents and emoji work), versions 1 to 40, up to 2953 bytes at level L.
- **Accessible.** High-contrast borders, full keyboard use, screen reader labels, light and dark themes that follow your system. See [DESIGN.md](DESIGN.md).
- **Translatable.** Translations work like WordPress, with standard `.po` files. English and Spanish are included.

## Privacy and security

- The page is served with this policy, sent as an HTTP header by `index.php`:

  ```
  default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self';
  connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'
  ```

  `connect-src 'none'` makes the browser refuse any connection from the page, whatever the code tries to do. There are no inline scripts or styles, and no fonts, images or scripts from other sites. You can check it yourself: open the developer tools on the Network tab and type something.
- Only the settings (error correction, margin and colors) are remembered, under the `ladyqr-settings` key in `localStorage`. They never leave your browser.
- The live demo on the website has no HTTP headers to send, because GitHub Pages does not allow them. It carries the same policy in a `<meta>` tag instead.

## Requirements

- PHP 7.4 or later is the target. PHP only generates the page and needs no extensions. The code has been tested on PHP 8.5 only.
- A web server that can run PHP. Apache is the target.
- No database.

The QR code itself needs a modern browser: ES modules, `<dialog>`, and `localStorage` (the editor works without it).

## Installation

1. Download this repository or clone it.
2. Upload it to the root of your site. The files the page needs are `index.php`, `includes/`, `assets/` and `languages/`. Everything else is for development and can stay out.
3. Optionally, create a configuration file (see below).

Open the site in a browser and it works.

## Configuration

The configuration file is `ladyqr-config.php`, a PHP file that returns an array. It is looked up in the web root first and, if it is not there, one level above, as WordPress does with `wp-config.php`. If both exist, the one in the web root wins; they are never merged. Putting it above the web root keeps it out of reach of the web server.

Copy `ladyqr-config.sample.php` to `ladyqr-config.php` and change what you need:

| Setting | Default | Meaning |
|---|---|---|
| `language` | `'en_US'` | Interface language, as a WordPress-style locale such as `es_ES`. It is one setting for the whole site; visitors do not choose it. |

`ladyqr-config.php` is ignored by Git, so your settings never end up in the repository.

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
wp i18n make-pot . languages/ladyqr.pot --slug=ladyqr --domain=ladyqr --skip-js --exclude=docs,guppy,assets,.github,_site
```

**Add a language:** copy `ladyqr.pot` to `ladyqr-<locale>.po`, translate it, compile it to `ladyqr-<locale>.mo` (Poedit does it when you save, or run `wp i18n make-mo languages`), and set `language` in the configuration file.

**Plurals** are not done with gettext plural forms. A helper, `__plural( $singular, $plural, $number )`, takes two already translated strings and picks the singular for exactly 1 and the plural for anything else. Translation tools see only the inner `__()` calls, so each form is its own string. It supports two forms only, which is enough for English and Spanish.

Strings the browser-side JavaScript needs are translated in PHP and written into the page as a `<script type="application/json">` block, which a strict CSP does not block.

## How it works

PHP generates the page, in the configured language, and what reaches the browser is plain HTML, CSS and JavaScript. The QR code is made entirely by the JavaScript.

```
index.php                  The editor page, and the CSP header
includes/config.php        Configuration loader
includes/i18n.php          Translation functions and the .mo reader
assets/js/qr.js            The QR code encoder
assets/js/render.js        SVG and PNG output, matrix and color helpers
assets/js/app.js           The editor's behavior
assets/css/app.css         Styles
assets/icon-128.png        Icon
assets/icon-full.png       Full size icon
languages/                 Translation template and translations
ladyqr-config.sample.php   Configuration sample
site/                      The website's landing page
tools/build-site.php       Builds the website
tools/make-qr.mjs          Makes a QR code SVG with the encoder
docs/hosting.md            What the target hosting allows, from the M1 tests
docs/PLAN.md               Roadmap and design decisions
DESIGN.md                  Look, behavior and accessibility
guppy/                     Project status published to Guppy (the author's project manager)
```

### Using the encoder

`assets/js/qr.js` and `assets/js/render.js` are plain ES modules with no dependencies. They also run in Node.js.

```js
import { encode } from './assets/js/qr.js';
import { toSvg, toPngBlob } from './assets/js/render.js';

const { modules, version, size, mask } = encode('https://example.com/', 'M');
// modules[y][x] is true for a dark module.

const svg = toSvg(modules, { margin: 4, fg: '#1d1a1b', bg: '#ffffff', size: 512 });
const png = await toPngBlob(modules, { size: 500 }); // browser only; resolves to a Blob
```

- `encode( text, level = 'M' )` takes any text, encodes it as UTF-8 in byte mode, and returns the module matrix. `level` is `'L'`, `'M'`, `'Q'` or `'H'`. It throws a `RangeError` when the text does not fit in version 40 and a `TypeError` for an unknown level. The version and the mask are chosen automatically.
- `toSvg( modules, { margin, fg, bg, size } )` returns SVG markup. Colors must look like `#rrggbb`. Without `size` the SVG scales to its container.
- `toPngBlob( modules, { size, margin, fg, bg } )` returns a `Promise` of a PNG exactly `size` by `size` pixels. Module edges are rounded to whole pixels, so modules differ in width by at most one pixel but stay sharp.
- `matrixToRows( modules )` and `rowsToMatrix( rows )` convert the matrix to and from an array of strings of `'0'` and `'1'`, one per row.
- `contrastRatio( colorA, colorB )` returns the WCAG contrast ratio of two colors, and `isInverted( moduleColor, backgroundColor )` tells whether the modules are lighter than the background.

Limits: only byte mode, so a text of digits alone makes a larger code than a numeric mode would. There is no ECI, Kanji or structured append support.

## Development

There is no build step. To try the editor locally:

```
php -S localhost:8080
```

and open http://localhost:8080/.

**Make a QR code** as an SVG with the project's own encoder, for example the one on the website:

```
node tools/make-qr.mjs https://ladyqr.nilovelez.com/ site/qr.svg L 4
```

**Build the website** into `_site/`, which is ignored by Git:

```
php tools/build-site.php
```

**How the encoder was verified.** The module matrix is identical to the one made by an independent implementation, the `qrcode` package on npm, for 328 inputs that cover all 40 versions at the four error correction levels (with the same version and mask, in byte mode). The codes decode back to the original text with the jsQR decoder in 346 of 348 cases, and the two it cannot read fail in the same way on the reference implementation's own symbol. In a real Chrome, the 500 px PNG, the 1000 px PNG and the SVG that the editor downloads also decode to the exact text, with accents and emoji. It has been tested in Chrome only; Firefox and Safari have not been tested yet. Automated tests are planned for a later milestone.

## Website

The public website at https://ladyqr.nilovelez.com/ is built and deployed by the GitHub Actions workflow `.github/workflows/pages.yml` on every push to `main`. It has a landing page (the `site/` folder) and a live demo of the real editor under `/try/`. GitHub Pages cannot run PHP, so the build renders `index.php` to static HTML.

In the repository settings, Pages uses **GitHub Actions** as its source and `ladyqr.nilovelez.com` as its custom domain, with a `CNAME` DNS record pointing to `nilovelez.github.io`. With this kind of deployment no `CNAME` file is needed in the repository.

## Roadmap

See [docs/PLAN.md](docs/PLAN.md). In short: publish the editor on the author's domain, then add a private, single-user admin with a login, then a URL shortener that writes its redirects into `.htaccess`, so Apache serves them without running PHP, and finally an installation guide and a release you can upload by FTP.

## Contributing

Issues and suggestions are welcome at https://github.com/nilovelez/ladyqr/issues. Work is organized by milestones, as described in the roadmap.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

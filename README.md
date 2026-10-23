# LadyQR

A privacy-oriented QR code generator and URL shortener, made to run on a minimal shared hosting plan.

The QR code is made entirely in the browser: what you type is never sent to a server, and the page loads nothing from outside. Downloads are PNG (500 or 1000 px) or SVG.

The URL shortener is planned; see [docs/PLAN.md](docs/PLAN.md) for the roadmap.

## Requirements

- PHP 7.4 or later. PHP generates the page; it needs no extensions.
- Any web server that can run PHP. Apache is the target.

## Try it

```
php -S localhost:8080
```

Then open http://localhost:8080/.

## Configuration

Copy `ladyqr-config.sample.php` to `ladyqr-config.php`, in the web root or one level above it, and change what you need. For now the only setting is the interface language.

## Translations

All text is in English in the code, and translations follow the WordPress way: `languages/ladyqr.pot` is the template, `.po` and `.mo` files are named like `ladyqr-es_ES.po`, and the compiled `.mo` is what the page reads. A string without a translation stays in English. To update the template:

```
wp i18n make-pot . languages/ladyqr.pot --slug=ladyqr --domain=ladyqr --skip-js --exclude=docs,guppy,assets,.github
```

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

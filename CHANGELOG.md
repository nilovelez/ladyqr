# Changelog

The notable changes in each version of LadyQR. It is not part of the release zip.

## 1.2.0 — 2026-10-26

- **Customizing the generator page.** For developers: a `theme` folder next to `index.php`, all of it optional. `custom.css` is linked after LadyQR's styles on the generator, the admin pages and the not-found page; `head.html`, `header.html` and `footer.html` are added to the generator only. The Content-Security-Policy stays strict, so a theme cannot make the page talk to another server. The folder is not in the release zip, so an update never touches it; the release also has `theme.zip`, a complete example theme that works as soon as it is uploaded. See `docs/THEME.md`.
- **`LADYQR_SITE_TITLE` and `LADYQR_SITE_DESCRIPTION`**, two optional settings: the title and the meta description of the generator page. The installer asks for them on its third screen, both optional.
- **Your own fonts in a theme.** The generator, the admin pages and the not-found page allow fonts from the site itself (`font-src 'self'`), and only those: nothing is loaded from other sites.
- **Three CSS variables** to move the page's furniture: `--page-top`, `--help-top` and `--help-right`.
- **`theme` is a reserved name** for short links.
- **The footer link** of the generator, "Made with LadyQR", goes to https://ladyqr.nilovelez.com/.
- **Two fixes in the installer and the admin forms**: the site address field has rounded corners all around when the site is not in a folder, and the placeholders are lighter (a new `--placeholder` color, still readable) so an empty field no longer looks filled in.
- **The installer's screens have a background**: the same drawn meadow as the website's hero, with the card centered on short screens.

## 1.1.0 — 2026-10-25

- **Smaller QR codes.** The encoder now picks the most compact mode for the text: numeric for digits only, alphanumeric for digits, uppercase letters, space and `$%*+-./:`, and byte mode for anything else. Nothing changes in the editor.
- **A QR code for each short link.** In the admin, a small QR icon before each short link opens the generator in a new tab with the link filled in.
- **`LADYQR_QR_BASE`**, an optional, advanced setting: what the QR codes of short links start with, such as `1QR.ES/`. With the domain in uppercase and without `https://`, the codes are smaller. See the README.
- **The generator takes its text from the address**, as in `https://example.com/#text=Hello`. The text is in the fragment, so it never reaches the server.
- **Automated tests** for the encoder, the editor, the admin and the installer, in `tests/`. They are for development and are not in the zip.
- **The website's live demo** works again: it is built without needing a local configuration.

## 1.0.0 — 2026-10-25

The first release.

- **QR code generator.** The code is made in your browser as you type, and what you type is never sent to a server. PNG (500 or 1000 px) and SVG downloads, error correction L, M, Q and H, margin and colors.
- **Optional link shortener.** Short links on your own domain, managed from a private single-user admin. Names ignore case.
- **Web installer.** Upload the files, open your site, and the installer asks what it needs: the QR code generator alone, or with the link shortener. No database.
- **Secure without `.htaccess`.** Until it is installed, every visit goes to the installer. If you only want the generator, delete the `admin` folder.
- **English and Spanish**, with standard `.po` files.

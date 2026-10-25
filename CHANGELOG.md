# Changelog

The notable changes in each version of LadyQR. It is not part of the release zip.

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

# Handoff

What a new working session needs to pick LadyQR up. The rules for working with Nilo are in `CLAUDE.md`; the roadmap in `docs/PLAN.md`. Keep this file current at the end of each session.

## Where it stands

- Milestones 0 to 6 are done. **1.0.0** is the first release: the README, `INSTALL.txt` and the configuration sample are up to date, and the design is accepted for this version.
- 1.0.0 is published on GitHub; `release.yml` attached `ladyqr-latest.zip` to it. Permanent link: https://github.com/nilovelez/ladyqr/releases/latest/download/ladyqr-latest.zip
- Nilo runs LadyQR on his own site, https://1qr.es/. Only he touches that site.
- The website and demo, https://ladyqr.nilovelez.com/, are deployed by GitHub Pages on every push to `main`.

## Next

- M7, as described in `docs/PLAN.md`. For the automated tests, the checks in `D:\Claude\ladyqr-tools\tests` are a start.
- To release a new version: publish a GitHub release with a new tag (such as `v1.0.1`); the workflow attaches the zip.

## How the code fits together

- `index.php` is the single entry point. It answers the route test first, sends every visit to `admin/install.php` while there is no configuration (if the installer exists), and then serves the generator, a short link or the 404 page.
- `ladyqr-config.php` holds `LADYQR_` constants; `ladyqr-data.php` returns the links as a PHP array. Neither is in the repository or the zip. Both are guarded with `defined( 'LADYQR' ) || exit;`.
- `.htaccess` is never shipped. The installer creates it (address rules, and for the shortener LadyQR's versioned block); the admin puts the block back or rewrites it when needed.
- The installer (`admin/install.php`) has four screens: language, welcome and choice, checks and settings, result.

## Test environment

Everything lives in `D:\Claude\ladyqr-tools\`, outside the repository:

| Folder | What |
|---|---|
| `apache\Apache24` | Apache 2.4 for Windows, running PHP 8.5 as CGI from `C:\php-8.5.8-nts-Win32-vs17-x64` |
| `conf\httpd-test.conf` | Port **8070**, Nilo's test site, document root `D:\Claude\ladyqr` |
| `conf\httpd-e2e.conf` | Port **8071**, for automated tests only, document root `www-e2e` |
| `php\php.ini` | PHP settings for both (sessions in `sessions`) |
| `tests` | Puppeteer scripts: `install-e2e.mjs` (82 checks, every installer path) and `install-ro.mjs` (a folder PHP cannot write to). `paths.mjs` finds the repo in `D:\repos\ladyqr` or `D:\repos\1qres`, or in `LADYQR_REPO`. |
| `shots` | Screenshots the tests leave behind |
| `start.cmd`, `stop.cmd` | Start and stop both servers |

- **Never run tests against 8070.** It is Nilo's site, with his own configuration and links; scripts that reset a site have wiped it before.
- To update 8070 with the current code, copy only the runtime files (`index.php`, `404.php`, `ladyqr-config.sample.php`, `admin`, `includes`, `assets`, `languages`) into `D:\Claude\ladyqr`, never `ladyqr-config.php`, `ladyqr-data.php` or `.htaccess`.
- Run the tests with `node install-e2e.mjs` from `tests`; Chrome is used from its default install path.
- Test site URLs for Nilo use the machine's LAN IP, for example `http://<ip>:8070/`.

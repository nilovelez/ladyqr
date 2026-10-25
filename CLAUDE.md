## Working on LadyQR

LadyQR is a privacy-oriented QR code generator with an optional single-user link shortener, in plain PHP, for minimal shared hosting. The roadmap and every design decision are in `docs/PLAN.md`; the visual rules in `DESIGN.md`; where the work stands and how to pick it up in `docs/HANDOFF.md`.

### How Nilo works

- **Talk to Nilo in Spanish.** Everything that goes into the repo (code, comments, texts, docs, commit messages) is in US English.
- **No work until it is confirmed.** If a request has any open point, doubt or slip (contradictory or swapped items, a missing detail), review it, answer the doubts, propose the whole plan and wait for his OK. Only then change files. Changes cost resources.
- **A question is not an order.** Answer it; do not act on it.
- **Do exactly what was asked.** No extras, no unrequested pushes, no unrequested screenshots, website or doc updates.
- **Never blame earlier decisions** of the project when something goes wrong; fix it.
- **Work on `main`, no branches.** Commit finished changes locally; push only when Nilo asks.
- **A trial ("probar") is not committed,** so it is easy to revert. Code only: no screenshots, website or docs for a trial.
- **Milestones:** at the end of one, summarize and stop. Do not start the next without his OK.
- **Never deploy to https://1qr.es/.** It is Nilo's own instance and only he touches it.
- **Test sites:** give their URLs with this machine's current LAN IP (check it), since Nilo often uses another computer. Credentials for test sites: `admin` / `password`.

### Principles

- **Native PHP, best-effort hardening.** Prefer what PHP and Apache already offer to custom mechanisms, and never break a shared host by asking for too much.
- **Secure by design without `.htaccess`.** The site must work and be safe if `.htaccess` is missing or ignored; `.htaccess` only adds routing and the address rules.
- **Two personas** to review any change:
  - *Alfonso*, a web implementer used to WordPress-style installs. He unzips, uploads by FTP, edits configuration files, and never reads code.
  - *Antonio*, non-technical, does not read. He uploads the files and never visits `/admin/`. A bot must not be able to take over his site.
- **Buttons:** gray primary, white secondary, red only for very specific actions such as the download. See `DESIGN.md`.

### Recipes

- **Translations.** Text domain `ladyqr`. After changing strings:
  - `wp i18n make-pot . languages/ladyqr.pot --slug=ladyqr --domain=ladyqr --skip-js --exclude=docs,guppy,assets,.github,_site,_release`
  - Add the new strings to `languages/ladyqr-es_ES.po`, then `wp i18n make-mo languages`.
  - The msgid sets of the `.pot` and the `.po` must be identical.
- **Release zip.** `php tools/build-release.php` builds `_release/ladyqr-latest.zip` (git-ignored). The `release.yml` workflow does the same when a release is published and attaches it to the release.
- **Shell.** The Bash tool mangles backslashes in heredocs and `node -e`; write scripts to files instead.

## Project status (Guppy)

This project publishes its status to Guppy, Nilo's project manager (https://github.com/manuelstone/guppy). Everything Guppy-related lives in `guppy/`, written in English:

- `guppy/STATUS.md`: extended status and work log, for agents working on this project. It is never synced anywhere.
- `guppy/STATUS.json`: public summary, following the status contract (https://github.com/manuelstone/guppy/blob/main/docs/status-contract.md). `.github/workflows/guppy-sync.yml` copies it to Guppy every time it changes on the default branch, and from there it goes to a public website. Never put private information, credentials or secrets in it.

At the end of every work session on the project, even if `status` stays the same:

1. Update the Status section of `guppy/STATUS.md` and add a brief entry at the top of its Log.
2. Update `guppy/STATUS.json`: change `status` if it changed, rewrite `status_text` so it says where the project stands now, and always take new `last_update` and `last_update_unix`. They record when the status was last reported, not when `status` last changed: an `active` project gets new dates after every session. Take both dates from a single timestamp: `ts=$(date +%s); date -d "@$ts" '+%Y-%m-%d %H:%M:%S %z'; echo "$ts"` (GNU date; the contract has the macOS and PowerShell versions).
3. Commit both files with the rest of the session's changes and push to the default branch.

Statuses: `active` (being worked on), `blocked` (waiting for something outside the project), `inactive` (stopped by choice), `planned` (decided, not started), `archived` (closed).

`status_text`: one or two sentences, under 200 characters, plain text. Say where the project stands and what comes next; if blocked, start with what it is waiting for and from whom. Self-contained: no relative dates, name versions and third parties. Rewrite it in full each time; the history goes in the Log.

When Nilo asks to change this project's data in Guppy (its icon, title, description or status), edit `guppy/STATUS.json` following the contract, commit and push to the default branch: the sync publishes it. A change of only the icon, title or description does not touch the dates. `icon` is any public URL, ideally a square 128×128 PNG. Never edit the Guppy repo for this.

Do not use CHANGELOG.md for this: it is for users. Do not edit `guppy-sync.yml`. If the Guppy sync action fails, read its log in the Actions tab and tell Nilo.

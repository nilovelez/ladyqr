## Project status (Guppy)

This project publishes its status to Guppy, Nilo's project manager (https://github.com/manuelstone/guppy). Everything Guppy-related lives in `guppy/`, written in English:

- `guppy/STATUS.md`: extended status and work log, for agents working on this project. It is never synced anywhere.
- `guppy/STATUS.json`: public summary, following the status contract (https://github.com/manuelstone/guppy/blob/main/docs/status-contract.md). `.github/workflows/guppy-sync.yml` copies it to Guppy every time it changes on the default branch, and from there it goes to a public website. Never put private information, credentials or secrets in it.

At the end of any session that changes the project status:

1. Update the Status section of `guppy/STATUS.md` and add a brief entry at the top of its Log.
2. Update `guppy/STATUS.json`: `status`, `status_text`, and `last_update` with `last_update_unix`. Take both dates from a single timestamp: `ts=$(date +%s); date -d "@$ts" '+%Y-%m-%d %H:%M:%S %z'; echo "$ts"` (GNU date; the contract has the macOS and PowerShell versions). If the status did not change, do not touch the dates.
3. Commit both files with the rest of the session's changes and push to the default branch.

Statuses: `active` (being worked on), `blocked` (waiting for something outside the project), `inactive` (stopped by choice), `planned` (decided, not started), `archived` (closed).

`status_text`: one or two sentences, under 200 characters, plain text. Say where the project stands and what comes next; if blocked, start with what it is waiting for and from whom. Self-contained: no relative dates, name versions and third parties. Rewrite it in full each time; the history goes in the Log.

When Nilo asks to change this project's data in Guppy (its icon, title, description or status), edit `guppy/STATUS.json` following the contract, commit and push to the default branch: the sync publishes it. Change the dates only when the status changes. `icon` is any public URL, ideally a square 128×128 PNG. Never edit the Guppy repo for this.

Do not use CHANGELOG.md for this: it is for users. Do not edit `guppy-sync.yml`. If the Guppy sync action fails, read its log in the Actions tab and tell Nilo.

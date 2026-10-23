# Hosting findings: OVH shared plan

What the target hosting really allows, found with the M1 spike on 2026-10-23. The plan is OVH's free shared hosting (about 100 MB, PHP and SSL), with the domain `1qr.es` attached to it.

Everything here was tested on that plan. Other hosts will differ, so treat it as a checklist of what to verify, not as a promise.

## Environment

| | |
|---|---|
| Web server | Apache |
| PHP | 8.4 as PHP-FPM, chosen by a `.ovhconfig` file in the home folder (not in `www`) |
| Web root | the `www` folder, inside the account's home folder |
| `open_basedir` | not set |
| Memory limit and time limit | 512 MB and 165 s |
| Upload limit | 128 MB per file |
| Time zone | `Europe/Paris` |
| Disk | PHP reports the whole cluster's disk, not the account's quota, so the 100 MB quota is only visible in the OVH panel |
| Existing files | a legacy `.htaccess` and the `.ovhconfig` in the home folder, none in `www`. The author's previous site is in `www/old` and is not touched |

## Results of the checks

| # | Check | Result | What it means for LadyQR |
|---|---|---|---|
| 1 | `Redirect` and `RedirectMatch` in `.htaccess` | Pass | Short links can be plain `Redirect` lines. |
| 2 | `RewriteRule` redirects | Pass | `mod_rewrite` is available too. |
| 3 | `ErrorDocument 404` to a PHP page | Pass | The page loads with a real 404 status. PHP starts with status 200, so the script has to set 404 itself. |
| 4 | `Header` in `.htaccess` | Pass | `mod_headers` works, but the CSP can also be sent from PHP. |
| 5 | Blocking one file | Pass | A `<Files>` block answers 403. |
| 6 | HTTPS detection with `%{HTTPS}` | Pass | A "force HTTPS" rule is safe and does not loop. PHP also sees `HTTPS=on`, port 443 and `X-Forwarded-Proto: https`. |
| 7 | MIME types | Pass | JavaScript is served as `application/javascript` and loads as an ES module, so the editor works as it is. |
| 8 | PHP rewrites a block of `.htaccess` | Pass | See below. |
| 9 | Sessions | Pass | The default session works. |
| 10 | `password_hash` | Pass | bcrypt at cost 12 takes about 195 ms. Argon2id is available. |
| 11 | A file above the web root | Pass | PHP can read it, and the home folder is writable. |
| 12 | `flock` across requests | **Fail** | See below. |
| 13 | Room and limits | Info | See the table above. |

### Writing `.htaccess` from PHP works

- PHP can write the file: a temporary file plus `rename()` over it works, with permissions 0644.
- The server can request itself over HTTPS with cURL (about 34 ms), so a write can be checked right away.
- An invalid directive makes that folder answer **500**. The check detects it, the backup is restored, and the folder answers 200 again. The rollback works.
- A change takes effect almost at once: a new redirect worked after 61 ms, and a removed one stopped working too.

### Redirects are answered by Apache alone

A `Redirect` returns a 302 with no `X-Powered-By` header, while a PHP page on the same server does carry `X-Powered-By: PHP/8.4`. So a short link is served without running PHP, which was the point of the design. For a relative target the `Location` header comes with an explicit `:443`, which does not matter for the external targets a short link points to.

### `flock` does not work across requests

With one request holding an exclusive lock for 6 seconds, another request on the same server, on the same file (same inode), took it twice. It is not a problem of the test.

What does work, because it is atomic:

- `mkdir()`: of 6 simultaneous attempts, exactly 1 succeeded.
- `fopen( $file, 'x' )` (exclusive creation): of 6 simultaneous attempts, exactly 1 succeeded.

For now there is no lock, because this is a personal single-user tool. A lock based on `mkdir()` with a timeout for stale locks is in the backlog.

## Things that differ from what one might assume

- **`http://` is not redirected to `https://`.** `http://www.1qr.es/` answers 200. A rule in `.htaccess` has to do it.
- **The bare domain had no certificate.** `https://1qr.es/` presented the cluster's generic certificate, and only `www.1qr.es` had one of its own. The cause is that the domain was moved to this hosting recently. It is fixed in the OVH control panel.
- **Sessions are stored in the shared `/tmp`**, and the cookie is neither `HttpOnly` nor `SameSite` by default. LadyQR uses the native session and sets only the standard cookie parameters, and `Secure` only over HTTPS.
- **The FTP server does not support TLS.** `AUTH TLS` is not implemented, so plain FTP would send the password in the clear. SFTP works on port 22 of the same host name, with the same user and password.

## Uploading with SFTP from Windows

The `sftp` client that comes with Windows is awkward to automate with a password:

- `sftp -b file` turns on batch mode, which disables passwords. Feed the commands through standard input instead.
- To pass the password without a keyboard, set `SSH_ASKPASS` to a small script that prints it, with `SSH_ASKPASS_REQUIRE=force`. Delete the script afterwards.
- `put -r` does not create the subfolders on this server. Create each folder with `mkdir` and upload the files one by one, including hidden ones such as `.htaccess`.
- `ls` without `-l` prints names in columns, which is hard to parse. Use `ls -la`.
- The bundled `curl` has no SFTP support, and its FTP over TLS fails here for the reason above.

## How it was tested

A package of 14 checks was uploaded to the hosting and run in a real browser, and a few follow-up probes were run for `flock`, `mkdir` and exclusive creation. It was first validated locally against Apache 2.4.55 with PHP 8.5 (10 passes), and with `.htaccess` files ignored to confirm that the checks fail when they should. The package was removed afterwards; it is in the Git history, in the commit `22ea142` of the `feat-spike` branch.

Not tested: PHP versions before 8.4, and other hosts.

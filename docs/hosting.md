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

There is no lock, because this is a personal single-user tool. A lock is not planned either: `.htaccess` is rarely written now.

## Things that differ from what one might assume

- **`http://` is not redirected to `https://`.** `http://www.1qr.es/` answers 200. A rule in `.htaccess` has to do it.
- **The bare domain had no certificate.** `https://1qr.es/` presented the cluster's generic certificate, and only `www.1qr.es` had one of its own. The cause is that the domain was moved to this hosting recently. It is fixed in the OVH control panel.
- **Sessions are stored in the shared `/tmp`**, and the cookie is neither `HttpOnly` nor `SameSite` by default. LadyQR uses the native session and sets only the standard cookie parameters, and `Secure` only over HTTPS.
- **The FTP server does not support TLS.** `AUTH TLS` is not implemented, so plain FTP would send the password in the clear. SFTP works on port 22 of the same host name, with the same user and password.

## Publishing on `1qr.es` (M2)

The editor was uploaded on 2026-10-24 and checked in production.

- **What is uploaded:** `index.php`, `includes/`, `assets/` (without `icon-full.png`, which the page does not use), `languages/` (each folder with its blank `index.html`) and `.htaccess`. Nothing else, and nothing is touched in `www/old`, where the author's previous site lives.
- **The server's `.htaccess`** is the repository's file with the HTTPS block enabled and `www` sent to the bare domain. The bare domain `1qr.es` is the canonical address, which keeps short links short:

  ```
  RewriteCond %{HTTP_HOST} ^www\.1qr\.es$ [NC]
  RewriteRule ^ https://1qr.es%{REQUEST_URI} [R=301,L]
  RewriteCond %{HTTPS} !=on
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
  ```

  `http://1qr.es/`, `http://www.1qr.es/` and `https://www.1qr.es/` all end at `https://1qr.es/` in a single hop, keeping the path and the query string.
- **Certificates:** both `1qr.es` and `www.1qr.es` have their own Let's Encrypt certificate (set up in the OVH panel).
- **Checked in production:** the page and its CSP and security headers, the MIME types, the PNG (500 and 1000 px) and SVG downloads decoding to the exact text, no console errors, no requests outside the site, and the configuration file blocked by `.htaccess` (403).
- **The configuration file is found above the web root**, and the one in the web root wins over it when both exist. With `language` set to `es_ES`, the page came out in Spanish, which also proves that the `.mo` translation loads in production.

### Changes take a few seconds to show

Files uploaded or deleted over SFTP are not seen by PHP at once, because the storage is a network disk shared with the web servers. Measured: a new file shows after about 3 to 16 seconds, and a deleted one disappears after about 16 seconds. OPcache is not the cause (`validate_timestamps` is on and `revalidate_freq` is 2 s). `realpath_cache_ttl` is 300 s, which was not observed to matter.

Files written by PHP itself on the server are seen at once, as the spike showed (a new redirect worked after 61 ms). So this matters when deploying and when editing the configuration file by hand, not for the shortener.

OPcache is why a hand edit of a PHP file, the configuration file included, can take up to its 2 second revalidation interval to show. When LadyQR rewrites the configuration file itself (to secure the password) it calls `opcache_invalidate()` on it, so its own change is seen at once.

### Folder listings

Apache's directory listing is on at OVH. With only `IndexIgnore *`, a folder such as `/assets/` showed an empty "Index of /assets" page: it exposed nothing, but it was still a listing page. The fix, borrowed from WordPress, is a **blank `index.html` in every folder** (`assets/`, `includes/` and `languages/`; the live server still has the earlier layout, with `assets/css/` and `assets/js/` each holding one, until the next deployment removes them), so a visitor gets an empty page. For that to work, `DirectoryIndex` in `.htaccess` has to include `index.html`, and it does (`index.php index.html`). `IndexIgnore *` stays as a second line of defense.

`Options -Indexes` was not used: on a host that does not allow `Options` in `.htaccess` it would make the whole site answer with an error.

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

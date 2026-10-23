# M1 hosting spike: instructions

The URL shortener will write its redirects into `.htaccess` from PHP, and its admin will rely on sessions, a lock and a configuration file that may sit above the web root. All of that depends on what the OVH free hosting plan (100 MB, PHP, SSL) really allows. This spike finds out before any of it is built.

You upload one folder, press one button, and send back a report. About 20 minutes, most of it uploading.

## What you need

- FTP access to the hosting, and the address the site answers on. The folder must be reachable at **`https://<address>/spike/`**, that is, directly under the web root. The test `.htaccess` files use the path `/spike/`.
- If `1qr.es` is already attached to the hosting, use it. If not, any address of the hosting works for these tests. Which domains the plan can serve is one of the manual checks below.

## Steps

1. **Get the files.** They are in the `spike/` folder of the `feat-spike` branch.
2. **Upload the whole `spike/` folder to the web root** (on OVH it is usually the `www` folder). Keep the folder names as they are.
   - **Make sure the hidden files go up.** Each `t-*` folder has a `.htaccess` file, and many FTP clients skip files that start with a dot unless you tell them to show hidden files. Without them most tests fail for the wrong reason. The expected files are listed at the end.
3. **Upload one more file, one level above the web root:** `spike/parent-probe/ladyqr-parent-probe.php`. On OVH that is the folder that contains `www`, not a folder inside it. This is for test 11, which checks that PHP can read a file there.
4. **Open `https://<address>/spike/`** (with `https`, because test 6 needs it) and press **Run all tests**. It takes a few seconds.
5. **Copy the report** with the button under the table and paste it back to me. It includes server paths, so redact whatever you prefer.
6. **Do the manual checks** below and send me what you see.
7. **Clean up.** Delete the `spike` folder, and the probe file above the web root. The test endpoints can rewrite a file inside `spike/`, so it should not stay on a public site.

## Manual checks

These cannot be automated. A sentence for each is enough.

1. **HTTP to HTTPS.** Open `http://<address>/spike/` (no `s`). Does it go to `https` by itself?
2. **Domains.** Can `1qr.es` be attached to this hosting? Can it be served from the root `/`, or only from a subfolder? Is there a limit on domains or subdomains?
3. **PHP versions.** Which versions does the control panel offer, and which one is selected? Hosting plans like this one often choose it with a `.ovhconfig` file; the report shows it if there is one in the web root.
4. **Redirects without PHP.** Open the browser's developer tools on the Network tab and visit `https://<address>/spike/t-redirect/go`. Look at the response headers of the first request (the redirect, status 302) and of the last (`target.php`). Does only the last one have `X-Powered-By`? Which `Server` header do they show? If the redirect carries no PHP header, Apache answered it alone, which is the point of the design.
5. **Anything in the control panel** that could interfere: a cache or CDN, a firewall or ModSecurity switch, a "performance" mode, or limits on the number of requests.
6. **Existing `.htaccess` in the web root.** The report says whether there is one and how big it is. If there is one, tell me what is in it. The shortener's block has to live alongside it.
7. **Space.** The quota the panel shows, and how much is used.

## Reading the results

Each result is `PASS`, `FAIL`, `WARN` or `INFO`. `INFO` means "no verdict, here are the facts". What each test decides:

| # | What it checks | If it passes | If it fails |
|---|---|---|---|
| 0 | PHP runs and what it is | Fixes the PHP version to target | Nothing else matters yet |
| 1 | `Redirect` and `RedirectMatch` in `.htaccess` | Short links can be plain `Redirect` lines, the lightest option | Try the rewrite style (test 2) |
| 2 | `RewriteRule` redirects | `mod_rewrite` is a second way to write them | If 1 and 2 both fail, redirects need PHP on every hit, with a data file instead of `.htaccess` |
| 3 | A missing URL loads a PHP page and keeps the 404 status | The editor can be the fallback, as designed | Use a rewrite rule to `index.php` for files that do not exist |
| 4 | `Header` in `.htaccess` | The CSP could come from Apache | No problem: PHP sends it with `header()` |
| 5 | Blocking one file | The configuration file can sit in the web root safely | Put it above the web root, or rely on it returning nothing when run |
| 6 | HTTPS detection in `.htaccess` | A "force HTTPS" rule is safe | No such rule in `.htaccess`: force HTTPS from PHP or the control panel |
| 7 | MIME types | The editor's ES modules load as they are | Add `AddType` lines to `.htaccess` (test 7 also tries that) |
| 8 | PHP rewrites a block of `.htaccess` safely | The planned writer works: block, atomic write, backup, self-check and rollback | If it cannot write at all, the design changes (see 1 and 2). If the server cannot request itself, the self-check is dropped and validation has to be stricter |
| 9 | Sessions | The admin login can use PHP sessions | Sign a cookie instead |
| 10 | Password hashing | The login can use `password_hash` | Needs another approach |
| 11 | A file above the web root | The configuration file can live there | The configuration file stays in the web root, protected by test 5 |
| 12 | A lock blocks a second request | Concurrent writes can be serialized | Use another kind of lock, or write less often |
| 13 | Space and limits | Confirms the 100 MB and the limits | May cap what the shortener can store |

Test 8 is the one that matters most. It writes a block, checks that the new redirect works and how soon, removes it, and then **breaks the file on purpose** with an invalid directive to see what the host does and whether the rollback brings the folder back. It only does that if the server can request itself, so it always has a way back, and it only ever touches `spike/t-write/.htaccess`.

## If something goes wrong

- **A test folder answers with a 500.** That folder's `.htaccess` uses a directive the host does not allow. That is the answer to that test, so just run it and report. Each feature has its own folder precisely so one failure does not take down the rest.
- **The dashboard itself does not load.** The `spike` folder has no `.htaccess`, so look at the web root: it may have one that blocks something. Tell me what you see.
- **Everything fails with 404.** The folder is not at `/spike/`. The page warns about it at the top.
- **Test 8 left `t-write` broken.** Delete `spike/t-write/.htaccess` and upload it again from the repository.

## What was tested locally

The package was run against Apache 2.4.55 with PHP 8.5 as CGI: 10 passes, 0 failures. As a control, with `.htaccess` files ignored (`AllowOverride None`) tests 1, 2, 3, 5 and 8 fail and test 4 warns, as they should, and the deliberate break in test 8 is rolled back.

Not tested, so the OVH results matter: test 6 (the local server has no HTTPS), anything specific to OVH (its proxy, PHP-FPM, file system, limits), PHP versions before 8.5, and browsers other than Chrome.

## Files expected on the hosting

```
spike/
  index.html  runner.js  env.php  target.php
  t-redirect/.htaccess
  t-rewrite/.htaccess
  t-errordoc/.htaccess  handler.php
  t-headers/.htaccess  ping.txt
  t-deny/.htaccess  secret.txt
  t-https/.htaccess  ok.txt
  t-mime/test.js  test.css  test.svg  test.png
  t-mime-fix/.htaccess  test.js  test.css  test.svg  test.png
  t-session/counter.php
  t-write/.htaccess  lab.php  ping.txt
  parent-probe/ladyqr-parent-probe.php     (this one goes ONE LEVEL ABOVE the web root)
```

Eight of those are `.htaccess` files.

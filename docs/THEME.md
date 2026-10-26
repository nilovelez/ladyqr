# Customizing the generator page

This guide is for developers who want the QR code generator, and the admin pages around it, to look like their own site. It does not need any change to LadyQR's files, so you can update LadyQR without losing your work.

Everything lives in a folder called `theme`, next to `index.php`. LadyQR does not ship it, so an update never touches it. The folder and every file in it are optional.

```
your-site/
├── index.php
├── theme/
│   ├── index.html     blank, so a server that lists folders does not list this one
│   ├── custom.css     your styles
│   ├── head.html      added at the end of <head>, on the generator
│   ├── header.html    a <header> at the top of <body>, on the generator
│   └── footer.html    the contents of the <footer>, on the generator
└── ...
```

The release has an example, `theme.zip`: https://github.com/nilovelez/ladyqr/releases/latest/download/theme.zip. Unzip it in your site's folder, next to `index.php`. It holds the blank `index.html` and a `custom.css` with a few commented lines to start from.

## The files

| File | Where it is used | What LadyQR does with it |
|---|---|---|
| `custom.css` | The generator, the admin pages (login and short links) and the not-found page | Links it with `<link rel="stylesheet">`, after LadyQR's own styles, so what you write wins. The link carries the file's modification time, so a change shows at once. |
| `head.html` | The generator only | Puts its contents right before `</head>`. |
| `header.html` | The generator only | Adds `<header>` right after `<body>` and puts the file's contents inside it. |
| `footer.html` | The generator only | Replaces what is inside `<footer>`. The usual "Made with LadyQR" link goes away; an empty `footer.html` leaves an empty footer. |

The installer is not themed: it is the first thing you see and it has to work the same everywhere.

The files are HTML, printed exactly as they are. PHP in them is not run. Write them in the language of your site: LadyQR has one language per site (`LADYQR_LANGUAGE`) and no language selector, so there is no translation of the theme files.

Anything in `theme/` can be read by anyone who knows its address, like any other file of your site. Do not put secrets in it.

## What you can include, and what you cannot

LadyQR promises that the generator never talks to another server, and a strict Content-Security-Policy makes the browser enforce it. The theme does not relax it, so it works inside these rules:

```
default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self';
connect-src 'none'; base-uri 'none'; form-action 'none'
```

| You can | You cannot |
|---|---|
| Put all your styles in `custom.css`, or in other `.css` files of your site linked from `head.html` | Write inline styles: no `<style>` element and no `style="…"` attributes |
| Use images that are files of your own site, in HTML and in CSS (`url(…)`) | Use images from another site, or `data:` images |
| Link scripts that are files of your own site, with `<script src="…">` | Write inline scripts or `onclick="…"` attributes, or load scripts from another site |
| Add meta tags, link tags (icons, canonical address, social cards) and `<script type="application/ld+json">` data blocks in `head.html` | Make requests of any kind to another site: no fetch, no analytics, no embedded frames, no forms that send data elsewhere |
| Use the system fonts | Use web fonts, even if the font files are on your own site |

Links in `header.html` and `footer.html` to other sites are fine: the policy stops the page from loading and sending things, not from linking.

The paths in `head.html`, `header.html` and `footer.html` are relative to the generator page, which is the site's root, so `theme/logo.png` works. If you installed LadyQR in a folder, they are relative to that folder.

## Hooks that are safe to use

Your CSS depends on LadyQR's markup, so these are the parts that will not change without a note in the changelog.

**Colors and shapes.** Override them in `:root`:

```css
:root {
	--accent: #0b6b3a;
	--accent-deep: #07522c;
}
```

`--bg`, `--surface`, `--zebra`, `--ink`, `--muted`, `--line`, `--border`, `--accent`, `--accent-ink`, `--accent-deep`, `--primary`, `--primary-ink`, `--primary-deep`, `--warn`, `--radius-sm`, `--radius`, `--radius-lg`, `--control`, `--font` and `--mono`. [DESIGN.md](../DESIGN.md) says what each one is for.

**Layout.** Three variables move the page's furniture:

| Variable | Default | What it moves |
|---|---|---|
| `--page-top` | `72px` | The space above the page's content, where the help button sits |
| `--help-top` | `16px` plus the safe area | The distance of the help button from the top |
| `--help-right` | `16px` plus the safe area | The distance of the help button from the right |

For a full-width bar at the top, you could write:

```css
:root {
	--page-top: 0;
	--help-top: 12px;
}
header {
	padding: 12px 20px;
	background: #fff;
}
```

**The generator's markup.**

```
body
├── header                     yours, when there is a header.html
├── button.help#help-open      the help button (position: absolute)
├── dialog#help-dialog         the help window
├── main
│   └── div.card.editor
│       └── div.card-body
│           ├── input#content.field      the text field
│           ├── div#qr.qr                the QR code
│           ├── details.settings         Customize
│           └── div.download             the download buttons
└── footer
```

**The admin and the not-found page** have `body.admin`, with `admin-panel` added on the pages that show the short links, and the same `main` and `.card` structure.

Selectors that are not in this guide, and the order of the elements inside the cards, are internal: they can change in any version.

## Examples

A site with its own color and logo, with no change to LadyQR's files:

`theme/custom.css`

```css
:root {
	--accent: #0b6b3a;
	--accent-deep: #07522c;
	--primary: #0b6b3a;
	--primary-deep: #07522c;
}
.site-bar {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 20px;
	background: var(--surface);
	border-bottom: 1px solid var(--line);
}
```

`theme/header.html`

```html
<div class="site-bar">
	<a href="https://example.com/"><img src="theme/logo.png" alt="Example Inc." height="32"></a>
	<span>QR codes</span>
</div>
```

`theme/footer.html`

```html
<p><a href="https://example.com/privacy">Privacy</a> · © Example Inc.</p>
```

`theme/head.html`

```html
<meta name="theme-color" content="#0b6b3a">
<link rel="canonical" href="https://example.com/qr/">
```

## Title and description

The title and the meta description of the generator page are settings, in `ladyqr-config.php`:

```php
define( 'LADYQR_SITE_TITLE', 'QR codes by Example Inc.' );
define( 'LADYQR_SITE_DESCRIPTION', 'Make a QR code for a link, right in your browser.' );
```

Without them the title is `LadyQR` and there is no description. They are for the generator page only.

## Short links and the name `theme`

A short link cannot be called `theme`: the admin refuses names of files and folders that exist, and `theme` is reserved besides.

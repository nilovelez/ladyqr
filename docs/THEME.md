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

The release has an example, `theme.zip`: https://github.com/nilovelez/ladyqr/releases/latest/download/theme.zip. Unzip it in your site's folder, next to `index.php`, and you have a working theme: a green accent, a top bar with a logo and a footer. Edit what you want and delete what you do not. It holds all the files above, with the blank `index.html`, and a `logo.png`.

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

LadyQR promises that the generator never talks to another server, and a strict Content-Security-Policy makes the browser enforce it. The theme does not relax it, so it works inside these rules (`font-src 'self'` is what lets you use your own fonts, and only your own):

```
default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self';
font-src 'self'; connect-src 'none'; base-uri 'none'; form-action 'none'
```

| You can | You cannot |
|---|---|
| Put all your styles in `custom.css`, or in other `.css` files of your site linked from `head.html` | Write inline styles: no `<style>` element and no `style="…"` attributes |
| Use images that are files of your own site, in HTML and in CSS (`url(…)`) | Use images from another site, or `data:` images |
| Link scripts that are files of your own site, with `<script src="…">` | Write inline scripts or `onclick="…"` attributes, or load scripts from another site |
| Add meta tags, link tags (icons, canonical address, social cards) and `<script type="application/ld+json">` data blocks in `head.html` | Make requests of any kind to another site: no fetch, no analytics, no embedded frames, no forms that send data elsewhere |
| Use fonts that are files of your own site, with `@font-face` in `custom.css`, and the system fonts | Use fonts from another site, such as Google Fonts |

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

`--bg`, `--surface`, `--zebra`, `--ink`, `--muted`, `--placeholder`, `--line`, `--border`, `--accent`, `--accent-ink`, `--accent-deep`, `--primary`, `--primary-ink`, `--primary-deep`, `--warn`, `--radius-sm`, `--radius`, `--radius-lg`, `--control`, `--font` and `--mono`. [DESIGN.md](../DESIGN.md) says what each one is for.

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

## The example theme

The `theme.zip` of every release is the folder [docs/theme](theme/), ready to upload:

| File | What it does |
|---|---|
| `custom.css` | The green accent (the colors it overrides are the four first lines), the top bar's style, and where the page and the help button start |
| `header.html` | The top bar: a logo (`logo.png`) and a line of text |
| `footer.html` | `Privacy · © Example Inc. · Made with LadyQR`, with the last part linking to https://ladyqr.nilovelez.com/ |
| `head.html` | A `theme-color` meta tag |
| `logo.png` | The logo, 64 × 60 px |
| `index.html` | Blank, so a server that lists folders does not list this one |

`footer.html` keeps a small "Made with LadyQR" link as a minimal credit. It is yours to keep or to remove: delete that last link from the file, or the whole file to get the usual footer back.

Everything in it says "Example Inc.": change the names, the link of the privacy page and the logo to your own.
## Title and description

The title and the meta description of the generator page are settings, in `ladyqr-config.php`:

```php
define( 'LADYQR_SITE_TITLE', 'QR codes by Example Inc.' );
define( 'LADYQR_SITE_DESCRIPTION', 'Make a QR code for a link, right in your browser.' );
```

Without them the title is `LadyQR` and there is no description. They are for the generator page only.

## Short links and the name `theme`

A short link cannot be called `theme`: the admin refuses names of files and folders that exist, and `theme` is reserved besides.

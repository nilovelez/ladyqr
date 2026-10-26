# LadyQR design

This document is the design system of LadyQR: the tokens, the components and the rules that keep every screen consistent, and then how each screen uses them. The styles live in `assets/app.css` (tokens, shared components and the editor) and `assets/admin.css` (the admin and the not-found page); the website has its own copy of the tokens in `site/site.css`. When you change one, update this file too, and check the [consistency checklist](#consistency-checklist).

## Principles

1. **Simple, clean and professional.** Flat surfaces, thin lines, small radii, one accent color. Nothing decorative competes with the task.
2. **One task per screen.** The editor turns text into a QR code, live, with no "generate" button. Each admin screen does one thing: list and add, edit, or delete.
3. **Privacy you can see.** Nothing is sent anywhere, and the interface says so in plain words. Only the editor's settings are remembered (in the browser), never what you type.
4. **Accessible by default.** Contrast, keyboard use and screen readers are requirements, not polish. See [Accessibility](#accessibility).
5. **Self-contained.** No external fonts, images or scripts. This is also what makes the strict CSP possible.
6. **One theme.** LadyQR is light only, everywhere, whatever the system preference. `color-scheme: light` is declared so native controls match.

## Tokens

All colors and shapes are CSS custom properties on `:root` in `assets/app.css`. Do not write a color, radius or control height anywhere else: use a token, or add one here first.

### Color

| Token | Value | Used for |
|---|---|---|
| `--bg` | `#f4f4f5` | Page background, hover fill of secondary buttons |
| `--surface` | `#ffffff` | Card, fields, dialog, menus, even table rows |
| `--zebra` | `#f7f7f8` | Soft gray fills: odd table rows, card headers, the settings column, field add-ons, the QR empty state |
| `--ink` | `#18181b` | Body text, headings, labels |
| `--muted` | `#5f5f66` | Secondary text: hints, placeholders, table headers, targets, footer |
| `--line` | `#e4e4e7` | Edges of containers (cards, tables, dialog) and dividers inside them |
| `--border` | `#8a8a93` | Edges of controls (fields, selects, secondary buttons, segmented control, color inputs) |
| `--accent` | `#d9261c` | The few actions that are the point of a screen (the download button), the selected state, links, focus ring, error text |
| `--accent-ink` | `#ffffff` | Text on `--accent` |
| `--accent-deep` | `#b81d14` | Hover of the download button, divider of the split button |
| `--primary` | `#3f3f46` | Primary buttons |
| `--primary-ink` | `#ffffff` | Text on `--primary` |
| `--primary-deep` | `#27272a` | Hover of primary buttons |
| `--warn` | `#a35200` | Warnings (contrast, insecure connection, a change that could not be checked) |

The accent is the red of the ladybug icon. Red means attention, even danger, so it is kept for very specific actions, such as downloading the QR code that was just made; ordinary buttons are gray. Nothing else is saturated, so the one action that matters stands out. Grays are neutral, with no warm or cool tint.

### Measured contrast

WCAG ratios, measured with `contrastRatio()` from `assets/render.js`. Re-measure whenever a color token changes.

| Pair | On `--surface` | On `--zebra` | On `--bg` | Needed |
|---|---|---|---|---|
| `--ink` | 17.72 | 16.55 | 16.12 | 4.5 |
| `--muted` | 6.33 | 5.92 | 5.76 | 4.5 |
| `--accent` (links, error text) | 4.95 | 4.62 | 4.50 | 4.5 |
| `--warn` | 5.58 | 5.21 | 5.08 | 4.5 |
| `--border` (control edges) | 3.42 | 3.20 | 3.11 | 3 |
| `--accent-ink` on `--accent` | 4.95 | | | 4.5 |
| `--primary-ink` on `--primary` | 10.44 | | | 4.5 |

Rules that come out of this:

- Text is at least 4.5:1, including small text. Do not lower a text color's opacity to make it look secondary; use `--muted`.
- The edge of anything you can type into or press is `--border`, at least 3:1 against what is on both sides of it.
- `--line` is for containers and dividers only, never for the edge of a control.
- Error text uses `--accent`; it stays readable on every background in the table.

### Shape

| Token | Value | Used for |
|---|---|---|
| `--radius-sm` | 4 px | Small things: code tags, color inputs, inline `code` |
| `--radius` | 6 px | Every control (fields, selects, buttons, segmented control, menus), tables, notices with a fill, code blocks |
| `--radius-lg` | 10 px | Cards and the dialog |
| `--control` | 44 px | Height of every field, select and button |

The help button is the only round element, because it is an icon button. The QR preview has square corners on purpose: rounded corners could be mistaken for part of the code.

Shadows are almost absent: cards have `0 1px 3px rgba(0,0,0,.05)`, and only floating things (the download menu, the dialog) have a real shadow.

### Typography

System fonts only: `ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`, and `ui-monospace, SFMono-Regular, Menlo, Consolas, monospace` for codes and numbers. No web fonts: they would need an external request, and the CSP forbids it.

| Element | Size | Weight |
|---|---|---|
| Page title (`h1` in a card) | 1.25 rem | 700 |
| Section title (`h2`) | 1.1 rem | 700 |
| Subsection (`h3`) | 1 rem | 700 |
| Body, fields, buttons | 1 rem | 400, buttons 600 |
| Labels | 0.875 rem, sentence case | 600 |
| Hints, table headers, notices | 0.8 to 0.9 rem | 400 to 600 |
| Editor text field | 1.35 rem (1.15 rem under 480 px) | 500 |
| Download button | 1.1 rem | 600 |

Labels are sentence case ("Short name"), not uppercase.

### Spacing

Card padding is 24 px (16 px under 480 px, 28 px in the wide editor). Inside a card, blocks are 20 px apart; form fields are 16 px apart; a label sits 6 px over its control; a hint or an error sits 6 px under it. Buttons in a row are 10 px apart.

## Components

Shared components are in `assets/app.css`, so the editor, the admin and the not-found page look the same.

**Card.** `.card` with `.card-body`. White, 1 px `--line` edge, `--radius-lg`. A card header (the editor's settings bar) uses the `--zebra` fill and a `--line` divider.

**Label.** `.label`, a real `<label>` above its control, so every control starts on the same left edge. The exception is a form with `.side-labels` (the short link forms): from 720 px up each `.field-row` puts its label in a 120 px column on the left, and the hints, errors and buttons line up with the fields.

**Field and select.** `.input`. 44 px tall, white, 1 px `--border`, `--radius`, 12 px side padding. Focus is a 2 px `--accent` ring drawn inside the edge. An invalid field (`aria-invalid="true"`) gets an `--accent` edge, and its message goes under it.

**Field with add-on.** `.input-group` with an `.addon` and an `.input`: fixed text in front of a field, such as the site's address before a short name. The add-on has the `--zebra` fill and shares the field's edge and height, so the pair reads as one control and lines up with the fields above and below.

**Hint and error.** `.hint` in `--muted`, `.field-error` in `--accent`, both under the control and linked to it with `aria-describedby`.

**Button.** `.btn` is the primary action: `--primary` gray fill, white text, 44 px, `--radius`, as wide as its text. `.btn.secondary` is white with a `--border` edge, for "Back", "Cancel" and similar. A red button (`--accent` fill, white text) is not a variant to reach for: it is kept for very specific actions, such as the editor's download button. `.btn.block` is full width, used only for the login button. Buttons that belong to a form go in `.form-actions`, a row aligned to the left. When the buttons take a step back and a step forward, the one that goes back comes first and the one that goes on (such as "Install LadyQR") comes after it. When a screen ends with destinations of equal weight and nothing left to decide, such as the end of the installer, they are all secondary.

**Links.** Text links use `--accent`, underlined. Actions inside a table ("Edit", "Delete") are links, not buttons.

**Notice.** `.notice`, a line of text with an optional icon: `--warn` by default, `.error` in `--accent`, `.info` in `--muted`. A result after an action uses `role="status"`; a failure uses `role="alert"`.

**Table.** `.table-wrap` around a `table.links`. White header row in `--muted` small text, then rows alternating `--zebra` (odd) and white (even), with no lines between rows. Cells are 10 × 14 px, on one line, and long text is cut with an ellipsis (the full value is in `title`). Under 600 px each row becomes a block: the name, the target, and then the code and the actions on one line.

**Code tag.** `.code-tag`, a small monospace label with a `--border` edge, for HTTP codes such as `301` and `302`.

**Banner.** `.banner`, a full `--accent` block with white text, used only for the permanent "No login is required" warning. It is the loudest thing on any page, on purpose.

**Code block.** `pre.code`, monospace, on `--zebra` with a `--line` edge.

**Visually hidden.** `.visually-hidden` keeps text for screen readers only, such as the header of the actions column.

## The editor

### Layout

The layout has two forms, with the breakpoint at 900 px of screen width. Below it, which includes every phone, it is a single centered column, 560 px wide at most:

```
                                             (?)  Help, top right of the page
 +---------------------------------------+
 | Settings                            v |  Card header, collapsible
 +---------------------------------------+
 |  [ Type or paste a link or text     ] |  Text field
 |            +-----------+              |
 |            |  QR code  |              |  Square preview
 |            +-----------+              |
 |   (warning, only when needed)         |
 |  [ Download  PNG 500px |  v ]         |  Split button
 +---------------------------------------+
        Made with LadyQR, get yours free     Footer
```

From 900 px up, the card (`.card.editor`) is 940 px wide at most and the settings become a column on its right, always open:

```
                                                         (?)
 +--------------------------------------+----------------+
 |  [ Type or paste a link or text    ] | Settings       |
 |           +-----------+              |----------------|
 |           |  QR code  |              | Error correct. |
 |           +-----------+              | Margin         |
 |  [ Download  PNG 500px |  v ]        | Colors         |
 +--------------------------------------+----------------+
```

- The settings are error correction, margin, module and background colors, and a reset button. On a narrow screen they are the card's header, collapsed until tapped. On a wide screen they are a 300 px side panel on `--zebra`, as tall as the card, and "Settings" is just its heading: no arrow, and it does not collapse.
- **In the page the settings come after the main content**, and CSS moves them to the top on narrow screens. That keeps the keyboard order the same as what a wide screen shows (field, download, then the settings). On the wide layout the "Settings" title is taken out of the tab order, since it does nothing.
- Each size remembers its own state: if you open the settings on a narrow screen, widen the window and narrow it again, they are still open.
- The two color pickers sit one under the other in the side panel, because the column is too narrow for them side by side.

### Parts

**Text field.** `.field`: one line, large, autofocused, with a placeholder. `autocomplete`, `autocapitalize` and `spellcheck` are off, because it takes URLs. Same edge, radius and focus ring as `.input`, only bigger.

**Settings.** A `<details>` element, closed by default on narrow screens. It contains:
- Error correction: four segmented buttons (L, M, Q, H) with their recovery percentage. `aria-pressed` marks the selected one, which takes the `--accent` fill.
- Margin: a range from 0 to 8 modules, with its value in monospace next to it.
- Module color and background color: native color inputs.
- Reset settings: a small secondary button that restores the defaults and clears what was remembered. Disabled while everything is already at the defaults.

**QR preview.** A square frame with a `--line` edge and square corners. It shows the QR code as inline SVG, or, when the field is empty or the text is too long for a QR code, a faint drawing of a QR code on `--zebra` with a message on top. Downloads are disabled in those two cases. The drawing is a skeleton generated by `ladyqr_skeleton_path()` in `index.php` and deliberately cannot be scanned.

**Warning.** A small `--warn` line under the preview with a triangle icon, shown only when needed: low contrast (4:1 or less), inverted colors (modules lighter than the background), or both.

**Download.** A split button, 52 px tall: the big part saves a 500 px PNG, the arrow opens a menu with PNG 1000 px and SVG. The menu closes on outside click, on Escape and after choosing.

**Help.** A round question-mark button at the top right of the page. On hover or keyboard focus it widens into a pill that reads "Help". It opens a modal `<dialog>` with "About", instructions and a note on PNG versus SVG, which closes with ×, Escape or a click outside.

**Footer.** The ladybug and "Made with LadyQR, get yours free", linking to the project, small and in `--muted`.

### Behavior

- The QR code is rebuilt on every input event, with no debounce.
- Text is never stored. Settings (level, margin, colors) are saved to `localStorage` under `ladyqr-settings`, validated when read; the editor works if storage is blocked or corrupt.
- Download file names: `qrcode-500.png`, `qrcode-1000.png` and `qrcode.svg`. PNG output is exactly the requested size, with module edges rounded to whole pixels.

## The admin

The admin pages (`/admin/`) use the shared components plus `assets/admin.css`. They have no JavaScript. Each page has the ladybug and "LadyQR admin" above the card, on the left (with "Signed in as … – Log out" on the right once logged in), and one card with the content of its state. The login and locked states use a 480 px card, centered on the page; the panel uses a 760 px card at the top of the page.

**Account line.** When someone is logged in, a line above the card on the right, outside it, in `--muted`: "Signed in as **admin** – Log out". "Log out" is a link that carries the session's token, so another site cannot log you out.

**States:**

- **Locked:** a heading, a short explanation, a code block with the two lines to add to the configuration file, and how the password is secured. It does not show the server's paths, because anyone can see this page.
- **Login:** a "Log in" heading, an optional notice that the session expired, an optional error, the user and password fields, a `--warn` notice when the connection is not HTTPS, and a full-width button. A wrong attempt shows the same message whatever was wrong.
- **Short links:** the result of securing the password if it just happened, the result of the last change, the table of links, and the "Add a short link" form.
  - The table has four columns: the short link (`/name`, a link to it), where it goes (muted, cut with an ellipsis), the code (`301` or `302` in a code tag), and the "Edit" and "Delete" links.
  - Before each short link, a 16 px QR icon in `--muted` (`--ink` on hover) opens the generator in a new tab with the link filled in. It is an inline SVG with `aria-hidden`; the link carries its label ("QR code for /name") in `aria-label` and `title`.
  - The form has the short name (with the site's address as an add-on in front), the address it goes to, and the type, a select whose options start with the code: "301 – Permanent redirect" and "302 – Temporary redirect" (the default). A hint under it explains that browsers remember permanent redirects.
- **Edit short link:** the same fields, filled in, with "Save changes" and "Cancel".
- **Delete short link:** the link in a `--zebra` box (name, code and target) and the question, with "Delete" and "Cancel".
- **Open:** the panel with the red banner above the card, permanently, saying that no login is required and that anyone who can reach the page can change the links. There is no account line, since nobody is logged in.

**Not found.** `404.php` uses the same card: the ladybug, "Page not found", one sentence and a "Make a QR code" button back to the editor. It answers with a real 404 status and `noindex`.

The admin is not linked from anywhere else, and every state carries `noindex` (see the README).

## The website

The landing page at https://ladyqr.nilovelez.com/ (`site/index.html` and `site/site.css`) repeats the app's tokens (a copy, because the site is deployed on its own) and follows the same rules: light only, system fonts, `--radius` buttons, `--radius-lg` cards with a `--line` edge, the primary buttons in `--primary` and the secondary ones with a `--border` edge.

- It carries the same `<meta>` CSP as the editor (scripts from its own origin, no connections), because GitHub Pages cannot send headers, and it loads `assets/app.css` and `assets/app.js` for the editor.
- Layout: a fixed header whose name and logo only appear after scrolling, a hero, a grid of six features, a screenshot of the editor (`site/img/editor.png`, the narrow layout at 560 px), the optional link shortener with a screenshot of the admin (`site/img/shortener.png`), a "verify it yourself" section, a self-hosting section and the roadmap.
- The hero is the welcome (the speech bubble, the bug and two buttons, over a drawn meadow) and the real editor, built from `index.php` by `tools/build-site.php`. On a wide screen the editor is the narrow layout, in half of the hero: the card has the `compact` class, which keeps that layout at every width (the wide layout, with the settings on the right, is only for the installed editor). On a phone the bug stands on the editor's top edge and the buttons are hidden.
- The hero's images are `assets/ladyqr-web.webp` (the bug, 640 × 480) and `assets/web-background-kawaii_1080.webp` (the meadow, 1980 × 1080), copied to `img/` by the build. The meadow is also in the release zip, because the installer uses it; the bug and the other versions of the meadow in `assets/` are not.

When the editor's look changes, take a new screenshot: the card alone, 560 px wide, with `LADYQR.NILOVELEZ.COM` typed in (it fits a version 1 code) and the settings closed.

## Accessibility

- **Contrast:** see [Measured contrast](#measured-contrast).
- **Keyboard:** everything is reachable and operable with the keyboard. Focus is a 2 px `--accent` ring on every link, button, summary, field and select (inside the edge for fields, outside for the rest). Escape closes the download menu and the dialog.
- **Screen readers:** every field has a real label; errors and hints are linked with `aria-describedby`; the QR preview is `role="img"` with a label and `aria-live="polite"`; results and warnings use `role="status"` or `role="alert"`; the split button's arrow has an `aria-label` and `aria-expanded`; table headers use `scope="col"`; decorative icons are `aria-hidden`.
- **Touch:** nothing depends on hover. Controls are 44 px tall (36 px for the small reset and help buttons).
- **Motion:** transitions are short, and are disabled under `prefers-reduced-motion`.
- **Zoom and layout:** sizes are in `rem`, the layouts reflow to one column, and there is no horizontal scrolling on a phone.

## Internationalization

- The interface is translatable. Do not size anything to the English text: Spanish and other languages are often 20 to 30 % longer. Let text wrap and let buttons grow.
- `<html lang>` follows the configured language. Right-to-left languages are not supported yet.
- Every visible string goes through `__()` and friends; strings the JavaScript needs are passed in a JSON block in the page.

## Technical constraints

- The CSP is `default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'none'` (the admin adds `form-action 'self'` and has no scripts), so: no inline `<script>` or `<style>`, no `style=""` attributes in the HTML, no external fonts, images or scripts, and no network calls.
- Assets are same-origin and carry a `?v=<file modification time>` query so browsers pick up changes.

## Brand assets

The ladybug with a QR code on its back comes in two shapes. No other sizes are generated: each use takes the closest file.

- **Fitted to the drawing, 4:3**, shown in the pages:
  - `assets/ladyqr.png`, 64 × 48 px: the footer of the editor, at 32 × 24.
  - `assets/ladyqr@2X.png`, 128 × 96 px: next to headings. 48 × 36 in the help dialog, the admin bar and the website's header; 64 × 48 on the not-found page; 128 × 96 at the top of the README.
  - `assets/web-background-kawaii_1080.webp`, 1980 × 1080 px: the background of the installer's screens (`body.installer`: `center bottom / cover`, over `rgb(191, 230, 247)`), the same meadow as the website's hero. The cards stay white on top of it.
  - `assets/ladyqr@4X.png`, 256 × 192 px: the installer's welcome ("Hi! I'm LadyQR"), at 128 × 96.
- **Square**, for the browser and the system:
  - `assets/icon-64.png`, 64 × 64 px: the favicon.
  - `assets/icon-128.png`, 128 × 128 px: the Apple touch icon, and the project's icon in Guppy.
- **For development only**, never in the release zip or the website: `assets/icon-full.png` (the full-size original, 1280 × 960) and `assets/icon-vector.ai` (the vector original).

The website keeps its own copies in `site/img/`.

## Consistency checklist

Before adding or changing a screen:

1. Colors, radii and the control height come from the tokens. No new gray without a row in the color table and a contrast measurement.
2. Every control is 44 px tall, with a `--border` edge and `--radius`; containers use `--line` and `--radius-lg`.
3. Labels go above controls, in sentence case. Hints and errors go under them.
4. Buttons are as wide as their text and sit at the left in `.form-actions`: gray for the primary action, white for the secondary one, and red only for very specific actions such as the download. A step back comes before the step forward. Only the login button is full width.
5. Lists of things are tables with alternating rows, not boxes or cards.
6. Red is attention: use `--accent` for links, focus, errors and the very few actions that are the point of a screen, never for an ordinary button.
7. Every message has the right role: `status` for results, `alert` for failures.
8. Check the screen at 390 px, 1100 px and in between, with the keyboard only, and in Spanish.

## Not designed yet

- Right-to-left languages.

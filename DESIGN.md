# LadyQR design

This document describes how LadyQR looks and behaves, and why. The styles live in `assets/css/app.css`, the page in `index.php` and the behavior in `assets/js/app.js`. When you change one, update this file too.

## Principles

1. **One task, done instantly.** The page exists to turn text into a QR code. The QR code updates as you type, with no "generate" button.
2. **Privacy you can see.** Nothing is sent anywhere, and the interface says so in plain words. Only the settings are remembered (in the browser), never what you type.
3. **Simple first, options second.** The text field, the QR code and the download button are always visible. Everything else is inside a collapsed Settings panel or the help dialog.
4. **Accessible by default.** Contrast, keyboard use and screen readers are requirements, not polish. See [Accessibility](#accessibility).
5. **Self-contained.** No external fonts, images or scripts. This is also what makes the strict CSP possible.

## Layout

A single centered column, 560 px wide at most, in this order:

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

- The settings are the card's header, like a Bootstrap card header: error correction, margin, module and background colors, and a reset button.
- The download button is split. The big part downloads a 500 px PNG; the arrow opens a menu with PNG 1000 px and SVG.
- On narrow screens the card padding shrinks from 24 px to 16 px and the text field gets a smaller font so a placeholder fits.

## Color

Colors are CSS custom properties on `:root`. The dark theme follows `prefers-color-scheme`; there is no manual switch.

| Token | Light | Dark | Used for |
|---|---|---|---|
| `--bg` | `#f5f4f3` | `#141213` | Page background, text field fill, settings header |
| `--surface` | `#ffffff` | `#1d1a1b` | Card, dialog, menu, help button |
| `--ink` | `#1d1a1b` | `#f1eeed` | Body text |
| `--muted` | `#6d6869` | `#a29b9c` | Labels, placeholders, secondary text |
| `--border` | `#847e7f` | `#7e7879` | Outlines of every control and container |
| `--line` | `#d3cfce` | `#4a4445` | Soft dividers inside a container |
| `--accent` | `#d9261c` | `#ef4a40` | Primary action, selected state, focus ring |
| `--accent-ink` | `#ffffff` | `#141213` | Text on `--accent` |
| `--accent-deep` | `#b81d14` | `#d93a30` | Hover state and the split button divider |
| `--warn` | `#a35200` | `#f0b35a` | Contrast and inverted colors warning |

The accent is the red of the ladybug icon. Nothing else is saturated, so the one action that matters stands out.

### Measured contrast

Ratios are WCAG relative luminance ratios, measured with `contrastRatio()` from `assets/js/render.js`. Re-measure whenever a token changes.

| Pair | Light | Dark | Needed |
|---|---|---|---|
| `--ink` on `--surface` | 17.27 | 14.96 | 4.5 |
| `--ink` on `--bg` | 15.72 | 16.16 | 4.5 |
| `--muted` on `--surface` | 5.48 | 6.34 | 4.5 |
| `--muted` on `--bg` | 4.99 | 6.84 | 4.5 |
| `--accent-ink` on `--accent` (button text) | 4.95 | 5.08 | 4.5 |
| `--warn` on `--surface` | 5.58 | 9.29 | 4.5 |
| `--border` on `--surface` | 3.98 | 3.99 | 3 |
| `--border` on `--bg` | 3.63 | 4.31 | 3 |
| `--accent` on `--surface` (focus ring, slider) | 4.95 | 4.71 | 3 |

Rules that come out of this:

- Text is at least 4.5:1, including small text. Do not lower a text color's opacity to make it look secondary; use `--muted`. (An earlier 85 % opacity on the small button labels dropped them to 3.89:1 and was removed.)
- The outline of anything that identifies a control is at least 3:1 against both sides of it. This is why `--border` is much darker than a typical hairline.
- `--line` is only for decorative dividers inside a container, never for the edge of a control.

## Typography

System fonts only: `ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif`, and `ui-monospace, SFMono-Regular, Menlo, Consolas, monospace` for the margin value and the help footer. No web fonts: they would need an external request, and the CSP forbids it.

| Element | Size | Weight |
|---|---|---|
| Text field | 1.6 rem (1.2 rem under 480 px) | 500 |
| Download button | 1.25 rem | 700 |
| Body and dialog text | 0.97 to 1 rem | 400 |
| Labels | 0.75 rem, uppercase, 0.06 em tracking | 600 |
| Warning | 0.85 rem | 500 |
| Footer | 0.85 rem | 400 |

## Shape and spacing

- Radius: 24 px card (its header follows it), 20 px dialog, 14 px text field and download button, 12 px download menu, 10 px small controls. The QR frame has no radius.
- Borders: 1 px `--border` on containers and controls, 2 px on the text field (it turns `--accent` when focused).
- The card body uses a 22 px gap between its parts. The card has a soft shadow in addition to its border.

## Components

**Text field.** One line, large, autofocused, with a placeholder. `autocomplete`, `autocapitalize` and `spellcheck` are off, because it takes URLs.

**Settings.** A `<details>` element, closed by default, styled as the card header. It contains:
- Error correction: four segmented buttons (L, M, Q, H) with their recovery percentage. `aria-pressed` marks the selected one.
- Margin: a range from 0 to 8 modules, with its value shown next to it.
- Module color and background color: native color inputs.
- Reset settings: restores the defaults and clears what was remembered. Disabled while everything is already at the defaults.

**QR preview.** A square frame with square corners: rounded corners could be mistaken for part of the code, or make it look cropped. It shows the QR code as inline SVG, or, when the field is empty or the text is too long for a QR code, a faint idealized drawing of a QR code (three finder patterns, an alignment pattern and scattered modules) with a message on top. Downloads are disabled in those two cases.

The drawing is a skeleton, not a real code: it is generated by `ladyqr_skeleton_path()` in `index.php` and deliberately cannot be scanned. It is drawn at 16 % opacity in `--muted`, which is fine because it is decoration; the message sits on a `--bg` label so it keeps its text contrast.

**Warning.** A small line under the QR preview with a triangle icon, shown only when needed and never blocking anything. One element, three texts:
- Low contrast: contrast ratio of 4:1 or less between modules and background.
- Inverted colors: modules lighter than the background, because many scanners fail on those.
- Both, when both are true.

**Download.** The big button saves a 500 px PNG. The arrow opens a menu with PNG 1000 px and SVG (vector). The menu closes on outside click, on Escape and after choosing an option.

**Help.** A circular question-mark button at the top right of the page. On hover or keyboard focus it widens into a pill that reads "Help". Clicking it opens a modal `<dialog>` with an "About" text, instructions and a note on PNG versus SVG. It closes with the × button, Escape or a click outside.

**Footer.** The ladybug icon and "Made with LadyQR, get yours free", linking to the project. It is how the project spreads, so keep it small and unobtrusive.

## Behavior

- The QR code is rebuilt on every input event. Rendering is cheap enough that no debounce is needed.
- Text is never stored. Settings (level, margin, colors) are saved to `localStorage` under `ladyqr-settings`, validated when read, and the editor works if storage is blocked or corrupt.
- Download file names: `qrcode-500.png`, `qrcode-1000.png` and `qrcode.svg`.
- PNG output is exactly the requested size. Module edges are rounded to whole pixels, so modules differ in width by at most one pixel but stay sharp.

## Accessibility

- **Contrast:** see [Measured contrast](#measured-contrast).
- **Keyboard:** everything is reachable and operable with the keyboard. Focus is a 2 px `--accent` outline with an offset, on every button, summary and input. The text field shows focus with its border color. Escape closes the download menu and the dialog.
- **Screen readers:** the text field has an `aria-label`; the QR preview is `role="img"` with a label and `aria-live="polite"`; the warning is `role="status"`; the split button's arrow has an `aria-label` and `aria-expanded`; decorative icons are `aria-hidden`. The help button has a label even when it shows only the icon.
- **Touch:** nothing depends on hover. The help pill is also reachable as a plain icon button on touch screens. Controls are at least 36 px tall.
- **Motion:** transitions are short, and are disabled under `prefers-reduced-motion`.
- **Color scheme:** `color-scheme` is declared for both themes, so native controls follow.
- **Zoom and layout:** sizes are in `rem` and the layout reflows to one column; there is no horizontal scrolling on a phone.

## Internationalization

- The interface is translatable (see the roadmap). Do not size anything to the English text: Spanish and other languages are often 20 to 30 % longer. Let text wrap and let buttons grow.
- `<html lang>` follows the configured language. Right-to-left languages are not supported yet.
- Every visible string goes through `__()` and friends; strings the JavaScript needs are passed in a JSON block in the page.

## Technical constraints

- The CSP is `default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'none'`, so: no inline `<script>` or `<style>`, no `style=""` attributes in the HTML, no external fonts, images or scripts, and no network calls. Styles set from JavaScript are fine, and the page uses attributes, not inline styles, for the SVG it builds.
- Assets are same-origin and carry a `?v=<file modification time>` query so browsers pick up changes.

## Brand assets

- `assets/icon-128.png`: the ladybug with a QR code on its back, 128 × 128 px. Used as favicon, in the help dialog, in the footer and as the project's icon in Guppy.
- `assets/icon-full.png`: the full-size original.

## Admin

The admin pages (`/admin/`) reuse the editor's tokens, card, footer and themes, and add `assets/css/admin.css`. They have no JavaScript. The page is one centered card with the ladybug and "LadyQR admin", then the content of the state it is in:

- **Locked:** a heading, a short explanation, a code block with the two lines to add to the configuration file, and how the password is secured. It does not show the server's paths, because anyone can see this page.
- **Login:** a "Log in" heading, an optional notice that the session expired, an optional error, a user field, a password field, a notice when the connection is not HTTPS, and one wide button. A wrong attempt shows the same message whatever was wrong.
- **Panel:** "You are logged in" (or "Short links" when the login is off), the result of securing the password if it just happened, a placeholder for what comes next, and a "Log out" button.
- **Open:** the panel with a red banner above the card, permanently, saying that no login is required and that anyone who can reach the page can change the links. It is the loudest element on any page of the project on purpose.

The message colors come from the existing tokens, so their contrast is already measured: error text is `--accent` on `--surface` (4.95 light, 4.71 dark), warnings are `--warn` on `--surface` (5.58 and 9.29), information is `--muted` (5.48 and 6.34), and the banner is `--accent-ink` on `--accent` (4.95 and 5.08). Messages with a role of `alert` or `status` are announced by screen readers. The form fields have the same 3:1 `--border` as the editor's controls, and their labels are real `<label>` elements.

The admin is not linked from anywhere else, and every state carries `noindex` (see the README).

## Website

The landing page at https://ladyqr.nilovelez.com/ (`site/index.html` and `site/site.css`) uses the same tokens, system fonts and contrast rules as the editor, and the same two themes. Differences:

- It has no JavaScript and its own `<meta>` CSP, because GitHub Pages cannot send headers.
- Layout: a header with the name and links, a hero with the headline and a QR code that points to the site itself, a grid of six features, a screenshot of the editor (a light and a dark version, chosen with `prefers-color-scheme`), a "verify it yourself" section, a self-hosting section and the roadmap.
- The buttons follow the editor: the primary action uses `--accent`, and the secondary one uses a `--border` outline.
- The live demo under `/try/` is the real editor, built from `index.php`.

## Not designed yet

- The admin interface for the URL shortener. It should reuse these tokens and components, and have its own section here when it exists.
- A manual light/dark switch.

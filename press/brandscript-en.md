# LadyQR: BrandScript (English)

The core message of LadyQR, following the StoryBrand framework (SB7, Donald Miller). The customer is the hero; LadyQR is the guide. The website, the README, the press kit and the author's own writing should all follow this story, each in its own voice.

## One-liner

> Most free QR generators want your text, your email and, sooner or later, a subscription. LadyQR makes the code in your browser, with nothing uploaded, so it stays yours.

Shorter variants:

- "QR codes that never leave your browser." (the README's tagline)
- "Type a link. Get a QR. Nothing leaves your browser."

## Where it comes from

LadyQR was made first for the author's own projects, and then shared. Some of his big projects built around QR codes stopped working because the company in the middle changed its rules, or the service simply stopped. A printed code cannot be fixed, so the lesson was: **do not put a stranger's server between your text and your code.** The full story belongs on the author's own website (Projects section, in Spanish). Here it only gives the guide its empathy and its authority, in one or two lines, with no names and no dates.

## 1. The character (the hero)

Anyone who has to put a QR code on something real, to be printed or shared: a restaurant menu, a poster, a business card, an event, a classroom, an association. A second hero, for the README and the press kit only: the web implementer who installs it for themselves or for a client.

**What they want:** a code that works, now and in a year, without giving anything away in exchange.

## 2. The problem

- **Villain:** the generator that stands between your text and your code.
- **External:** many free generators ask for an account or an email for something trivial. Their "dynamic" codes go through their own server, with ads, tracking and a plan that expires.
- **Internal:** the fear of printing two thousand flyers and watching the code stop working. The feeling of being watched for typing a link.
- **Philosophical:** a QR code is text drawn as a picture. It should not need an account, someone else's server or a subscription.

## 3. The guide (LadyQR)

- **Empathy:** "You only wanted a QR code." And: "We have been there: codes we printed stopped working because the company in the middle changed the rules."
- **Authority:**
  - Private by construction: a strict Content Security Policy with `connect-src 'none'` makes the browser refuse any connection from the page. Anyone can check it in the developer tools, on the Network tab.
  - Its own encoder, about 450 lines with no dependencies, verified against an independent implementation in all 40 versions and the four error correction levels.
  - It runs on a free shared hosting plan of 100 MB. **1qr.es** is the proof (see below).
  - Free and open source (GPL-2.0-or-later), accessible, translatable.
  - Made by someone who needed it first.

## 4. The plan

**Process plan** (two doors):

1. *Use it:* type the link, watch the code appear, download a PNG or an SVG.
2. *Make it yours:* download the zip, upload it by FTP, open your site and let the installer do the rest.

**Agreement plan** (what removes the fear of trying it): free, no account, no ads, no analytics, no database. It remembers your settings and never the text you type. Do not take our word for it: check it.

## 5. Call to action

- **Direct:** Download the latest version.
- **Transitional:** "How do I know it's private?", the step-by-step check, and the example of 1qr.es.

## 6. Failure (what's at stake)

- A printed code that dies when someone else's subscription expires or their rules change.
- Your text sitting on a server you do not control.
- A code that does not scan, because of low contrast or inverted colors.
- Giving your email for something that should take one click.

## 7. Success

- A code in seconds, with no account and no trace.
- It works as long as the link inside it works. With the shortener, for as long as your own domain does.
- Short links on your own domain, a QR code for each one, and everything verifiable.

**Transformation (identity):** from the person who borrows a QR code generator to the person who owns one.

## The role of 1qr.es

The author's own instance is the success story and the reference implementation: a five-character domain, a free shared hosting plan, short links and smaller codes thanks to `LADYQR_QR_BASE` in uppercase. Texts link to it as an example, never as the product. Only the author touches that site.

## Using it

- Talk about the person and their problem first, then about LadyQR.
- Every text answers three questions fast: what is it, how does it make my life better, how do I get it.
- One voice per piece, same story:
  - **Website** (English): the end user. Warm and short, with a small wink now and then. It is free and open source, so it does not sound like sales copy.
  - **README** (English): technical. What it is, how it works, how to set it up, deploy, configure and update. The story only opens it.
  - **Press notes** (English): a mix of the two. The project, who it is for, use cases, requirements, and a simple technical explanation.
  - **The author's website** (Spanish, Projects section): the personal story, the motivation and what he learned.
- Keep the tone of the product: clear and calm, not hype. The name carries the character: the ladybug. Let it show in small nods at the edges (the greeting, the closing line, the end card), one or two per piece, never in the headline or in the problem.

## Claims: safe and careful

Safe, because the repository proves them:

- The page cannot send what you type anywhere (CSP, checkable).
- No account, no ads, no analytics, no database.
- Free and open source, GPL-2.0-or-later.
- The encoder is verified against an independent implementation.
- It runs on a minimal shared hosting plan.

Careful:

- **Other generators.** Never name them. Talk about "many free generators" and about what the author lived through.
- **Small codes with `LADYQR_QR_BASE`.** Most phone cameras open them as a web address, but a code should be tested before it is printed.

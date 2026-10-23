# Status

LadyQR is a privacy-oriented QR code generator and URL shortener. Milestones 0 and 1 are done: the generator works and is verified, with a public website and live demo at https://ladyqr.nilovelez.com/, and the OVH hosting has been tested (see `docs/hosting.md`). Next is milestone 2, publishing the editor on 1qr.es, which waits for the author to set up the SSL certificate of the bare domain. Then the admin login (M3) and the redirect manager (M4). The roadmap is in `docs/PLAN.md`.

## Log

Newest entries first. One per work session, brief.

- 2026-10-23: Milestone 1 done. The hosting spike was run on OVH (11 of 12 checks pass; flock does not work, http is not redirected to https, and the bare domain has no certificate yet). Findings in docs/hosting.md, decisions and next milestones in docs/PLAN.md, spike removed from the repository.
- 2026-10-23: Milestone 0 merged into main. Public website, live demo, README, DESIGN.md and the site's QR code added, and deployed with GitHub Pages at ladyqr.nilovelez.com. Next: milestone 1, the hosting spike.
- 2026-10-23: Roadmap written (`docs/PLAN.md`), `LICENSE` added, status changed to active. Milestone 0 starts next session.
- 2026-10-23: guppy/STATUS.md created. Project registered in Guppy as planned; no code yet. Renamed to LadyQR and icon added.

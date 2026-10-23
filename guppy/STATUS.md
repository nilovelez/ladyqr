# Status

LadyQR is a privacy-oriented QR code generator and URL shortener. Milestones 0 to 2 are done: the generator works and is verified, there is a public website with a live demo at https://ladyqr.nilovelez.com/, the OVH hosting has been tested (see `docs/hosting.md`), and the editor is published on the author's own domain, https://1qr.es/, over HTTPS. Next are the admin login (M3) and the redirect manager (M4). The roadmap is in `docs/PLAN.md`.

## Log

Newest entries first. One per work session, brief.

- 2026-10-24: Milestone 2 done. The editor is published on 1qr.es with a fixed .htaccess (HTTPS and www redirects), checked in production. Found that SFTP changes take 3 to 16 seconds to be seen by PHP. Next: milestone 3, the admin login.
- 2026-10-23: Milestone 1 done. The hosting spike was run on OVH (11 of 12 checks pass; flock does not work, http is not redirected to https, and the bare domain has no certificate yet). Findings in docs/hosting.md, decisions and next milestones in docs/PLAN.md, spike removed from the repository.
- 2026-10-23: Milestone 0 merged into main. Public website, live demo, README, DESIGN.md and the site's QR code added, and deployed with GitHub Pages at ladyqr.nilovelez.com. Next: milestone 1, the hosting spike.
- 2026-10-23: Roadmap written (`docs/PLAN.md`), `LICENSE` added, status changed to active. Milestone 0 starts next session.
- 2026-10-23: guppy/STATUS.md created. Project registered in Guppy as planned; no code yet. Renamed to LadyQR and icon added.

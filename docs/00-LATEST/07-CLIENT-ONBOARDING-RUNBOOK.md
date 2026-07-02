# Client onboarding runbook (1 page)

Companion to [02-INSTALL.md](02-INSTALL.md) (plugin/theme mechanics) — this page is the **agency workflow**: cloning the master site for a new client and running the magic-link onboarding.

Status: steps 4–5 describe the wizard in [2026-07-02-client-onboarding-wizard-design.md](../superpowers/specs/2026-07-02-client-onboarding-wizard-design.md) — build pending. Steps 1–3, 6–8 already work today.

## Dev steps

- [ ] **1. Clone** — set up the new domain in Hostinger, use "copy website" from the dev-domain master copy
- [ ] **2. Identify** — open the new site's wp-admin, set site name + tagline (Launchpad → Business details)
- [ ] **3. Users** — create the client's admin user(s) now, before sending anything
- [ ] **4. Send** — Launchpad → "Create and send content form" → client's email. This sends the magic link (no login needed on their end)
- [ ] **5. Wait** — client completes the wizard unattended (business info → branding → menu → payments → email → VAT/legal)

## Dev finishing pass (after client submits)

- [ ] **6. Review & import** — Client Intake → open the request → "Import submitted data"
- [ ] **7. Polish** — quick pass on colours/fonts client already chose; confirm connected payment + email accounts actually work; review the auto-drafted VAT/legal pages before publishing them; spot-check the menu
- [ ] **8. Gate** — run Setup Health (must be green or only understood staging warnings) → tick every item on the Go Live checklist honestly → `View site`

## Rules

- Never type a client's payment or email password into anything — those are always "Connect" (OAuth), never a text field.
- Auto-drafted legal pages stay unpublished until step 7 is done — don't skip the review.
- If Setup Health flags a leftover staging admin account, delete it before handover.

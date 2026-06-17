# Takeaway OS Accessibility Audit

Updated: 2026-06-17

## Audit Basis

- Source review of header, drawer, contact panel, accessibility toolkit/theme helpers, modal markup, admin nav shell
- Live browser sweep of homepage, menu, checkout, tracker, and admin shell screens

## Confirmed Accessible Patterns

| Pattern | Evidence | Status |
| --- | --- | --- |
| Skip link | Present in live DOM | PASS |
| Semantic headings | Public and admin screens use real H1/H2 structure | PASS |
| Admin nav current state | `aria-current=\"page\"` used in primary and secondary shell nav | PASS |
| Admin nav labels | `aria-label=\"Takeaway OS sections\"` and secondary labels present | PASS |
| Menu filter groups | Dietary/allergen groups and nav labels exist | PASS |
| Configurator dialog semantics | Modal appears as dialog in live DOM snapshot | PASS |
| Popup dialog semantics | `TTOS_Public_UI` renders `role=\"dialog\"` and `aria-modal=\"true\"` | PASS |
| Banner region semantics | Banner uses `role=\"region\"` with label | PASS |
| Tracker / checkout form fields | Native form controls and labels present | PASS |

## Accessibility Helpers Present

| Area | Status | Notes |
| --- | --- | --- |
| Theme accessibility toolkit | PASS | `inc/accessibility.php`, `inc/amh-accessibility-toolkit.php`, `inc/amh-accessibility-universal.php` present |
| Back-to-top button | PASS | Footer helper present |
| Account/dropdown/menu close handling | PASS | `assets/js/theme.js` manages ESC/close behaviors |
| Mobile drawer behavior | PASS | Drawer logic and close interactions present |
| Admin shell focus visibility potential | PASS | CSS and link-based nav structures are healthier than old tab swapping pattern |

## Gaps / Unknowns

| Gap | Status | Notes |
| --- | --- | --- |
| Full keyboard-only walkthrough for drawer/contact/modal | UNKNOWN | Source suggests support, but this audit did not run full tab-order scripting |
| Screen-reader narration quality for configurator and upsell toggles | UNKNOWN | DOM is promising, but not fully SR-tested |
| Colour contrast under all saved client branding combinations | PARTIAL | Default token system looks deliberate; custom client branding can still create contrast risk |
| Accessibility of old placeholder content/media | PARTIAL | Content quality depends on client data entry |

## Design-Level Accessibility Read

- Admin shell unification is a real accessibility improvement because it removes the prior illusion that primary navigation had transformed into local tabs.
- The new secondary-nav pattern is easier to understand visually and structurally.
- The site relies heavily on branded surfaces and pills, so contrast and focus-state regression testing should stay part of packaging QA.

## Release View

| Area | Decision | Status |
| --- | --- | --- |
| Public browsing and ordering flows | Good baseline accessibility structure | PASS |
| Admin shell hierarchy | Clearer and safer than previous pattern | PASS |
| Full accessibility sign-off for production | Still needs dedicated keyboard + screen-reader pass | PARTIAL |

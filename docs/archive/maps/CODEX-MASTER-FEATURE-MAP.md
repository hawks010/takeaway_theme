# Takeaway OS / Takeaway Theme Master Feature Map

Updated: 2026-06-17

## Audit Basis

- Source audited from local workspace `takeaway-os/` and `takeaway-theme/`
- Staging verified at `https://takeaway.thatdeveloper.co.uk/`
- Existing project docs reviewed as secondary evidence only
- Status labels used exactly as requested

## Version And Packaging Truth

| Item | Verified value | How verified | Status |
| --- | --- | --- | --- |
| Active staging theme | `takeaway-theme 0.3.33` | `wp theme list` on staging and live browser styling match | PASS |
| Active staging plugin | `takeaway-os 1.3.11` | `wp plugin get takeaway-os --field=version` on staging | PASS |
| Source theme version | `0.3.33` | `takeaway-theme/style.css`, `takeaway-theme/functions.php` | PASS |
| Source plugin version | `1.3.11` | `takeaway-os/takeaway-os.php` header and constant | PASS |
| Bundled plugin manifest version | `1.3.11` | `takeaway-theme/inc/bundled-plugins/manifest.json`, `inc/plugin-checklist.php` | PASS |
| Local bundled plugin zip present | `takeaway-theme/inc/bundled-plugins/takeaway-os.zip` missing locally | shell check | FAIL |
| Existing docs current | older docs still mention `0.3.4` / `1.3.0` era states | docs review | PARTIAL |

Recommended package filenames:

- `takeaway-theme-v0.3.33-bundled.zip`
- `takeaway-os-v1.3.11.zip`

## Repo Map

| Path | Responsibility | Surface | Safe to change? | Notes |
| --- | --- | --- | --- | --- |
| `takeaway-os/` | Main plugin bootstrap and feature layer | Admin + frontend + checkout | Medium risk | Core business logic lives here |
| `takeaway-os/includes/` | PHP classes for admin, Woo, setup, features, content, ops | Mixed | Medium/high | Most release-critical code |
| `takeaway-os/assets/` | Admin and frontend CSS/JS | Mixed | Medium | UI regressions likely if changed casually |
| `takeaway-theme/` | Classic WP theme shell and template layer | Frontend | Medium | Public presentation and wrappers |
| `takeaway-theme/inc/` | Theme setup, helpers, performance, contact, plugin installer | Mixed | Medium | Important glue layer |
| `takeaway-theme/template-parts/` | Header, footer, home sections | Frontend | Safer than core logic | Visual regressions still possible |
| `takeaway-theme/woocommerce/` | Woo template overrides | Frontend/account | Medium | Affects account UX and Woo output |
| `takeaway-theme/inc/bundled-plugins/` | Theme-bundled plugin manifest | Packaging | High | Local plugin zip currently missing |
| `docs/` | Historical planning and handover docs | Internal | Safe | Some stale version references |
| `test-artifacts/` | Prior smoke-test evidence | Internal | Safe | Useful but not authoritative |

## Current Feature Matrix

| Feature | Status | Verified summary | Blocks packaging? | Blocks paid production? |
| --- | --- | --- | --- | --- |
| Frontend branded homepage/header/footer | PASS | Loads styled on staging with theme tokens and branded shell | no | no |
| Menu archive / ordering page | PASS | Public menu loads, category nav works, configurator triggers present | no | no |
| Configurator modal | PASS | Modal opens on staging with options, note, quantity, total | no | no |
| Smart modal upsells | PASS | Suggested extras render inside modal and source logic supports add-as-separate-item | no | no |
| Basket and checkout UI | PASS | Basket page and checkout page render; checkout exposes fulfilment control | no | no |
| Order tracker | PASS | Tracker page exists and renders form flow | no | no |
| Rewards / loyalty UI | PARTIAL | Rewards page renders, but depends on real order history and points activity | no | no |
| Admin shell unification | PASS | Site Content now preserves primary nav; Features uses secondary nav; Setup Health primary-only | no | no |
| Setup Health | PASS | Screen renders with checks and repair actions; staging currently shows `80% complete` | no | no |
| Orders / kitchen / customers / reports screens | PASS | Pages render in admin shell without console warnings in sweep | no | no |
| Cuisine field storage | PASS | Cuisine exists in settings and saves to `ttos_settings[business][cuisine]` | no | no |
| Cuisine-driven homepage eyebrow | PASS | Hero eyebrow reads cuisine label | no | no |
| Cuisine-driven starter menu/profile generation | FAIL | Current onboarding/starter products remain generic and hard-coded | no | yes |
| Starter menu quality | PARTIAL | Starter data exists, but staging currently shows duplicate menu items/content | no | yes |
| Payment readiness | NEEDS STAGING CONFIG | Woo pages exist, but live payment proof was intentionally not completed | no | yes |
| SMTP / transactional email readiness | NEEDS STAGING CONFIG | Plugin presence known, but live send reliability not fully reverified in this pass | no | yes |
| Packaging readiness | PARTIAL | Source versions are aligned, but local bundled plugin zip is missing | yes | no |

## Key Known Gaps

| Gap | Status | Evidence | Impact |
| --- | --- | --- | --- |
| Cuisine type does not drive seeded categories/products | FAIL | `TTOS_Onboarding::apply_profile()` + `TTOS_Production::apply_starter_menu()` use generic seeded menu | New installs do not feel cuisine-aware |
| Starter/demo products appear duplicated on staging | RISK | Live menu sweep showed repeated burgers, drinks, kebabs, pizzas, sides | Pollutes smoke tests and weakens client demos |
| Placeholder business/social content remains on staging | NEEDS CLIENT SETUP | Homepage and footer still show test email, placeholder socials, demo hygiene/Google links | Not client-ready |
| Local bundled plugin zip missing | FAIL | `takeaway-theme/inc/bundled-plugins/takeaway-os.zip` absent locally | Prevents trustworthy final package assembly |
| Live payments and SMTP not fully signed off | NEEDS STAGING CONFIG | Guardrails intentionally avoided production payment/email activation | Paid production not ready |
| Non-admin role browser verification unavailable | UNKNOWN | No owner/manager staging login available in this audit | Capability gating preserved in code, but not live-proved here |

## Future Roadmap Items Confirmed As Not Complete

| Item | Status | Notes |
| --- | --- | --- |
| Full cuisine-aware Starter Builder | FUTURE ROADMAP | Current system only stores cuisine label and uses generic starter seeding |
| Reservations | FUTURE ROADMAP | Not found in audited code paths |
| Full POS / printer / EPOS live connectors | FUTURE ROADMAP | Settings and retry/log infrastructure exist, but not live-finished integrations |
| SMS live delivery | FUTURE ROADMAP | Settings exist; no production sign-off |
| QR ordering / multi-location | FUTURE ROADMAP | Module flags exist but no finished public flow found in this audit |

## Overall Read

Takeaway OS is no longer a concept build. It is a functioning theme/plugin product with a real branded ordering journey, admin cockpit, setup health system, menu configurator, rewards surface, operations layer, and reporting shell.

The main release truth is this:

- The core shell and direct-order UX are real
- The admin shell unification work is real and live on staging
- The smart upsell flow is real
- The packaging and onboarding story are not fully clean yet
- Paid production is still blocked by client/staging setup, duplicate demo content cleanup, and missing packaging completeness

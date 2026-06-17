# Takeaway OS Admin Map

Updated: 2026-06-17

## Staging Sweep Summary

Live browser verification covered:

- Dashboard
- Site Content
- Features
- Setup Health
- Orders
- Kitchen
- Customers
- Reports

Observed result:

- Shared primary nav visible on audited admin screens
- `Site Content` now keeps primary nav and adds secondary tabs underneath
- `Features` uses secondary section links
- `Setup Health`, `Orders`, `Kitchen`, `Customers`, `Reports` stay primary-only
- No console warnings/errors returned in the sweep

## Screen Map

| Screen | Status | Menu slug | Capability | Shared shell | Secondary nav | Verified notes |
| --- | --- | --- | --- | --- | --- | --- |
| Dashboard | PASS | `takeaway-os` | `ttos_access` | yes | no | H1 `Restaurant cockpit` |
| Launchpad | PASS | `takeaway-os-launchpad` | `ttos_manage_settings` | yes | no | Source verified; not revisited in live sweep this pass |
| Setup Health | PASS | `takeaway-os-setup-health` | `ttos_manage_settings` | yes | no | Live `80% complete` state on staging |
| Menu Builder | PASS | `takeaway-os-menu` | `ttos_manage_menu` | yes | no | Source verified; not revisited live in this pass |
| Orders | PASS | `takeaway-os-orders` | `ttos_view_orders` | yes | no | Live cockpit screen opened |
| Kitchen Screen | PASS | `takeaway-os-kitchen` | `ttos_view_orders` | yes | no | Live kitchen display screen opened |
| Customers | PASS | `takeaway-os-customers` | `ttos_view_reports` | yes | no | CRM/campaign screen opened |
| Reports | PASS | `takeaway-os-reports` | `ttos_view_reports` | yes | no | Reporting dashboard opened |
| Business Settings | PASS | `takeaway-os-settings` | `ttos_manage_settings` | yes | no | Source verified |
| Payments | PASS | `takeaway-os-payments` | `ttos_manage_settings` | yes | no | Source verified |
| Delivery | PASS | `takeaway-os-delivery` | `ttos_manage_settings` | yes | no | Source verified |
| Add-ons | PASS | `takeaway-os-modules` | `ttos_modules` | yes | no | Source verified |
| Features | PASS | `takeaway-os-features` | `ttos_modules` | yes | yes | Secondary nav shows Matrix, Meal deals, Loyalty, etc. |
| Site Content | PASS | `takeaway-os-site-content` | `ttos_manage_settings` | yes | yes | Primary active `Site Content`; secondary active `Homepage` |
| Operations | PASS | `takeaway-os-operations` | `ttos_manage_settings` | yes | no | Source verified |
| Go Live | PASS | `takeaway-os-golive` | `ttos_manage_settings` | yes | no | Source verified |
| Production Tools | PASS | `takeaway-os-production` | `ttos_manage_settings` | yes | yes | Source indicates section links are rendered as secondary nav |

## Primary Navigation Truth

Source of truth now lives in:

- `takeaway-os/includes/class-admin-shell.php`

Primary items currently defined there:

- Dashboard
- Launchpad
- Setup Health
- Menu
- Orders
- Kitchen
- Customers
- Reports
- Features
- Site Content
- Settings
- Payments
- Delivery
- Add-ons
- Operations
- Go Live
- Production

Capability gating is preserved per item using the same per-screen caps such as:

- `ttos_access`
- `ttos_manage_settings`
- `ttos_manage_menu`
- `ttos_view_orders`
- `ttos_view_reports`
- `ttos_modules`

Status: PASS

## Secondary Navigation Truth

| Screen family | Status | Notes |
| --- | --- | --- |
| Site Content | PASS | Secondary row for Homepage, Menu Page, Business Info, Opening Times, Delivery & Collection, Contact & Map, Reviews, Offers, Social Links, Footer, Policies, Banner, Popup, Export / Import |
| Features | PASS | Secondary section row visible and visually lighter than primary |
| Production | PASS | Source shows shared secondary-nav renderer used for section links |
| Setup Health | PASS | Correctly no invented secondary row |
| Orders / Kitchen / Customers / Reports | PASS | Correctly primary-only |

## Admin Screen Risks

| Risk | Status | Notes |
| --- | --- | --- |
| Role-based browser verification incomplete | UNKNOWN | No owner/manager staging login was available |
| `TTOS_Admin` remains large | RISK | High change blast radius for future work |
| Setup Health still reports 80% | PARTIAL | Product shell works, but staging config is not fully complete |

## Screens That Need Client Or Staging Completion

| Screen | Status | Why |
| --- | --- | --- |
| Setup Health | NEEDS STAGING CONFIG | Warnings/failures remain on staging |
| Payments | NEEDS STAGING CONFIG | Live gateway sign-off intentionally not performed |
| Go Live | NEEDS CLIENT SETUP | Final launch checklist depends on real client completion |

# Reservation + CRM System — Design Spec

**Date:** 2026-06-15  
**Version:** 1.1 (post-review corrections)  
**Plugin:** `takeaway-os` v1.4.0 MVP + v1.4.1 (deposits)  
**Theme:** `takeaway-theme` v0.4.0

---

## 0. Release Roadmap

This work is **explicitly not part of v1.3.0**. v1.3.0 must finish first:
- Public front end, menu, Woo pages, utility/policy pages
- Banner/popup, Setup Health, admin polish
- Final QA and packaging

```
v1.3.0  Core takeaway product (current sprint)
v1.4.0  Reservations MVP (this spec)
v1.4.1  Deposits + payment expiry + deeper capacity rules
v1.5.0+ Table service / mini POS (out of scope for this spec)
```

---

## 1. Scope

Covers **Zone 1 (Reservations)** and **Zone 3 (Unified Customer Records)**.

Explicitly out of scope: Zone 2 (live table service / mini POS).

### v1.4.0 MVP delivers

- Toggle-able reservation booking form (`[ttos_booking_form]` shortcode)
- Availability slots — configurable interval, window, party size, capacity
- Manual and auto-confirmation rules
- Customer record linked to existing activity (orders, bookings, notes, VIP, allergens)
- Staff operational dashboard (Mode A — full-screen, WP-agnostic)
- Manager settings (Mode B — embedded in WP admin)
- My Account "My Bookings" endpoint
- Automated emails (confirmation, reminder, cancellation)
- Configurable terminology, cancellation window, capacity display
- Secure guest cancellation link (no WC account required)
- Two custom WP roles: `ttos_manager`, `ttos_staff`

### Deferred to v1.4.1

- Deposits via WooCommerce (payment, refunds, partial refunds, abandoned checkout, slot hold, expiry)
- Kitchen ticket tag on "seated" status

### Not in scope (v1.5+)

- Table service / mini POS / live order management

---

## 2. Unified Customer Record

### Design principle

There must be **one customer profile** per unique person. The `ttos_customer` CPT is that record. It is not a parallel CRM — it is the single source of truth that all activity types plug into.

```
ttos_customer (one record per unique email)
├── activity: orders (WC orders matched by email or user ID)
├── activity: reservations (ttos_reservation posts linked by customer_id)
├── activity: no-shows (counted on customer record)
├── activity: cancellations (counted on customer record)
├── notes (private staff notes)
├── VIP flag
└── allergens / preferences
```

When WooCommerce orders exist for the same email, they are surfaced from the customer record view — the customer record does not duplicate the order data, it links to it. This prevents parallel CRM spaghetti.

### 2.1 `ttos_customer` CPT

One record per unique email address. Created automatically on first booking; subsequent bookings update it. WC order matching happens on save (by email → `wp_users` → WC orders).

**CPT registration flags:**
```php
'public'              => false,
'show_ui'             => false,   // no default WP list table
'exclude_from_search' => true,
'show_in_rest'        => false,
```

| Meta key | Type | Description |
|---|---|---|
| `_ttos_email` | `string` | Unique email (identity key) |
| `_ttos_phone` | `string` | Phone number |
| `_ttos_wc_user_id` | `int` | → WP user ID if linked (0 if guest) |
| `_ttos_is_vip` | `bool` | VIP flag — shown as alert in staff dashboard |
| `_ttos_allergens` | `string` | Free-text dietary / allergy notes |
| `_ttos_staff_notes` | `string` | Private staff notes |
| `_ttos_booking_count` | `int` | Total lifetime bookings (denormalised) |
| `_ttos_noshow_count` | `int` | Total no-shows (denormalised) |
| `_ttos_cancel_count` | `int` | Total cancellations (denormalised) |
| `_ttos_last_booking` | `string` (Y-m-d) | Date of most recent reservation |
| `_ttos_first_seen` | `string` (Y-m-d H:i:s) | Date of first contact |

`post_title` = customer display name (`First Last`).  
`post_status` = `private` (not `publish` — customer records are never publicly accessible).

### 2.2 `ttos_reservation` CPT

One record per booking. `post_title` = human-readable reference (e.g. "Thompson × 4 — 22 Jun 7:30 pm").

**CPT registration flags:**
```php
'public'              => false,
'show_ui'             => false,
'exclude_from_search' => true,
'show_in_rest'        => false,
```

`post_status` = `private` always. Operational status tracked in `_ttos_status`, not `post_status`.

| Meta key | Type | Description |
|---|---|---|
| `_ttos_date` | `string` (Y-m-d) | Booking date |
| `_ttos_time` | `string` (H:i) | Booking time |
| `_ttos_covers` | `int` | Party size |
| `_ttos_status` | `string` | See §3 Status lifecycle |
| `_ttos_customer_id` | `int` | → `ttos_customer` post ID |
| `_ttos_wc_user_id` | `int` | → WP user ID (0 if guest) |
| `_ttos_table` | `string` | Table name / number (optional, future use) |
| `_ttos_source` | `string` | `online` or `staff` |
| `_ttos_notes` | `string` | Customer's special requests |
| `_ttos_cancel_token` | `string` | Secure random token for guest cancellation link |
| `_ttos_email_log` | `array` | `[{type, recipient, timestamp, success, message_id}]` — no full bodies |

**Future-safe fields** (not implemented in v1.4.0, reserved for v1.4.1+):
`_ttos_deposit_required`, `_ttos_deposit_amount`, `_ttos_wc_order_id`

**Availability note:** Date/time queries via postmeta can slow down at high volume. CPT is acceptable for v1.4.0 MVP. Custom DB tables should be evaluated for v2 if booking volumes grow significantly.

---

## 3. Reservation Status Lifecycle

```
pending → confirmed → seated → completed
pending → cancelled
confirmed → cancelled
confirmed → no-show
```

**Auto-confirm rule:** Bookings with covers ≤ N (default 6, configurable) skip `pending` and land directly in `confirmed`.

| Status | Colour | Who can set it |
|---|---|---|
| `pending` | Amber | System (on create) |
| `confirmed` | Green | System (auto) or staff |
| `seated` | Blue | Staff (mark arrived) |
| `completed` | Grey | WP cron (midnight after booking date) or staff |
| `cancelled` | Red | Customer (via My Bookings or email cancel link, within cancellation window) or staff |
| `no-show` | Pink | Staff only |

---

## 4. Custom WP Roles

Two roles ship with the plugin. Registered on activation; see §4.1 for uninstall behaviour.

| Role slug | Display name | Access |
|---|---|---|
| `ttos_manager` | Manager | Full Mode A: reservations, CRM records, email log, reports, settings |
| `ttos_staff` | Staff | Mode A limited: view bookings, create bookings, update status (arrive/no-show) |

### 4.1 Granular capabilities

| Capability | administrator | ttos_manager | ttos_staff |
|---|---|---|---|
| `ttos_view_reservations` | ✓ | ✓ | ✓ |
| `ttos_create_reservations` | ✓ | ✓ | ✓ |
| `ttos_update_reservation_status` | ✓ | ✓ | ✓ |
| `ttos_cancel_reservations` | ✓ | ✓ | — |
| `ttos_manage_reservations` | ✓ | ✓ | — |
| `ttos_view_customers` | ✓ | ✓ | — |
| `ttos_manage_customers` | ✓ | ✓ | — |
| `ttos_view_reports` | ✓ | ✓ | — |
| `ttos_manage_settings` | ✓ | ✓ | — |
| `read` | ✓ | ✓ | ✓ |

`ttos_manager` and `ttos_staff` do **not** get `manage_options`. Login redirects to the Mode A dashboard via `login_redirect` hook.

### 4.2 Uninstall behaviour

```
On uninstall:

If "preserve data" (default):
  - Remove role objects and capabilities
  - Leave ttos_reservation and ttos_customer post records intact
  - Leave reservation settings in wp_options intact
  - Staff/manager users are reassigned to subscriber role

If "erase all data" (user must explicitly opt in):
  - Remove roles and capabilities
  - Delete all ttos_reservation posts and meta
  - Delete all ttos_customer posts and meta
  - Delete all reservation/CRM wp_options keys
```

**Never erase booking or customer records unless the owner explicitly opts in to clean erase.** This avoids GDPR/record-keeping issues for businesses with past reservation data.

---

## 5. Rendering Modes

### Mode A — Full-screen operational dashboard

URL: `/wp-admin/admin.php?page=ttos-dashboard`

WP chrome (admin bar, sidebar) hidden via CSS on `ttos-mode-a` body class. Login redirect via `login_redirect` hook for `ttos_manager` and `ttos_staff`.

**Screens:**
- Today's reservations — sorted by time (default)
- Reservation detail panel — slide-in from booking card
- Customer profile panel — slide-in from reservation detail (manager only)
- New reservation form — modal overlay
- Reports — manager only; covers per day/week, cancellation rate, no-show rate, top customers by booking count; date-range filterable
- Settings — manager only; full-screen render of the same settings as Mode B

### Mode B — Embedded in WP admin

Standard WP admin page. WP chrome visible. For `administrator` access to plugin settings via sidebar.

Both modes share `ttos-admin.css` and `ttos-admin.js`.

---

## 6. CSS / JS Architecture

### `ttos-admin.css`

Single stylesheet for both modes. Loaded on all `ttos_*` admin pages.

Design tokens:
```css
:root {
  --ttos-bg:       #1c1c1e;
  --ttos-surface:  #2c2c2e;
  --ttos-border:   rgba(255,255,255,.08);
  --ttos-primary:  #ff4000;
  --ttos-text:     #f5f5f7;
  --ttos-muted:    #8e8e93;
  --ttos-success:  #34c759;
  --ttos-warning:  #ff9500;
  --ttos-error:    #ff3b30;
  --ttos-info:     #0a84ff;
}
```

### `ttos-admin.js`

Vanilla JS only. No framework, no build step. Progressively enhanced — page renders useful HTML without JS; JS adds slide-in panels, status action buttons, modal forms. No Vue or other framework.

---

## 7. CRM Settings Tab (Reservations)

New tab in `TTOS_Site_Content`. Additive only — existing installs unaffected.

| Section | Key options |
|---|---|
| Master switch | Enable reservations (default **OFF**); show "My Bookings" in My Account; allow guest bookings |
| Terminology | Singular/plural noun for "Reservation"; guest noun; CTA button text |
| Availability | Time slot interval; earliest bookable (from now); booking window ahead |
| Party size & confirmation | Min/max party size; auto-confirm threshold |
| Capacity | Max covers per slot (0 = no limit); availability display style |
| Booking form fields | Special requests, dietary, occasion, marketing opt-in (phone always shown) |
| Cancellation policy | Allow customer cancellations: yes/no; cancellation window in hours (e.g. 24h) |
| Email notifications | From name; reply-to; staff notification email(s); reminder timing; which emails to send |
| Customer records | Enable CRM; WC account linking; VIP flag; allergen field; VIP badge in staff view |

**Master switch defaults to OFF.** Existing pure-takeaway sites are not affected by the update — they must explicitly enable reservations.

All other settings have sensible defaults so a new client can go live with minimal configuration.

---

## 8. Availability Model (v1.4.0)

v1.4.0 implements the core availability model. The data model is designed to accommodate future additions without breaking changes.

**Implemented in v1.4.0:**
- Time slot interval (15 min / 30 min / 1 hour)
- Booking window (earliest, latest)
- Party size min/max
- Max covers per slot

**Reserved for v1.4.1+ (data model leaves space, not yet implemented):**
- Buffer time between slots
- Different capacity by day of week
- Blackout dates / holiday closures
- Special opening times
- Max bookings per slot (distinct from max covers)
- Table/area labels
- Last bookable time before service

---

## 9. Public Booking Form

Shortcode: `[ttos_booking_form]`

Fields: date picker → time slots → party size → name / email / phone → special requests → optional fields (dietary, occasion if enabled) → marketing opt-in → confirmation.

No deposit step in v1.4.0. Deposits deferred to v1.4.1.

On submit:
- Creates `ttos_reservation` (`post_status = private`)
- Creates or updates `ttos_customer` (matched by email)
- Generates `_ttos_cancel_token` (random, stored on reservation — used in guest cancel link)
- Links to WC user account if email matches existing account
- Sends booking received / confirmation email to customer
- Sends new booking notification to staff

---

## 10. My Account Integration

Endpoint: `/my-account/my-bookings/` — only shown when reservations are enabled.

**Content:**
- Upcoming reservation highlight (next confirmed booking)
- Full booking history with status badges
- Cancel button on `pending` / `confirmed` bookings within configurable cancellation window
- New booking link

### Guest bookings

Guests receive a secure cancel link in their confirmation email: `/cancel-booking/?token={cancel_token}`. Token is a random string stored on the reservation. Expired or already-cancelled tokens show a polite error. No WC account required.

### Customer frontend design

No WP branding. Uses theme CSS tokens. Greeting banner + pill tabs (My Bookings, Orders, Addresses, Account details) — matches approved `my-account.html` mockup. `woo.css` overrides carry over from v0.3.4.

---

## 11. Email System

Sent via `wp_mail()` using a branded HTML template.

| Trigger | Recipient | Notes |
|---|---|---|
| Booking received (pending) | Customer | Includes secure cancel link |
| Booking confirmed | Customer | Restaurant address included |
| Reminder | Customer | Action Scheduler job; timing configurable |
| Booking cancelled | Customer | — |
| New booking | Staff | Includes admin link |
| Cancellation | Staff (optional) | — |

**Reminder scheduling:** Uses WooCommerce Action Scheduler (already bundled with WC) — not raw WP-Cron. Scheduled on booking create/confirm, cancelled if booking is cancelled before firing.

**Email log per reservation** (`_ttos_email_log` array):
```
[{type, recipient, timestamp, success (bool), message_id}]
```
No full email bodies stored. Minimises personal data retained.

---

## 12. WooCommerce Connections (v1.4.0)

| Connection | How |
|---|---|
| My Bookings tab | Custom WC endpoint; visible when reservations enabled |
| Account linking | Match email to `wp_users` on booking save; set `_ttos_wc_user_id` on customer record |
| Order surfacing | Customer profile view surfaces WC orders matched by email — no data duplication |

Deposit WC order integration deferred to v1.4.1.

---

## 13. File Structure (new files)

```
takeaway-os/
  includes/
    class-reservation-cpt.php       # CPT registration + meta (reservation)
    class-customer-cpt.php          # CPT registration + meta (customer)
    class-reservation-email.php     # Email dispatch + Action Scheduler jobs
    class-booking-form.php          # Shortcode + AJAX handlers
    class-roles.php                 # Role registration / removal / uninstall
    class-staff-dashboard.php       # Mode A page + AJAX endpoints
    class-myaccount-bookings.php    # WC My Account endpoint
    class-guest-cancel.php          # Guest cancellation token handler
  assets/
    css/ttos-admin.css              # Mode A + B unified design system
    js/ttos-admin.js                # Dashboard interactivity (vanilla JS)

takeaway-theme/
  assets/css/woo.css                # My Account styles (already in v0.3.4+)
  templates/myaccount/
    my-bookings.php                 # WC endpoint template
    my-bookings-upcoming.php        # Upcoming reservation partial
    my-bookings-item.php            # Booking list row partial
```

---

## 14. Out-of-Box Defaults

Master switch is **OFF** by default. Enabling it activates:

- Guest bookings: on
- My Bookings in My Account: on
- Time slot: 30 min, earliest: 2 hours ahead, window: 3 months
- Party size: 1–20, auto-confirm ≤ 6
- No capacity limit
- Fields: name, email, phone, special requests (all on by default)
- Customer cancellations: on, window: 24 hours
- All customer emails: on; staff notification: on
- CRM: on, WC linking: on, VIP flag: on, allergen field: on

---

## 15. Version Target

```
takeaway-os  v1.4.0   Reservations MVP (this spec)
takeaway-os  v1.4.1   Deposits + deeper capacity
takeaway-theme v0.4.0  My Account styles + guest cancel page
```

"Enable reservations" master switch defaults OFF. Existing takeaway-only sites update safely with no visible change.

# Takeaway Testing

## Smoke Checklist

- Activate the bundled theme and confirm the setup popup appears automatically.
- Confirm Takeaway OS installs, activates, or updates correctly from the theme setup screen.
- Confirm Launchpad opens after a fresh install or activation.
- Install/activate WooCommerce from Launchpad.
- Apply the Takeaway WooCommerce profile.
- Run `Takeaway OS -> Setup Health`.
- Confirm Home is assigned as the static front page.
- Confirm Menu, Basket, Checkout, and My Account are assigned to WooCommerce.
- Confirm header and footer menus exist and are assigned.
- Confirm Home renders the styled theme front page.
- Confirm Menu shows products.
- Confirm Basket, Checkout, Order Tracker, Allergens, Delivery Checker, Meal Deals, and Rewards pages are not blank.
- Confirm a newer installed Takeaway OS is not downgraded by the bundled theme.
- Confirm Setup Health clearly separates staging/manual payment readiness from Stripe/live payment readiness.
- Confirm Setup Health clearly warns when SMTP is active but not configured.

## Browser / Screenshot Checklist

- Theme activation popup
- Theme setup page
- Takeaway OS Launchpad
- Setup Health
- Homepage desktop
- Homepage mobile
- Menu desktop
- Menu mobile
- Basket
- Checkout
- Takeaway Tickets
- Order cockpit
- CRM dashboard
- Reports dashboard

## WooCommerce Checkout Test

1. Create or use starter products.
2. Add a configurable item to the cart.
3. Confirm required options validate.
4. Confirm option pricing changes the basket total.
5. Confirm checkout shows fulfilment and requested time fields.
6. Use a safe manual/test gateway and place a staging order.
7. Confirm the order appears in WooCommerce and the Takeaway order views.
8. Use `wp wc shop_order list` for WP-CLI order checks on HPOS stores. Do not rely on `wp post list --post_type=shop_order`.

## Takeaway Tickets / Order Test

- Open `Takeaway OS -> Orders`.
- Open `Takeaway OS -> Takeaway Tickets`.
- Open the full-screen ticket board when needed.
- Change an order status and prep time.
- Confirm the order state updates cleanly.

## CRM / Reports Test

- Open `Takeaway OS -> Customers`.
- Open `Takeaway OS -> Reports`.
- Confirm neither screen fatals when WooCommerce orders exist.

## Plugin Update Test

1. Install an older `takeaway-os` plugin.
2. Activate `takeaway-theme-v0.3.33-bundled.zip`.
3. Open the theme setup page.
4. Confirm the status shows `Update available`.
5. Click `Update bundled Takeaway OS`.
6. Confirm the plugin version becomes `1.3.11`.
7. Confirm data/settings remain intact.

## Payment / Email Beta Checks

- Stripe plugin installed and active.
- Stripe mode and enabled/connected status visible in Setup Health.
- BACS/manual staging gateway available for no-charge browser orders.
- FluentSMTP or equivalent SMTP plugin active.
- SMTP configured if credentials are available.
- WooCommerce new-order email is enabled.
- Admin email and WooCommerce from-name/from-address are valid.

## Production Gating

- Do not mark paid production safe until live Stripe/card payment capture is verified.
- Do not mark paid production safe until a real order email has been delivered to the expected inbox.
- Keep BACS/manual payment described as staging-only unless the client explicitly wants it live.

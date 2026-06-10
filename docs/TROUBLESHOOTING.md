# Takeaway Troubleshooting

## Popup did not appear

- Open `Appearance -> Takeaway Theme Setup`.
- Confirm the current user can install/activate plugins.
- Reset Launchpad progress from `Takeaway OS -> Setup Health` if you want the guided prompt again.

## Bundled plugin ZIP missing

- Check `[takeaway-theme/inc/bundled-plugins/takeaway-os.zip](/Users/sonny-work/Documents/Takeaway theme/takeaway-theme/inc/bundled-plugins/takeaway-os.zip)`.
- Rebuild the theme zip so the bundled plugin is included.

## Plugin says installed but old version remains

- Open the theme setup page.
- Confirm the status reads `Update available`.
- Click `Update bundled Takeaway OS`.
- If the update fails, check filesystem credentials/permissions on the host.

## WooCommerce missing

- Install/activate it from Launchpad.
- Re-open Setup Health afterward and re-run assignments.

## Pages blank

- Open `Takeaway OS -> Setup Health`.
- Run `Repair pages and menus`.
- Use `Replace content repair` only when you intentionally want to overwrite Takeaway-managed page content.

## Homepage not assigned

- Run `Assign homepage and WooCommerce pages` from Setup Health.
- Confirm `Settings -> Reading` uses a static front page afterward.

## Checkout blank

- Confirm WooCommerce is active.
- Confirm the Checkout page exists and is assigned in Setup Health.
- Re-run `Apply Takeaway WooCommerce profile` if needed.

## Header / footer missing

- Run `Repair pages and menus`.
- Confirm the primary and footer menu locations are assigned in Setup Health.

## Menu empty

- Run `Create starter content` or add real WooCommerce products in Menu Builder.
- Confirm products are published and not all hidden/sold out.

## Payment gateway not connected

- Open `WooCommerce -> Settings -> Payments`.
- Enable a safe test/manual gateway for staging.
- Re-run the Setup Health payment checks.
- Stripe disabled in test mode is acceptable for staging if BACS/manual order testing is enabled.
- Paid production still needs a real live-payment verification pass.

## Email plugin active but warning remains

- Open FluentSMTP or the configured SMTP plugin.
- Add and verify the SMTP/API connection.
- Confirm WooCommerce new-order emails are enabled.
- Re-run the Setup Health email test.
- Paid production still needs confirmed delivery to the expected inbox.

## WP-CLI cannot find orders

- On HPOS stores, use `wp wc shop_order list --user=<admin-id>`.
- Do not rely on `wp post list --post_type=shop_order` for order verification.

## Admin locked out recovery

- Log in as a full WordPress Administrator.
- Disable client mode from `Takeaway OS -> Operations` if needed.
- If the Takeaway theme/plugin is unavailable, switch themes or deactivate plugins with WP-CLI or the host file manager.

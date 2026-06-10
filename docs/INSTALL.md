# Takeaway Install

1. Upload `takeaway-theme-v0.2.6-bundled.zip` in WordPress under `Appearance -> Themes -> Add New -> Upload Theme`.
2. Activate the theme.
3. Confirm the Takeaway setup popup appears automatically after activation.
4. Click the Takeaway OS action button:
   - `Install Takeaway OS` when missing
   - `Activate Takeaway OS` when already installed but inactive
   - `Update bundled Takeaway OS` when the bundled copy is newer
5. After Launchpad opens, install and activate WooCommerce from the `Required plugins` step.
6. Click `Apply Takeaway WooCommerce profile`.
7. Open `Takeaway OS -> Setup Health`.
8. Use `Repair pages and menus` and `Assign homepage and WooCommerce pages` if any checks fail.
9. Use `Create starter content` if you want demo menu items for preview/testing.
10. Click `View site` once the core checks are green.

Notes:
- The bundled theme never downgrades a newer Takeaway OS install.
- The bundled plugin installs as `wp-content/plugins/takeaway-os/`.
- Use `Replace content repair` only when you intentionally want to overwrite Takeaway-managed page content.
- Use BACS/manual gateways only for staging tests unless Stripe is explicitly in test mode.
- Paid production requires verified live Stripe/card payment capture and confirmed order email delivery.
- Setup Health should be green or have only understood staging warnings before client handover.

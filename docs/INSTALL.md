# Takeaway Install

1. Upload `takeaway-theme-v0.3.0-bundled.zip` in WordPress under `Appearance -> Themes -> Add New -> Upload Theme`.
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


## v1.3.0 notes

### Update from 1.2.5
1. Upload/activate takeaway-theme v0.3.0-bundled.
2. Appearance -> Takeaway Theme Setup shows the bundled Takeaway OS 1.3.0 vs the installed version; click "Update bundled Takeaway OS".
3. Settings, Site Content and orders are preserved (add-only migrations).
4. Run Setup Health -> "Repair pages and menus" once to create the new contact/policy pages (existing pages are never overwritten; conflicts get a fresh "Takeaway ..." twin).

### After install/update checklist
- Branding: pick colours or a preset; upload logo + favicon.
- Site Content: fill Business Info, Opening Times, Homepage; review Policies (starter content only).
- Delivery postcodes: Business Settings -> Delivery -> "Delivery postcode notes" (comma-separated prefixes, e.g. `MK18, MK17`) powers the public delivery checker. Prefix matching only.
- Stripe + SMTP remain Setup Health warnings until configured with real credentials; both are required before paid production.
- Review duplicate-page warnings in Setup Health (stray old pages are flagged, never deleted).
- If Setup Health reports a page-builder header/footer hijack, use its admin-only repair (drafts templates, clears conditions, deletes nothing).

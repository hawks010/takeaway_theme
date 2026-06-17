# Takeaway Theme Client Child Starter

Use this child theme for any client-specific design or template work that must survive parent theme updates.

## Safe use cases

- client-only CSS tweaks
- client-only template-part overrides
- one-off layout changes for a single restaurant
- safe snippets that should not ship to every customer

## Do not put here

- core bug fixes
- reusable checkout fixes
- Takeaway OS business logic
- anything that should ship to every client

## Common workflow

1. Install `takeaway-theme` as the parent theme.
2. Copy this folder and rename it for the client, for example `takeaway-theme-blueprint-child`.
3. Update `style.css` metadata to the real client child theme name.
4. Add CSS in `assets/css/client-overrides.css`.
5. Override parent templates only when needed.

## Override-friendly areas

- `template-parts/home/*`
- `template-parts/header/*`
- `template-parts/footer/*`
- WooCommerce template files only when strictly necessary

## Rule

If a change should survive a parent theme update and only belongs to one client, it belongs in the child theme, not in `takeaway-theme`.


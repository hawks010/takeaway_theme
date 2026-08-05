# Update Architecture

Updated: 2026-06-17

## Recommended model

Use GitHub as the product source of truth, but keep product updates separate from client custom work.

Recommended structure:

1. One product repo for now
   - `takeaway-os/`
   - `takeaway-theme/`
   - `takeaway-theme-child-starter/`
   - `docs/`
   - `scripts/`
2. Two release artefacts from that repo
   - `takeaway-os-vX.Y.Z.zip`
   - `takeaway-theme-vX.Y.Z-bundled.zip`
3. One child theme per client
   - never customise the parent theme directly for a client

This keeps release management simple while the product is still moving quickly.

## Why this is the right shape

What is already update-safe in the current build:

- business settings mostly live in `ttos_settings`
- site content lives separately in `ttos_site_content`
- page assignments live in named options like `ttos_page_*`
- menu locations are stored in `theme_mod` / WordPress menu assignments
- upgrade routines are mostly add-only and do not intentionally overwrite existing saved values
- the bundled installer already avoids downgrading a newer plugin install

What is not automatically update-safe:

- direct edits inside `takeaway-theme`
- direct edits inside `takeaway-os`
- one-off CSS dropped into parent theme files
- client-specific template edits inside the shipped parent theme

## Hard commercial rule

Product fixes go in the product.

Client-specific presentation changes go in the child theme.

Client business data goes in the database through Takeaway OS / WooCommerce settings.

## Safe customisation policy

Safe to update without losing client data:

- branding colours/tokens saved in Takeaway OS
- business info
- opening hours
- CRM/site content
- delivery settings
- WooCommerce product/order/customer data
- module settings

Not safe if edited in the wrong place:

- custom header/footer markup added directly to the parent theme
- custom client landing-page sections added directly to the parent theme
- client-specific plugin logic edits inside `takeaway-os`

## Child theme rule

Use `takeaway-theme-child-starter/` as the base for every client deployment that needs custom front-end work.

Examples:

- custom spacing
- unique hero layout
- client-only footer variation
- local campaign landing page styling

Do not use the child theme for core bug fixes that all customers need.

## GitHub release workflow

Recommended release flow:

1. Develop in the product repo.
2. Tag release candidates with normal semantic versions.
3. Run `scripts/build_release_packages.sh`.
4. Verify:
   - bundled plugin zip version
   - plugin release zip
   - theme release zip
5. Publish GitHub release with the generated zip files attached.
6. Deploy only those artefacts to staging/production.

## Updater recommendation

Recommended order:

1. Plugin updater first
2. Theme updater second
3. License/update entitlement layer after the basic update flow is proven

Why plugin first:

- most product logic lives in `takeaway-os`
- theme updates are more likely to clash with client front-end customisations
- plugin-only updates let us patch operational features with less design risk

## Theme updater caution

If a client is using a child theme properly, parent theme updates are usually safe.

If a client has been customised by editing the parent theme directly, parent theme updates are risky and may overwrite that work.

That is exactly why the child-theme policy must become non-optional before commercial rollout.

## Repo recommendation

Current recommendation:

- stay on one repo until the commercial updater flow is stable

Later, if needed:

- split into separate repos for `takeaway-os` and `takeaway-theme`

But do not split too early unless you genuinely need separate release cadence, access control, or licensing.

## Current status

The codebase is already fairly safe for database-backed settings/content.

The remaining commercial-release risk is not the settings architecture. It is human process:

- someone editing parent theme files for a client
- someone editing plugin files directly on a live site
- shipping updates without a child-theme/customisation policy

## Next practical step

The repo now has the missing pieces to support a safe parent-theme updater:

1. `takeaway-theme` bundles `plugin-update-checker`
2. the parent theme registers a GitHub-backed updater against `hawks010/takeaway_theme`
3. the updater is locked to release assets matching `takeaway-theme-v*-bundled.zip`
4. the release workflow publishes those zip files as GitHub release assets on tagged releases

This avoids the biggest monorepo risk: WordPress trying to install the whole repository zip as a theme update.

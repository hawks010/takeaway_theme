#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT/takeaway-os"
THEME_DIR="$ROOT/takeaway-theme"
MANIFEST="$THEME_DIR/inc/bundled-plugins/manifest.json"

plugin_version="$(grep -E "^define\('TTOS_VERSION', '" "$PLUGIN_DIR/takeaway-os.php" | sed -E "s/^define\('TTOS_VERSION', '([^']+)'.*$/\1/" | head -n 1)"
theme_version="$(grep -E '^Version:' "$THEME_DIR/style.css" | sed -E 's/^Version:[[:space:]]+//' | head -n 1 | tr -d '\r')"

if [[ -z "$plugin_version" || -z "$theme_version" ]]; then
  echo "Could not detect plugin/theme versions." >&2
  exit 1
fi

plugin_zip="$ROOT/takeaway-os-v${plugin_version}.zip"
theme_zip="$ROOT/takeaway-theme-v${theme_version}-bundled.zip"
bundled_zip="$THEME_DIR/inc/bundled-plugins/takeaway-os.zip"

exclude_patterns=(
  '*/.DS_Store'
  '*/__MACOSX/*'
  '*/.git/*'
  '*/test-artifacts/*'
  '*/backups/*'
  '*/screenshots/*'
)

echo "Building bundled plugin zip..."
zip -qr "$bundled_zip" "takeaway-os" -x "${exclude_patterns[@]}"

echo "Building plugin release zip..."
zip -qr "$plugin_zip" "takeaway-os" -x "${exclude_patterns[@]}"

echo "Building theme release zip..."
zip -qr "$theme_zip" "takeaway-theme" -x "${exclude_patterns[@]}"

echo "Bundled manifest:"
cat "$MANIFEST"

echo
echo "Built:"
echo "  $bundled_zip"
echo "  $plugin_zip"
echo "  $theme_zip"

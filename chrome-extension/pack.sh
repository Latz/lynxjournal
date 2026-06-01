#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION=$(grep '"version"' "$SCRIPT_DIR/manifest.json" | sed 's/.*"version": *"\([^"]*\)".*/\1/')
OUTPUT="$SCRIPT_DIR/linkdigest-extension-v${VERSION}.zip"

FILES=(
    manifest.json
    background.js
    i18n.js
    popup.html
    popup.js
    popup.css
    settings.html
    settings.js
    tagify.css
    tagify.min.js
    icon16.png
    icon32.png
    icon48.png
    icon64.png
    icon128.png
    icon256.png
    _locales/de/messages.json
    _locales/en/messages.json
)

cd "$SCRIPT_DIR"
rm -f "$OUTPUT"
zip -r "$OUTPUT" "${FILES[@]}"

echo "Packed: $OUTPUT"

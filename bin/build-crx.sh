#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
EXT_DIR="$PROJECT_DIR/chrome-extension"
DIST_DIR="$PROJECT_DIR/dist"

VERSION=$(python3 -c "import json; print(json.load(open('$EXT_DIR/manifest.json'))['version'])")
if [ -z "$VERSION" ]; then
    echo "ERROR: Could not read version from manifest.json" >&2
    exit 1
fi

ZIP_NAME="lynxjournal-chrome-extension-${VERSION}.zip"
ZIP_FILE="$DIST_DIR/$ZIP_NAME"

mkdir -p "$DIST_DIR"

echo "=========================================="
echo "LynxJournal Chrome Extension Build"
echo "=========================================="
echo ""
echo "Version : $VERSION"
echo "Output  : $ZIP_FILE"
echo ""

# Chrome expects files at the root of the zip (no wrapping folder).
cd "$EXT_DIR"
zip -r "$ZIP_FILE" . \
    -x "*.zip" \
    -x "chrome-extension.md" \
    -x "final-check.md" \
    -x ".DS_Store" \
    -x "*~"
cd "$PROJECT_DIR"

echo ""
echo "=========================================="
echo "Build complete"
echo "=========================================="
echo ""
echo "ZIP  : $ZIP_FILE"
echo "     : $(du -sh "$ZIP_FILE" | cut -f1)"
echo ""

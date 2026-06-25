#!/usr/bin/env bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
WP_PATH="/home/latz/www/wp-stable"
PLUGINS_DIR="$WP_PATH/wp-content/plugins"
DIST_DIR="$PROJECT_DIR/dist/lynxjournal"
TEMP_SLUG="lynx-journal"
TEMP_DIR="$PLUGINS_DIR/$TEMP_SLUG"

cleanup() {
    rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

echo "=========================================="
echo "LynxJournal Plugin Check"
echo "=========================================="
echo ""

# Build the plugin first
echo "Building plugin..."
bash "$SCRIPT_DIR/build-plugin.sh"

echo ""
echo "=========================================="
echo "Running plugin check on dist..."
echo ""

if [ ! -d "$DIST_DIR" ]; then
    echo "ERROR: dist not found at $DIST_DIR" >&2
    exit 1
fi

# Copy dist into a temp plugin directory so the checker only sees built files
cp -r "$DIST_DIR" "$TEMP_DIR"

CHECK_OUTPUT=$(wp plugin check "$TEMP_SLUG" \
    --path="$WP_PATH" 2>&1) || true

echo "$CHECK_OUTPUT"

# Summary
ERROR_COUNT=$(echo "$CHECK_OUTPUT" | grep -c $'\tERROR\t' || true)
WARNING_COUNT=$(echo "$CHECK_OUTPUT" | grep -c $'\tWARNING\t' || true)

echo ""
echo "=========================================="
echo "Summary"
echo "=========================================="
echo "  Errors   : $ERROR_COUNT"
echo "  Warnings : $WARNING_COUNT"
echo "=========================================="

if [ "$ERROR_COUNT" -gt 0 ]; then
    exit 1
fi

exit 0

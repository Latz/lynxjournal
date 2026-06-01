#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DIST_DIR="$PROJECT_DIR/dist"

echo "=========================================="
echo "LinkDigest Plugin Build"
echo "=========================================="
echo ""

# Resolve version from plugin header
VERSION=$(grep -m1 "Version:" "$PROJECT_DIR/linkdigest.php" | sed 's/.*Version: *//')
if [ -z "$VERSION" ]; then
    echo "ERROR: Could not read plugin version from linkdigest.php" >&2
    exit 1
fi

PLUGIN_NAME="linkdigest"
ZIP_NAME="${PLUGIN_NAME}-${VERSION}.zip"
ZIP_PATH="$DIST_DIR/$ZIP_NAME"
STAGE_DIR="$DIST_DIR/${PLUGIN_NAME}"

echo "Version : $VERSION"
echo "Output  : $ZIP_PATH"
echo ""

# Require node_modules (avoid slow npm install inside a build script)
if [ ! -d "$PROJECT_DIR/node_modules" ]; then
    echo "ERROR: node_modules not found. Run 'npm install' first." >&2
    exit 1
fi

# Clean previous dist
rm -rf "$DIST_DIR"
mkdir -p "$STAGE_DIR"

# Build compiled JS/CSS (wp-scripts writes to build/)
echo "Building JavaScript assets..."
npm --prefix "$PROJECT_DIR" run build

# Copy files into staging directory, honouring .distignore
echo "Copying plugin files..."
rsync -a \
    --exclude-from="$PROJECT_DIR/.distignore" \
    --exclude='dist/' \
    "$PROJECT_DIR/" "$STAGE_DIR/"

# Flatten PHP into includes/ (src/ is excluded by .distignore above)
echo "Flattening PHP into includes/..."
mkdir -p "$STAGE_DIR/includes"
cp "$PROJECT_DIR/src/php/ScheduleMode.php" "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/traits/"*.php "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/traits/Admin/"*.php "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/class-linkdigest.php" "$STAGE_DIR/includes/"

# Patch require_once paths in the staged linkdigest.php
sed -i \
    -e "s|src/php/ScheduleMode\.php|includes/ScheduleMode.php|g" \
    -e "s|src/php/traits/Admin/Menu\.php|includes/Menu.php|g" \
    -e "s|src/php/traits/Admin/Dashboard\.php|includes/Dashboard.php|g" \
    -e "s|src/php/traits/Admin/LinksPage\.php|includes/LinksPage.php|g" \
    -e "s|src/php/traits/Admin/AddLink\.php|includes/AddLink.php|g" \
    -e "s|src/php/traits/Admin/Categories\.php|includes/Categories.php|g" \
    -e "s|src/php/traits/trait-post-type\.php|includes/trait-post-type.php|g" \
    -e "s|src/php/traits/MetaBoxes\.php|includes/MetaBoxes.php|g" \
    -e "s|src/php/traits/Publishing\.php|includes/Publishing.php|g" \
    -e "s|src/php/traits/Batch\.php|includes/Batch.php|g" \
    -e "s|src/php/traits/Queries\.php|includes/Queries.php|g" \
    -e "s|src/php/traits/ScheduleValidator\.php|includes/ScheduleValidator.php|g" \
    -e "s|src/php/traits/RestApi\.php|includes/RestApi.php|g" \
    -e "s|src/php/traits/Scheduler\.php|includes/Scheduler.php|g" \
    -e "s|src/php/class-linkdigest\.php|includes/class-linkdigest.php|g" \
    "$STAGE_DIR/linkdigest.php"

# Rename build/ → schedule/ in the staging dir
mv "$STAGE_DIR/build" "$STAGE_DIR/schedule"
sed -i "s|'build/|'schedule/|g" "$STAGE_DIR/includes/Menu.php"

# Zip the staging directory
echo "Creating archive..."
(cd "$DIST_DIR" && zip -r -q "$ZIP_PATH" "$PLUGIN_NAME")

echo ""
echo "=========================================="
echo "Build complete"
echo "=========================================="
echo ""
echo "Archive : $ZIP_NAME"
echo "Size    : $(du -h "$ZIP_PATH" | cut -f1)"
echo "Path    : $ZIP_PATH"
echo ""

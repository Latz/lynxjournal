#!/bin/bash

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DIST_DIR="$PROJECT_DIR/dist"

echo "=========================================="
echo "LynxJournal Plugin Build"
echo "=========================================="
echo ""

# Resolve version from plugin header
VERSION=$(grep -m1 "Version:" "$PROJECT_DIR/lynxjournal.php" | sed 's/.*Version: *//')
if [ -z "$VERSION" ]; then
    echo "ERROR: Could not read plugin version from lynxjournal.php" >&2
    exit 1
fi

PLUGIN_NAME="lynxjournal"
STAGE_DIR="$DIST_DIR/${PLUGIN_NAME}"

echo "Version : $VERSION"
echo "Output  : $STAGE_DIR"
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

# Production-only Composer dependencies (league/commonmark, required at
# runtime by TemplateRenderer.php) must ship in the dist package. Prune dev
# deps for the build, then always restore them afterward for local testing.
echo "Installing production Composer dependencies..."
composer install --no-dev --optimize-autoloader --working-dir="$PROJECT_DIR" --quiet
restore_dev_deps() {
    echo "Restoring development Composer dependencies..."
    composer install --working-dir="$PROJECT_DIR" --quiet
}
trap restore_dev_deps EXIT

# Copy files into staging directory, honouring .distignore
echo "Copying plugin files..."
rsync -a \
    --exclude-from="$PROJECT_DIR/.distignore" \
    --exclude='dist/' \
    --exclude='assets/' \
    --exclude='chrome-extension/' \
    --exclude='languages/' \
    --exclude='README.MD' \
    --exclude='fixed.md' \
    --exclude='.*' \
    "$PROJECT_DIR/" "$STAGE_DIR/"

# Copy explicitly included folders
echo "Copying assets/..."
rsync -a "$PROJECT_DIR/assets/" "$STAGE_DIR/assets/"

echo "Copying chrome-extension/..."
rsync -a \
    --exclude='*.zip' \
    --exclude='README.md' \
    "$PROJECT_DIR/chrome-extension/" "$STAGE_DIR/chrome-extension/"

echo "Copying languages/..."
rsync -a "$PROJECT_DIR/languages/" "$STAGE_DIR/languages/"

echo "Copying vendor/ (production dependencies)..."
rsync -a "$PROJECT_DIR/vendor/" "$STAGE_DIR/vendor/"
# plugin-check expects composer.json alongside a real vendor/ directory, for
# transparency about what's bundled (WordPress.org "unneeded folders" check).
cp "$PROJECT_DIR/composer.json" "$STAGE_DIR/composer.json"

# Flatten PHP into includes/ (src/ is excluded by .distignore above)
echo "Flattening PHP into includes/..."
mkdir -p "$STAGE_DIR/includes"
cp "$PROJECT_DIR/src/php/ScheduleMode.php" "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/traits/"*.php "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/traits/Admin/"*.php "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/class-lynxjournal.php" "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/notifications/"*.php "$STAGE_DIR/includes/"
cp "$PROJECT_DIR/src/php/notifications/channels/"*.php "$STAGE_DIR/includes/"

# Patch require_once paths in the staged lynxjournal.php
sed -i \
    -e "s|src/php/ScheduleMode\.php|includes/ScheduleMode.php|g" \
    -e "s|src/php/notifications/channels/\([A-Za-z]*\)\.php|includes/\1.php|g" \
    -e "s|src/php/notifications/\([A-Za-z]*\)\.php|includes/\1.php|g" \
    -e "s|src/php/traits/Admin/Menu\.php|includes/Menu.php|g" \
    -e "s|src/php/traits/Admin/Dashboard\.php|includes/Dashboard.php|g" \
    -e "s|src/php/traits/Admin/LinksPage\.php|includes/LinksPage.php|g" \
    -e "s|src/php/traits/Admin/AddLink\.php|includes/AddLink.php|g" \
    -e "s|src/php/traits/Admin/Categories\.php|includes/Categories.php|g" \
    -e "s|src/php/traits/trait-post-type\.php|includes/trait-post-type.php|g" \
    -e "s|src/php/traits/MetaBoxes\.php|includes/MetaBoxes.php|g" \
    -e "s|src/php/traits/Publishing\.php|includes/Publishing.php|g" \
    -e "s|src/php/traits/Batch\.php|includes/Batch.php|g" \
    -e "s|src/php/traits/TemplateRenderer\.php|includes/TemplateRenderer.php|g" \
    -e "s|src/php/traits/Queries\.php|includes/Queries.php|g" \
    -e "s|src/php/traits/ScheduleValidator\.php|includes/ScheduleValidator.php|g" \
    -e "s|src/php/traits/RestApi\.php|includes/RestApi.php|g" \
    -e "s|src/php/traits/RestApiSupport\.php|includes/RestApiSupport.php|g" \
    -e "s|src/php/traits/Admin/DashboardActions\.php|includes/DashboardActions.php|g" \
    -e "s|src/php/traits/Admin/TemplatePage\.php|includes/TemplatePage.php|g" \
    -e "s|src/php/traits/Admin/NotificationFailureNotice\.php|includes/NotificationFailureNotice.php|g" \
    -e "s|src/php/traits/Scheduler\.php|includes/Scheduler.php|g" \
    -e "s|src/php/class-lynxjournal\.php|includes/class-lynxjournal.php|g" \
    "$STAGE_DIR/lynxjournal.php"

# Rename build/ → schedule/ in the staging dir
mv "$STAGE_DIR/build" "$STAGE_DIR/schedule"
sed -i "s|'build/|'schedule/|g" "$STAGE_DIR/includes/Menu.php"

SVN_TRUNK="$PROJECT_DIR/svn/trunk"

echo "Syncing to svn/trunk..."
rsync -a --delete "$STAGE_DIR/" "$SVN_TRUNK/"

echo ""
echo "=========================================="
echo "Build complete"
echo "=========================================="
echo ""
echo "Path : $STAGE_DIR"
echo "Size : $(du -sh "$STAGE_DIR" | cut -f1)"
echo "SVN  : $SVN_TRUNK"
echo ""

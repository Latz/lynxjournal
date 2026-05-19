#!/usr/bin/env bash
# Install the WordPress test suite and create the test database.
#
# Usage:
#   bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#
# Example:
#   bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

set -e

DB_NAME=${1:-wordpress_test}
DB_USER=${2:-root}
DB_PASS=${3:-''}
DB_HOST=${4:-localhost}
WP_VERSION=${5:-latest}

WP_TESTS_DIR=${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR:-/tmp/wordpress}

# ---------------------------------------------------------------------------
# Download WordPress core
# ---------------------------------------------------------------------------
download_wp() {
    local archive
    if [[ "$WP_VERSION" == "latest" ]]; then
        local api
        api=$(curl -s https://api.wordpress.org/core/version-check/1.7/ | python3 -c "import sys,json; d=json.load(sys.stdin); print(d['offers'][0]['version'])")
        archive="wordpress-${api}.tar.gz"
        curl -s "https://wordpress.org/${archive}" -o /tmp/wordpress.tar.gz
    else
        curl -s "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" -o /tmp/wordpress.tar.gz
    fi
    tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C "$WP_CORE_DIR"
    rm /tmp/wordpress.tar.gz
    return 0
}

# ---------------------------------------------------------------------------
# Download the WP test suite via SVN
# ---------------------------------------------------------------------------
install_test_suite() {
    if [[ -d "$WP_TESTS_DIR/includes" ]]; then
        return
    fi
    mkdir -p "$WP_TESTS_DIR"
    git clone --depth=1 --filter=blob:none --sparse \
        "https://github.com/WordPress/wordpress-develop.git" /tmp/wp-develop-sparse 2>/dev/null \
        || { echo "git clone failed"; return 1; }
    git -C /tmp/wp-develop-sparse sparse-checkout set tests/phpunit/includes tests/phpunit/data 2>/dev/null
    cp -r /tmp/wp-develop-sparse/tests/phpunit/includes "$WP_TESTS_DIR/includes"
    cp -r /tmp/wp-develop-sparse/tests/phpunit/data    "$WP_TESTS_DIR/data" 2>/dev/null || true
    rm -rf /tmp/wp-develop-sparse

    cat > "$WP_TESTS_DIR/wp-tests-config.php" <<PHP
<?php
define( 'ABSPATH', '${WP_CORE_DIR}/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
PHP
}

# ---------------------------------------------------------------------------
# Create test database
# ---------------------------------------------------------------------------
create_db() {
    local host="${DB_HOST%%:*}"
    local port="${DB_HOST##*:}"
    [[ "$port" == "$host" ]] && port=3306
    mysql -u "$DB_USER" --password="$DB_PASS" -h "$host" --port="$port" \
        -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;" 2>/dev/null && \
    echo "Database '${DB_NAME}' ready." || \
    echo "WARN: Could not create database. It may already exist."
    return 0
}

mkdir -p "$WP_CORE_DIR"
download_wp
install_test_suite
create_db

echo ""
echo "Done. Set WP_TESTS_DIR=${WP_TESTS_DIR} and run:"
echo "  vendor/bin/pest --testsuite=Integration --bootstrap tests/bootstrap-integration.php"

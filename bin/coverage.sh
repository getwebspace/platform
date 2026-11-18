#!/bin/sh
# Code coverage of the whole test suite, php-fpm included. Runs inside the dev container:
#
#   make run-coverage                  # prints the table
#   make run-coverage ARGS=--min=50    # fails below 50 %
#
# Two passes, because pcov cannot be on in phpunit and php-fpm at the same time
# (both write to the one SQLite file and the slowdown makes it "database is locked"):
#   1. phpunit with pcov            - code the tests call directly
#   2. php-fpm with pcov, no pcov in phpunit - code served to the HTTP tests
# Results are merged by tests/Coverage/report.php. pcov is installed on first use
# and is not part of the production image
set -eu

cd "${PLATFORM_HOME:-/var/container}"

INI_DIR=/usr/local/etc/php/conf.d
FPM_INI="$INI_DIR/zz-coverage-fpm.ini"
OUT=/tmp/coverage-fpm
CLOVER=/tmp/coverage-clover.xml

if ! php -m | grep -qx pcov; then
    echo "installing pcov..."
    apk add --no-cache --virtual .pcov-deps $PHPIZE_DEPS >/dev/null
    pecl install pcov >/dev/null
    docker-php-ext-enable pcov
    apk del .pcov-deps >/dev/null
fi

# keep it off unless asked: with it on, php-fpm slows down and the tests start to time out
INI="$INI_DIR/docker-php-ext-pcov.ini"
grep -q '^pcov.enabled' "$INI" || echo 'pcov.enabled=0' >> "$INI"

cleanup() {
    rm -f "$FPM_INI"
    sv restart php >/dev/null 2>&1 || true
}
trap cleanup EXIT INT TERM

echo "pass 1/2: phpunit"
php -d pcov.enabled=1 -d pcov.directory="$PWD/src" vendor/bin/phpunit --configuration phpunit.xml \
    --coverage-clover="$CLOVER" --coverage-filter=src --no-progress 2>&1 | grep -v 'JIT is incompatible' | tail -n 4

echo "pass 2/2: php-fpm"
rm -rf "$OUT"
mkdir -p "$OUT"
chmod 777 "$OUT"
printf 'auto_prepend_file=%s/tests/Coverage/fpm_prepend.php\npcov.enabled=1\npcov.directory=%s/src\n' "$PWD" "$PWD" > "$FPM_INI"
sv restart php >/dev/null
sleep 3
php vendor/bin/phpunit --configuration phpunit.xml --no-progress 2>&1 | grep -v 'JIT is incompatible' | tail -n 4

echo
php -d error_reporting=0 tests/Coverage/report.php "$CLOVER" "$OUT" "$@"

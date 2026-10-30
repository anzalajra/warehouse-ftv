#!/bin/sh
# Contract tests for the production wrapper, without touching a real database.
set -eu
project_root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
fixture=$(mktemp -d)
trap 'rm -rf "$fixture"' EXIT HUP INT TERM
mkdir -p "$fixture/scripts" "$fixture/bin"
cp "$project_root/scripts/start-container.sh" "$fixture/scripts/"
cat > "$fixture/bin/php" <<'STUB'
#!/bin/sh
echo "$*" >> "$STARTUP_TEST_LOG"
if [ "${STARTUP_TEST_FAIL:-}" = "$2" ]; then exit 42; fi
STUB
cat > "$fixture/bin/test-server" <<'STUB'
#!/bin/sh
echo "server $*" >> "$STARTUP_TEST_LOG"
exit 17
STUB
chmod +x "$fixture/bin/php" "$fixture/bin/test-server"
export PATH="$fixture/bin:$PATH" STARTUP_TEST_LOG="$fixture/log"
unset WAREHOUSE_RUN_MIGRATIONS STARTUP_TEST_FAIL

status=0
sh "$fixture/scripts/start-container.sh" test-server 'argument with spaces' || status=$?
[ "$status" -eq 17 ]
cat > "$fixture/expected" <<'EXPECTED'
artisan config:clear --no-interaction
artisan route:clear --no-interaction
artisan event:clear --no-interaction
artisan view:clear --no-interaction
artisan filament:clear-cached-components --no-interaction
artisan icons:clear --no-interaction
artisan migrate --force --no-interaction
server argument with spaces
EXPECTED
diff -u "$fixture/expected" "$STARTUP_TEST_LOG"

for failure in config:clear route:clear event:clear view:clear filament:clear-cached-components icons:clear migrate; do
    : > "$STARTUP_TEST_LOG"
    status=0
    STARTUP_TEST_FAIL="$failure" sh "$fixture/scripts/start-container.sh" test-server || status=$?
    [ "$status" -eq 42 ]
    if grep -q '^server ' "$STARTUP_TEST_LOG"; then exit 1; fi
done

: > "$STARTUP_TEST_LOG"
status=0
WAREHOUSE_RUN_MIGRATIONS=0 sh "$fixture/scripts/start-container.sh" test-server || status=$?
[ "$status" -eq 17 ]
if grep -q '^artisan migrate ' "$STARTUP_TEST_LOG"; then exit 1; fi

: > "$STARTUP_TEST_LOG"
status=0
sh "$fixture/scripts/start-container.sh" || status=$?
[ "$status" -eq 64 ]
[ ! -s "$STARTUP_TEST_LOG" ]
echo 'PASS: startup ordering, failure propagation, server exit, migration opt-out, required server command.'

#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

for command in php composer npm sqlite3; do
    if ! command -v "$command" >/dev/null 2>&1; then
        echo "Perintah '$command' belum tersedia. Lihat README.md untuk instalasi runtime." >&2
        exit 1
    fi
done

if ! php -r 'exit(PHP_VERSION_ID >= 80200 && PHP_VERSION_ID < 80500 ? 0 : 1);'; then
    echo "composer.lock memerlukan PHP 8.2-8.4. Aktifkan PHP 8.4 sebelum setup." >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.example .env
fi

if ! grep -q '^APP_ENV=local$' .env || ! grep -q '^DB_CONNECTION=sqlite$' .env; then
    echo "Setup ini hanya untuk .env lokal dengan APP_ENV=local dan DB_CONNECTION=sqlite." >&2
    exit 1
fi

composer install --no-scripts
php artisan package:discover --ansi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --ansi
fi

new_database=false
if [[ ! -f database/database.sqlite ]]; then
    touch database/database.sqlite
    new_database=true
fi

php artisan migrate --force
if [[ "$new_database" == true ]] ||
   [[ "$(sqlite3 database/database.sqlite 'SELECT COUNT(*) FROM users;')" == 0 ]] ||
   [[ "$(sqlite3 database/database.sqlite 'SELECT COUNT(*) FROM computer_rooms;')" == 0 ]]; then
    php artisan db:seed --force
fi

touch storage/installed
npm ci

echo "Setup lokal selesai. Jalankan 'composer test' untuk tes atau 'composer dev' untuk aplikasi."

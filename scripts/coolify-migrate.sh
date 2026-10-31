#!/usr/bin/env bash
# Coolify: corre no contentor one-shot "migrate" antes de app/queue.
set -u
cd /var/www/html

# Garantir que DB_URL e restantes vêm do ambiente do deploy, não de caches do build.
php artisan optimize:clear --no-interaction 2>/dev/null || true

max_attempts=30
sleep_seconds=2

for attempt in $(seq 1 "${max_attempts}"); do
  if php artisan migrate --force --no-interaction; then
    exit 0
  fi
  echo "migrate: attempt ${attempt}/${max_attempts} failed, sleeping ${sleep_seconds}s..." >&2
  sleep "${sleep_seconds}"
done

echo "migrate: giving up after ${max_attempts} attempts" >&2
exit 1

#!/bin/bash
# Script to check performance metrics

echo "=== Performance Check ==="
echo ""

echo "1. Checking Nginx access log (last 10 requests with timing):"
docker-compose exec nginx tail -20 /var/log/nginx/access.log | grep -E "GET|POST" | tail -10

echo ""
echo "2. Checking PHP-FPM slow log:"
docker-compose exec app cat /var/log/php-fpm-slow.log 2>/dev/null || echo "No slow queries found"

echo ""
echo "3. Checking Laravel logs (last errors):"
docker-compose exec app tail -20 storage/logs/laravel.log 2>/dev/null | grep -E "ERROR|WARNING" || echo "No errors found"

echo ""
echo "4. PHP-FPM Process Status:"
docker-compose exec app ps aux | grep php-fpm | head -5

echo ""
echo "5. Nginx Process Status:"
docker-compose exec nginx ps aux | grep nginx | head -5

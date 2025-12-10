#!/bin/sh
set -e

# Production startup sequence
if [ "${APP_ENV:-dev}" = "prod" ]; then
  echo "🚀 Production startup sequence..."
  
  # Check if /var/www/html/var needs ownership fix (detect volume mount)
  # If directory is owned by root, it's likely a fresh volume mount
  VAR_OWNER=$(stat -c '%U' /var/www/html/var 2>/dev/null || echo "www-data")
  
  if [ "$VAR_OWNER" = "root" ]; then
    echo "📁 Detected volume mount, fixing ownership..."
    chown -R www-data:www-data /var/www/html/var
  else
    echo "✅ Ownership already correct (www-data)"
  fi
  
  # Ensure proper permissions (directories: 755, files: 644)
  # Only if not already set correctly
  echo "🔒 Ensuring proper permissions..."
  find /var/www/html/var -type d ! -perm 755 -exec chmod 755 {} + 2>/dev/null || true
  find /var/www/html/var -type f ! -perm 644 -exec chmod 644 {} + 2>/dev/null || true
  
  # Run Symfony commands as www-data (not root!)
  # This ensures cache files are owned by www-data
  echo "♻️  Clearing and warming up cache as www-data..."
  su -s /bin/sh www-data -c "php bin/console cache:clear --no-warmup --env=prod"
  su -s /bin/sh www-data -c "php bin/console cache:warmup --env=prod"
  
  # Run migrations as www-data (safer, no need for root)
  echo "🗄️  Running database migrations as www-data..."
  su -s /bin/sh www-data -c "php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod"
  
  echo "✨ Startup complete, starting Apache..."
fi

# Preserve default php-apache entrypoint behavior
# This starts Apache as root, which then spawns workers as www-data
exec docker-php-entrypoint "$@"


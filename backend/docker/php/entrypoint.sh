#!/bin/sh
set -e

# Si des arguments sont passés (ex: commande one-shot), on les exécute
# et on quitte — utilisé par le service "migrate" dans docker-compose.
if [ $# -gt 0 ]; then
  exec "$@"
fi

echo "🚀 Démarrage des services..."

# Clés OAuth2 : garantir la lecture par php-fpm (www-data).
# Les .pem sont copiés depuis le build (ou montés) avec des permissions
# root-only ; php-fpm tourne en www-data et ne peut pas les lire -> 500 sur /oauth2/token.
if [ -d /app/config/jwt ]; then
  chown root:www-data /app/config/jwt/private.pem /app/config/jwt/public.pem 2>/dev/null || true
  chmod 640 /app/config/jwt/private.pem 2>/dev/null || true
  chmod 644 /app/config/jwt/public.pem 2>/dev/null || true
fi

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

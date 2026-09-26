#!/bin/sh
set -e

# Si des arguments sont passés (ex: commande one-shot), on les exécute
# et on quitte — utilisé par le service "migrate" dans docker-compose.
if [ $# -gt 0 ]; then
  exec "$@"
fi

echo "🚀 Démarrage des services..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

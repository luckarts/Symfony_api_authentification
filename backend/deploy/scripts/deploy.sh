#!/usr/bin/env bash
# =============================================================================
# deploy.sh — Orchestrateur de déploiement Docker
# =============================================================================
# Exécuté sur le serveur par ci-gate.sh après réception de :
#   deploy authentification-symfony <staging|prod> <sha>
#
# Déroulé :
#   1. Tirer la nouvelle image Docker taguée par SHA
#   2. Démarrer le conteneur avec docker compose
#   3. Optionnel : nettoyer les anciennes images
#
# Usage (via CI) :
#   ssh oracle "deploy authentification-symfony staging abc123..."
#   ssh oracle "deploy authentification-symfony prod abc123..."
#
# En local (debug) :
#   cd /home/deploy/authentification-symfony
#   bash deploy/scripts/deploy.sh staging abc123...
# =============================================================================

set -euo pipefail

# ── Validation des arguments ─────────────────────────────────────────────────
if [[ $# -lt 2 ]]; then
  echo "Usage: $0 <environment> <sha>"
  echo "  environment : staging | prod"
  echo "  sha         : SHA du commit (40 chars hex)"
  exit 1
fi

ENVIRONMENT="$1"
SHA="$2"

# ── Configuration ───────────────────────────────────────────────────────────
REGISTRY="${REGISTRY:-registry.bachelart.fr}"
PROJECT_NAME="authentification-symfony"
PROJECT_DIR="/home/deploy/${PROJECT_NAME}"
DEPLOY_DIR="${PROJECT_DIR}/deploy"
COMPOSE_FILE="${DEPLOY_DIR}/docker-compose.prod.yml"

# ── Vérifications pré-déploiement ────────────────────────────────────────────
if [[ ! -f "$COMPOSE_FILE" ]]; then
  echo "❌ Fichier docker-compose introuvable : $COMPOSE_FILE"
  echo "   Les fichiers deploy/ ont-ils été synchronisés par rsync ?"
  exit 1
fi

if [[ ! -f "${PROJECT_DIR}/.env.${ENVIRONMENT}" ]]; then
  echo "❌ Fichier .env.${ENVIRONMENT} introuvable dans ${PROJECT_DIR}"
  echo "   Créez-le avec : cp deploy/.env.${ENVIRONMENT}.example ${PROJECT_DIR}/.env.${ENVIRONMENT}"
  exit 1
fi

# ── Pull de la nouvelle image ────────────────────────────────────────────────
echo "📦 Pulling image ${REGISTRY}/${PROJECT_NAME}:${SHA}..."
docker pull "${REGISTRY}/${PROJECT_NAME}:${SHA}"

# ── Déploiement ──────────────────────────────────────────────────────────────
echo "🚀 Deploying ${PROJECT_NAME} (${ENVIRONMENT}) — SHA: ${SHA}..."
cd "${PROJECT_DIR}"

REGISTRY="${REGISTRY}" \
  PROJECT_NAME="${PROJECT_NAME}" \
  SHA="${SHA}" \
  ENVIRONMENT="${ENVIRONMENT}" \
  docker compose -f "${COMPOSE_FILE}" up -d

# ── Vérification rapide ──────────────────────────────────────────────────────
echo "⏳ Waiting for container to be healthy..."
sleep 5

CONTAINER_NAME="${PROJECT_NAME}-${ENVIRONMENT}"
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
  echo "✅ ${CONTAINER_NAME} is running"
else
  echo "❌ ${CONTAINER_NAME} failed to start — check logs:"
  docker compose -f "${COMPOSE_FILE}" logs --tail=20
  exit 1
fi

# ── Nettoyage des anciennes images ──────────────────────────────────────────
echo "🧹 Cleaning old images..."
docker image prune -f --filter "until=24h"

echo "✅ Deployment complete: ${PROJECT_NAME} (${ENVIRONMENT}) @ ${SHA}"

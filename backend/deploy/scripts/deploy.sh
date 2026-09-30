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
  echo "Usage: $0 <environment> <image_tag>"
  echo "  environment : staging | prod"
  echo "  image_tag   : tag de l'image (latest-staging, latest-prod, ou SHA)"
  exit 1
fi

ENVIRONMENT="$1"
IMAGE_TAG="$2"

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

# ── Réseau Traefik ────────────────────────────────────────────────────────────
# Le réseau traefik-web est partagé (déclaré external dans docker-compose).
# On le crée s'il n'existe pas — pour un premier déploiement sur serveur vierge.
if ! docker network inspect traefik-web &>/dev/null; then
  echo "🌐 Creating traefik-web network..."
  docker network create traefik-web
fi

# ── Pull de la nouvelle image ────────────────────────────────────────────────
echo "📦 Pulling image ${REGISTRY}/${PROJECT_NAME}:${IMAGE_TAG}..."
docker pull "${REGISTRY}/${PROJECT_NAME}:${IMAGE_TAG}"

# ── Déploiement ──────────────────────────────────────────────────────────────
echo "🚀 Deploying ${PROJECT_NAME} (${ENVIRONMENT}) — tag: ${IMAGE_TAG}..."
cd "${PROJECT_DIR}"

REGISTRY="${REGISTRY}" \
  PROJECT_NAME="${PROJECT_NAME}" \
  IMAGE_TAG="${IMAGE_TAG}" \
  ENVIRONMENT="${ENVIRONMENT}" \
  docker compose --env-file "${PROJECT_DIR}/.env.${ENVIRONMENT}" -f "${COMPOSE_FILE}" up -d

# ── Vérification rapide ──────────────────────────────────────────────────────
echo "⏳ Waiting for container to be healthy..."
sleep 5

CONTAINER_NAME="${PROJECT_NAME}-${ENVIRONMENT}"
if docker ps --format '{{.Names}}' | grep -qx "${CONTAINER_NAME}"; then
  echo "✅ ${CONTAINER_NAME} is running"
else
  echo "❌ ${CONTAINER_NAME} failed to start — check logs:"
  docker logs "${CONTAINER_NAME}" --tail=20 2>/dev/null || true
  exit 1
fi

# ── Migrations + OAuth2 (one-shot) ─────────────────────────────────────────
echo "🗄️  Running migrations..."
REGISTRY="${REGISTRY}" \
  PROJECT_NAME="${PROJECT_NAME}" \
  IMAGE_TAG="${IMAGE_TAG}" \
  ENVIRONMENT="${ENVIRONMENT}" \
  docker compose --env-file "${PROJECT_DIR}/.env.${ENVIRONMENT}" -f "${COMPOSE_FILE}" \
    --profile migrate run --rm migrate
echo "✅ Migrations + OAuth2 setup complete"

# ── Nettoyage des anciennes images ──────────────────────────────────────────
echo "🧹 Cleaning old images..."
docker image prune -f --filter "until=24h"

echo "✅ Deployment complete: ${PROJECT_NAME} (${ENVIRONMENT}) @ ${IMAGE_TAG}"

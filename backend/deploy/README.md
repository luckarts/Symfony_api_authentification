# Déploiement Docker (Staging / Production)

## Architecture

Le déploiement utilise `docker-compose.prod.yml` et le script `deploy.sh`.

## Gestion des clés OAuth2

Les clés OAuth2 (`private.pem` et `public.pem`) sont stockées dans le dossier `/app/config/jwt` du conteneur API.
Comme ce dossier est éphémère par défaut, nous utilisons un volume nommé Docker pour les persister entre chaque déploiement.

- **Nom du volume :** `authentification-symfony-<environment>_jwt_data`
- **Comportement :** Les clés sont générées automatiquement lors du premier déploiement par le service `migrate`.
- **Sauvegarde :** Ne jamais lancer `docker compose down -v` ni `docker volume prune` sans avoir sauvegardé ce volume.
- **Rotation volontaire :** Pour forcer la rotation des clés (et invalider tous les tokens existants), supprimez les fichiers dans le volume, puis relancez le déploiement ou le service `migrate`.

Exemple de sauvegarde du volume de clés (Production) :
```bash
docker run --rm -v authentification-symfony-prod_jwt_data:/jwt -v "$PWD":/backup alpine \
  tar czf /backup/jwt-backup.tgz -C /jwt .
```

## Purge des comptes non vérifiés

Les comptes qui ne valident jamais leur adresse email sont supprimés via une commande de
maintenance explicite (pas de cron interne) : `app:users:purge-unverified`.

```bash
# Aperçu (dry-run implicite) — aucune suppression
docker exec app-backend php bin/console app:users:purge-unverified

# Suppression réelle des comptes créés il y a plus de 5 minutes (défaut)
docker exec app-backend php bin/console app:users:purge-unverified --force

# Seuil personnalisé (ex. 7 jours), batch limité
docker exec app-backend php bin/console app:users:purge-unverified --force --older-than="7 days" --limit=1000
```

Depuis le Makefile racine :

```bash
make purge-unverified                                            # aperçu (> 5 min)
make purge-unverified ARGS="--force"                             # suppression (> 5 min)
make purge-unverified ARGS='--force --older-than="7 days"'       # seuil personnalisé
```

**Comportement :**

- **Dry-run par défaut** : rien n'est supprimé sans `--force`.
- `--older-than` accepte toute durée relative (`"5 minutes"`, `"7 days"`, `"1 hour"`) —
  seuil par défaut : `5 minutes`. Une durée nulle ou négative est refusée.
- Seuls les comptes `isVerified = false` et créés **strictement avant** le seuil sont
  concernés (un compte créé il y a exactement 5 minutes est conservé).
- Les comptes `ROLE_ADMIN` / `ROLE_SUPER_ADMIN` sont **épargnés** (comptes créés manuellement).
- Les tokens OAuth2 (access + refresh) du compte sont **révoqués** avant suppression.
- Les demandes de reset password liées sont supprimées en cascade (FK `ON DELETE CASCADE`).
- Les événements de sécurité (`security_events`) sont **conservés** (traçabilité d'audit).
- Garde-fou : si `REQUIRE_EMAIL_VERIFICATION=false` dans l'environnement, la commande
  **refuse de s'exécuter** (sinon elle supprimerait tous les utilisateurs normaux) —
  `--force` permet de passer outre volontairement.

**Planification (optionnelle) :** pour automatiser, ajouter un cron sur l'hôte ou un
workflow GitHub Actions planifié qui exécute la commande `--force`.


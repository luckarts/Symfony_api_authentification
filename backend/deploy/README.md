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

# Mise en production — Africa Global Press (cPanel mutualisé)

Checklist pas à pas. Cocher chaque ligne ; ne pas sauter les vérifications. Hébergement cible : cPanel mutualisé,
PHP 8.3, MySQL, **sans Node ni Redis sur le serveur** (décision du propriétaire, 2026-10-28).

## 0. À vérifier chez l'hébergeur (question Q9)

- [ ] PHP **8.2 ou plus** (8.3 visé) pour le domaine, en ligne de commande aussi (`php -v` en SSH ou « Terminal » cPanel).
- [ ] Extensions PHP : `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` (avec WebP), `intl`, `zip`, `curl`, `exif`.
- [ ] Tâches cron autorisées à la minute.
- [ ] Liens symboliques autorisés (`storage:link`) ; sinon voir §4.
- [ ] Boîte `contact@pressafrica.media` créée et son mot de passe SMTP connu ; quota d'envoi horaire (pour `newsletterBatch`).
- [ ] Certificat HTTPS (AutoSSL / Let's Encrypt) actif sur le domaine.

## 1. Préparer les fichiers (sur votre poste, pas sur le serveur)

```bash
git clone <dépôt> pressafrica && cd pressafrica/pressmedia_laravel
composer install --no-dev --optimize-autoloader
npm ci
php artisan site:assets          # copie le CSS/JS/polices/images du prototype, Turbo et Quill dans public/assets
```

Envoyer sur le serveur tout le dossier `pressafrica/` (le site lit `../prototype_html/data` pour l'import initial).

## 2. Base de données et `.env`

- [ ] Créer la base MySQL et son utilisateur dans cPanel (« Bases de données MySQL »).
- [ ] Copier `.env.example` en `.env` et compléter : `APP_URL=https://pressafrica.media`, `DB_CONNECTION=mysql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, **`MAIL_PASSWORD`**, `DEMO_PASSWORD` (mot de passe fort des 4 comptes de départ).
- [ ] `php artisan key:generate` puis **sauvegarder `APP_KEY` hors du serveur** : sans elle, les sauvegardes chiffrées et les clés d'API stockées sont illisibles.
- [ ] Vérifier : `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.

## 3. Racine web

- [ ] Dans cPanel, faire pointer la racine du domaine vers `pressafrica/pressmedia_laravel/public` (« Domaines » → racine du document).
- [ ] Si la racine ne peut pas être changée : mettre le contenu de `public/` dans `public_html/` et adapter les deux chemins de `public_html/index.php` vers `../pressafrica/pressmedia_laravel/…`.

## 4. Installation

```bash
cd pressafrica/pressmedia_laravel
php artisan migrate --seed --force    # tables, rôles, 64+64 articles, médias, carrousel, menus, 4 comptes de départ
php artisan storage:link              # images téléversées servies depuis /storage
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

- [ ] Si `storage:link` est refusé : créer le lien dans le gestionnaire de fichiers cPanel (`public/storage` → `storage/app/public`), ou demander à l'hébergeur.
- [ ] Droits d'écriture sur `storage/` et `bootstrap/cache/` (755 dossiers, 644 fichiers).

## 5. Tâche cron (une seule ligne, indispensable)

Dans cPanel → « Tâches cron », toutes les minutes :

```
* * * * * cd /home/<compte>/pressafrica/pressmedia_laravel && php artisan schedule:run >> /dev/null 2>&1
```

Elle déclenche : publication des articles planifiés (chaque minute), envoi des campagnes newsletter par lots
(chaque minute), sauvegarde de la base (03:15) et des médias (dimanche 04:15), heure du Bénin.

## 6. Premiers pas dans l'administration

- [ ] Se connecter sur `/admin/login` avec `admin@pressafrica.media` et `DEMO_PASSWORD`.
- [ ] **Changer immédiatement** les mots de passe (et noms) des 4 comptes `admin@`, `editor@`, `author@`, `moderator@pressafrica.media`, ou créer les vrais comptes et supprimer ceux-là.
- [ ] Réglages : vérifier titre, couleurs, `maintenance` désactivée, `newsletterBatch` sous le quota horaire / 60.
- [ ] Sauvegardes : cliquer « Back up now », puis télécharger le fichier : il doit apparaître dans la liste.
- [ ] Publicités : zones inactives par défaut ; renseigner les vrais codes AdSense avant de les activer.
- [ ] GA4 : laisser désactivé tant que le DPO ne l'a pas validé (Q8).

## 7. Vérifications après mise en ligne

- [ ] `https://pressafrica.media/` redirige vers `/fr` ; un article (`/fr/articles/…`), une rubrique (`/fr/politique`), `/en` s'affichent ; une ancienne adresse `…/index.html` ou `….html` redirige (301) vers l'adresse sans extension.
- [ ] Le site répond en HTTPS uniquement ; `/robots.txt` et `/sitemap.xml` utilisent le bon domaine.
- [ ] Une page inexistante affiche la page 404 du site, sans détail technique.
- [ ] Commentaire de test → arrive « en attente » dans l'admin ; formulaire de contact → e-mail reçu sur `SUPPORT_MAIL`.
- [ ] Inscription newsletter de test → e-mail de confirmation reçu, lien de confirmation fonctionnel.
- [ ] Le lendemain : une sauvegarde automatique datée de la nuit figure dans Admin → Backups.
- [ ] Search Console : soumettre `/sitemap.xml` (et `/news-sitemap.xml` pour Google News).

## 8. Mise à jour du site (versions suivantes)

```bash
php artisan down --render="errors::503"     # ou Réglages > maintenance
# envoyer les fichiers modifiés (vendor/ et public/assets/ reconstruits sur votre poste)
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Avant toute mise à jour : Admin → Backups → « Back up now ».

## 9. Restauration d'urgence

```bash
php artisan backup:restore db-AAAAMMJJ-HHMMSS-auto.sql.gz.enc --force
```

Une copie de sécurité de l'état courant est faite avant la restauration. Les fichiers sont dans
`storage/app/private/backups/` ; ils ne se lisent qu'avec l'`APP_KEY` du site.

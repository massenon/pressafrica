# Mise en ligne — guide pas à pas (Africa Global Press)

Guide pratique pour publier le site Laravel (`pressmedia_laravel/`) sur un hébergement cPanel mutualisé.
La checklist technique complète reste `pressmedia_laravel/docs/DEPLOY.md` ; ce fichier en est la version
« à suivre dans l'ordre ».

> **Sécurité.** Ne réutilisez jamais en production l'`APP_KEY` ni le `DEMO_PASSWORD` de votre poste local :
> générez une clé neuve sur le serveur et choisissez un autre mot de passe. Aucun mot de passe ne doit être
> écrit dans le dépôt Git.

## 1. Vérifier chez l'hébergeur (cPanel)

- [ ] PHP **8.2 ou plus**, pour le site **et** dans le terminal : `php -v`.
- [ ] Extensions PHP : `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `intl`, `zip`, `curl`, `exif`.
- [ ] Tâches cron autorisées à la minute.
- [ ] Certificat SSL actif (AutoSSL / Let's Encrypt).
- [ ] Une base MySQL et son utilisateur, créés dans « Bases de données MySQL » (noter le nom de la base,
      l'utilisateur et son mot de passe).
- [ ] La boîte `contact@pressafrica.media` créée, avec son mot de passe.

## 2. Préparer les fichiers sur votre PC

Depuis `C:\Projects\pressafrica` (PowerShell) :

```bash
git pull origin claude/brave-heisenberg-53zohy
```

```bash
cd pressmedia_laravel
```

`--no-dev` n'installe que les paquets utiles en production :

```bash
composer install --no-dev --optimize-autoloader
```

```bash
npm ci
```

Copie le CSS, le JavaScript, les polices, les images et les médias du site dans `public/assets` :

```bash
php artisan site:assets
```

Envoyez ensuite tout le dossier `pressafrica/` sur le serveur (par exemple en zip via le gestionnaire de
fichiers cPanel, puis « Extraire »). **Laissez de côté :**

- `pressmedia_laravel/.env` (celui de votre poste) ;
- `pressmedia_laravel/database/database.sqlite` ;
- `pressmedia_laravel/node_modules/`.

Le dossier `prototype_html/data` doit être envoyé : il sert au remplissage initial de la base.

## 3. Le `.env` du serveur

Sur le serveur, copiez **`.env.production.example`** (et non `.env.example`, réservé au poste local) en `.env`.
Les valeurs de production y sont déjà ; il reste à remplir les champs vides et le bloc base de données.
Le `.env` doit ressembler à ceci :

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pressafrica.media
APP_KEY=                       ← laissé vide, rempli par key:generate (étape 4)
LOG_LEVEL=warning

DB_CONNECTION=mysql            ← au lieu de sqlite
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=moncompte_pressmedia     ← nom donné par cPanel
DB_USERNAME=moncompte_pressuser
DB_PASSWORD=************

SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict

MAIL_MAILER=smtp
MAIL_PASSWORD=************     ← mot de passe de contact@pressafrica.media

DEMO_PASSWORD=************     ← NOUVEAU mot de passe fort pour les 4 comptes de départ
```

Le reste ne change pas : `MAIL_HOST=mail.pressafrica.media`, `MAIL_PORT=587`,
`MAIL_USERNAME` / `MAIL_FROM_ADDRESS=contact@pressafrica.media`, `SUPPORT_MAIL`, etc.

| Variable | Local (`.env.example`) | Production (`.env.production.example`) |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | `false` |
| `APP_URL` | `http://127.0.0.1:8000` | `https://pressafrica.media` |
| `APP_KEY` | générée sur le poste | **générée sur le serveur**, jamais la même |
| `LOG_LEVEL` | `debug` | `warning` |
| `DB_CONNECTION` | `sqlite` | `mysql` + `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| `SESSION_SECURE_COOKIE` | `false` | `true` |
| `SESSION_SAME_SITE` | `lax` | `strict` |
| `MAIL_MAILER` | `log` (e-mails écrits dans `storage/logs`) | `smtp` + `MAIL_PASSWORD` |
| `DEMO_PASSWORD` | au choix | mot de passe fort, puis vidé après l'installation |

## 4. Racine web et installation

- [ ] Dans cPanel → Domaines, faire pointer la racine du domaine vers `pressafrica/pressmedia_laravel/public`.
      Si ce n'est pas possible : voir `pressmedia_laravel/docs/DEPLOY.md` §3.
- [ ] Activer « Force HTTPS Redirect » ; faire rediriger `www.` vers le domaine sans `www`.

Puis, dans le terminal cPanel :

```bash
cd ~/pressafrica/pressmedia_laravel
```

```bash
php artisan key:generate
```

Copiez la valeur `APP_KEY` (dans le `.env`) dans un endroit sûr, **hors du serveur** : sans elle, les
sauvegardes chiffrées sont illisibles.

Crée les tables, les rôles, les 64 + 64 articles, les médias, le carrousel, les menus et les 4 comptes de départ :

```bash
php artisan migrate --seed --force
```

```bash
php artisan storage:link
```

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Si vous modifiez le `.env` plus tard, relancez `php artisan config:cache`.

- [ ] Droits d'écriture sur `storage/` et `bootstrap/cache/` (755 pour les dossiers, 644 pour les fichiers).
- [ ] Si `storage:link` est refusé : créer le lien `public/storage` → `storage/app/public` dans le gestionnaire
      de fichiers cPanel, ou le demander à l'hébergeur.

## 5. Tâche cron : une seule ligne

Dans cPanel → Tâches cron, réglez « chaque minute » et remplacez `<compte>` par votre identifiant cPanel :

```
* * * * * cd /home/<compte>/pressafrica/pressmedia_laravel && php artisan schedule:run >> /dev/null 2>&1
```

Si `php -v` affiche une version inférieure à 8.2, remplacez `php` par le chemin de PHP 8.3, souvent
`/opt/cpanel/ea-php83/root/usr/bin/php`.

Cette ligne suffit pour tout, à l'heure du Bénin :

| Tâche | Fréquence |
|---|---|
| Publication des articles planifiés | chaque minute |
| Envoi des campagnes newsletter, par lots (réglage `newsletterBatch`, 50 par défaut) | chaque minute |
| Sauvegarde chiffrée de la base | chaque nuit à 3 h 15 |
| Sauvegarde des médias | le dimanche à 4 h 15 |

Aucune autre tâche cron n'est nécessaire (aucun e-mail n'est mis en file d'attente).
Réglez `newsletterBatch` (Admin → Réglages) sous le quota d'envoi horaire de l'hébergeur divisé par 60.

## 6. Comptes et mots de passe

1. Connectez-vous sur `https://pressafrica.media/admin/login` avec `admin@pressafrica.media` et votre
   `DEMO_PASSWORD`.
2. **Tout de suite :** « My account » (`/admin/account`) → changez le nom et le mot de passe de l'administrateur.
3. Admin → **Staff accounts** (`/admin/users`) :
   - changez le mot de passe de `editor@`, `author@` et `moderator@pressafrica.media` ;
   - ou créez les vrais comptes, puis **désactivez** ces trois-là. Un compte ne se supprime pas : ses articles
     gardent leur auteur.
4. Ensuite, videz la ligne `DEMO_PASSWORD=` du `.env` puis relancez `php artisan config:cache`.

Relancer `db:seed` plus tard ne réécrit jamais un compte existant : un mot de passe changé reste changé.

## 7. Vérifier après la mise en ligne

- [ ] `https://pressafrica.media` affiche `/fr`, et `/en` fonctionne aussi ; une ancienne adresse en `.html`
      redirige vers l'adresse sans extension.
- [ ] Le site ne répond qu'en HTTPS ; `/robots.txt` et `/sitemap.xml` utilisent le bon domaine.
- [ ] Une page inexistante affiche la page 404 du site, sans détail technique.
- [ ] Un message envoyé par le formulaire de contact arrive sur `rarhodes06@gmail.com`.
- [ ] Une inscription de test à la newsletter reçoit l'e-mail de confirmation, et le lien fonctionne.
- [ ] Un commentaire de test arrive « en attente » dans Admin → Comments.
- [ ] Admin → Backups → « Back up now » : un fichier apparaît. Le lendemain matin, une sauvegarde automatique
      de la nuit est dans la liste.
- [ ] Publicités et GA4 restent désactivés jusqu'aux vrais codes AdSense et à l'avis du DPO (Q8).
- [ ] Google Search Console : soumettre `/sitemap.xml` (et `/news-sitemap.xml` pour Google News).

## 8. Mettre à jour le site plus tard

Avant toute mise à jour : Admin → Backups → « Back up now ». Puis sur le serveur :

```bash
php artisan down --render="errors::503"
```

Envoyez les fichiers modifiés (avec `vendor/` et `public/assets/` reconstruits sur votre PC), puis :

```bash
php artisan migrate --force
```

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

```bash
php artisan up
```

## 9. Restauration d'urgence

```bash
php artisan backup:restore db-AAAAMMJJ-HHMMSS-auto.sql.gz.enc --force
```

Une copie de sécurité de l'état actuel est faite avant la restauration. Les fichiers de sauvegarde sont dans
`storage/app/private/backups/` et ne se lisent qu'avec l'`APP_KEY` du site.

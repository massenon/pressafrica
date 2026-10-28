# Plan d'implémentation technique et fonctionnel — Africa Global Press (pressmedia)

Date : 2026-10-28. Ce plan découle de `docs/PRD.md` (les EX-xx et INV-xx cités y sont définis). Il ne contient **ni durée ni charge** : aucune base n'existe pour les estimer (règle du PRD). Il donne l'ordre, les dépendances et le critère de sortie de chaque phase. Les seuils chiffrés restent à fixer après mesure (PRD Q6).

## 1. Point de départ (mesuré)

| Couche | État |
|---|---|
| **Prototype HTML** (`prototype_html/`, généré par `tools/build_site.py`) | 171 pages (85 FR + 85 EN dont 64 + 64 articles), site public complet, console d'administration de 22 pages, données en `localStorage`. C'est la **spécification exécutable** de l'interface. |
| **Port Laravel** (`pressmedia_laravel/`) | Squelette seulement : Laravel 12.70, Sanctum, Spatie Permission (60 permissions, 4 rôles seedés), Purifier, Livewire 3.8, îlot Vue `SearchBox`, schéma de contenu migré (catégories, articles, médias, podcasts, traductions FR/EN). Tout le reste est `todo` (`docs/PROGRESS.md`). |
| **Décisions du propriétaire** | Blade + îlots Vue + **Turbo Drive** (EX-07, 10) ; **Tailwind** (Q3) ; hébergement **cPanel mutualisé, sans Node, sans Redis** (EX-39) ; français et thème clair par défaut (EX-36). |
| **Non décidé / non vérifié** | Hébergeur et version de PHP (Q9, le propriétaire vérifie) ; source des statistiques de production (Q7, EX-19) ; contenus Magazine/Radios/TV (EX-02, rédacteur en chef, Q4) ; liens sortants des annuaires (EX-04, Q5) ; durées RGPD (Q8, DPO ou juriste). |

## 2. Architecture technique cible

| Domaine | Choix | Source / statut |
|---|---|---|
| Serveur | Laravel 12.70, PHP 8.3 (Laravel 12 exige au moins 8.2), MySQL en production, SQLite en local | DOCUMENTÉ (`CLAUDE.md`) |
| Hébergement | cPanel mutualisé : assets compilés avant envoi, racine web sur `public/`, `CACHE_STORE`/`SESSION_DRIVER` en `file` ou `database`, files par `database` + cron `queue:work --stop-when-empty` (ou `sync`), cron `schedule:run` à la minute | EX-39, ARBITRÉ côté contraintes ; détails à confirmer par Q9 |
| Public | Blade (rendu serveur) + îlots Vue 3/Pinia/vue-i18n/TypeScript sur `[data-island]` + **Turbo Drive** pour la navigation sans rechargement (FR↔EN inclus) | EX-07, EX-10, ARBITRÉ |
| Administration | Livewire 3 + Tailwind, Chart.js pour les graphiques, SweetAlert2, Quill, Choices, flatpickr | `CLAUDE.md` mis à jour (Tailwind) |
| Authentification | Sanctum (sessions pour l'admin, jetons pour l'API), OTP e-mail pour l'activation des comptes lecteurs | EX-33 / EX-34 |
| Droits | spatie/laravel-permission, noms `module.action` (`posts.create`, `audit.view`…), contrôle par policies et `can()`, jamais par nom de rôle (INV-06) | DOCUMENTÉ |
| Contenu | Tables `*_translations` FR/EN, URLs `/fr/…` et `/en/…` identiques au prototype | DOCUMENTÉ |
| Assainissement | mews/purifier sur tout HTML saisi (INV-04) | DOCUMENTÉ |
| Mesure | GA4 / Search Console selon Q7 ; journal first-party possible. **Rien n'est choisi** | EX-19, PROPOSÉ |

**Tables à créer ou compléter** (modèle du prototype, `TECHNICAL.md` §8 + modules du 2026-10-28) : `users`, `roles`, `permissions`, `categories` (+ traductions, hiérarchie illimitée), `posts` (+ traductions, statut, `published_at` pour la planification), `tags` et pivot, `media`, `comments` (+ consentement horodaté), `ads` (zones desktop/mobile) et `ad_events`, `newsletter_subscribers` (+ consentement), `menus`, `settings`, `podcast_shows`, `podcast_episodes`, `videos`, `magazine_issues` (+ pivot articles), `radio_stations`, `tv_channels`, `audit_log` (**insert seulement**, INV-02), `backups` (métadonnées), `analytics_*` selon Q7.

## 3. Plan en phases

Chaque phase se termine par ses tests et la mise à jour de `docs/PROGRESS.md` (règle `CLAUDE.md` : un module par session).

| # | Phase | Contenu (EX) | Dépend de | Critère de sortie |
|---|---|---|---|---|
| 0 | **Déblocage** | Q9 (hébergeur, PHP, SSH, cron), Q7 (source des stats), Q4/Q5 (contenus et liens sortants), Q8 (DPO) ; Lighthouse de référence (EX-11) ; mesure Firefox/Safari (M2) | — | Q9 répondue ; les autres sont des blocages **nommés**, pas des hypothèses |
| 1 | **Fondations** | Authentification (login, jetons), rôles/permissions CRUD, **audit log append-only** (EX-28, INV-02), paramètres, i18n FR/EN, thème clair par défaut (EX-36) | 0 (Q9 pour cron) | Un administrateur se connecte, change les droits d'un rôle ; l'action apparaît au journal ; un éditeur n'accède ni à l'audit ni aux sauvegardes |
| 2 | **Rédaction** | Articles, catégories, tags (EX-23), médias (conversion WebP, EX-24), planification (EX-25), modération des commentaires (EX-26) | 1 | Un article planifié se publie seul à l'échéance (cron) ; un commentaire public arrive « en attente » |
| 3 | **Import des contenus** | 64 articles par langue depuis le dump WordPress (`exxhrgce_wp623.sql`, `data/articles.json`), correspondance FR↔EN, médias | 2 | Nombre d'articles importés = nombre de la source ; anomalies de source **conservées et listées**, non corrigées |
| 4 | **Public Blade** | Accueil, rubrique, article, podcasts ; blocs article (EX-37) ; Turbo Drive et remontage des îlots (EX-10) ; SEO complet (EX-35) ; sitemaps, robots | 2, 3 | Parcours R1, R3 et R4 du PRD exécutables par un tiers ; l'article FR↔EN se charge sans rechargement complet |
| 5 | **Rubriques médias** | Podcasts, Vidéos, Magazine, Radios, Télévision : public + admin (EX-01 à 06) ; liens sortants selon Q5 | 4 | Chaque rubrique publiée depuis l'admin, sans passer par un fichier JSON ; plus de bloc « Bientôt » |
| 6 | **Audience et monétisation** | Réglages Analytics (EX-12, 13), consentement par catégorie (EX-14, INV-05), tableau de bord et rapports (EX-15 à 18, 21), clics pub (EX-20), newsletter, menus, widgets | 4, Q7 | Les totaux d'un export égalent ceux de l'écran ; aucun script tiers avant consentement |
| 7 | **Sécurité, RGPD, sauvegardes** | EX-33, EX-34, EX-29, EX-30 ; export et suppression des données d'un utilisateur ; sauvegarde planifiée par cron ; restauration testée | 1 à 6, Q8 | Restauration d'une sauvegarde réussie sur une copie ; rate limiting actif ; `APP_DEBUG=false` |
| 8 | **Qualité et mise en ligne** | Tests, UAT, performance, accessibilité, bascule (checklist `TECHNICAL.md` §10) | tout | Parcours R1 à R10 passés ; Lighthouse mesuré ; seuils fixés par le propriétaire après mesure |

L'ordre 4 → 5 → 6 peut se chevaucher une fois la phase 4 stable ; les phases 1, 2 et 7 ne se chevauchent pas (l'audit et les droits d'abord, la sécurité en dernier mais **conçue dès la phase 1**).

## 4. Exigences fonctionnelles (FR) → réalisation

| Domaine | EX | Laravel (composants) | Prototype |
|---|---|---|---|
| Rubriques Magazine, Radios, TV, Vidéos | 01-06 | Modèles + `*Translation`, Livewire CRUD, vues Blade, JSON-LD (`VideoObject`, `Periodical`, `ItemList`) | Fait (contenu de démonstration) |
| Navigation rapide | 07-11 | Layout Blade, Turbo, îlots Vue remontés sur `turbo:load` | Fait (Turbo 8.0.23) |
| Réglages Analytics | 12-14 | Page Settings, validation des identifiants, gabarit de snippet derrière `data-consent-cat` | Fait (aperçu, rien n'est exécuté) |
| Analytics et exports | 15-21 | Service de lecture selon Q7, tableau de bord, exports CSV/JSON/PDF, pas de chiffres inventés (INV-08) | Fait avec données **synthétiques** |
| Administration newsroom | 22-27 | Livewire par module, policies par module et action, planificateur Laravel pour la publication | Fait (localStorage) |
| Audit, sauvegardes | 28-30 | Table `audit_log` sans route de modification, commande `backup:run` planifiée, restauration confirmée | Fait (instantané navigateur) |
| Transverses | 31-38 | Tailwind adaptatif, SEO par partials Blade, blocs article en composants | Fait pour 31, 35 à 38 ; 32 non vérifié hors Edge |

## 5. Exigences non fonctionnelles (NFR)

| NFR | Exigence | Source / statut | Vérification |
|---|---|---|---|
| **Performance** | Chargement rapide, navigation sans rechargement, images en `srcset` + lazy, WebP | EX-08, EX-09 ; **seuil chiffré non fixé** (le « Lighthouse > 90, < 2 s » de `CLAUDE.md` n'a pas de source, Q6) | Lighthouse accueil, article, podcasts, **à exécuter** (M1) |
| **Hébergement** | Fonctionner sans Node ni Redis ; planificateur par cron | EX-39 | Déploiement d'essai sur le cPanel (Q9) |
| **Sécurité** | Limitation de débit connexion/inscription/OTP, requêtes paramétrées, échappement `{{ }}`, CSRF, `$fillable`, cookies HttpOnly + SameSite, secrets hors dépôt, mots de passe hachés (Bcrypt/Argon2), fichiers téléversés validés et renommés, pas de trace d'exception en production | EX-33, INV-04, INV-09 ; valeurs numériques (longueur minimale, délai OTP « par ex. 10 minutes ») **à confirmer** | Revue de sécurité, tests Pest, analyse des dépendances |
| **Confidentialité (RGPD)** | Consentement explicite horodaté, minimisation, export et suppression des données, scripts tiers bloqués avant consentement (INV-05) | EX-34 ; **durées de conservation : décision du DPO** (Q8) | Parcours R9 du PRD, signature du DPO |
| **Accessibilité** | WCAG 2.2 AA, lien d'évitement, focus visible, `alt` et `title` sur chaque image | `TECHNICAL.md` §5, EX-35 | axe (0 violation sur les pages auditées du prototype), à rejouer sur les pages ajoutées (M3) |
| **Compatibilité** | Navigateurs modernes ; mise en page bureau, portable, tablette, mobile | EX-31, EX-32 ; **seul Edge/Chromium testé**, Firefox et Safari non testés | Matrice de test (M2) |
| **SEO** | Titre/description uniques, canonical, hreflang, Open Graph, Twitter Card, JSON-LD, sitemaps, robots | EX-35 | Contrôle automatique des pages générées + Search Console |
| **Internationalisation** | FR par défaut ; tout texte en FR et EN ; publication FR seule autorisée sans traduction (INV-10) | EX-36, INV-07, INV-10 | Tests de bascule de langue |
| **Intégrité et traçabilité** | Journal d'audit non modifiable ; archivage logique plutôt que suppression ; identifiants publics non séquentiels | INV-01 à INV-03 | Tests : aucune route d'update/delete sur `audit_log` |
| **Disponibilité et reprise** | Sauvegardes automatique et manuelle, restauration testée périodiquement, chiffrement | EX-29, EX-30 ; fréquence et rétention **à fixer par le propriétaire avec le DPO** | Restauration sur copie |
| **Maintenabilité** | Un module par session ; tests Pest/PHPUnit ; commentaires qui expliquent le pourquoi ; documentation à jour | `CLAUDE.md` | `php artisan test`, `docs/PROGRESS.md` |
| **Observabilité** | Journaux applicatifs sans données personnelles ; erreurs 500 sans trace côté visiteur | EX-33 | Revue des journaux |

## 6. Stratégie de test et de recette

- **Unitaires et fonctionnels** : Pest/PHPUnit par module (droits, planification, import, assainissement, audit).
- **Navigateur** : Playwright (déjà utilisé sur le prototype : 74 vérifications admin, navigation Turbo, popup) ; axe pour l'accessibilité ; Lighthouse pour les mesures.
- **Recette** : les parcours R1 à R10 du PRD, exécutés par quelqu'un qui n'a pas écrit le produit.
- **Données du prototype** : tout chiffre non mesuré porte l'étiquette « démo » (INV-08).

## 7. Risques et dépendances ouvertes

| Risque | Effet | Action | Destinataire |
|---|---|---|---|
| Version de PHP ou extensions insuffisantes sur le cPanel | Blocage du déploiement | Vérifier avant la phase 1 | Propriétaire (Q9) |
| Pas de worker permanent | Files et planification moins réactives | Cron à la minute, envoi synchrone pour le critique | Équipe technique |
| Source des statistiques non choisie | Phase 6 bloquée ; chiffres de démonstration trompeurs | Décider GA4 API, journal interne ou les deux | Propriétaire (Q7) |
| Contenus Magazine/Radios/TV sans source ; sites officiels non ouverts | Informations erronées publiées | Relecture avant publication | Rédacteur en chef (Q4) |
| Durées de conservation et transferts GA4/Meta | Non-conformité RGPD | Signature avant la phase 7 | DPO ou juriste (Q8) |
| Firefox et Safari non testés | Défauts invisibles aujourd'hui | Matrice de test en phase 0 | Équipe technique |

## 8. Hors périmètre et « plus tard » (reprise du PRD §5)

- **Hors périmètre** : boutique, paiement, factures, abonnements payants, gains d'auteur ; AMP, Web Stories, RTL ; application mobile native ; authentification réelle dans le prototype (c'est un module Laravel).
- **Plus tard** : quiz, sondages, recettes, évènements, galerie ; agrégateur RSS et génération IA ; lecture en direct réelle des radios et télévisions (aucune source de flux, droits à négocier, Q4).
- **Écartés par décision** : Inertia avec SSR (pas de Node) ; Bootstrap (Tailwind retenu).

<p align="center"><img src="docs/social-preview.png" alt="Africa Global Press : site d'information panafricain et console de rédaction" width="100%"></p>

# Africa Global Press

**Média d'information panafricain, bilingue français / anglais.** Site public d'actualités (articles, podcasts,
vidéos, magazine, annuaires de radios et de télévisions) et console d'administration complète pour la rédaction,
construits avec Laravel 12 pour un hébergement cPanel mutualisé.

En ligne : **[pressafrica.media](https://pressafrica.media)** · Projet personnel, Bénin.

---

## Le projet

Africa Global Press couvre l'actualité du continent africain : politique, économie, société, culture, sport,
science et technologie. Le projet est parti d'une **maquette HTML complète** (environ 170 pages générées), devenue
la spécification exécutable du produit, puis porté sur **Laravel** page par page, en gardant exactement le même
rendu.

- Contenu de départ : **64 articles en français et 64 en anglais**, 3 émissions de podcast, 8 épisodes, 8 vidéos,
  6 numéros de magazine, 6 radios et 8 chaînes de télévision.
- Rendu public **identique à la maquette**, vérifié par un outil de comparaison HTML automatique.
- **154+ tests automatisés** et 10 parcours de recette du cahier des charges.

## Fonctionnalités

### Site public
| | |
|---|---|
| **Actualités bilingues** | Chaque article existe en FR et EN, avec passage d'une langue à l'autre sur la même page |
| **Rubriques et page d'accueil** | Carrousel « à la une », bandeau Flash Infos, dernières actualités, sélection éditoriale, rubriques thématiques |
| **Médias** | Podcasts (lecteur audio), vidéos, magazine, annuaires de radios et de chaînes de télévision |
| **Commentaires et questions-réponses** | Les lecteurs commentent ; la rédaction répond publiquement, avec son nom et un badge ; le lecteur peut être prévenu par e-mail |
| **Newsletter** | Inscription avec double confirmation, choix des thèmes, désinscription en un clic |
| **Recherche, contact, pages légales** | Recherche instantanée, formulaire de contact, mentions légales, confidentialité, cookies |
| **Navigation sans rechargement** | Hotwire Turbo, préchargement au survol, transitions de page |
| **Mode sombre** et **accessibilité** | Thème clair / sombre, navigation au clavier, contrastes AA, Lighthouse accessibilité 100 |
| **Bandeau de consentement** | Catégories mesure d'audience, réseaux sociaux et publicité : rien ne se charge sans accord |
| **SEO** | Adresses propres (`/fr/politique`), sitemap, sitemap Google News, flux RSS, hreflang, données structurées, `llms.txt` |
| **PWA** | Installable sur mobile, consultation hors ligne des pages déjà visitées |

### Console d'administration
| | |
|---|---|
| **Articles** | Éditeur riche, brouillon / relecture / planification / publication, rubriques, mots-clés, image de couverture |
| **Mise en avant** | Carrousel d'accueil, sélection éditoriale et emplacements de la page d'accueil |
| **Médiathèque** | Téléversement, conversion automatique en WebP, plusieurs tailles, suppression des données EXIF |
| **Médias** | Podcasts, vidéos, magazine, radios, télévisions |
| **Modération** | File des commentaires, réponses de la rédaction, filtres anti-spam et anti-robots |
| **Menus** | Menu principal et méga-menus éditables |
| **Publicité** | Zones publicitaires (bandeau, dans l'article, barre latérale), statistiques d'affichages et de clics |
| **Newsletter** | Abonnés, campagnes rédigées, testées puis envoyées par lots via la tâche planifiée |
| **Audience** | Statistiques internes sans cookie ni adresse IP : pays, pages, recherches, sources ; export CSV / JSON ; GA4 prêt mais désactivé |
| **Équipe** | Comptes, rôles et permissions par module (`module.action`), écran de gestion des droits |
| **Journal d'audit** | Toutes les actions sensibles, en ajout seul : rien ne peut être modifié ni effacé |
| **Sauvegardes** | Chiffrées (AES-256-GCM), quotidiennes et hebdomadaires, restauration testée |
| **RGPD** | Export et effacement des données d'une personne ; anonymisation des comptes en « ancien auteur » |
| **Réglages** | Identité du site, sécurité, mode maintenance, newsletter, PWA, mesure d'audience |

### Sécurité et exploitation
- Connexion protégée contre la force brute (par compte et par adresse), mots de passe robustes vérifiés.
- En-têtes de sécurité, HTTPS forcé, cookies chiffrés `Secure` / `HttpOnly` / `SameSite`.
- Tout contenu HTML nettoyé (HTML Purifier) avant enregistrement.
- Pages d'erreur sur mesure (403, 404, 419, 429, 500, 503) et mode maintenance.
- Cache des pages publiques, vidé automatiquement à chaque modification.
- Une seule tâche cron pour tout : publications planifiées, envoi de la newsletter, sauvegardes.

## Technologies

| Couche | Choix |
|---|---|
| Back-end | **Laravel 12**, PHP 8.3, MySQL (SQLite en local) |
| Administration | Livewire 3, Tailwind CSS, Quill, Chart.js |
| Site public | Blade + markup de la maquette, Hotwire Turbo, JavaScript sans framework |
| Droits | spatie/laravel-permission |
| Sécurité du contenu | mews/purifier (HTML Purifier) |
| Qualité | PHPUnit, Playwright, Lighthouse, outil maison de parité HTML |
| Hébergement | cPanel mutualisé, sans Node ni Redis sur le serveur |

## Performances mesurées

| Page (PageSpeed Insights, production) | Performance | Accessibilité | Bonnes pratiques | SEO |
|---|---|---|---|---|
| `/fr` sur ordinateur | **100** | **100** | **100** | 100 |
| `/fr` sur mobile | 95 | 97 → 100 (contraste corrigé) | **100** | 92 |

## Structure du dépôt

```
pressafrica/
├── pressmedia_laravel/   Le produit : application Laravel (site public + administration)
│   ├── app/              Contrôleurs, modèles, middlewares, commandes planifiées, services
│   ├── resources/        Vues Blade, chrome exporté de la maquette, traductions
│   ├── database/         Migrations et jeux de données de départ
│   ├── tests/            Tests automatisés et parcours de recette
│   └── docs/             Suivi d'avancement (PROGRESS.md), checklist de déploiement (DEPLOY.md)
├── prototype_html/       Maquette HTML complète : la spécification exécutable du rendu
├── docs/                 Cahier des charges (PRD), plan, document technique, recette, guide de mise en ligne
└── tools/                Générateurs de la maquette, outils de parité, Lighthouse et recette
```

## Décisions du client

| Sujet | Décision |
|---|---|
| Rendu public | Strictement celui de la maquette : aucune refonte |
| Hébergement | cPanel mutualisé : pas de Node ni de Redis, une seule tâche cron |
| Navigation | Hotwire Turbo sur le site public ; Livewire pour l'administration |
| Langues | Français par défaut, anglais ; aucune détection automatique de la langue du navigateur |
| Adresses | Propres, sans `.html` ; les anciennes adresses redirigent (301) |
| Mesure d'audience | Journal interne sans cookie ni IP ; Google Analytics 4 intégré mais désactivé jusqu'à l'avis du DPO |
| Publicité | Zones prêtes, inactives jusqu'aux vrais codes AdSense, soumises au consentement « publicité » |
| Commentaires | Réponses des lecteurs et de la rédaction (nom + badge) ; e-mail au lecteur s'il l'a demandé ; pas de durée de conservation, suppression par l'administrateur ; protections anti-robots renforcées |
| Fuseau horaire | Bénin (Africa/Porto-Novo) |
| Comptes de départ | Créés aussi en production, à changer dès la mise en ligne |

## Documentation

- [Cahier des charges](docs/PRD.md) · [Plan d'implémentation](docs/IMPLEMENTATION-PLAN.md) · [Document technique](docs/TECHNICAL.md)
- [Rapport de recette](docs/RECETTE.md) · [Guide de mise en ligne](docs/MISE-EN-LIGNE.md)
- [Avancement module par module](pressmedia_laravel/docs/PROGRESS.md)

---

<sub>Projet personnel · © Africa Global Press · Bénin</sub>

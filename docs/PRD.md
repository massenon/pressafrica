# Africa Global Press — Document d'exigences produit (PRD)

Version 1 — 2026-10-28. Périmètre : site public (prototype HTML et port Laravel) et back-office.
Convention de statut : **DOCUMENTÉ** (une source écrite le porte), **PROPOSÉ** (personne ne l'a demandé, attend une validation nommée), **ARBITRÉ** (une décision l'a tranché, auteur et date cités). Aucune exigence n'a deux statuts.

---

## 1. Objet et lecteurs

Ce PRD sert à **décider** quatre choses, par le propriétaire du produit (le demandeur des messages du 2026-10-28, appelé « le propriétaire » ci-dessous) :

1. quelles exigences sont réellement demandées et lesquelles ne sont que des propositions de l'équipe technique ;
2. quelle pile technique porte le site public Laravel (§6, EX-07 à EX-11) ;
3. quels modules d'administration manquent encore (§6, EX-22 à EX-30) ;
4. ce qui attend une signature de professionnel (DPO / juriste) avant toute mise en production d'outils de mesure (§9).

Lecteurs secondaires : l'équipe de développement (recette §8, invariants §5) et la rédaction (contenus des rubriques, §6 EX-02).

---

## 2. Sources

| # | Document | Nature | Autorité | Lu |
|---|---|---|---|---|
| S1 | Messages du propriétaire dans la session de travail, **tous datés du 2026-10-28** (citations en §6) | Demande du client / propriétaire du produit | **Fait foi** sur le périmètre et les priorités | Intégralement |
| S2 | `keyfeatures.txt` (80 lignes) | Liste de fonctionnalités de plateforme, en anglais. Origine non documentée dans le dépôt ; son vocabulaire (« Key Features », « professional PRDs », « Bootstrap 5 ») ressemble à une fiche produit d'éditeur de script, pas à un cahier des charges signé | Référence fournie ; **pas contractuelle à ce jour** (voir Q1) | Intégralement |
| S3 | `docs/TECHNICAL.md` (mis à jour 2026-10-26), §7 back-office, §8 pile cible, §9 qualité | Documentation technique, **rédigée par l'équipe technique/assistant** | Descriptive ; ses « décisions » (§8) ne sont pas signées par le propriétaire | §1-2, 5-11 |
| S4 | `pressmedia_laravel/CLAUDE.md` (2026-10-26), lignes 5-9 (pile) et 11-17 (règles) | Consigne de travail du dépôt Laravel, **rédigée par l'assistant** | Idem S3 | Intégralement |
| S5 | `pressmedia_laravel/docs/PROGRESS.md` | Suivi d'avancement (lignes 1-30) | Factuelle | Intégralement |
| S6 | `FOXIZ.txt` (571 lignes) | Texte commercial du thème WordPress Foxiz | **Étalon visuel et fonctionnel, pas une exigence** | **Parcouru par mots-clés seulement** (Analytics l.70, 315 ; GDPR l.544-545 ; backups l.194) ; non lu en entier |
| S7 | `prototype_html/data/articles.json` (114 éléments : 72 articles, 42 pages) issu du dump WordPress `exxhrgce_wp623.sql` | Données du site actuel pressafrica.media | Source des contenus éditoriaux existants | `articles.json` mesuré ; **le dump SQL n'a pas été relu directement** |
| S8 | `clone/` (34 entrées : 9 pages HTML capturées + leurs dossiers) et `data/clone_pages.json` | Captures du site actuel | Référence de mise en page | Non relues pour ce PRD |
| S9 | Code mesuré : `prototype_html/assets/js/admin*.js`, `tools/build_site.py`, `tools/site_check.py`, `pressmedia_laravel/app/Models`, migrations | Existant | Factuel | Mesures en §3 |

**Non lus ou non disponibles :** aucun cahier des charges signé, aucun contrat, aucune étude du trafic réel du site actuel, aucun accès aux statistiques GA existantes. Le propriétaire dit « sauvegarde » et « analytics » sans fournir de volumétrie.

### Anomalies entre sources (conservées, non corrigées)

| # | Anomalie | Sources en conflit | Qui fait foi | Suite |
|---|---|---|---|---|
| A1 | Front public : « Vue 3, **Inertia 2**… **No server-side rendering** » contre « Blade + îlots Vue, **pas d'Inertia SSR** » | S3 §8 (l.97) contre S4 (l.8-9) | **Inconnu.** S4 est postérieur de forme mais écrit par l'assistant et jamais validé par le propriétaire. S3 §8 lui-même note le conflit (point 1, l.100) | Q2 |
| A2 | « Responsive Design : **Bootstrap 5** » contre prototype **Tailwind 3.4** ; admin cible « Livewire… **Bootstrap** » | S2 l.6 ; S3 §1 (l.9) et §8 (l.96, point 2 l.101) ; S4 l.6 | Inconnu pour le public. Pour l'admin, S3 §8 prévoit un portage des jetons Tailwind vers Bootstrap | Q3 |
| A3 | « **Zéro cookie de suivi natif** » contre GA4, Meta Pixel, Search Console demandés | S2 l.52 contre S1 (EX-12) | Compatibles seulement si les scripts tiers restent bloqués avant consentement (S2 l.53) ; interprétation PROPOSÉE en EX-14, à valider par le DPO | Q8 |
| A4 | « Livewire 3.6 » demandé contre **3.8** installé | S3 §8 (l.96) ; S5 l.30 | Installé fait foi ; `^3.6` l'autorise, pas de décision à prendre | — |
| A5 | « Lighthouse > 90, < 2 s » inscrit dans S4 | S4 l.9 | **Aucune source.** Chiffre non retenu comme exigence (voir EX-11) | Q6 |
| A6 | `site_check.py` interdit tout lien externe hors hôtes sociaux (ligne 13), alors que les annuaires Radios/TV ont besoin de liens sortants | S9 contre EX-04 | Règle de l'outil, non du propriétaire | Q5 |
| A7 | 60 permissions annoncées (12 modules × 5 actions) : le total changera avec les nouveaux modules | S5 l.6 ; mesure §3 | Recalculé au seed | — |
| A8 | Commentaires d'articles : le formulaire public écrit dans `localStorage` clé `agp-comments`, le module admin lit le magasin `comments` | S9 | Deux clés distinctes : **aucune jonction** | EX-26 |

---

## 3. Contexte et existant (mesuré le 2026-10-28)

**Site public prototype** (`prototype_html/`) : 85 pages FR + 85 pages EN (dont 64 articles par langue) + `index.html` de redirection = 171 pages générées par `tools/build_site.py`. Vérification `tools/site_check.py` : « OK: no problems » (29 840 liens, 4 750 images). Rubriques Magazine, Radios africaines, Télévision africaine : bloc « Bientôt sur Africa Global Press » toujours affiché (`landing_body`). Podcasts (8 épisodes, audio synthétique) et **Vidéos** (8 clips MP4 de 10 s générés par `tools/make_videos.py`, **contenu de démonstration**) sont peuplés.

**Contenus source (S7)** : 114 éléments (72 articles, 42 pages). **Aucun article dans une catégorie magazine, radios ou télévision.** Les catégories WordPress `television-fr` / `television` sont rattachées à la page **Vidéos** (`CATS["videos"].db`), et `magazine-fr` / `magazine-en` n'ont aucun contenu ; `radios` et `television` n'ont aucune catégorie source (`db=[]`). Tout contenu de ces trois rubriques est donc **sans source** (EX-02).

**Back-office prototype** : 14 pages HTML dont login = 13 pages fonctionnelles (`ads, authors, categories, comments, featured, index, media, menus, newsletter, podcasts, post-edit, posts, settings`). 12 modules de permission (`posts, categories, media, featured, comments, newsletter, ads, podcasts, menus, settings, users, ai`) × 5 actions (voir, créer, modifier, supprimer, publier/approuver) = 60. 4 rôles : Administrator, Editor, Author, Moderator. Réglages : 8 onglets (General, Appearance, Languages, Privacy, Security, Maintenance, PWA, AI) — **pas d'onglet Analytics**. Tableau de bord : 6 cartes (Published, In review, Drafts, Scheduled, Total views, Comments to moderate) + Subscribers pour les non-auteurs, courbe de vues sur 30 jours, top catégories, file éditoriale, derniers articles. **La courbe est synthétique** (fonction `series()` déterministe, graine par article) ; les vues par article sont des valeurs de seed. **Aucune statistique par pays, par terme de recherche, par appareil, par source de trafic, par langue ; aucun export de rapport analytics ; pas de module Tags (les étiquettes existent dans l'éditeur d'articles seulement), Audit, Sauvegardes, Analytics** (recherche des mots *audit, backup, analytics, device, search term* dans `admin*.js` : 0 occurrence). Le mot *country* n'apparaît que dans `admin-formats.js` (constructeurs de formats d'articles).

**Application Laravel** (`pressmedia_laravel/`) : 9 modèles (`Category, CategoryTranslation, Post, PostTranslation, PodcastShow(+Translation), PodcastEpisode(+Translation), User`), 6 migrations. Rôles/permissions seedés (S5 l.6). Aucun modèle Comment, Ad, Media, Tag, Audit, Backup, Video, Magazine, Radio, Television. Pages publiques, authentification, import : « todo » (S5 l.8-12). Sécurité, RGPD, SEO : lignes « todo » ajoutées le 2026-10-28 (S5 l.13-26).

**Tests de navigateur** : Microsoft Edge (Chromium) seulement (S3 §9, l.106-113). **Firefox et Safari n'ont jamais été testés.**

---

## 4. Périmètre

Dans ce PRD :
- site public FR/EN : pages, rubriques, article, partage, commentaires, newsletter, SEO ;
- back-office : modules de contenu, rubriques éditoriales (Magazine, Radios, Télévision, Vidéos), audience (commentaires, newsletter), analytics et réglages de mesure, utilisateurs et permissions, journalisation, sauvegardes ;
- port du prototype vers Laravel (module par module, S4 l.16).

## 5. Hors périmètre et « plus tard »

| Élément | Classement | Motif |
|---|---|---|
| Boutique, paiement, factures, abonnements payants, gains d'auteur (S2 l.65, l.69) | **Hors périmètre** | Non demandés par le propriétaire en S1 ; aucune donnée source |
| Quiz, sondages, recettes, évènements, galerie (formats de S2 l.25) | **Plus tard** | Un constructeur existe dans le prototype (`admin-formats.js`) ; non redemandé le 2026-10-28 |
| Agrégateur RSS, génération IA (S2 l.35, l.77) | **Plus tard** | Un onglet IA existe dans les réglages ; non redemandé |
| Lecture en direct réelle des radios et télévisions (flux audio/vidéo tiers) | **Plus tard** | Aucune source de flux ; nécessite droits et contrats (Q4) |
| AMP, Web Stories, RTL (S6) | **Hors périmètre** | S6 est un étalon, non une exigence |
| Application mobile native | **Hors périmètre** | Non demandée ; la PWA existe |
| Authentification réelle dans le prototype | **Hors périmètre** | Le prototype est une maquette (« no real authentication ») ; l'authentification est un module Laravel (S5 l.8) |

---

## 5 bis. Invariants (déclarés une fois, les exigences y renvoient)

| # | Invariant | Source / statut |
|---|---|---|
| INV-01 | Les identifiants publics ne sont jamais séquentiels ni modifiables : UUID/ULID ou slug stable. | Demande S1 (liste sécurité collée) — DOCUMENTÉ |
| INV-02 | Toute écriture d'administration est journalisée (qui, quoi, quand, avant/après) et le journal n'est pas modifiable depuis l'interface. | S1 « audits journalisation des logs » ; S3 §8 (`audit_log` dans le modèle de données suggéré) — DOCUMENTÉ |
| INV-03 | Archivage logique à la place de la suppression pour tout contenu éditorial et tout commentaire ; la suppression physique est réservée à l'effacement RGPD. | Aucune source écrite — **PROPOSÉ** |
| INV-04 | Tout HTML saisi est assaini avant enregistrement ; aucun rendu non échappé de donnée utilisateur. | S4 l.13 ; S1 liste sécurité ; S2 l.59 — DOCUMENTÉ |
| INV-05 | Aucun script tiers (GA4, GTM, Meta Pixel, publicités, vidéos tierces) ne s'exécute avant consentement de sa catégorie. | S2 l.53 ; mécanisme existant `data-consent-cat` — DOCUMENTÉ |
| INV-06 | Les droits se testent par permission `module.action`, jamais par nom de rôle. | S4 l.14 — DOCUMENTÉ |
| INV-07 | Langue par défaut : français ; thème par défaut : clair, sur le site public **et** l'administration ; le choix du visiteur mémorisé l'emporte ; ni la langue du navigateur ni la préférence du système ne servent de défaut. | S1 2026-10-28 (§6 EX-36) — ARBITRÉ |
| INV-08 | Toute donnée chiffrée du prototype qui n'est pas mesurée est étiquetée « démo » dans l'interface. | Proposé par ce PRD — **PROPOSÉ** |
| INV-09 | Tout lien sortant porte `rel="noopener"` ; les liens issus de contenus utilisateurs portent aussi `nofollow`. | S2 l.57 ; règle de `site_check.py` — DOCUMENTÉ |
| INV-10 | Les textes existent en FR et EN ; un enregistrement sans traduction EN se publie seulement en FR et le signale. | S4 l.12 ; comportement actuel de `build_site.py` — DOCUMENTÉ |

---

## 6. Exigences

### A. Rubriques Magazine, Radios africaines, Télévision africaine, Vidéos

**EX-01 — Retirer le bloc « Bientôt sur Africa Global Press » des rubriques Magazine, Radios et Télévision.**
Source : S1, « Les rubriques Magazine, Radios et Télévision affichent toujours le bloc « Bientôt ». … IL FAUT LE FAIRE AUSSI ». · Statut : **DOCUMENTÉ** · État : non fait.

**EX-02 — Ces trois rubriques sont peuplées avec des données de démonstration FR/EN, étiquetées comme telles.**
Source : aucune (S7 : zéro contenu). Contenu envisagé : numéros de magazine (couverture, titre, date, sommaire), annuaire de stations de radio, annuaire de chaînes, rattachés aux articles existants quand c'est pertinent. · Statut : **PROPOSÉ** · Validation : le rédacteur en chef (Q4). Ne pas présenter ces contenus comme réels.

**EX-03 — Un module d'administration par rubrique (Magazine, Radios, Télévision) : liste, création, modification, statut brouillon/publié, FR et EN, export, archivage.**
Source : S1, « creer leur module de gestion dans admin ». Modèle : le module Podcasts (`admin-podcasts.js`, S3 §6). · Statut : **DOCUMENTÉ**.

**EX-04 — Les annuaires Radios/TV peuvent porter un lien sortant vers le site officiel de chaque station ou chaîne.**
Source : aucune. Conflit : A6 (`site_check.py` bloque les liens externes hors réseaux sociaux). Conforme à INV-09 si adopté. · Statut : **PROPOSÉ** · Validation : propriétaire (Q5).

**EX-05 — Page Vidéos peuplée (lecteur, grille filtrable par rubrique, menu d'en-tête, JSON-LD `VideoObject`), sans bloc « Bientôt ».**
Source : S1, « peupler des donnees pour …/fr/videos.html et dans header … enelever ceci Bientôt sur Africa Global Press ». · Statut : **DOCUMENTÉ** · État : livré dans le prototype le 2026-10-28 ; **les 8 vidéos sont des clips muets de 10 s générés à partir de photos d'articles**, à remplacer par de vraies vidéos.

**EX-06 — Un module d'administration Vidéos (même forme que EX-03).**
Source : aucune en tant que telle ; déduit par symétrie des trois autres rubriques. · Statut : **PROPOSÉ** · Validation : propriétaire.

### B. Pile technique du site public et rapidité de chargement

**EX-07 — Pages publiques rendues côté serveur en Blade, avec des îlots Vue montés sur `[data-island]` seulement.**
Source : S4 l.8-9 (décision de l'assistant, 2026-10-26). Contredit S3 §8 l.97 (Inertia, sans SSR) : voir A1. Raisons avancées par S4 : pages d'actualité à indexer, pas de serveur Node en production. · Statut : **ARBITRÉ** — propriétaire, 2026-10-28 (« OUI » à la recommandation Blade + îlots Vue + Turbo Drive) ; hébergement cPanel mutualisé sans Node : voir EX-39. Conséquence si refusé : Inertia sans SSR laisse une coquille vide aux robots sans JavaScript (S3 §8 point 1, l.100), ce qui pénalise l'indexation d'un site d'actualité.

**EX-08 — Les pages publiques se chargent vite et la navigation entre pages ne doit pas donner l'impression d'un rechargement complet.**
Source : S1, « Pages publiques en Blade : accueil, catégorie, article, podcasts (FAST LOAD SANS RECHARGEMENT) » et « QUEL EST LE STACK TECHNIQUE RETENU ». · Statut : **DOCUMENTÉ** (le besoin ; les solutions sont EX-09 à EX-11).

**EX-09 — Socle sans JavaScript applicatif, à adopter dans tous les cas.** Éléments : compatibilité bfcache (pas de `unload`, pas de `Cache-Control: no-store` sur les pages publiques) ; `@view-transition { navigation: auto }` (**déjà présent** dans `src.css`) ; préchargement par règles de spéculation (*Speculation Rules*, Chromium seulement, dégradation silencieuse ailleurs) ; cache HTTP/page côté serveur ; en-têtes de cache longs sur `assets/` (S2 l.17 « Hybrid Cache System », S3 §10) ; images responsives, WebP et chargement différé (déjà en place, S2 l.19-20). · Statut : **PROPOSÉ** · Validation : propriétaire.

**EX-10 — Navigation sans rechargement complet : choisir entre trois options après mesure (EX-11).**

| Option | Pour | Contre |
|---|---|---|
| **Livewire 3 `wire:navigate`** avec préchargement au survol (`wire:navigate.hover`) | Livewire est déjà une dépendance (installé en 3.8, A4) ; pas de nouvel outil | Charge le JS Livewire sur le public alors qu'il est prévu pour l'administration ; les îlots Vue doivent se remonter à l'évènement `livewire:navigated` ; poids à mesurer |
| **Hotwire Turbo Drive** | Conçu pour ce seul besoin ; amélioration progressive | **Nouvelle dépendance** à maintenir ; même problème de remontage des îlots |
| **Inertia avec SSR** | Vraie navigation SPA | **Écartée par S4** (serveur Node en production, coût d'exploitation) ; contredit EX-07 |

Sans l'une de ces options, EX-09 seul reste valable : navigation classique, rapide, sans coquille vide. · Statut : **ARBITRÉ** — propriétaire, 2026-10-28 : **Hotwire Turbo Drive retenu** (« OUI » à l'offre de l'ajouter au prototype). Livewire `wire:navigate` reste réservé à l'administration. Mesuré dans le prototype : Turbo minifié 92 Ko, 25,7 Ko en gzip (esbuild + gzip -9). Reste à faire : EX-11 (Lighthouse) pour fixer les seuils, pas pour choisir l'outil.

**EX-11 — Porte de décision par mesure : un comparatif Lighthouse (pages accueil, article, podcasts) entre le socle EX-09 seul et chacune des options EX-10, avant tout choix.**
Aucun seuil n'est fixé ici : le « Lighthouse > 90, < 2 s » de S4 n'a aucune source (A5). Le propriétaire fixe les seuils **après** avoir vu les mesures (Q6). **Cette mesure n'a pas été exécutée.** · Statut : **PROPOSÉ**.

**EX-39 — Contrainte d'hébergement : cPanel mutualisé, sans serveur Node et sans Redis.**
Source : S1, « jai un hebergeemnt cpanel mutualiser pas de serveur Node en production » puis « Redis n'est pas proposé » (2026-10-28). · Statut : **DOCUMENTÉ**. Conséquences à porter dans le port Laravel : assets compilés avant envoi (`npm run build` hors serveur) ; racine web sur `public/` ; planificateur par cron cPanel (`schedule:run` chaque minute) ; pas de worker permanent (files par cron `queue:work --stop-when-empty` ou envoi synchrone) ; cache et sessions en fichiers ou base de données ; compression et en-têtes de cache longs sur les assets. **Non vérifié** : hébergeur, version de PHP, SSH, extensions disponibles (question Q9).

### C. Réglages Analytics

**EX-12 — Onglet « Analytics » dans Réglages : identifiant GA4, vérification Search Console, Meta Pixel, et réglages RGPD associés.**
Source : S1, « ADD DANS ADMIN SETTINGS : Analytics (GA4, Search Console, Pixel Meta, RGPD, etc...) » ; S6 l.315 (étalon : intégration Google Analytics). · Statut : **DOCUMENTÉ**.

**EX-13 — Champs complémentaires de l'onglet Analytics : GTM, vérification Bing, mode de consentement (*consent mode*), anonymisation de l'IP, respect de *Do Not Track*, durée de conservation, exclusion du personnel connecté.**
Source : aucune (le « etc... » du propriétaire n'énumère pas). · Statut : **PROPOSÉ** · Validation : propriétaire pour la liste ; DPO pour les valeurs par défaut (Q8). Aucune durée n'est fixée ici.

**EX-14 — Les scripts de mesure et de publicité ne se chargent qu'après consentement de leur catégorie (analytique, réseaux sociaux/publicité) ; sans consentement, aucun cookie ni requête tiers.**
Source : S2 l.52-53 ; mécanisme déjà présent (`<script type="text/plain" data-consent-cat>` activés par `app.js`, bannière avec trois catégories). Réconcilie A3 : « zéro cookie natif » signifie que la plateforme elle-même n'en pose pas. · Statut : **DOCUMENTÉ** pour le blocage ; l'interprétation d'A3 reste à valider par le DPO (Q8).

### D. Analytics, rapports et visualisation

**EX-15 — Le tableau de bord admin montre au minimum les vues par pays, par termes de recherche et par thématique (rubrique).**
Source : S1, « Tableaux de bord a tt les statistiques (vues par pays, par recherche, par thematiques, par etc.....) ». · Statut : **DOCUMENTÉ** · État : **absent** (§3).

**EX-16 — Autres dimensions : par langue, par type d'appareil, par source de trafic, articles les plus lus, évolution dans le temps, commentaires, inscriptions newsletter.**
Source : le « etc..... » de S1 sans liste. · Statut : **PROPOSÉ** · Validation : propriétaire.

**EX-17 — Les rapports analytics sont exportables.**
Source : S1, « exports des rapports analytics ». · Statut : **DOCUMENTÉ** · État : seuls les articles, la newsletter et les podcasts ont un export (CSV/JSON), pas les statistiques.

**EX-18 — Formats d'export : CSV, JSON et impression/PDF d'un rapport à période et filtres choisis ; les totaux de l'export égalent ceux de l'écran.**
Source : aucune sur les formats (S2 l.75 évoque CSV/JSON pour la newsletter seulement). · Statut : **PROPOSÉ**.

**EX-19 — Source réelle des chiffres en production.**
Dans le prototype, **tous les chiffres d'audience sont synthétiques** (INV-08) : le prototype ne peut pas prouver leur justesse. En production il faut une source : (a) l'API de données GA4, ou (b) un journal de visites interne au site (premier parti), ou (c) les deux. Cette décision change l'hébergement, le RGPD (EX-14) et la précision des « vues par pays/recherche ». · Statut : **PROPOSÉ** · Validation : propriétaire (Q7), consultation du DPO (Q8).

**EX-20 — Clics publicitaires : nombre de clics et taux de clic par zone publicitaire et par page.**
Source : S1, « ads clicks » ; S2 l.42 (zones publicitaires illimitées, bureau/mobile, AdSense et bannières). · Statut : **DOCUMENTÉ** · État : les zones existent (`admin-ads.js`, un emplacement vide a été ajouté dans la colonne latérale des articles) ; **aucun comptage de clics ni d'affichages**.

**EX-21 — Widgets de visualisation avancée : courbes, barres, cartes de chiffres, tableaux, rapports.**
Source : S1, « Advanced Data Visualization Widgets – Includes charts, analytics widgets, statistics cards, and reports ». · Statut : **DOCUMENTÉ** · État : courbe SVG, barres de catégories et cartes de chiffres existent ; le projet cible parle de Chart.js (S3 §8 l.96). Tous les graphiques gardent une alternative tableau (accessibilité, déjà le cas pour la courbe).

### E. Modules d'administration

**EX-22 — La barre latérale est mise à jour avec les nouveaux modules.**
Source : S1, « ajoute des modules et update de sidebar des modules ». · Statut : **DOCUMENTÉ**. Regroupement proposé (**PROPOSÉ**, à valider) :

| Groupe | Entrées |
|---|---|
| Vue d'ensemble | Tableau de bord, **Analytics** |
| Rédaction | Articles, Catégories, **Tags**, Médiathèque, À la une et widgets |
| Rubriques | Podcasts, **Vidéos, Magazine, Radios, Télévision** |
| Audience | Commentaires, Newsletter, Auteurs et rôles |
| Site | Zones publicitaires, Menus, Réglages |
| Système | **Journal d'audit, Sauvegardes** |

**EX-23 — Gestion des tags (création, fusion, suppression, comptage d'usage).**
Source : S1, « Gestion des articles, catégories, tags et médias » ; S2 l.32 (« keyword tagging »). · Statut : **DOCUMENTÉ** · État : étiquettes saisies dans l'éditeur, **pas de module**.

**EX-24 — Gestion des articles, catégories et médias.**
Source : S1 (même phrase) ; S2 l.25-31, l.36. · Statut : **DOCUMENTÉ** · État : modules présents dans le prototype (`posts`, `categories`, `media`, éditeur) ; port Laravel « todo » (S5 l.11).

**EX-25 — Planification des publications : date et heure futures, statut « planifié », publication automatique à l'échéance.**
Source : S1, « Planification des publications » ; S2 l.26 et l.22 (« Token-Based Cron System »). · Statut : **DOCUMENTÉ** · État : statut « Scheduled » et carte du tableau de bord existent ; **le déclenchement automatique n'est pas mesuré** (un site statique n'a pas de tâche planifiée). En production : commande planifiée Laravel.

**EX-26 — Modération des commentaires des pages d'article, alimentée par le formulaire public.**
Source : S1, « gestion des commentaires des blog details » et « Modération des commentaires » ; S2 l.70 (flux d'approbation optionnel). · Statut : **DOCUMENTÉ** · État : formulaire public livré (consentement, anti-spam *honeypot*) mais **non relié** au module admin (A8). Règle de lecture : un commentaire publié doit afficher le nom et le texte seulement, jamais l'e-mail.

**EX-27 — Gestion complète des utilisateurs et des permissions : matrice module × action, rôles personnalisés, changement de rôle, suspension, vérification de compte.**
Source : S1, « Gestion des utilisateurs et permissions complete » ; S2 l.64, l.67. · Statut : **DOCUMENTÉ** · État : matrice 12 × 5 et 4 rôles ; création de rôles personnalisés, suspension et vérification : **non vérifiées** dans le prototype.

**EX-28 — Journal d'audit consultable et filtrable (qui, quoi, objet, date, avant/après), exportable, non modifiable (INV-02).**
Source : S1, « Gestion des audits journalisation des logs » ; S3 §8 (`audit_log`). · Statut : **DOCUMENTÉ** · État : **inexistant**.

**EX-29 — Sauvegardes automatique (planifiée) et manuelle (à la demande), avec liste des sauvegardes existantes et téléchargement.**
Source : S1, « sauveagrde automatique et manuel » ; S6 l.194 (étalon : import/export de sauvegarde). · Statut : **DOCUMENTÉ** · État : **inexistant**.

**EX-30 — Restauration d'une sauvegarde, durée de conservation, chiffrement des fichiers, test de restauration périodique.**
Source : aucune. Une sauvegarde contient des données personnelles : la durée relève du DPO (Q8). · Statut : **PROPOSÉ**.

### F. Transverses

**EX-31 — Mise en page entièrement adaptative : bureau, portable, tablette, mobile, performances cohérentes.**
Source : S1, « Fully Responsive Layout – Optimized for desktops, tablets, and mobile devices » ; S2 l.6. · Statut : **DOCUMENTÉ** · État : vérifié en 4 largeurs (1440, 1100/1024, 768, 390 px) sur le tableau de bord admin, la page Vidéos et un article, sans débordement horizontal ; les autres pages relèvent de `tools/qa/browser_audit.py` (non relancé après les ajouts du 2026-10-28).

**EX-32 — Comportement homogène sur les navigateurs modernes.**
Source : S1, « consistent performance across modern browsers ». · Statut : **DOCUMENTÉ** · État : **testé sur Edge seulement ; Firefox et Safari non testés** (S3 §9). Les fonctions récentes utilisées (`overflow-x: clip`, `@view-transition`, Speculation Rules) ont des supports différents : voir M2.

**EX-33 — Sécurité applicative : limitation de débit, requêtes paramétrées, échappement, CSRF, assignation de masse protégée, cookies HttpOnly/Secure/SameSite, vérification de courriel par code à durée limitée, mots de passe forts hachés, téléversements stricts hors disque public, `APP_DEBUG=false` avec pages d'erreur personnalisées.**
Source : S1, liste collée « To secure a Laravel web application… » ; S2 l.52-61 ; suivi S5 l.18-25. · Statut : **DOCUMENTÉ** · État : « todo » côté Laravel.

**EX-34 — RGPD : minimisation, consentement explicite horodaté à l'inscription et aux formulaires, export des données d'un utilisateur, suppression/anonymisation sur demande, politique de confidentialité à jour, chiffrement des données sensibles.**
Source : S1, même liste ; S5 l.26. · Statut : **DOCUMENTÉ** · État : consentements case à cocher + horodatage ajoutés aux formulaires newsletter et commentaire du prototype ; le reste « todo ». Durées de conservation : Q8.

**EX-35 — SEO : titre et description uniques, Open Graph (image large), Twitter Card, Schema.org (NewsArticle, BreadcrumbList), JSON-LD, `alt` et `title` sur toutes les images, robots.txt, sitemaps, vitesse.**
Source : S1, « page seo, OG, Twitter Card, Schema.org, Breadcrumb, JSON-LD… » ; S2 l.45-49 ; suivi S5 l.13-17. · Statut : **DOCUMENTÉ** · État : largement présent dans le prototype (titre, description, Open Graph, hreflang, JSON-LD, sitemap) ; version Laravel « todo ».

**EX-36 — Le site s'ouvre en français par défaut et le thème par défaut est clair, public et administration.**
Source : S1, « le site saffiche en francais par defaut et est theme switch light par default meme en admin ». · Statut : **ARBITRÉ** — propriétaire, 2026-10-28. · État : livré (la redirection racine n'utilise plus la langue du navigateur ; `prefers-color-scheme` n'est plus suivi).

**EX-37 — Blocs de la page article repris de Foxiz : barre de partage (mobile fixe, bureau collante), bloc « Partager cet article », auteur, précédent/suivant, newsletter, commentaires, colonne latérale avec emplacement publicitaire.**
Source : S1, « ON VA UPDATE LE DOSSIER PROTOTYPE_HTML : DANS BLOG DETAILS… » puis « OUI » au plan proposé. · Statut : **ARBITRÉ** — propriétaire, 2026-10-28. · État : livré dans le prototype (64 articles FR + 64 EN). Réseaux demandés : Facebook, LinkedIn, Instagram, e-mail, X ; **Instagram n'a pas d'adresse de partage officielle : le bouton ouvre le profil du site** (Q9).

**EX-38 — Page de connexion admin : panneau de gauche masqué sur mobile (formulaire seul) ; logo Pressafrica à la place du « A » ; cartes « Top categories » et « Editorial queue » cohérentes aux 4 largeurs.**
Source : S1 (deux messages du 2026-10-28). · Statut : **ARBITRÉ** — propriétaire, 2026-10-28 · État : livré.

**Décompte** : 39 exigences — **24 DOCUMENTÉ** (dont EX-39), **10 PROPOSÉ** (EX-02, 04, 06, 09, 11, 13, 16, 18, 19, 30), **5 ARBITRÉ** (EX-07, 10, 36, 37, 38).

---

## 7. Rôles et habilitations

**Règle : un rôle sans fiche n'obtient rien** (le défaut contraire, « tout », est la règle la plus large et est refusé). Chaque nouveau module reçoit une fiche avant sa mise en service.

Existant (prototype, `admin.js`) : **Administrator** : tout (12 modules × 5 actions). **Editor** : tout sauf Réglages, Auteurs et rôles, Zones publicitaires, Menus (voir seulement). **Author** : articles (voir, créer, modifier), médiathèque (voir, créer), catégories (voir), IA (voir, créer). **Moderator** : commentaires (tout), articles et newsletter (voir).

Proposition pour les nouveaux modules (**PROPOSÉ**, validation : propriétaire, Q10) :

| Module | Administrator | Editor | Author | Moderator |
|---|---|---|---|---|
| Analytics (voir/exporter) | tout | voir | — | — |
| Tags | tout | tout | voir | — |
| Vidéos, Magazine, Radios, Télévision | tout | tout sauf supprimer | — | — |
| Journal d'audit | voir/exporter | — | — | — |
| Sauvegardes | tout | — | — | — |
| Réglages > Analytics | modifier | voir | — | — |

Le nombre total de permissions (A7) sera recalculé au seed ; ne pas figer « 60 ».

---

## 8. Critères de recette (parcours exécutables par quelqu'un qui n'a pas écrit le produit)

**R1 — Rubrique de bout en bout (Magazine, puis Radios, puis Télévision).** Un éditeur se connecte, crée une fiche FR + EN, la publie, régénère le site. Résultat attendu : la page `/fr/<rubrique>` et `/en/<rubrique>` affiche la fiche, **plus aucun bloc « Bientôt »**, la fiche est dans le plan du site ; archivée, elle disparaît des deux langues ; une ligne d'audit existe pour chaque action (INV-02). Un auteur ne voit pas le module.

**R2 — Mesure et consentement.** L'administrateur saisit un identifiant GA4 dans Réglages > Analytics. Un visiteur ouvre un article : la bannière apparaît ; il **refuse** → l'onglet Réseau ne montre aucune requête vers les domaines de mesure et aucun cookie tiers ; il **accepte « analytique »** → le script se charge avec le bon identifiant. L'administrateur retire l'identifiant → plus aucun chargement. (INV-05)

**R3 — Du visiteur au rapport.** Un visiteur consulte des articles de rubriques différentes ; l'administrateur ouvre Analytics, filtre sur une période, voit pays, termes de recherche, rubriques ; exporte en CSV : les totaux de l'export **égalent ceux de l'écran**. *Dans le prototype les chiffres sont synthétiques : ce parcours n'est recevable qu'en production, avec la source choisie en EX-19.*

**R4 — Commentaire.** Un visiteur poste un commentaire sur un article avec consentement ; un modérateur le voit en attente, l'approuve ; il apparaît sous l'article (sans e-mail) ; le journal d'audit trace l'approbation ; un auteur ne peut pas modérer. (Échoue aujourd'hui : A8.)

**R5 — Planification.** Un auteur crée un article ; un éditeur le planifie à une date future ; à l'échéance il se publie sans intervention, apparaît en page de rubrique et dans le plan du site ; l'audit trace la planification et la publication.

**R6 — Publicité.** Une zone latérale reçoit une bannière ; un visiteur consentant clique ; le clic et le taux de clic apparaissent dans Analytics et dans le module des zones, par zone et par page.

**R7 — Rôle personnalisé.** L'administrateur crée un rôle « Radio » avec les seules permissions du module Radios ; un utilisateur de ce rôle ne voit **que** l'entrée Radios dans la barre latérale et reçoit un refus en ouvrant directement l'adresse des Réglages.

**R8 — Sauvegarde et restauration.** Sauvegarde manuelle, téléchargement ; restauration sur une base vide : même nombre d'articles, de comptes, de commentaires ; audit à chaque étape. (EX-30, si validé.)

**R9 — RGPD.** Export des données d'un utilisateur, puis suppression : ses données personnelles sont anonymisées, ses articles restent attribués à « ancien auteur » ; la ligne d'audit ne contient plus de donnée personnelle superflue.

**R10 — Adaptatif et navigateurs.** Les parcours R1, R4 et la lecture d'un article sont déroulés à 1440, 1024, 768 et 390 px **dans Chrome/Edge, Firefox et Safari**, sans défilement horizontal. *Non exécuté sur Firefox et Safari à ce jour.*

---

## 9. Questions ouvertes

### Décisions de direction — le propriétaire

| # | Question | Pourquoi maintenant |
|---|---|---|
| ~~Q1~~ | **Répondue le 2026-10-28 (propriétaire)** : `keyfeatures.txt` est la liste des fonctionnalités de **Foxiz** (le thème pris pour modèle), pas un cahier des charges propre au projet. Conséquence : EX-14, 23-25, 27, 33 restent DOCUMENTÉ comme *modèle fonctionnel*, chaque fonctionnalité devant encore être retenue ou écartée explicitement. | Voir EX concernées |
| ~~Q2~~ | **Tranchée le 2026-10-28 (propriétaire)** : Blade + îlots Vue + Turbo Drive (EX-07, EX-10). Inertia avec SSR écarté (pas de Node sur le mutualisé, EX-39). | — |
| ~~Q3~~ | **Tranchée le 2026-10-28 (propriétaire) : Tailwind.** Public et admin. L'anomalie A2 est close ; `CLAUDE.md` et `TECHNICAL.md` §8 (qui disent Bootstrap pour l'admin) sont à aligner. | — |
| Q9 | Hébergeur, version de PHP (8.3 visé), SSH, extensions et limites du plan cPanel mutualisé ? | EX-39 ; conditionne le planificateur, les sauvegardes et la compilation des assets |
| Q5 | Autoriser les liens sortants dans les annuaires Radios/TV (EX-04) ? | A6 |
| Q6 | Seuils de performance : à fixer **après** mesure M1 ; supprimer ou garder le « > 90, < 2 s » de S4 ? | A5 |
| Q7 | Source des statistiques en production : API GA4, journal interne, ou les deux (EX-19) ? **Réponse du propriétaire (2026-10-29) : journal interne (b) ; GA4 implémenté mais désactivé par défaut.** | Change l'architecture et le RGPD |
| Q9 | Bouton Instagram du partage : ouvrir le profil (actuel) ou retirer, faute d'adresse de partage officielle ? | EX-37 |
| Q10 | Fiche de permissions des nouveaux modules (§7) et regroupement de la barre latérale (EX-22) | Un rôle sans fiche n'obtient rien |

### Signature d'un professionnel nommé

| # | Question | À qui |
|---|---|---|
| Q8 | Base légale, durées de conservation (mesure d'audience, journal d'audit, sauvegardes, commentaires, inscriptions newsletter) ; usage du mode de consentement ; transferts hors UE liés à GA4/Meta ; rédaction de la politique de confidentialité et de cookies ; lecture exacte de « zéro cookie natif » (A3) ; anonymisation de l'IP et *Do Not Track* par défaut | **Le délégué à la protection des données (DPO) ou, à défaut, un juriste habilité.** Ni le propriétaire ni l'équipe technique ne signent à leur place. Aucun nom de DPO n'est présent dans les sources : à désigner. |
| Q4 | Contenus réels de Magazine, Radios, Télévision : numéros, droits de reproduction des couvertures, liste des stations/chaînes, droits de diffusion ; relecture des textes et titres des 8 vidéos, rédigés par l'équipe technique | **Le rédacteur en chef** (nom absent des sources ; l'équipe éditoriale de `articles.json` signe « Africa Global Press » / « Pressafrica Media » sans identité nominative) |

### Mesures que nous pouvons faire nous-mêmes — à faire, pas à demander

| # | Mesure | État |
|---|---|---|
| M1 | Comparatif Lighthouse (accueil, article, podcasts) : socle EX-09, + `wire:navigate`, + Turbo (EX-11) | **Non exécuté** |
| M2 | Parcours R1, R4, lecture d'article sous Firefox et Safari (EX-32) | **Non exécuté** |
| M3 | Audit axe des pages ajoutées le 2026-10-28 (blocs d'article, Vidéos, nouveaux modules admin) | **Non exécuté** depuis les ajouts (dernier résultat dans S3 §9 : 0 violation sur les pages auditées avant) |
| M4 | Compter les permissions après ajout des modules (A7) | À faire au seed |
| M5 | Relire le dump SQL `exxhrgce_wp623.sql` pour confirmer l'absence de contenu magazine/radio/télévision au-delà de `articles.json` | À faire (S7 mesuré seulement via `articles.json`) |

---

## 10. Journal des arbitrages

| Date | Décision | Auteur | Source |
|---|---|---|---|
| 2026-10-26 | Rendu public Blade + îlots Vue (EX-07) | Assistant (consigne du dépôt) — **non validé par le propriétaire** | S4 l.8-9 |
| 2026-10-28 | Ajouter au TODO : SEO (page, OG, Twitter Card, Schema.org, Breadcrumb, JSON-LD, `alt`/`title`, robots, sitemaps), sécurité, RGPD | Propriétaire | S1 → S5 l.13-26 |
| 2026-10-28 | Premier chantier : analyse Foxiz des blocs de la page article puis intégration (« OUI ») | Propriétaire | S1 → EX-37 |
| 2026-10-28 | Français par défaut ; thème clair par défaut y compris admin | Propriétaire | S1 → EX-36, INV-07 |
| 2026-10-28 | Page de connexion admin : formulaire seul sur mobile ; cartes du tableau de bord cohérentes aux 4 largeurs ; logo Pressafrica dans l'admin | Propriétaire | S1 → EX-38 |
| 2026-10-28 | Page Vidéos peuplée avec contenu de démonstration, bloc « Bientôt » retiré | Propriétaire | S1 → EX-05 |
| 2026-10-28 | Même traitement pour Magazine, Radios, Télévision, avec modules d'administration ; ajout Analytics, exports, tags, audit, sauvegardes, sidebar | Propriétaire (demande) | S1 → EX-01, 03, 12-30 |
| 2026-10-28 | Cadre CSS : **Tailwind** retenu (public et admin) ; Bootstrap 5 écarté | Propriétaire | S1 → Q3, A2 |
| 2026-10-28 | `keyfeatures.txt` = liste des fonctionnalités de Foxiz (modèle fonctionnel, non un cahier des charges propre) | Propriétaire | S1 → Q1 |
| 2026-10-28 | Hébergement : cPanel mutualisé, sans Node, sans Redis | Propriétaire | S1 → EX-39 |
| 2026-10-28 | Site public : Blade + îlots Vue + **Hotwire Turbo Drive** ; ajout au prototype HTML (FR/EN d'un article sans rechargement) | Propriétaire (« OUI ») sur recommandation de l'assistant | S1 → EX-07, EX-10 |
| 2026-10-28 | Barre de défilement verticale premium dans l'administration | Propriétaire | S1 → EX-38 |

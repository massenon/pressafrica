# Recette et mesures — Laravel (2026-10-29)

Port Laravel (`pressmedia_laravel/`), branche `claude/brave-heisenberg-53zohy`. Tous les chiffres ci-dessous ont été
**mesurés** dans l'environnement de développement ; ceux de production restent à mesurer sur l'hébergeur (Q9).

## 1. Lighthouse (mobile, Chromium, 4 pages)

Outil : `tools/qa/lighthouse/run.sh`. `php artisan serve` n'a ni gzip ni cache ; `tools/qa/lighthouse/gzproxy.py`
ajoute la compression et les en-têtes de cache qu'Apache appliquera en production (`public/.htaccess`).

| Page | Perf. (sans gzip) | **Perf. (avec gzip)** | Accessibilité | Bonnes pratiques | SEO | LCP (gzip) | CLS |
|---|---|---|---|---|---|---|---|
| Accueil `/fr` | 78 | **92** | 100 | 100 | 100 | 3,3 s | 0,000 |
| Article (Cap-Vert) | 87 | **96** | 100 | 100 | 100 | 2,7 s | 0,000 |
| Rubrique `/fr/politique` | 85 | **94** | 100 | 100 | 100 | 2,9 s | 0,000 |
| Podcasts | 91 | **97** | 100 | 100 | 100 | 2,4 s | 0,004 |

Corrigé pendant la mesure : rôle ARIA du bandeau cookies (`<aside role=dialog>` interdit), requête 404 du service
worker, JavaScript non minifié (Turbo 217 → 92 ko, `app.js` 36 → 23 ko). Prototype statique mesuré en même temps :
performance 82 à 91, accessibilité 100. **Le seuil chiffré reste à fixer par le propriétaire après mesure sur
l'hébergeur (Q6).**

## 2. Parcours du PRD (§8)

Automatisés dans `pressmedia_laravel/tests/Feature/AcceptanceJourneysTest.php` (exécutables par un tiers :
`php artisan test --filter=AcceptanceJourneysTest`).

| Parcours | Résultat | Remarque |
|---|---|---|
| R1 Rubrique de bout en bout | **Réussi** | Fiche radio FR + EN publiée depuis l'admin, visible dans les deux langues, sans bloc « Bientôt », page au plan du site ; archivée, elle disparaît ; audit à chaque action. |
| R2 Mesure et consentement | **Réussi (serveur)** | GA4 absent par défaut ; activé, le script reste inerte (`text/plain`) jusqu'au consentement « mesure d'audience » ; retiré, plus rien n'est chargé. Vérification « onglet Réseau » faite en navigateur pour la publicité et la newsletter ; à refaire avec un vrai identifiant GA4 en production. |
| R3 Du visiteur au rapport | **Réussi** | Pays, recherches, rubriques à l'écran ; totaux de l'export CSV/JSON = écran. Source : journal interne (Q7). |
| R4 Commentaire | **Réussi** | Anomalie A8 levée : le formulaire public écrit en base ; un auteur ne peut pas modérer ; l'e-mail n'est jamais affiché. |
| R5 Planification | **Réussi** | Auteur → éditeur planifie → publication seule à l'échéance (cron), rubrique et plan du site ; audit création / mise à jour / publication. |
| R6 Publicité | **Réussi (par zone)** | Clic compté, taux de clic dans le module des zones et dans Analytics. Le détail **par page** n'est pas mesuré (par zone seulement). |
| R7 Rôle personnalisé | **Réussi** | Rôle « radio » : seule l'entrée Radios dans la barre latérale ; `/admin/settings` refusé. |
| R8 Sauvegarde et restauration | **Réussi** | Sauvegarde, téléchargement, perte de données, restauration : mêmes nombres d'articles, comptes, commentaires ; audit à chaque étape. |
| R9 RGPD | **Réussi** | Export JSON puis effacement : compte devenu « Ancien auteur », articles conservés ; le journal ne contient qu'une empreinte de l'adresse. |
| R10 Adaptatif et navigateurs | **Partiel** | Chromium : 6 pages × 1440 / 1024 / 768 / 390 px, aucun défilement horizontal (`tools/qa/acceptance/r10.js`). **Firefox et Safari non testés** (absents de l'environnement) : à faire sur un poste équipé. |

## 3. Vérifications complémentaires

- Parité avec le prototype (`tools/qa/laravel_parity/sweep.sh`) : accueil, 9 rubriques, 64 articles FR, 10 pages fixes,
  10 pages médias identiques ; en-tête et pied de page identiques en FR et EN.
- Accessibilité de l'admin (axe) : 0 violation sur tableau de bord, articles, éditeur, mise en avant, commentaires.
- Suite automatique : `php artisan test` → **151 tests** au vert.

## 4. Reste à faire avant la mise en ligne

| Point | Responsable |
|---|---|
| Vérifications de l'hébergeur (PHP, extensions, cron, lien symbolique, quota d'envoi) — `docs/DEPLOY.md` §0 | Propriétaire (Q9) |
| Lighthouse sur le vrai serveur, puis fixer le seuil | Propriétaire (Q6) |
| Firefox et Safari aux 4 largeurs (R10) | Équipe technique |
| Validation DPO : GA4 / AdSense, textes des politiques, conservation du journal d'audit | DPO (Q8) |
| Contenus réels Magazine / Radios / TV, vraies vidéos et audios | Rédacteur en chef (Q4) |
| Changer les 4 comptes de départ dès la mise en ligne : Admin > Staff accounts (`/admin/users`) et My account (`/admin/account`) | Propriétaire |

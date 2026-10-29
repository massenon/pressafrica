# Spécification — Carrousel d'accueil administrable, questions-réponses sous les articles, administration au look du prototype

Date : 2026-10-29 · Statut : **PROPOSÉ**, à valider par le propriétaire · Suite du PRD (`docs/PRD.md`) : les exigences continuent sa numérotation (EX-40 à EX-52).
Source : demande du propriétaire du 2026-10-29 (« gestion des images du carrousel depuis l'admin, par défaut ce qui est là, CRUD » ; « section questions-réponses admin/utilisateur pour les blog details du système de commentaires »), et l'étape suivante du plan (admin au look du prototype, Livewire, Quill, sélecteur de médias, écran « Mise en avant »).

---

## 1. Problème

1. **Carrousel figé.** Le carrousel d'accueil (4 diapositives : 3 articles choisis + 1 diapositive d'introduction « Malabo ») vient de l'import (`home_slots`, image et textes de l'intro dans le code). La rédaction ne peut ni changer une image, ni un texte, ni l'ordre, ni ajouter une diapositive sans développeur. Le prototype prévoyait déjà ce contrôle (`admin-featured.js`, « hero (max 6) »).
2. **Commentaires sans dialogue.** Un lecteur pose une question sous un article ; la rédaction ne peut répondre que par e-mail (prototype : « Reply queued ») et la réponse n'apparaît pas sur la page. Les autres lecteurs ne voient ni la question traitée ni la réponse ; la page perd la valeur d'un échange public et la rédaction répond plusieurs fois à la même question.
3. **Admin provisoire.** Les écrans Laravel sont fonctionnels mais n'ont ni le look du prototype (barre latérale, cartes, `admin.min.css`), ni éditeur riche, ni sélecteur de médias : la saisie d'un article ou d'une diapositive oblige à taper des identifiants et du HTML.

Coût de ne rien faire : dépendance à un développeur pour chaque changement de une ; questions de lecteurs sans réponse visible ; erreurs de saisie (HTML, identifiants) en production.

## 2. Objectifs

| # | Objectif (résultat mesurable) |
|---|---|
| O1 | Un éditeur modifie, ajoute, réordonne ou retire une diapositive du carrousel **sans développeur**, en moins de 2 minutes, et le changement est visible à l'accueil dès l'enregistrement. |
| O2 | Le jour de la mise en service, le carrousel affiché est **identique** à l'actuel (mêmes 4 diapositives, même ordre, mêmes images et textes) : contrôle de parité `tools/qa/laravel_parity` = 0 différence sur `fr/index.html`. |
| O3 | Toute question de lecteur approuvée peut recevoir une **réponse publique de la rédaction** affichée sous la question, avec un badge « Rédaction ». |
| O4 | Les lecteurs peuvent répondre à un commentaire (fil à **un seul niveau**), sous la même modération que les commentaires. |
| O5 | Les écrans d'admin reprennent le look du prototype (mêmes composants que `prototype_html/admin/*.html`) et la saisie ne demande plus d'identifiant ni de HTML brut. |

## 3. Hors périmètre (non-objectifs)

| Non-objectif | Raison |
|---|---|
| Comptes lecteurs, connexion, notifications par e-mail aux lecteurs | Pas d'espace lecteur à ce stade (PRD, hors périmètre) ; envoi d'e-mails dépend de Q9 (hébergeur, SMTP). |
| Votes, « j'aime », réactions emoji sur les commentaires | Absent du prototype ; utile seulement avec un volume de commentaires qui n'existe pas encore. |
| Fils de discussion à plusieurs niveaux | Un niveau suffit pour « question → réponse » ; plus de profondeur complique la modération et l'affichage mobile. |
| Carrousel vidéo, animations nouvelles | Le carrousel garde le comportement et le CSS du prototype (aucune refonte, règle `CLAUDE.md`). |
| Génération IA de réponses | Module IA non encore spécifié côté Laravel. |

## 4. Récits utilisateur

**Éditeur / administrateur (rédaction)**
- En tant qu'éditeur, je veux voir le carrousel actuel sous forme de liste de diapositives (aperçu, titre, langue, ordre, statut) pour savoir ce qui est en une.
- En tant qu'éditeur, je veux créer une diapositive à partir d'un article (image, titre, résumé et lien repris de l'article) pour mettre un sujet en une en quelques clics.
- En tant qu'éditeur, je veux créer une diapositive libre (image téléversée ou choisie dans la médiathèque, titre, texte, bouton et lien, en FR et EN) pour annoncer une rubrique ou un événement.
- En tant qu'éditeur, je veux réordonner, masquer ou supprimer une diapositive pour garder une une à jour.
- En tant qu'éditeur, je veux programmer une diapositive (début / fin d'affichage) pour préparer la une à l'avance.
- En tant qu'administrateur, je veux revenir au carrousel par défaut pour annuler une mauvaise manipulation.
- En tant que modérateur, je veux répondre publiquement à une question de lecteur depuis la file de modération pour que tous les lecteurs voient la réponse.
- En tant que modérateur, je veux approuver, refuser ou marquer comme spam une réponse de lecteur comme un commentaire.
- En tant qu'éditeur, je veux écrire un article dans un éditeur riche (gras, intertitres, listes, liens, image de la médiathèque) sans taper de HTML.

**Lecteur**
- En tant que lecteur, je veux voir sous l'article les questions publiées et les réponses de la rédaction, pour trouver une réponse sans reposer la question.
- En tant que lecteur, je veux répondre à un commentaire publié, en donnant mon consentement comme pour un commentaire.
- En tant que lecteur, je veux savoir que ma réponse est en attente de modération.

**Cas limites**
- Carrousel vide (toutes diapositives masquées) : l'accueil n'affiche pas de bloc vide (règle actuelle : diapositives filtrées) et l'admin avertit.
- Article d'une diapositive dépublié ou supprimé : la diapositive n'est plus affichée et l'admin la signale.
- Diapositive sans traduction EN : la version FR s'affiche sur `/en` (règle INV-10), signalée dans la liste.
- Réponse à une réponse : interdite (un niveau), le bouton « Répondre » n'apparaît que sous les commentaires de premier niveau.
- Commentaire parent passé en spam ou supprimé : ses réponses ne sont plus affichées.

## 5. Exigences

### P0 — indispensables

**EX-40 — Carrousel : modèle et valeurs par défaut.**
Table `hero_slides` : ordre, statut (brouillon / publié), début et fin d'affichage (facultatifs), article lié (facultatif), image (médiathèque), et par langue : titre, texte, libellé du bouton, lien, texte alternatif. Une migration de données crée **les 4 diapositives actuelles** (3 articles choisis + intro Malabo, mêmes textes FR/EN qu'aujourd'hui) ; le seeder fait de même.
- [ ] Après `migrate:fresh --seed`, l'accueil est identique à aujourd'hui (O2, contrôle de parité = 0).
- [ ] Une diapositive liée à un article reprend par défaut l'image, le titre, le résumé et le lien de l'article ; chaque champ peut être remplacé.
- [ ] Maximum 6 diapositives publiées à la fois (règle du prototype `admin-featured.js`) ; la 7ᵉ est refusée avec un message clair.

**EX-41 — Carrousel : CRUD dans l'admin (écran « Mise en avant »).**
Liste, création, modification, réordonnancement (glisser-déposer, ou flèches haut/bas au clavier), masquage, suppression, aperçu de l'image, bouton « Rétablir le carrousel par défaut » (administrateur seulement, avec confirmation).
- [ ] Droits : `featured.view|create|edit|delete|publish` (permissions existantes) ; un auteur n'y a pas accès.
- [ ] Toute action est inscrite au journal d'audit (`module = featured`) avec la diapositive et les champs modifiés.
- [ ] L'image est choisie dans la médiathèque ou téléversée (conversion WebP existante) ; le texte alternatif FR est obligatoire (EX-35).
- [ ] Une diapositive programmée n'apparaît qu'entre ses dates de début et de fin (heure de Paris, à confirmer).

**EX-42 — Carrousel : rendu public inchangé.**
L'accueil lit `hero_slides` au lieu de `home_slots.hero` et du texte codé en dur ; le balisage, les attributs (`fetchpriority`, `loading`, libellés ARIA « Diapositive n / N ») et le CSS restent ceux du prototype.
- [ ] La première diapositive est en `loading="eager" fetchpriority="high"`, les suivantes en `lazy`/`low`.
- [ ] Les points et le libellé « n / N » suivent le nombre réel de diapositives.

**EX-43 — Commentaires : réponses à un niveau.**
Colonne `parent_id` sur `comments` (nulle pour une question/commentaire, renseignée pour une réponse) et `staff_user_id` (renseigné quand la réponse vient de la rédaction).
- [ ] Une réponse dont le parent est déjà une réponse est refusée (422).
- [ ] Une réponse hérite de l'article et de la langue du parent.

**EX-44 — Réponse publique de la rédaction depuis la modération.**
Dans la file `/admin/comments`, bouton « Répondre publiquement » sous chaque commentaire approuvé.
- [ ] Droit requis : `comments.publish`. La réponse est publiée immédiatement (pas de file), signée du nom de la personne et d'un badge « Rédaction ».
- [ ] La réponse est assainie comme un commentaire (texte brut, pas de lien cliquable) et journalisée.
- [ ] Répondre à un commentaire encore « en attente » l'approuve dans la même action (après confirmation).

**EX-45 — Réponse d'un lecteur sur la page article.**
Bouton « Répondre » sous chaque commentaire de premier niveau publié ; il ouvre le formulaire existant (mêmes champs, consentement, piège à robots, limite 5/min/IP) avec le commentaire visé rappelé.
- [ ] La réponse suit la règle de modération (`commentsApproval`) et le message « en attente de modération ».
- [ ] Sans JavaScript, le lien mène au formulaire avec le commentaire visé en paramètre ; le formulaire reste utilisable.

**EX-46 — Affichage public questions-réponses.**
Sous l'article : commentaires publiés du plus ancien au plus récent, réponses publiées indentées sous leur parent ; les réponses de la rédaction en premier sous la question, avec badge « Rédaction ».
- [ ] Jamais d'adresse e-mail affichée (EX-26) ; nom et texte seulement.
- [ ] JSON-LD : `Comment` dans le `NewsArticle` (avec `author`, `dateCreated`, `text`), réponses en `comment` du parent.
- [ ] Compteur « n commentaires » (questions + réponses publiées).

**EX-47 — Administration au look du prototype.**
Gabarit admin Laravel reprenant `prototype_html/admin/*.html` : barre latérale par groupes (EX-22), en-tête, cartes, tableaux, badges, `admin.min.css` copié par `site:assets` ; thème clair par défaut (EX-36). Les écrans existants (articles, rubriques, tags, médias, commentaires, réglages, rôles, audit, médias de rubrique) passent sur ce gabarit.
- [ ] Contrôle de parité visuelle sur le tableau de bord, la liste des articles et l'écran « Mise en avant » face au prototype (mêmes blocs, mêmes classes).
- [ ] Navigation entre écrans sans rechargement complet (Livewire `wire:navigate`, décision `CLAUDE.md`).
- [ ] Accessibilité : axe sans violation sérieuse sur ces 3 écrans.

**EX-48 — Éditeur riche et sélecteur de médias.**
Quill pour le corps des articles (intertitres H2/H3, gras, italique, listes, citation, lien, image de la médiathèque) ; sortie assainie par Purifier (configuration actuelle). Sélecteur de médias en fenêtre modale (recherche, téléversement, choix) utilisé par l'article (couverture, image du corps) et par le carrousel.
- [ ] Aucun champ d'identifiant à taper (articles du magazine, article d'une diapositive) : sélection par recherche de titre.
- [ ] Le HTML produit par Quill passe Purifier sans perte (tests).

### P1 — fortement souhaitables

- **EX-49 — Aperçu du carrousel** dans l'admin à la taille ordinateur et mobile avant publication.
- **EX-50 — Notification interne** : le tableau de bord admin affiche le nombre de questions de lecteurs sans réponse de la rédaction depuis plus de 48 h.
- **EX-51 — Épingler une réponse** de la rédaction comme « réponse retenue » sous la question.

### P2 — plus tard (prévu dans le modèle, non construit)

- **EX-52** — Comptes lecteurs (réponses signées par un compte vérifié, notification par e-mail d'une réponse) : prévoir `user_id` nullable sur `comments` sans l'utiliser. Plusieurs carrousels (par rubrique) : prévoir une colonne `placement` (`home` par défaut) sur `hero_slides`.

## 6. Mesures de succès

| Type | Indicateur | Cible | Mesure |
|---|---|---|---|
| Avancé | Parité de l'accueil après bascule | 0 différence | `tools/qa/laravel_parity/sweep.sh fr/index.html en/index.html` |
| Avancé | Changements de carrousel faits sans développeur | 100 % dès la 1ʳᵉ semaine | Journal d'audit, `module = featured` |
| Avancé | Délai médian de réponse de la rédaction à une question | **à fixer après mesure** (aucune donnée aujourd'hui, règle du PRD) | Écart `created_at` question / réponse rédaction |
| Retardé | Part des questions publiées ayant une réponse de la rédaction | **à fixer après 1 mois** de mesure | Requête sur `comments` |
| Retardé | Erreurs de saisie (HTML cassé, identifiant faux) signalées | 0 | Retours de la rédaction |

## 7. Questions ouvertes

| # | Question | Qui répond | Bloquante ? |
|---|---|---|---|
| S1 | Les lecteurs peuvent-ils répondre aux autres lecteurs, ou seulement la rédaction répond-elle (questions-réponses strictes) ? La spec propose les deux (EX-44, EX-45). | Propriétaire | **Oui** pour EX-45 |
| S2 | Signature des réponses de la rédaction : nom de la personne + badge, ou seulement « La rédaction » ? | Rédacteur en chef (Q4) | Non (défaut : nom + badge) |
| S3 | Fuseau horaire des programmations (début/fin des diapositives, publication planifiée) : Africa/Abidjan (UTC), Europe/Paris ? | Propriétaire | Non (défaut : configuration de l'application) |
| S4 | Les réponses de la rédaction doivent-elles aussi être envoyées par e-mail au lecteur ? Dépend de l'envoi d'e-mails (Q9) et du RGPD (Q8). | Propriétaire + DPO | Non (hors périmètre v1) |
| S5 | Durée de conservation des commentaires et réponses (RGPD). | DPO (Q8) | Non pour la construction, oui pour la mise en ligne |

## 8. Phasage et dépendances

| Étape | Contenu | Dépend de | Critère de sortie |
|---|---|---|---|
| A | EX-40, EX-41 (écran simple), EX-42 | — | Parité de l'accueil = 0 ; CRUD testé ; audit |
| B | EX-43 à EX-46 | S1 pour EX-45 | Question → réponse rédaction visible ; réponse lecteur en attente ; tests |
| C | EX-47, EX-48 (gabarit admin, Quill, sélecteur), écran « Mise en avant » sur le nouveau gabarit | A | Parité visuelle des 3 écrans ; axe ; tests Purifier/Quill |
| D | P1 (EX-49 à EX-51) | C | — |

Aucune date ni charge : aucune base n'existe pour les estimer (règle du PRD). Ordre recommandé : A → B → C (A et B sont courts et livrent la valeur demandée ; C est le plus long et profite des deux).

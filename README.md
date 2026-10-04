# Tracteur

**Éditeur web de tracts accessibles.** On écrit son tract dans le navigateur, avec la charte graphique de son organisation ; Tracteur produit un **PDF balisé** (conforme PDF/UA-1 lorsque les contrôles de l'éditeur sont satisfaits) et un **mail HTML** (`.eml`) avec ce PDF en pièce jointe.

Conçu pour les organisations syndicales et associatives dont les militant·es ne sont pas des spécialistes de la communication ni de l'accessibilité : l'interface est simple, indulgente (on ne perd jamais son travail) et l'outil impose de bonnes pratiques (textes alternatifs, contrastes, structure des titres).

> Interface en français. Licence [AGPL-3.0-or-later](LICENSE). Pas de dépendance à installer : PHP, SQLite et un navigateur Chrome sans interface côté serveur.

## Fonctionnalités

**Édition**
- Tract composé de blocs : intertitres, textes (gras, italique, liens, mots en couleur d'accentuation), listes, images, encadrés, pictogrammes de la banque ; blocs déplaçables, dupliquables, repliables.
- Titre sur une ou deux lignes ; deux modèles de mise en page (1 ou 2 colonnes, ordre de lecture conservé).
- Aperçu du tract paginé (A4) et du mail ; onglet **Accessibilité** qui signale les problèmes (titre absent, image sans texte alternatif, liens peu clairs, texte trop long, risque de page blanche…).
- Brouillon enregistré automatiquement dans le navigateur ; export/import du projet en `.json`.

**Sorties**
- **PDF balisé** produit côté serveur par Chrome headless, puis corrigé par un post-traitement PHP (`src/pdfua.php`) : artefacts, rôles standards, descriptions de liens, métadonnées XMP `pdfuaid:part=1` (inscrites seulement si aucune erreur n'est relevée). Validé avec [veraPDF](https://verapdf.org/) sur des tracts types.
- **Mail HTML** au format `.eml` (styles en ligne, mise en page en tableaux, images en pièces jointes), PDF joint, ouvert comme brouillon dans la messagerie.

**Chartes graphiques** (une ou plusieurs par compte)
- Logo, couleurs avec contrôle de contraste WCAG (4,5:1), polices, coordonnées, appel, style des intertitres et des encadrés.
- En-tête et pied de page au choix : composés automatiquement, bandeau en image, ou **dessinés en SVG** (Inkscape par exemple) — les textes du dessin sont extraits et ré-écrits en vrai texte, les liens sont automatiques, et `{titre}` place le titre du tract dans l'en-tête.
- Protection d'une charte, corbeille (30 jours), historique de 15 versions, détection des modifications simultanées, export/import JSON.

**Banque d'éléments** (pictogrammes, numéros, séparateurs)
- Chaque élément porte son texte alternatif. Ajout de plusieurs fichiers à la fois ou d'un dossier entier, nettoyage automatique des SVG, 35 éléments fournis par défaut.

**Administration**
- Un compte par organisation, isolation stricte des données, mot de passe provisoire à changer, limitation des tentatives de connexion et du débit de génération de PDF.

## Dépendances

| Élément | Version / détail |
|---|---|
| PHP | 8.1 ou plus |
| Extensions PHP | `pdo_sqlite`, `mbstring`, `dom`, `gd`, `zlib`, `iconv`, `curl` et `zip` (installation et paquetage) |
| Fonctions PHP | `proc_open` autorisé (lancement de Chrome) |
| Navigateur sans interface | Chrome, Chromium ou Edge. Sur Linux, `chrome-headless-shell` ([Chrome for Testing](https://googlechromelabs.github.io/chrome-for-testing/)) est téléchargé par l'assistant d'installation dans `data/chrome/` (environ 115 Mo) |
| Python 3 | Linux mutualisé seulement : extrait quelques bibliothèques système manquantes de paquets RPM, sans droits administrateur |
| Serveur web | Apache (fichiers `.htaccess` fournis). Avec nginx, interdire l'accès à `data/` et `src/` |
| Base de données | SQLite (un seul fichier, `data/tracteur.sqlite`) |

Aucun gestionnaire de paquets (Composer, npm) n'est nécessaire : le code est autonome. Les polices sont intégrées (voir [THIRD-PARTY.md](THIRD-PARTY.md)).

## Installation

### En local (développement ou essai)

```bash
git clone https://github.com/<votre-compte>/tracteur.git
cd tracteur
php tools/install.php        # crée data/tracteur.sqlite, un compte « admin » et un compte « demo »
php -S localhost:8000        # ou placez le dossier sous Apache/XAMPP
```

Les mots de passe générés sont écrits dans `data/identifiants_initiaux.txt` (à supprimer après lecture). Ouvrez `http://localhost:8000/`. Sous Windows, Chrome ou Edge installés sont détectés automatiquement ; sous Linux, installez `chromium` ou lancez l'assistant d'installation.

### Sur un hébergement mutualisé

1. Construisez le paquet : `php tools/empaqueter.php` (produit `dist/tracteur_deploiement.zip`, sans base de données ni outils de développement), ou téléchargez-le depuis les versions du dépôt.
2. Envoyez et décompressez le zip sur l'hébergement (PHP 8.1 ou plus, idéalement sur un sous-domaine en HTTPS).
3. Créez le fichier `data/cle_installation.txt` contenant une phrase aléatoire d'au moins 16 caractères.
4. Ouvrez `installation.php`, saisissez cette phrase, puis suivez les quatre étapes : diagnostic, création de la base et du compte `admin`, installation du moteur PDF (« Installer Chrome »), test de création d'un PDF.
5. Verrouillez l'installation, puis **supprimez** `installation.php` et `data/cle_installation.txt`.

Pour **mettre à jour**, envoyez de nouveau le contenu du zip **sans toucher au dossier `data/`** (base de données, Chrome installé).

Testé sur PlanetHoster (CloudLinux) avec PHP 8.4, et sous Windows avec XAMPP.

### Configuration facultative

`data/config.local.php` peut retourner un tableau qui remplace les réglages par défaut du moteur PDF :

```php
<?php
return ['chrome' => '/usr/bin/chromium', 'timeout' => 90, 'simultanes' => 2, 'par_minute' => 8];
```

## Utilisation

Le [manuel d'utilisation](docs/MANUEL.md) est aussi accessible dans l'application (menu « Aide »). Résumé :

1. L'administrateur crée le compte de l'organisation (page « Comptes »).
2. L'organisation crée sa charte (page « Chartes »), éventuellement complète sa banque d'éléments.
3. Les rédacteurs et rédactrices écrivent dans l'éditeur, contrôlent l'onglet « Accessibilité », puis exportent **PDF + mail**.

## Organisation du code

```
index.php, login.php, app.php, …   pages (PHP « à plat », sans framework)
api/                               points d'accès JSON : PDF, banque, charte
src/                               bootstrap (base, sessions, CSRF), chartes, banque,
                                   moteur PDF (pdf.php), PDF/UA (pdfua.php),
                                   extraction des en-têtes/pieds SVG (pied.php)
assets/                            éditeur (editeur.js, editeur.css), polices intégrées
docs/MANUEL.md                     manuel d'utilisation (affiché par aide.php)
tools/                             installation CLI, paquetage, export d'amorce
tests/run.php                      tests de bout en bout
data/                              base SQLite, Chrome, fichiers temporaires (non versionné)
```

## Tests

Les tests de bout en bout interrogent une installation en marche (ils créent et suppriment un compte d'essai) :

```bash
php tools/install.php
php tests/run.php http://localhost:8000/
```

Une cinquantaine de vérifications : droits et isolation des comptes, chartes, contraste, banque, import/export, génération du PDF. La génération du PDF demande un Chrome utilisable.

Pour vérifier l'accessibilité d'un PDF produit : [veraPDF](https://verapdf.org/) (`verapdf --flavour ua1 fichier.pdf`) ou PAC (Windows).

## Sécurité

- Jetons CSRF sur tous les formulaires, sessions durcies, limitation des tentatives de connexion (5 par 15 minutes), mots de passe hachés (`password_hash`).
- Le HTML envoyé au moteur PDF est réduit à une liste blanche de balises et d'attributs ; les SVG téléversés sont nettoyés (scripts, ressources externes, entités).
- Chaque compte n'accède qu'à ses propres chartes et à sa propre banque (filtre imposé côté serveur).
- Limitation du nombre de PDF simultanés et par minute.
- Le dossier `data/` ne doit jamais être servi par le serveur web (Apache : `data/.htaccess`).

Pour signaler une faille, merci d'écrire directement à l'éditeur plutôt que d'ouvrir une discussion publique.

## Contribuer

Les retours, corrections et propositions sont les bienvenus (issues et pull requests). Quelques repères : le code et ses commentaires sont en français ; l'accessibilité prime sur l'esthétique ; un formulaire refusé ne doit jamais faire perdre le travail déjà saisi ; lancez `tests/run.php` avant toute proposition. L'interface n'est pas encore traduite : une internationalisation serait précieuse.

## Licence

Copyright © 2026 Thomas Garnier — <thomas.garnier83@gmail.com>.

Tracteur est un logiciel libre distribué sous licence **GNU Affero General Public License version 3 ou ultérieure** ([LICENSE](LICENSE)). Si vous modifiez Tracteur et le proposez en ligne à d'autres personnes, la licence vous demande de mettre à leur disposition le code source de vos modifications.

Les polices intégrées et les composants tiers conservent leurs propres licences : voir [THIRD-PARTY.md](THIRD-PARTY.md).

# Manuel d'utilisation de Tracteur

Tracteur est un éditeur de tracts **accessibles**. Vous écrivez votre tract dans votre navigateur, avec la charte graphique de votre organisation, puis vous obtenez :

- un **PDF** prêt à imprimer ou à diffuser, balisé pour les lecteurs d'écran (titres, listes, ordre de lecture, textes alternatifs) ;
- un **mail** au format HTML (fichier `.eml`), avec le PDF en pièce jointe, qui s'ouvre dans votre messagerie comme un brouillon prêt à envoyer.

Ce manuel s'adresse à celles et ceux qui rédigent les tracts. Les parties « Chartes », « Banque d'éléments » et « Administration » concernent les personnes qui gèrent l'espace de leur organisation.

## Pour commencer

### Se connecter

Ouvrez l'adresse de votre installation de Tracteur, puis saisissez votre **identifiant** et votre **mot de passe**. L'identifiant est celui de votre organisation (par exemple `mon-syndicat`) : toutes les personnes de l'organisation peuvent partager le même compte.

À la première connexion, le mot de passe provisoire donné par l'administrateur doit être remplacé par un mot de passe personnel d'au moins 12 caractères. Le menu « Mot de passe » permet de le changer plus tard.

Après 5 essais ratés en 15 minutes, la connexion est suspendue quelques minutes : attendez, puis recommencez.

### Les pages de votre espace

- **Éditeur** : là où l'on écrit les tracts.
- **Chartes** : l'identité graphique de l'organisation (logo, couleurs, coordonnées, en-tête, pied de page).
- **Banque d'éléments** : les pictogrammes, numéros et séparateurs que l'on insère dans les tracts.
- **Aide** : ce manuel.
- **Mot de passe** et **Déconnexion**.

## Écrire un tract

### La barre du haut

- **Nouveau** : commence un tract vide (votre travail en cours est d'abord sauvegardé dans le navigateur).
- **Ouvrir un projet…** et **Enregistrer le projet** : enregistrent le tract dans un petit fichier `.json` sur votre ordinateur, et le rouvrent plus tard ou sur une autre machine.
- **PDF seul** : crée le PDF.
- **Mail seul (.eml)** : crée le mail, sans PDF joint.
- **Exporter PDF + mail** : crée le PDF, le télécharge, puis crée le mail avec ce PDF en pièce jointe.

> Votre travail n'est jamais perdu : le tract en cours est conservé automatiquement dans votre navigateur. Si vous fermez la page par erreur, le message « Brouillon précédent restauré » apparaît à la réouverture.

### La colonne de gauche

Elle est organisée en **boîtes repliables** : cliquez sur le titre d'une boîte pour l'ouvrir ou la fermer. Le résumé à droite du titre rappelle ce qui est choisi. Les boutons « Tout replier » et « Tout déplier » agissent sur toutes les boîtes.

**Charte et modèle**

- **Charte** : l'identité graphique du tract (logo, couleurs, coordonnées, en-tête et pied de page). Changer de charte recharge la page ; votre brouillon est conservé.
- **Modèle** : « 1 colonne » (texte plus grand, idéal pour un texte long) ou « 2 colonnes » (encadrés colorés ; l'ordre de lecture reste : colonne de gauche, puis colonne de droite).

**Titre et objet du mail**

- **Titre du tract** : pour le couper en deux lignes, appuyez sur **Entrée** à l'endroit voulu (ou tapez le signe `|`).
- **Titre en majuscules** : met le titre en capitales. À éviter pour un titre long, moins lisible.
- **Objet du mail** : à renseigner seulement s'il doit être différent du titre.

**Typographie et mise en forme** : police du tract, police du titre, alignement du texte (justifié ou à gauche), décoration des intertitres.

**Contenu du tract** : la liste des blocs (voir ci-dessous).

### Les blocs

Un tract est une suite de blocs. Sous la liste, « Ajouter un bloc » propose : intertitre, texte, liste, image, encadré, et un accès à la banque d'éléments. Chaque bloc peut être déplacé (haut/bas), dupliqué, supprimé ou replié.

**Intertitre** : un titre de section, avec un niveau (2, 3…). Il peut recevoir une décoration (pictogramme ou numéro pris dans la banque), à gauche ou à droite.

**Texte** : un ou plusieurs paragraphes. Dans la barre du bloc :

- **G** (gras) et **I** (italique) ;
- **Saut de ligne** (ou Maj + Entrée au clavier) : va à la ligne **sans créer un nouveau paragraphe**. La touche Entrée, elle, crée un nouveau paragraphe ;
- **Lien** : sélectionnez d'abord les mots, puis cliquez, puis saisissez l'adresse (`https://…` ou `mailto:…`). Choisissez des mots qui disent où mène le lien (« le site du syndicat »), jamais « cliquez ici ». Le même bouton devient **Retirer le lien** dès que le curseur ou la sélection se trouve dans un lien : cliquez dessus pour retirer ce lien (les mots restent) ;
- **Couleur** : sélectionnez des mots puis cliquez pour les mettre dans la couleur d'accentuation de la charte (la couleur est choisie par la charte : son contraste est contrôlé). Comme pour les liens, le même bouton devient **Couleur normale** dès que le curseur ou la sélection est dans des mots colorés : cliquez dessus pour annuler. Non disponible dans les encadrés, dont le fond change.

**Liste** : à puces ou numérotée. Chaque élément est un paragraphe : la touche Entrée passe à l'élément suivant, et vous disposez des mêmes boutons que pour un texte (gras, italique, lien, couleur, saut de ligne). Les listes des anciens projets sont reprises telles quelles.

**Image** : choisissez un fichier (PNG, JPEG, SVG).

- **Texte alternatif** : décrivez ce que montre l'image, pas son format. Obligatoire, sauf si l'image est purement décorative (case « décorative »).
- Taille, crédit (légende), inclinaison éventuelle.
- Option « texte à côté » : l'image et un texte sont placés côte à côte.

**Encadré** : un texte mis en valeur sur fond jaune, rouge ou gris (selon la charte). Il peut commencer par un titre : choisissez un « Niveau du texte » (H2, H3…) ; les paragraphes qui deviendront des titres sont marqués d'un trait rouge dans la zone de saisie, et vous décidez si le niveau s'applique au premier paragraphe seulement ou à tous. **Pour un titre sur deux lignes, utilisez le saut de ligne (Maj + Entrée), pas Entrée** : Entrée crée un nouveau paragraphe, donc un second titre si le niveau s'applique à tous les paragraphes, ou un simple texte sinon. Un titre coupé par un saut de ligne reste un seul titre pour les lecteurs d'écran. Vous pouvez y ajouter un **pictogramme** pris dans la banque (par exemple un porte-voix pour une annonce) : « Choisir un pictogramme dans la banque », puis sa position (à gauche, à droite ou au-dessus du texte), sa taille et, si vous le souhaitez, son **inclinaison** (de −180 à 180 degrés, 0 = droit) pour donner du mouvement ; seul le pictogramme s'incline, le texte reste droit, dans le PDF comme dans le mail. Comme pour toute image, indiquez son texte alternatif, ou cochez « Décoratif » s'il ne fait qu'embellir. Sur un encadré rouge, choisissez un pictogramme clair : un pictogramme rouge s'y verrait à peine.

**Banque d'éléments** : ouvre la banque pour insérer un pictogramme, un numéro ou un séparateur. Chaque élément porte déjà son texte alternatif.

### Les trois onglets de droite

- **Tract (PDF)** : l'aperçu du tract tel qu'il sera imprimé.
- **Mail** : l'aperçu du mail.
- **Accessibilité** : la liste des points à corriger ou à surveiller (titre manquant, image sans texte alternatif, lien peu clair, texte trop long, risque de page blanche…). Le nombre à côté du nom de l'onglet indique le nombre de problèmes. Un tract sans erreur reçoit la mention PDF/UA dans les métadonnées du PDF.

## Produire le PDF et le mail

Cliquez sur **PDF seul**, **Mail seul (.eml)** ou **Exporter PDF + mail**. La création du PDF peut prendre **quelques secondes** : un bandeau jaune avec une roue qui tourne s'affiche sous les boutons, et les boutons d'export sont grisés jusqu'à la fin. Ne fermez pas la page pendant ce temps.

Dans le mail, avec l'en-tête simple (logo et nom de l'organisation), **le titre du tract s'affiche à droite du logo, à la place du nom** ; il n'est pas répété sous l'en-tête. Avec un en-tête en image, le titre reste sous l'image.

Pour envoyer le mail : double-cliquez sur le fichier `.eml` téléchargé. Il s'ouvre dans votre messagerie comme un brouillon (testé avec Outlook), avec le PDF en pièce jointe. Relisez, ajoutez les destinataires, envoyez.

Si le PDF ne peut pas être créé par le serveur, Tracteur vous propose d'ouvrir la fenêtre d'impression du navigateur (puis « Enregistrer au format PDF »), ou de créer le mail sans PDF joint. Ce PDF-là n'est en revanche pas balisé de la même façon.

## L'accessibilité en pratique

Tracteur fait une grande partie du travail, mais certaines choses ne dépendent que de vous :

- Un **titre** clair, et des **intertitres** dans l'ordre (ne pas sauter de niveau).
- Un **texte alternatif** utile pour chaque image porteuse de sens : ce qu'on verrait si on décrivait l'image au téléphone. Une image purement décorative se marque « décorative ».
- Des **liens explicites**.
- Des **phrases courtes**, un tract de **une à deux pages**.
- Ne jamais transmettre une information par la **seule couleur**.

Le PDF produit est balisé (titres, listes, liens, ordre de lecture, langue, titre du document). Une vérification avec l'outil libre veraPDF sur des tracts types a donné « conforme PDF/UA-1 ». Cela garantit la structure, pas la pertinence des textes alternatifs.

## Les chartes

Une charte rassemble l'identité graphique : nom de l'organisation, logo, couleurs, coordonnées, en-tête, pied de page, polices. Un compte peut avoir plusieurs chartes (par exemple une par type de document), et la charte **par défaut** est celle qui s'ouvre dans l'éditeur.

### Créer et gérer

Page **Chartes** : créer une charte vierge ou une copie d'une charte existante, la modifier, l'ouvrir dans l'éditeur, la définir par défaut.

Pour **sauvegarder ou transférer** vos chartes : « Télécharger toutes mes chartes » produit un fichier `.json` ; « Ajouter des chartes depuis un fichier » les réimporte (sur ce compte ou sur une autre installation). Rien n'est remplacé : un nom déjà pris reçoit un numéro.

### Protections

Comme plusieurs personnes peuvent utiliser le même compte :

- **Protéger** une charte la verrouille : plus de modification ni de suppression tant que la protection n'est pas levée.
- **Supprimer** place la charte dans la **corbeille** : elle y reste 30 jours et peut être restaurée.
- Chaque enregistrement conserve les **15 versions précédentes** : « Historique des versions », en bas de la page de la charte, permet d'y revenir.
- Si deux personnes modifient la même charte en même temps, la seconde est prévenue avant d'écraser le travail de la première.

### Modifier une charte

La page est organisée en boîtes repliables :

- **Identité** : nom interne de la charte.
- **Organisation et contacts** : nom, site web, page Facebook, mail, adresses, phrase d'appel.
- **Couleurs** : couleur principale, couleur d'accent, texte, liens. Un **contrôle de contraste** (4,5:1 minimum) bloque l'enregistrement d'une couleur illisible.
- **En-tête** : trois choix en langage courant — *composé avec mon logo et mes coordonnées*, *bandeau en image* (PNG ou JPEG, avec texte alternatif), ou *dessiné par moi* (fichier SVG).
- **Pied de page** : *composé avec mon appel, mes liens et mes adresses*, ou *dessiné par moi* (SVG).
- **Mise en forme du contenu** : style des intertitres, des encadrés, espace sous l'en-tête, police par défaut.

**Dessiner son en-tête ou son pied de page en SVG** : réalisez le dessin avec un logiciel de dessin vectoriel (Inkscape par exemple) en gardant les textes en texte (non convertis en tracés). Tracteur extrait les textes, les remet sous forme de vrai texte lisible par les lecteurs d'écran, et conserve le décor. Un texte identique au site, à la page Facebook ou au mail de la charte devient automatiquement un lien. Dans un **en-tête**, écrivez `{titre}` à l'endroit où doit apparaître le titre du tract ; il s'y affiche (réduit automatiquement s'il est trop long) et ne se répète pas sous l'en-tête.

## La banque d'éléments

La banque contient les pictogrammes, numéros et séparateurs insérables dans les tracts. Chaque élément a un **nom**, une **catégorie**, des **mots-clés**, une **taille** proposée et un **texte alternatif** (ou la mention « décoratif »). Un nouveau compte reçoit une banque par défaut, que l'on complète : rien n'est jamais remplacé d'un coup.

Page **Banque d'éléments** :

- **Ajouter des éléments** : choisissez un ou plusieurs fichiers (SVG, PNG, JPEG). Ils sont envoyés, puis vous complétez pour chacun la catégorie et le texte alternatif. Le nom est prérempli d'après le nom du fichier. « Appliquer à tous » reporte une même catégorie et une même taille. Rien n'est perdu si un champ est incomplet.
- **Un dossier d'icônes** : envoyez un dossier entier (ou plusieurs sous-dossiers). Le nom du sous-dossier devient la catégorie, le nom du fichier devient le nom ; « Tout ajouter tel quel » valide d'un coup.
- **Modifier / Supprimer** un élément.
- **Ajouter les éléments par défaut manquants** : complète votre banque avec la banque d'origine, sans rien remplacer.
- **Télécharger ma banque** (sauvegarde `banque.js`) et **Importer un fichier banque.js**.

Les SVG sont nettoyés automatiquement (scripts et ressources externes supprimés). Pour un SVG, convertissez d'abord les textes en tracés.

## Administration des comptes

Réservé au compte `admin`. La page **Comptes** permet de :

- **créer** le compte d'une organisation (identifiant, nom, mot de passe provisoire — généré si vous le laissez vide), éventuellement à partir de la charte d'un compte existant ;
- **réinitialiser** un mot de passe (un nouveau mot de passe provisoire est affiché une seule fois) ;
- **désactiver** ou réactiver un compte (la connexion est coupée immédiatement) ;
- **supprimer** un compte, avec ses chartes et sa banque, après confirmation en saisissant son identifiant.

Chaque compte ne voit que ses propres chartes et sa propre banque.

## Questions fréquentes

**Le PDF met longtemps à se créer.** Quelques secondes à une quinzaine, selon le serveur. Attendez la fin du bandeau d'attente.

**« Trop de PDF demandés, patientez une minute ».** Le nombre de PDF par compte est limité (8 par minute).

**« Le serveur est occupé ».** Plusieurs créations de PDF sont en cours en même temps ; réessayez dans un instant.

**Mon tract déborde sur une deuxième page presque vide.** L'onglet « Accessibilité » prévient quand le texte se termine tout près du bas d'une page ou quand le pied de page passerait seul sur la page suivante : raccourcissez ou allongez de quelques lignes, puis vérifiez le PDF.

**Un titre est trop long pour l'en-tête dessiné.** Il est réduit automatiquement jusqu'à environ 55 % de sa taille ; au-delà, l'éditeur demande de le raccourcir.

**J'ai fermé la page sans enregistrer.** Rouvrez l'éditeur : le brouillon est restauré. Pour garder un tract durablement, utilisez « Enregistrer le projet ».

**Je veux changer d'ordinateur.** Enregistrez le projet (fichier `.json`) et ouvrez-le sur l'autre machine.

**Mon logo ou mon texte n'apparaît pas comme prévu.** Vérifiez la charte choisie dans l'éditeur, puis le mode d'en-tête et de pied de page dans la charte.

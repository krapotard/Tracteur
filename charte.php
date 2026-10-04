<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/charte.php';
require __DIR__ . '/src/vue.php';
$u = exiger_connexion();
if ($u['role'] === 'admin') redirige('admin.php');
$pdo = db(); $cid = (int)$u['id'];
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

$st = $pdo->prepare('SELECT id, nom, contenu, maj_le, protegee FROM chartes WHERE id = ? AND compte_id = ? AND supprimee_le IS NULL'); $st->execute([$id, $cid]);
$row = $st->fetch();
if (!$row) { flash('err', 'Charte introuvable.'); redirige('chartes.php'); }
$charte = json_decode($row['contenu'], true) ?: charte_vierge($u['nom']);
$nomCharte = $row['nom'];
$erreurs = []; $enAttente = []; $versionPage = (string)$row['maj_le'];
$protegee = !empty($row['protegee']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restaurer_version') {      // retour a une version precedente
    exiger_post();
    $sv = $pdo->prepare('SELECT nom, contenu, cree_le FROM chartes_versions WHERE id = ? AND charte_id = ?'); $sv->execute([(int)($_POST['version_id'] ?? 0), $id]);
    $v = $sv->fetch();
    if ($protegee) flash('err', 'Cette charte est prot&eacute;g&eacute;e : levez la protection (page &laquo;&nbsp;Chartes&nbsp;&raquo;) pour la modifier.');
    elseif (!$v) flash('err', 'Version introuvable.');
    else {
        charte_sauver_version($pdo, $id);                                    // la version actuelle reste elle-meme retrouvable
        $pdo->prepare("UPDATE chartes SET nom = ?, contenu = ?, maj_le = datetime('now') WHERE id = ? AND compte_id = ?")->execute([$v['nom'], $v['contenu'], $id, $cid]);
        attente_effacer($cid, $id);
        flash('ok', 'Version du ' . h((string)$v['cree_le']) . ' restaur&eacute;e. La version pr&eacute;c&eacute;dente est conserv&eacute;e dans l\'historique.');
    }
    redirige('charte.php?id=' . $id);
}

$stocke = $charte;                                               // version enregistree
if ($_SERVER['REQUEST_METHOD'] === 'POST') $charte = array_replace($charte, attente_lire($cid, $id));   // + fichiers deja recus lors d'un essai refuse
else attente_effacer($cid, $id);                                  // page rouverte : on repart de la version enregistree

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exiger_post();
    $p = fn(string $k) => (string)($_POST[$k] ?? '');
    $adresses = [];
    foreach ((array)($_POST['adresse'] ?? []) as $bloc) { if (is_string($bloc)) $adresses[] = preg_split('/\R/u', $bloc) ?: []; }
    $neuf = [
        'orgNom' => $p('orgNom'), 'orgNomMaj' => $p('orgNomMaj'),
        'site' => ['url' => $p('site_url'), 'texte' => $p('site_texte'), 'libelle' => $p('site_libelle')],
        'facebook' => ['url' => $p('fb_url'), 'texte' => $p('fb_texte'), 'libelle' => $p('fb_libelle')],
        'mail' => $p('mail'), 'adresses' => $adresses, 'appel' => $p('appel'),
        'couleurs' => ['rouge' => $p('couleur_rouge'), 'jaune' => $p('couleur_jaune'), 'texte' => $p('couleur_texte'), 'lien' => $p('couleur_lien')],
        'logo' => !empty($_POST['retirer_logo']) ? '' : ($charte['logo'] ?? ''), 'logoSvg' => (empty($_POST['retirer_svg']) && empty($_POST['retirer_logo'])) ? ($charte['logoSvg'] ?? '') : '', 'police' => $p('police'),
        'logoCote' => $p('logoCote'), 'enteteStyle' => $p('enteteStyle'), 'intertitres' => $p('intertitres'), 'encadres' => $p('encadres'),
        'enteteMode' => $p('enteteMode'), 'piedMode' => $p('piedMode'), 'espaceEntete' => $p('espaceEntete'),
        'depart' => $charte['depart'] ?? 'vide', 'largeurMail' => $charte['largeurMail'] ?? 680,
    ];
    if (isset($_FILES['logo_svg']) && $_FILES['logo_svg']['error'] === UPLOAD_ERR_OK) {
        $e2 = []; $brut = (string)file_get_contents($_FILES['logo_svg']['tmp_name']);
        $r = !@getimagesizefromstring($brut) ? assainir_svg($brut, $e2) : null;
        if ($r) $neuf['logoSvg'] = 'data:image/svg+xml;base64,' . base64_encode($r[0]);
        else { foreach ($e2 ?: ['Le logo vectoriel doit &ecirc;tre un fichier SVG.'] as $m) $erreurs[] = $m; }
    }
    $neuf['iconesPied'] = !empty($_POST['iconesPied']);
    foreach (['entete' => ['enteteImage', 'd\'en-t&ecirc;te'], 'pied' => ['piedImage', 'de pied de page']] as $pre => [$cle, $lib]) {
        if (!empty($_POST[$pre . '_retirer'])) { $neuf[$cle] = null; continue; }
        $d = is_array($charte[$cle] ?? null) ? $charte[$cle] : ['png' => '', 'svg' => '', 'alt' => ''];
        $nouveauPng = isset($_FILES[$pre . '_png']) && $_FILES[$pre . '_png']['error'] === UPLOAD_ERR_OK;
        if ($nouveauPng) { $img = traiter_banniere($_FILES[$pre . '_png']['tmp_name'], $erreurs); if ($img) { $d['png'] = $img; $d['svg'] = ''; } }
        if (isset($_FILES[$pre . '_svg']) && $_FILES[$pre . '_svg']['error'] === UPLOAD_ERR_OK) {
            $e2 = []; $brut = (string)file_get_contents($_FILES[$pre . '_svg']['tmp_name']);
            $r = !@getimagesizefromstring($brut) ? assainir_svg($brut, $e2) : null;
            if ($r) $d['svg'] = 'data:image/svg+xml;base64,' . base64_encode($r[0]); else foreach ($e2 ?: ['La version vectorielle doit &ecirc;tre un fichier SVG.'] as $m) $erreurs[] = $m;
        }
        $d['alt'] = $p($pre . '_alt');
        if ($d['png'] === '' && ($d['alt'] !== '' || $d['svg'] !== '')) $erreurs[] = "Image $lib : ajoutez d'abord l'image (PNG ou JPEG, utilis&eacute;e dans le mail)." . ($pre === 'pied' ? ' <strong>Vous avez dessin&eacute; un pied de page avec du texte et des liens ?</strong> Choisissez &laquo;&nbsp;J\'ai d&eacute;j&agrave; dessin&eacute; mon pied de page&nbsp;&raquo; dans la bo&icirc;te Pied de page : elle ne demande que le SVG.' : '');
        $neuf[$cle] = $d['png'] !== '' ? $d : null;
    }
    $neuf['piedTexte'] = $p('piedTexte');
    foreach (['entete_fond' => ['enteteFond', 'd\'en-t&ecirc;te'], 'pied_fond' => ['piedFond', 'de pied de page']] as $pre => [$cle, $lib]) {
        if (!empty($_POST[$pre . '_retirer'])) { $neuf[$cle] = null; continue; }
        $d = is_array($charte[$cle] ?? null) ? $charte[$cle] : ['png' => '', 'svg' => ''];
        if (isset($_FILES[$pre . '_png']) && $_FILES[$pre . '_png']['error'] === UPLOAD_ERR_OK) { $img = traiter_banniere($_FILES[$pre . '_png']['tmp_name'], $erreurs); if ($img) { $d['png'] = $img; $d['svg'] = ''; } }
        if (isset($_FILES[$pre . '_svg']) && $_FILES[$pre . '_svg']['error'] === UPLOAD_ERR_OK) {
            $e2 = []; $brut = (string)file_get_contents($_FILES[$pre . '_svg']['tmp_name']);
            $r = !@getimagesizefromstring($brut) ? assainir_svg($brut, $e2) : null;
            if ($r) $d['svg'] = 'data:image/svg+xml;base64,' . base64_encode($r[0]); else foreach ($e2 ?: ['La version vectorielle doit &ecirc;tre un fichier SVG.'] as $m) $erreurs[] = $m;
        }
        if ($d['png'] === '' && $d['svg'] !== '') $erreurs[] = "Fond $lib : ajoutez d'abord l'image (PNG ou JPEG).";
        $neuf[$cle] = $d['png'] !== '' ? $d : null;
    }
    foreach (['entete_compose' => ['enteteCompose', 'droite'], 'pied_compose' => ['piedCompose', 'gauche']] as $pre => [$cle, $coteDef]) {
        if (!empty($_POST[$pre . '_retirer'])) { $neuf[$cle] = null; continue; }
        $cur = is_array($charte[$cle] ?? null) ? $charte[$cle] : null;
        if (isset($_FILES[$pre]) && $_FILES[$pre]['error'] === UPLOAD_ERR_OK) {
            $e2 = []; $notes = [];
            $r = extraire_pied_compose((string)file_get_contents($_FILES[$pre]['tmp_name']), $e2, $notes, $coteDef, $pre === 'entete_compose');
            if ($r) { $cur = $r; foreach (array_unique($notes) as $m) flash('ok', $m); }
            else foreach ($e2 as $m) $erreurs[] = $m;
        } elseif ($cur && isset($_POST[$pre . '_table'])) {      // le tableau etait affiche : on lit les cases "afficher"
            foreach ($cur['textes'] as $i => $t) $cur['textes'][$i]['actif'] = isset($_POST[$pre . '_actif'][$i]);
        }
        if ($cur && isset($_POST[$pre . '_table'])) foreach ($cur['textes'] as $i => $t) if (($t['role'] ?? '') === 'titre') {   // zone du titre
            $lg = (float)($_POST[$pre . '_titre_largeur'] ?? 0); $cur['textes'][$i]['largeur'] = $lg > 0 ? $lg : null;
            if (in_array($_POST[$pre . '_titre_ancre'] ?? '', ['debut', 'milieu', 'fin'], true)) $cur['textes'][$i]['ancre'] = $_POST[$pre . '_titre_ancre'];
        }
        if ($cur && in_array($_POST[$pre . '_cote'] ?? '', ['gauche', 'droite'], true)) $cur['cote'] = $_POST[$pre . '_cote'];
        $neuf[$cle] = $cur;
    }
    $nomNeuf = mb_substr(trim($p('nom_charte')), 0, 80);
    if ($nomNeuf === '') $erreurs[] = 'Le nom de la charte est obligatoire.';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $l = traiter_logo($_FILES['logo']['tmp_name'], $erreurs);
        if ($l) $neuf['logo'] = $l;
    } elseif (isset($_FILES['logo']) && !in_array($_FILES['logo']['error'], [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
        $erreurs[] = 'Le logo n\'a pas pu &ecirc;tre t&eacute;l&eacute;vers&eacute; (fichier trop gros ?).';
    }
    $versionForm = (string)($_POST['version'] ?? '');
    if ($protegee) $erreurs[] = 'Cette charte est <strong>prot&eacute;g&eacute;e</strong> : elle ne peut pas &ecirc;tre modifi&eacute;e. Levez la protection depuis la page &laquo;&nbsp;Chartes&nbsp;&raquo;, ou enregistrez-la sous un autre nom en cr&eacute;ant une copie.';
    elseif ($versionForm !== '' && $versionForm !== (string)$row['maj_le']) {
        $erreurs[] = '<strong>Cette charte a &eacute;t&eacute; modifi&eacute;e par quelqu\'un d\'autre</strong> (le ' . h((string)$row['maj_le']) . ') pendant que vous la modifiiez. Vos saisies sont conserv&eacute;es ci-dessous. Si vous enregistrez de nouveau, elles remplaceront cette version, qui restera disponible dans l\'historique (en bas de page).';
        $versionPage = (string)$row['maj_le'];       // un second enregistrement est alors un choix volontaire
    }
    [$propre, $errV] = valider_charte($neuf);
    $erreurs = array_merge($erreurs, $errV);
    if (!$errV) {
        foreach (verifier_contrastes($propre['couleurs']) as $r)
            if (!$r['ok']) $erreurs[] = 'Contraste insuffisant pour les ' . $r['libelle'] . ' : ' . number_format($r['ratio'], 2, ',', '') . ':1 (minimum 4,5:1). Choisissez une couleur plus fonc&eacute;e ou plus claire.';
    }
    if (!$erreurs) {
        charte_sauver_version($pdo, $id);
        $pdo->prepare("UPDATE chartes SET nom = ?, contenu = ?, maj_le = datetime('now') WHERE id = ? AND compte_id = ?")
            ->execute([$nomNeuf, json_encode($propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $id, $cid]);
        attente_effacer($cid, $id);
        flash('ok', 'Charte enregistr&eacute;e.');
        redirige('charte.php?id=' . $id);
    }
    // erreurs : on r&eacute;affiche la saisie sans rien enregistrer
    // On reaffiche la saisie ET les fichiers deja recus (meme ceux que la validation a refuses pour un autre motif, par exemple une image sans texte alternatif).
    $charte = $propre;
    foreach (CLES_FICHIERS_CHARTE as $cleF) if (array_key_exists($cleF, $neuf)) $charte[$cleF] = $neuf[$cleF];
    attente_ecrire($cid, $id, $neuf);
    $libellesF = ['logo' => 'logo', 'logoSvg' => 'logo vectoriel', 'enteteImage' => 'image d\'en-t&ecirc;te', 'piedImage' => 'banni&egrave;re du pied de page', 'enteteFond' => 'fond d\'en-t&ecirc;te', 'piedFond' => 'fond de pied de page', 'enteteCompose' => 'en-t&ecirc;te dessin&eacute; (SVG)', 'piedCompose' => 'pied de page dessin&eacute; (SVG)'];
    foreach ($libellesF as $cleF => $lib) if (!empty($neuf[$cleF]) && $neuf[$cleF] != ($stocke[$cleF] ?? null)) $enAttente[] = $lib;
    $nomCharte = $nomNeuf;
}

$c = $charte + ['site' => ['url' => '', 'texte' => '', 'libelle' => ''], 'facebook' => ['url' => '', 'texte' => '', 'libelle' => ''], 'adresses' => [], 'couleurs' => []];
$k = $c['couleurs'] + ['rouge' => '#C00000', 'jaune' => '#FCC953', 'texte' => '#1a1a1a', 'lien' => '#0b4f9c'];
$adr = array_map(fn($a) => implode("\n", $a), $c['adresses']);
$nbAdr = count(array_filter($adr, fn($a) => trim($a) !== ''));
while (count($adr) < 2) $adr[] = '';
if (count($adr) < 6) $adr[] = '';
$contrastes = verifier_contrastes($k);
$contrasteOk = !in_array(false, array_column($contrastes, 'ok'), true);
$modeE = $c['enteteMode'] ?? (!empty($c['enteteCompose']) ? 'svg' : (!empty($c['enteteImage']) ? 'image' : 'standard'));
$modeP = $c['piedMode'] ?? (!empty($c['piedCompose']) ? 'svg' : 'standard');
const ETATS_ENTETE = ['standard' => 'Compos&eacute; avec mon logo et mes coordonn&eacute;es', 'image' => 'Bandeau en image', 'svg' => 'Dessin&eacute; par moi (SVG)'];
const ETATS_PIED = ['standard' => 'Compos&eacute; avec mon appel, mes liens et mes adresses', 'svg' => 'Dessin&eacute; par moi (SVG)'];

page_debut('Modifier la charte', $u, 'chartes');
if ($erreurs) { echo '<div class="msg msg-err" role="alert"><strong>Rien n\'a &eacute;t&eacute; enregistr&eacute;.</strong><ul>'; foreach ($erreurs as $e) echo '<li>' . $e . '</li>'; echo '</ul></div>'; }
if ($erreurs && $enAttente) echo '<div class="msg msg-ok" role="status"><strong>Vos fichiers sont conserv&eacute;s.</strong> Ils ont bien &eacute;t&eacute; re&ccedil;us et sont affich&eacute;s ci-dessous : <em>' . implode(', ', $enAttente) . '</em>. Vous n\'avez pas &agrave; les d&eacute;poser de nouveau : corrigez seulement le point signal&eacute; ci-dessus, puis enregistrez.</div>';

// ---------- petits composants d'interface ----------
$champ = fn(string $nom, string $val, string $lib, string $aide = '', string $type = 'text', string $extra = '') =>
    '<label for="' . $nom . '">' . $lib . '</label><input id="' . $nom . '" name="' . $nom . '" type="' . $type . '" value="' . h($val) . '"' . ($aide ? ' aria-describedby="' . $nom . '-a"' : '') . ' ' . $extra . '>'
    . ($aide ? '<p class="aide" id="' . $nom . '-a">' . $aide . '</p>' : '');
/** Boite repliable : titre + resume de l'etat (dans le titre, visible meme replie). */
$boite = function (string $cle, string $titre, string $etat, string $contenu, bool $ouvert = false, string $dataEtat = '') {
    return '<details class="boite" data-id="' . $cle . '"' . ($ouvert ? ' open' : '') . '><summary><span class="titre">' . $titre . '</span><span class="etat"' . ($dataEtat ? ' data-etat="' . $dataEtat . '"' : '') . '>' . $etat . '</span></summary><div class="boite-corps">' . $contenu . '</div></details>';
};
$sous = fn(string $titre, string $contenu) => '<details class="sous"><summary>' . $titre . '</summary><div class="boite-corps">' . $contenu . '</div></details>';
$carte = fn(string $nom, string $val, string $titre, string $aide, bool $coche) => '<label class="carte-choix"><input type="radio" name="' . $nom . '" value="' . $val . '"' . ($coche ? ' checked' : '') . '><span><strong>' . $titre . '</strong><span class="aide">' . $aide . '</span></span></label>';
$radios = function (string $nom, string $legende, array $options, string $val, string $aide = '') {
    $o = '<fieldset><legend>' . $legende . '</legend>';
    foreach ($options as $kk => $l) $o .= '<label class="case"><input type="radio" name="' . $nom . '" value="' . $kk . '"' . ($kk === $val ? ' checked' : '') . '> ' . $l . '</label>';
    return $o . ($aide ? '<p class="aide">' . $aide . '</p>' : '') . '</fieldset>';
};
/** Image avec texte alternatif (bandeau d'en-tete, banniere de pied). */
$ct_image = function (string $pre, string $aide, ?array $d) {
    $o = '<p class="aide">' . $aide . '</p>';
    if ($d) $o .= '<p>Image actuelle :</p><img class="apercu-logo" style="max-width:100%;max-height:120px" src="' . h($d['svg'] ?: $d['png']) . '" alt="' . h($d['alt']) . '">'
                . '<label class="case"><input type="checkbox" name="' . $pre . '_retirer" value="1"> Retirer cette image</label>';
    return $o . '<label for="' . $pre . '_png">' . ($d ? 'Remplacer l\'image' : 'Votre image') . ' (PNG ou JPEG, utilis&eacute;e dans le mail)</label><input id="' . $pre . '_png" name="' . $pre . '_png" type="file" accept="image/png,image/jpeg">'
        . '<label for="' . $pre . '_svg">Version vectorielle pour le tract et le PDF (SVG, facultatif)</label><input id="' . $pre . '_svg" name="' . $pre . '_svg" type="file" accept=".svg,image/svg+xml">'
        . '<label for="' . $pre . '_alt">Texte alternatif de l\'image</label><input id="' . $pre . '_alt" name="' . $pre . '_alt" type="text" maxlength="250" value="' . h((string)($d['alt'] ?? '')) . '" aria-describedby="' . $pre . '_alt-a">'
        . '<p class="aide" id="' . $pre . '_alt-a">Obligatoire d&egrave;s qu\'il y a une image. Reprenez le texte qu\'elle contient ou d&eacute;crivez-la. Exemple : &laquo;&nbsp;Les &eacute;chos du CSE&nbsp;&raquo;.</p>';
};
/** Image de fond purement decorative. */
$ct_fond = function (string $pre, string $aide, ?array $d, string $extra = '') {
    $o = '<p class="aide">' . $aide . '</p>';
    if ($d) $o .= '<p>Fond actuel :</p><img class="apercu-logo" style="max-width:100%;max-height:100px" src="' . h($d['svg'] ?: $d['png']) . '" alt="Fond d&eacute;coratif actuel (sans texte alternatif : il ne porte aucune information)">'
                . '<label class="case"><input type="checkbox" name="' . $pre . '_retirer" value="1"> Retirer ce fond</label>';
    return $o . '<label for="' . $pre . '_png">' . ($d ? 'Remplacer le fond' : 'Image de fond') . ' (PNG ou JPEG, sans texte)</label><input id="' . $pre . '_png" name="' . $pre . '_png" type="file" accept="image/png,image/jpeg">'
        . '<label for="' . $pre . '_svg">Version vectorielle pour le tract et le PDF (SVG, facultatif)</label><input id="' . $pre . '_svg" name="' . $pre . '_svg" type="file" accept=".svg,image/svg+xml">' . $extra;
};
/** Bandeau dessine dans un logiciel vectoriel (SVG) : decor + textes reecrits en vrai texte. */
$ct_compose = function (string $pre, ?array $pc, string $coteDef) {
    $o = '<p class="aide">Gardez les textes en <em>texte</em> dans votre dessin : ils sont lus (position, police, taille, couleur) puis <strong>r&eacute;&eacute;crits en vrai texte accessible</strong> ; seul le d&eacute;cor reste une image. <strong>Liens</strong> : tout texte identique au site, &agrave; la page Facebook ou &agrave; l\'adresse mail de la charte devient cliquable. Aucun PNG n\'est demand&eacute;.' . ($pre === 'entete_compose' ? ' <strong>Titre du tract dans l\'en-t&ecirc;te</strong> : &eacute;crivez <code>{titre}</code> &agrave; l\'endroit voulu dans votre dessin (id&eacute;alement dans une zone de texte, dont la largeur fixe la place disponible). Le titre de chaque tract s\'y affiche, et ne se r&eacute;p&egrave;te plus sous l\'en-t&ecirc;te.' : '') . '</p>';
    if ($pc) {
        $cote = $pc['cote'] ?? $coteDef;
        $o .= '<p>Actuellement : ' . $pc['w'] . ' &times; ' . $pc['h'] . ' mm.</p><img class="apercu-logo" style="max-width:100%;max-height:90px" src="' . h($pc['svg']) . '" alt="D&eacute;cor (sans les textes, list&eacute;s ci-dessous)">'
            . '<fieldset><legend>Placement sur la page</legend><label class="case"><input type="radio" name="' . $pre . '_cote" value="gauche"' . ($cote === 'gauche' ? ' checked' : '') . '> Coll&eacute; au bord gauche de la page</label>'
            . '<label class="case"><input type="radio" name="' . $pre . '_cote" value="droite"' . ($cote === 'droite' ? ' checked' : '') . '> Coll&eacute; au bord droit de la page</label><p class="aide">Un dessin de 202 mm de large fait pour toucher le bord droit se place &agrave; droite.</p></fieldset>'
            . '<input type="hidden" name="' . $pre . '_table" value="1"><table><caption>Textes lus dans le dessin (d&eacute;cochez ceux qui ne doivent pas s\'afficher)</caption>'
            . '<thead><tr><th scope="col">Afficher</th><th scope="col">Texte</th><th scope="col">Police</th><th scope="col">Taille</th><th scope="col">Graisse</th><th scope="col">Couleur</th></tr></thead><tbody>';
        foreach ($pc['textes'] as $i => $t)
            $o .= '<tr><td><input type="checkbox" name="' . $pre . '_actif[' . $i . ']" value="1"' . (($t['actif'] ?? true) ? ' checked' : '') . ' aria-label="Afficher le texte &laquo;&nbsp;' . h($t['texte']) . '&nbsp;&raquo;"></td><th scope="row">' . (($t['role'] ?? '') === 'titre' ? 'Titre du tract <span class="aide">(remplac&eacute; par le titre de chaque tract)</span>' : h($t['texte'])) . '</th><td>' . (POLICES_DISPO[$t['police']] ?? h($t['police'])) . '</td><td>' . round($t['taille'] / 25.4 * 72, 1) . ' pt</td><td>' . (int)$t['graisse'] . '</td>'
                . '<td><span style="display:inline-block;width:14px;height:14px;border:1px solid #1a1a1a;vertical-align:middle;background:' . h($t['couleur']) . '"></span> <code>' . h($t['couleur']) . '</code></td></tr>';
        $o .= '</tbody></table>';
        foreach ($pc['textes'] as $t) if (($t['role'] ?? '') === 'titre')
            $o .= '<fieldset><legend>Zone du titre dans l\'en-t&ecirc;te</legend><p class="aide">Le titre de chaque tract s\'&eacute;crit &agrave; cet endroit. S\'il est trop long, il est r&eacute;duit automatiquement (jusqu\'&agrave; environ 55 % de sa taille), puis l\'&eacute;diteur vous signale qu\'il faut le raccourcir.</p>'
                . '<label for="' . $pre . '_titre_largeur">Largeur de la zone (mm)</label><input id="' . $pre . '_titre_largeur" name="' . $pre . '_titre_largeur" type="number" min="20" max="200" step="1" style="max-width:140px" value="' . h((string)($t['largeur'] ?? '')) . '" placeholder="automatique">'
                . '<label for="' . $pre . '_titre_ancre">Alignement du titre dans la zone</label><select id="' . $pre . '_titre_ancre" name="' . $pre . '_titre_ancre">'
                . '<option value="debut"' . (($t['ancre'] ?? 'debut') === 'debut' ? ' selected' : '') . '>&Agrave; gauche (le point de d&eacute;part du dessin est le bord gauche)</option>'
                . '<option value="milieu"' . (($t['ancre'] ?? '') === 'milieu' ? ' selected' : '') . '>Centr&eacute; (le point de d&eacute;part du dessin est le centre)</option>'
                . '<option value="fin"' . (($t['ancre'] ?? '') === 'fin' ? ' selected' : '') . '>&Agrave; droite (le point de d&eacute;part du dessin est le bord droit)</option></select></fieldset>';
        $o .= '<label class="case"><input type="checkbox" name="' . $pre . '_retirer" value="1"> Retirer ce dessin</label>';
    }
    return $o . '<label for="' . $pre . '">' . ($pc ? 'Remplacer le fichier SVG' : 'Votre fichier SVG') . '</label><input id="' . $pre . '" name="' . $pre . '" type="file" accept=".svg,image/svg+xml">';
};
$panneau = fn(string $groupe, string $pour, string $contenu) => '<div class="panneau" data-groupe="' . $groupe . '" data-pour="' . $pour . '">' . $contenu . '</div>';
?>
<p><a href="chartes.php">&larr; Retour aux chartes</a> &nbsp;|&nbsp; <a href="app.php?charte=<?= $id ?>">Essayer cette charte dans l'&eacute;diteur</a></p>
<?php if ($protegee): ?><div class="msg msg-err" role="status"><strong>Cette charte est prot&eacute;g&eacute;e.</strong> Vous pouvez la consulter, mais pas la modifier. Pour la modifier, levez la protection depuis la page &laquo;&nbsp;Chartes&nbsp;&raquo;.</div><?php endif; ?>
<p class="aide">Chaque bo&icirc;te se replie : cliquez sur son titre. Le r&eacute;sum&eacute; &agrave; droite du titre rappelle ce qui est choisi.</p>
<form method="post" action="charte.php?id=<?= $id ?>" enctype="multipart/form-data" id="formCharte">
  <?= champ_csrf() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="version" value="<?= h($versionPage) ?>">
  <p class="outils-boites"><button type="button" class="btn btn-sec btn-petit" id="toutDeplier">Tout d&eacute;plier</button> <button type="button" class="btn btn-sec btn-petit" id="toutReplier">Tout replier</button></p>

<?php
// ---- 1. Identite ----
echo $boite('identite', 'Identit&eacute; de la charte', h($nomCharte),
    $champ('nom_charte', $nomCharte, 'Nom de la charte (interne)', 'Visible seulement dans la liste de vos chartes. Exemple : &laquo;&nbsp;Tract courant&nbsp;&raquo;, &laquo;&nbsp;Les &eacute;chos du CSE&nbsp;&raquo;.', 'text', 'required maxlength="80"')
  . $champ('orgNom', (string)($c['orgNom'] ?? ''), 'Nom de l\'organisation', 'Exemple : Syndicat des services publics de Quelquepart. Affich&eacute; dans l\'en-t&ecirc;te du tract et du mail.', 'text', 'required maxlength="120"')
  . $champ('orgNomMaj', (string)($c['orgNomMaj'] ?? ''), 'Nom en capitales (facultatif)', 'Laiss&eacute; vide, il est calcul&eacute; automatiquement.', 'text', 'maxlength="120"'), true);

// ---- 2. Coordonnees et liens ----
$liens = ($c['site']['url'] ? 1 : 0) + ($c['facebook']['url'] ? 1 : 0) + (!empty($c['mail']) ? 1 : 0);
$coord = '<p class="aide">Ces informations servent &agrave; l\'en-t&ecirc;te, au pied de page et au mail. Chaque lien a besoin d\'un intitul&eacute; explicite : c\'est lui que lit un lecteur d\'&eacute;cran, plut&ocirc;t que l\'adresse brute. Un champ laiss&eacute; vide masque simplement l\'&eacute;l&eacute;ment.</p>'
  . $champ('site_url', (string)$c['site']['url'], 'Site web : adresse', '', 'url', 'placeholder="https://"')
  . $champ('site_texte', (string)$c['site']['texte'], 'Site web : texte affich&eacute;', 'Exemple : syndicat-exemple.org')
  . $champ('site_libelle', (string)$c['site']['libelle'], 'Site web : intitul&eacute; du lien', 'Exemple : Site du syndicat')
  . $champ('fb_url', (string)$c['facebook']['url'], 'Page Facebook : adresse', '', 'url', 'placeholder="https://"')
  . $champ('fb_texte', (string)$c['facebook']['texte'], 'Page Facebook : texte affich&eacute;')
  . $champ('fb_libelle', (string)$c['facebook']['libelle'], 'Page Facebook : intitul&eacute; du lien', 'Exemple : Page Facebook du syndicat')
  . $champ('mail', (string)($c['mail'] ?? ''), 'Adresse mail du syndicat', '', 'email')
  . '<h3>Adresses postales</h3><p class="aide">Une ligne par ligne d\'adresse (rue, ville, t&eacute;l&eacute;phone&hellip;). Laissez vide les blocs inutiles.</p>';
foreach ($adr as $i => $a) $coord .= '<label for="adresse' . $i . '">Adresse ' . ($i + 1) . '</label><textarea id="adresse' . $i . '" name="adresse[]" rows="3" maxlength="500">' . h($a) . '</textarea>';
echo $boite('coordonnees', 'Coordonn&eacute;es et liens', $liens . ' lien' . ($liens > 1 ? 's' : '') . ' &middot; ' . $nbAdr . ' adresse' . ($nbAdr > 1 ? 's' : ''), $coord);

// ---- 3. Couleurs ----
$col = '';
foreach (['rouge' => 'Couleur principale (bandeau, barres, encadr&eacute; color&eacute;)', 'jaune' => 'Couleur d\'accent (encadr&eacute; clair)', 'texte' => 'Couleur du texte', 'lien' => 'Couleur des liens'] as $cle => $lib)
    $col .= '<label for="couleur_' . $cle . '">' . $lib . '</label><div class="ligne-couleur"><input type="color" value="' . h($k[$cle]) . '" data-vers="couleur_' . $cle . '" aria-label="S&eacute;lecteur de couleur : ' . strip_tags($lib) . '">'
          . '<input type="text" id="couleur_' . $cle . '" name="couleur_' . $cle . '" value="' . h($k[$cle]) . '" pattern="#[0-9A-Fa-f]{6}" maxlength="7" required aria-describedby="format-couleur"></div>';
$col .= '<p class="aide" id="format-couleur">Format <code>#RRGGBB</code>. L\'enregistrement est refus&eacute; si une combinaison est trop peu contrast&eacute;e (minimum 4,5:1, norme WCAG AA).</p>'
      . '<table><caption>Contraste des combinaisons utilis&eacute;es (valeurs enregistr&eacute;es)</caption><thead><tr><th scope="col">Combinaison</th><th scope="col">Rapport</th><th scope="col">R&eacute;sultat</th></tr></thead><tbody>';
foreach ($contrastes as $r) $col .= '<tr><td>' . $r['libelle'] . '</td><td>' . number_format($r['ratio'], 2, ',', '') . ':1</td><td>' . ($r['ok'] ? '<span class="etat-ok">Conforme</span>' : '<span class="etat-ko">Insuffisant</span>') . '</td></tr>';
$col .= '</tbody></table>';
$pastilles = ''; foreach ($k as $v) $pastilles .= '<span class="pastille" style="background:' . h($v) . '"></span>';
echo $boite('couleurs', 'Couleurs', $pastilles . ($contrasteOk ? ' contrastes conformes' : ' <span class="etat-ko">contraste insuffisant</span>'), $col);

// ---- 4. Logo ----
$lg = '';
if (!empty($c['logo'])) $lg .= '<p>Logo actuel :</p><img class="apercu-logo" src="' . h($c['logo']) . '" alt="Logo actuel de la charte"><label class="case"><input type="checkbox" name="retirer_logo" value="1"> Supprimer le logo (le tract et le mail n\'afficheront aucun logo)</label>';
else $lg .= '<p class="aide">Aucun logo : le tract et le mail n\'en affichent pas. Ajoutez-en un ci-dessous si vous le souhaitez.</p>';
$lg .= '<label for="logo">' . (!empty($c['logo']) ? 'Remplacer le logo' : 'Ajouter un logo') . ' (PNG ou JPEG, de pr&eacute;f&eacute;rence sur fond transparent)</label><input id="logo" name="logo" type="file" accept="image/png,image/jpeg" aria-describedby="logo-a">'
     . '<p class="aide" id="logo-a">Il est redimensionn&eacute; &agrave; 400 px de large au maximum. Il sert pour le mail (Outlook n\'affiche pas les SVG). Dans le tract, le logo est d&eacute;coratif (le nom de l\'organisation est &eacute;crit en texte).</p>';
if (!empty($c['logoSvg'])) $lg .= '<p>Logo vectoriel actuel (tract et PDF) :</p><img class="apercu-logo" src="' . h($c['logoSvg']) . '" alt="Logo vectoriel actuel de la charte"><label class="case"><input type="checkbox" name="retirer_svg" value="1"> Retirer le logo vectoriel (le tract utilisera alors le PNG)</label>';
$lg .= '<label for="logo_svg">Logo vectoriel pour le tract et le PDF (SVG, facultatif)</label><input id="logo_svg" name="logo_svg" type="file" accept=".svg,image/svg+xml" aria-describedby="logosvg-a">'
     . '<p class="aide" id="logosvg-a">Net &agrave; toutes les tailles. Convertissez d\'abord les textes en trac&eacute;s. Les scripts et ressources externes sont supprim&eacute;s. Sur un fond de couleur, choisissez la version du logo qui reste lisible.</p>';
echo $boite('logo', 'Logo', empty($c['logo']) ? 'Aucun logo' : (empty($c['logoSvg']) ? 'Logo image' : 'Logo image + vectoriel'), $lg);

// ---- 5. En-tete ----
$choixE = '<fieldset class="choix"><legend>Comment voulez-vous construire l\'en-t&ecirc;te de vos tracts ?</legend>'
    . $carte('enteteMode', 'standard', 'Je veux un en-t&ecirc;te simple, compos&eacute; avec mon logo et mes coordonn&eacute;es', 'Un bandeau pr&ecirc;t &agrave; l\'emploi : nom de l\'organisation, liens, adresses et logo. Aucun fichier &agrave; pr&eacute;parer en dehors du logo.', $modeE === 'standard')
    . $carte('enteteMode', 'image', 'J\'ai d&eacute;j&agrave; un bandeau en image (JPEG ou PNG)', 'Une image pleine largeur plac&eacute;e en haut. Vous la d&eacute;crivez par un texte alternatif.', $modeE === 'image')
    . $carte('enteteMode', 'svg', 'J\'ai d&eacute;j&agrave; dessin&eacute; mon en-t&ecirc;te dans un logiciel vectoriel (fichier SVG)', 'Cr&eacute;&eacute; avec Inkscape ou &eacute;quivalent. Les textes sont r&eacute;&eacute;crits en vrai texte accessible, avec des liens cliquables.', $modeE === 'svg')
    . '</fieldset>';
$stdE = $radios('logoCote', 'C&ocirc;t&eacute; du logo', ['droite' => '&Agrave; droite', 'gauche' => '&Agrave; gauche'], (string)($c['logoCote'] ?? 'droite'), 'Dans le mail, le logo est toujours &agrave; gauche du nom.')
      . $radios('enteteStyle', 'Style du bandeau', ['bandeau' => 'Bandeau plein de la couleur principale (texte blanc)', 'filet' => 'Fond blanc avec un filet de la couleur principale'], (string)($c['enteteStyle'] ?? 'bandeau'))
      . $sous('Image de fond d&eacute;corative derri&egrave;re le bandeau (facultatif)', $ct_fond('entete_fond', 'Une image <strong>purement d&eacute;corative</strong> derri&egrave;re le texte du bandeau, par exemple une trace de peinture. Le texte reste du vrai texte, blanc ; le serveur v&eacute;rifie qu\'il reste lisible. Utilis&eacute; seulement avec le bandeau plein.', $c['enteteFond'] ?? null));
echo $boite('entete', 'En-t&ecirc;te du tract', ETATS_ENTETE[$modeE], $choixE
    . $panneau('enteteMode', 'standard', $stdE)
    . $panneau('enteteMode', 'image', $ct_image('entete', 'Un bandeau pleine largeur plac&eacute; en haut du tract et du mail, <strong>&agrave; la place</strong> du bandeau de texte. Mettez alors les coordonn&eacute;es dans le pied de page.', $c['enteteImage'] ?? null))
    . $panneau('enteteMode', 'svg', $ct_compose('entete_compose', $c['enteteCompose'] ?? null, 'droite')), true, 'enteteMode');

// ---- 6. Pied de page ----
$choixP = '<fieldset class="choix"><legend>Comment voulez-vous construire le pied de page de vos tracts ?</legend>'
    . $carte('piedMode', 'standard', 'Je veux un pied de page simple, compos&eacute; avec mon appel, mes liens et mes adresses', 'Du texte clair et cliquable, avec en option des ic&ocirc;nes, une banni&egrave;re image ou un fond d&eacute;coratif.', $modeP === 'standard')
    . $carte('piedMode', 'svg', 'J\'ai d&eacute;j&agrave; dessin&eacute; mon pied de page dans un logiciel vectoriel (fichier SVG)', 'Les textes et les liens du dessin sont r&eacute;&eacute;crits en vrai texte accessible. Aucun PNG &agrave; fournir.', $modeP === 'svg')
    . '</fieldset>';
$piedTexteChoix = '<fieldset><legend>Couleur du texte du pied de page sur ce fond</legend><label class="case"><input type="radio" name="piedTexte" value="sombre"' . (($c['piedTexte'] ?? 'sombre') !== 'clair' ? ' checked' : '') . '> Texte sombre (appel dans la couleur principale, liens dans la couleur des liens)</label><label class="case"><input type="radio" name="piedTexte" value="clair"' . (($c['piedTexte'] ?? '') === 'clair' ? ' checked' : '') . '> Texte blanc</label></fieldset>';
$stdP = $champ('appel', (string)($c['appel'] ?? ''), 'Phrase d\'appel', 'Exemple : Rejoignez-nous, syndiquez-vous !', 'text', 'maxlength="120"')
      . '<label class="case"><input type="checkbox" name="iconesPied" value="1"' . (!empty($c['iconesPied']) ? ' checked' : '') . '> Ajouter des ic&ocirc;nes (globe, courrier) devant les liens</label>'
      . $sous('Banni&egrave;re image au-dessus du pied de page (facultatif)', $ct_image('pied', 'Par exemple un appel &agrave; adh&eacute;rer en image. Ne mettez pas dedans les liens et adresses : ils s\'&eacute;crivent en texte dans le pied (cliquables et lisibles par tous).', $c['piedImage'] ?? null))
      . $sous('Image de fond d&eacute;corative derri&egrave;re le pied de page (facultatif)', $ct_fond('pied_fond', 'Une image <strong>purement d&eacute;corative</strong> derri&egrave;re l\'appel et les liens, qui restent du vrai texte. Le serveur v&eacute;rifie la lisibilit&eacute; du texte sur l\'image.', $c['piedFond'] ?? null, $piedTexteChoix));
echo $boite('pied', 'Pied de page du tract', ETATS_PIED[$modeP], $choixP
    . $panneau('piedMode', 'standard', $stdP)
    . $panneau('piedMode', 'svg', $ct_compose('pied_compose', $c['piedCompose'] ?? null, 'gauche')), true, 'piedMode');

// ---- 7. Contenu ----
$cont = $radios('intertitres', 'D&eacute;coration des intertitres', ['barre' => 'Barre verticale &agrave; gauche', 'souligne' => 'Soulignement', 'aucun' => 'Aucune'], (string)($c['intertitres'] ?? 'barre'), 'Chaque tract peut la d&eacute;sactiver dans l\'&eacute;diteur.')
      . $radios('encadres', 'Forme des encadr&eacute;s', ['carre' => 'Angles droits', 'arrondi' => 'Angles arrondis'], (string)($c['encadres'] ?? 'carre'))
      . '<label for="espaceEntete">Espace sous l\'en-t&ecirc;te (mm)</label><input id="espaceEntete" name="espaceEntete" type="number" min="0" max="20" step="1" style="max-width:120px" value="' . (int)($c['espaceEntete'] ?? 6) . '" aria-describedby="esp-a"><p class="aide" id="esp-a">Distance entre le bas de l\'en-t&ecirc;te et le titre (ou le d&eacute;but du texte), pour un en-t&ecirc;te en image, dessin&eacute; en SVG, ou sans logo. 6 mm par d&eacute;faut. Avec l\'en-t&ecirc;te simple et son logo, la mise en page est automatique.</p>'
      . '<label for="police">Police par d&eacute;faut des tracts</label><select id="police" name="police">';
foreach (POLICES_DISPO as $pid => $pn) $cont .= '<option value="' . $pid . '"' . (($c['police'] ?? 'barlow') === $pid ? ' selected' : '') . '>' . $pn . '</option>';
$cont .= '</select><p class="aide">Chaque tract peut ensuite changer de police dans l\'&eacute;diteur. Dans les mails, la police n\'appara&icirc;t que chez les destinataires qui l\'ont install&eacute;e.</p>';
echo $boite('contenu', 'Mise en forme du contenu', 'Intertitres : ' . ['barre' => 'barre', 'souligne' => 'soulignement', 'aucun' => 'aucune d&eacute;coration'][$c['intertitres'] ?? 'barre'] . ' &middot; ' . (POLICES_DISPO[$c['police'] ?? 'barlow'] ?? 'Barlow'), $cont);
?>

  <div class="barre-fixe"><button class="btn" type="submit"<?= $protegee ? ' disabled aria-disabled="true"' : '' ?>>Enregistrer la charte</button> <a class="btn btn-sec" href="chartes.php">Annuler</a></div>
</form>
<?php
$sv = $pdo->prepare('SELECT id, nom, cree_le FROM chartes_versions WHERE charte_id = ? ORDER BY id DESC'); $sv->execute([$id]);
$versions = $sv->fetchAll();
$hv = '<p class="aide">&Agrave; chaque enregistrement, la version pr&eacute;c&eacute;dente est gard&eacute;e (les 15 derni&egrave;res). Vous pouvez y revenir &agrave; tout moment ; la version actuelle est elle aussi conserv&eacute;e avant le retour.</p>';
if (!$versions) $hv .= '<p>Aucune version ant&eacute;rieure pour le moment.</p>';
else {
    $hv .= '<table><thead><tr><th scope="col">Enregistr&eacute;e le</th><th scope="col">Nom</th><th scope="col">Action</th></tr></thead><tbody>';
    foreach ($versions as $v) $hv .= '<tr><td>' . h((string)$v['cree_le']) . '</td><td>' . h((string)$v['nom']) . '</td><td>'
        . ($protegee ? '&mdash;' : '<form method="post" action="charte.php?id=' . $id . '">' . champ_csrf() . '<input type="hidden" name="action" value="restaurer_version"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="version_id" value="' . (int)$v['id'] . '"><button class="btn btn-sec btn-petit" type="submit" onclick="return confirm(\'Revenir &agrave; cette version ?\')">Restaurer<span style="position:absolute;left:-9999px"> la version du ' . h((string)$v['cree_le']) . '</span></button></form>') . '</td></tr>';
    $hv .= '</tbody></table>';
}
echo $boite('versions', 'Historique des versions', count($versions) . ' version' . (count($versions) > 1 ? 's' : ''), $hv);
?>
<script>
(function () {
  var erreurs = <?= $erreurs ? 'true' : 'false' ?>;
  // couleurs : le s&eacute;lecteur et le champ texte restent synchronis&eacute;s (sans JavaScript, le champ texte suffit)
  document.querySelectorAll('input[type=color][data-vers]').forEach(function (s) {
    var t = document.getElementById(s.dataset.vers);
    s.addEventListener('input', function () { t.value = s.value.toUpperCase(); });
    t.addEventListener('input', function () { if (/^#[0-9a-fA-F]{6}$/.test(t.value)) s.value = t.value; });
  });
  // en-t&ecirc;te / pied : seuls les outils du choix sont affich&es ; le r&eacute;sum&eacute; du titre suit le choix
  var ETATS = { enteteMode: <?= json_encode(array_map('html_entity_decode', ETATS_ENTETE), JSON_UNESCAPED_UNICODE) ?>, piedMode: <?= json_encode(array_map('html_entity_decode', ETATS_PIED), JSON_UNESCAPED_UNICODE) ?> };
  ['enteteMode', 'piedMode'].forEach(function (g) {
    function maj() {
      var r = document.querySelector('input[name="' + g + '"]:checked'), v = r ? r.value : '';
      document.querySelectorAll('.panneau[data-groupe="' + g + '"]').forEach(function (p) { p.hidden = p.dataset.pour !== v; });
      var e = document.querySelector('[data-etat="' + g + '"]'); if (e && ETATS[g][v]) e.textContent = ETATS[g][v];
    }
    document.querySelectorAll('input[name="' + g + '"]').forEach(function (r) { r.addEventListener('change', maj); });
    maj();
  });
  // bo&icirc;tes repliables : &eacute;tat m&eacute;moris&eacute; d'une visite &agrave; l'autre
  var CLE = 'tracteur-charte-boites', etat = {};
  try { etat = JSON.parse(localStorage.getItem(CLE) || '{}'); } catch (e) {}
  document.querySelectorAll('details.boite[data-id]').forEach(function (d) {
    if (erreurs) d.open = true;
    else if (d.dataset.id in etat) d.open = !!etat[d.dataset.id];
    d.addEventListener('toggle', function () { etat[d.dataset.id] = d.open; try { localStorage.setItem(CLE, JSON.stringify(etat)); } catch (e) {} });
  });
  document.getElementById('toutDeplier').onclick = function () { document.querySelectorAll('details.boite').forEach(function (d) { d.open = true; }); };
  document.getElementById('toutReplier').onclick = function () { document.querySelectorAll('details.boite').forEach(function (d) { d.open = false; }); };
  // un champ invalide cach&eacute; dans une bo&icirc;te repli&eacute;e emp&ecirc;cherait l'envoi sans message : on l'ouvre
  document.getElementById('formCharte').addEventListener('invalid', function (e) { var d = e.target.closest('details'); while (d) { d.open = true; d = d.parentElement.closest('details'); } }, true);
})();
</script>
<?php page_fin();

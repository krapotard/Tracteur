<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/banque.php';
require __DIR__ . '/src/amorce.php';
require __DIR__ . '/src/vue.php';
$u = exiger_connexion();
if ($u['role'] === 'admin') redirige('admin.php');
$pdo = db(); $cid = (int)$u['id'];
$reaffiche = null; $imageAttente = null;

function fichier_recu(string $champ): ?string {
    return (isset($_FILES[$champ]) && $_FILES[$champ]['error'] === UPLOAD_ERR_OK) ? $_FILES[$champ]['tmp_name'] : null;
}
function echec_televersement(string $champ): ?string {
    if (!isset($_FILES[$champ])) return null;
    $c = $_FILES[$champ]['error'];
    return in_array($c, [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true) ? null : 'Le fichier n\'a pas pu &ecirc;tre t&eacute;l&eacute;vers&eacute; (trop gros ?).';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exiger_post();
    $action = (string)($_POST['action'] ?? '');
    $err = [];
    if ($action === 'ajouter') {
        // Le fichier est traite d'abord, meme si le reste est incomplet : s'il est valide on le garde pour la correction.
        $f = fichier_recu('fichier'); $img = null;
        if (($m = echec_televersement('fichier'))) $err[] = $m;
        elseif ($f) $img = traiter_fichier_element($f, $err);
        elseif (($att = banque_attente_lire($cid))) $img = $att;               // image deja recue lors d'un essai refuse
        else $err[] = 'Choisissez un fichier (SVG, PNG ou JPEG).';
        $e = champs_element($_POST, $err);
        if (nb_elements($pdo, $cid) >= MAX_ELEMENTS) $err[] = 'Limite de ' . MAX_ELEMENTS . ' &eacute;l&eacute;ments atteinte.';
        if (!$err && $img) {
            $e += ['id' => id_element_libre($pdo, $cid, $e['nom'])] + $img;
            enregistrer_element($pdo, $cid, $e);
            banque_attente_effacer($cid);
            flash('ok', '&Eacute;l&eacute;ment &laquo;&nbsp;' . h($e['nom']) . '&nbsp;&raquo; ajout&eacute; &agrave; la banque.');
        } elseif ($img) {                                                       // refuse : on garde l'image ET la saisie, sans redirection
            banque_attente_ecrire($cid, $img);
            $reaffiche = $_POST; $imageAttente = $img;
        }
    } elseif ($action === 'preparer') {
        if (!isset($_FILES['fichiers']) || !is_array($_FILES['fichiers']['name'] ?? null) || !array_filter($_FILES['fichiers']['name'])) $err[] = 'Choisissez au moins un fichier (SVG, PNG ou JPEG).';
        else {
            $msgs = []; $n = lot_preparer($cid, $_FILES['fichiers'], $msgs, (array)($_POST['chemins'] ?? []));
            foreach ($msgs as $m) $err[] = $m;
            if ($n && empty($_POST['paquet'])) flash('ok', $n . ' fichier' . ($n > 1 ? 's' : '') . ' re&ccedil;u' . ($n > 1 ? 's' : '') . ' : compl&eacute;tez les informations ci-dessous, puis ajoutez-les &agrave; la banque.');
        }
        if (!empty($_POST['paquet'])) { foreach ($err as $m) flash('err', $m); sortie_json(['ok' => true]); }
        $reaffiche = []; // pas de redirection : le lot s'affiche tout de suite
    } elseif ($action === 'ajouter_lot') {
        $lot = lot_lire($cid); $reste = []; $ajoutes = 0; $retires = 0; $place = MAX_ELEMENTS - nb_elements($pdo, $cid);
        foreach ($lot as $it) {
            $in = $_POST['lot'][$it['uid']] ?? null;
            if (!is_array($in)) { $reste[] = $it; continue; }             // formulaire incomplet : on garde
            if (!empty($in['ignorer'])) { $retires++; continue; }
            $e2 = []; $e = champs_element($in, $e2);
            foreach (['nom', 'categorie', 'alt', 'taille'] as $k) $it[$k] = $in[$k] ?? $it[$k];
            $it['tags'] = (string)($in['tags'] ?? ''); $it['deco'] = !empty($in['deco']);
            if ($place <= 0) $e2[] = 'Limite de ' . MAX_ELEMENTS . ' &eacute;l&eacute;ments atteinte.';
            if ($e2) { $it['erreurs'] = $e2; $reste[] = $it; continue; }
            enregistrer_element($pdo, $cid, $e + ['id' => id_element_libre($pdo, $cid, $e['nom']), 'src' => $it['src'], 'w' => $it['w'], 'h' => $it['h']]);
            $ajoutes++; $place--;
        }
        lot_ecrire($cid, $reste);
        if ($ajoutes) flash('ok', $ajoutes . ' &eacute;l&eacute;ment' . ($ajoutes > 1 ? 's ajout&eacute;s' : ' ajout&eacute;') . ' &agrave; la banque.' . ($reste ? ' Les fichiers ci-dessous attendent encore une correction : ils sont conserv&eacute;s.' : ''));
        elseif ($retires && !$reste) flash('ok', 'Lot abandonn&eacute; : aucun fichier ajout&eacute;.');
        if ($reste && !$ajoutes) flash('err', 'Rien n\'a &eacute;t&eacute; ajout&eacute; : corrigez les points signal&eacute;s (vos saisies sont conserv&eacute;es).');
        $reaffiche = [];
    } elseif ($action === 'ajouter_tout') {                                   // lot prerempli : on valide tel quel
        $lot = lot_lire($cid); $reste = []; $ajoutes = 0; $place = MAX_ELEMENTS - nb_elements($pdo, $cid);
        foreach ($lot as $it) {
            $e2 = []; $e = champs_element($it, $e2);
            if ($place <= 0) $e2[] = 'Limite de ' . MAX_ELEMENTS . ' &eacute;l&eacute;ments atteinte.';
            if ($e2) { $it['erreurs'] = $e2; $reste[] = $it; continue; }
            enregistrer_element($pdo, $cid, $e + ['id' => id_element_libre($pdo, $cid, $e['nom']), 'src' => $it['src'], 'w' => $it['w'], 'h' => $it['h']]);
            $ajoutes++; $place--;
        }
        lot_ecrire($cid, $reste);
        if ($ajoutes) flash('ok', $ajoutes . ' &eacute;l&eacute;ment' . ($ajoutes > 1 ? 's ajout&eacute;s' : ' ajout&eacute;') . ' &agrave; la banque.' . ($reste ? ' ' . count($reste) . ' fichier(s) attendent une correction (texte alternatif manquant ?) : ils sont conserv&eacute;s ci-dessous.' : ''));
        elseif ($reste) flash('err', 'Rien n\'a &eacute;t&eacute; ajout&eacute; : il manque des informations (texte alternatif ?).');
        $reaffiche = [];
    } elseif ($action === 'defauts') {                                        // complete avec la banque par defaut, sans rien remplacer
        $n = ajouter_banque_par_defaut($pdo, $cid);
        flash('ok', $n ? $n . ' &eacute;l&eacute;ment' . ($n > 1 ? 's' : '') . ' de la banque par d&eacute;faut ajout&eacute;' . ($n > 1 ? 's' : '') . '. Vos &eacute;l&eacute;ments existants n\'ont pas &eacute;t&eacute; touch&eacute;s.' : 'Tous les &eacute;l&eacute;ments par d&eacute;faut sont d&eacute;j&agrave; dans votre banque.');
    } elseif ($action === 'abandonner_lot') {
        lot_ecrire($cid, []); flash('ok', 'Lot abandonn&eacute;.');
    } elseif ($action === 'modifier') {
        $s = $pdo->prepare('SELECT contenu FROM banque_elements WHERE compte_id = ? AND elem_id = ?'); $s->execute([$cid, (string)($_POST['elem'] ?? '')]);
        $ancien = json_decode((string)$s->fetchColumn(), true);
        if (!is_array($ancien)) $err[] = '&Eacute;l&eacute;ment introuvable.';
        else {
            $e = champs_element($_POST, $err);
            $f = fichier_recu('fichier');
            if (($m = echec_televersement('fichier'))) $err[] = $m;
            $img = ['src' => $ancien['src'], 'w' => $ancien['w'], 'h' => $ancien['h']];
            if (!$err && $f) { $img = traiter_fichier_element($f, $err) ?? $img; }
            if (!$err) { enregistrer_element($pdo, $cid, $e + ['id' => $ancien['id']] + $img); flash('ok', '&Eacute;l&eacute;ment &laquo;&nbsp;' . h($e['nom']) . '&nbsp;&raquo; modifi&eacute;.'); }
        }
    } elseif ($action === 'supprimer') {
        $pdo->prepare('DELETE FROM banque_elements WHERE compte_id = ? AND elem_id = ?')->execute([$cid, (string)($_POST['elem'] ?? '')]);
        flash('ok', '&Eacute;l&eacute;ment supprim&eacute;. Les tracts d&eacute;j&agrave; enregistr&eacute;s qui l\'utilisent ne changent pas.');
    } elseif ($action === 'importer') {
        $f = fichier_recu('banquejs');
        if (($m = echec_televersement('banquejs'))) $err[] = $m; elseif (!$f) $err[] = 'Choisissez un fichier banque.js.';
        else {
            [$a, $i, $msgs] = importer_banque_js($pdo, $cid, (string)file_get_contents($f));
            foreach ($msgs as $m) $err[] = $m;
            if ($a || !$msgs) flash('ok', $a . ' &eacute;l&eacute;ment(s) ajout&eacute;(s), ' . $i . ' ignor&eacute;(s) (d&eacute;j&agrave; pr&eacute;sents ou invalides). Rien n\'a &eacute;t&eacute; remplac&eacute;.');
        }
    } else $err[] = 'Action inconnue.';
    foreach ($err as $m) flash('err', $m);
    if (!$reaffiche) redirige('banque.php');
} else banque_attente_effacer($cid);       // page rouverte : l'ancienne image isolee est abandonnee ; le lot, lui, est conserve

$st = $pdo->prepare('SELECT contenu FROM banque_elements WHERE compte_id = ? ORDER BY id'); $st->execute([$cid]);
$els = array_map(fn($r) => json_decode($r['contenu'], true), $st->fetchAll());
$cats = array_values(array_unique(array_filter(array_map(fn($e) => (string)($e['categorie'] ?? ''), $els))));
$cache = 'position:absolute;left:-9999px';
$lot = lot_lire($cid);

$selectTaille = function (string $name, string $sel) { $o = '<select id="' . $name . '" name="taille">'; foreach (TAILLES_BANQUE as $k => $l) $o .= '<option value="' . $k . '"' . ($k === $sel ? ' selected' : '') . '>' . $l . '</option>'; return $o . '</select>'; };

page_debut('Banque d\'&eacute;l&eacute;ments', $u, 'banque');
?>
<p>La banque contient les pictogrammes, num&eacute;ros et s&eacute;parateurs que vos camarades ins&egrave;rent dans leurs tracts. Chaque &eacute;l&eacute;ment porte son <strong>texte alternatif</strong> : l'accessibilit&eacute; est r&eacute;gl&eacute;e une fois pour toutes, &agrave; l'ajout. La banque se <strong>compl&egrave;te</strong> ; rien n'est jamais remplac&eacute; d'un coup.</p>
<p><a class="btn btn-sec" href="banque_export.php">T&eacute;l&eacute;charger ma banque (sauvegarde banque.js)</a></p>
<form method="post" action="banque.php"><?= champ_csrf() ?><input type="hidden" name="action" value="defauts"><p><button class="btn btn-sec" type="submit">Ajouter les &eacute;l&eacute;ments par d&eacute;faut manquants</button> <span class="aide">Pictogrammes, num&eacute;ros, s&eacute;parateurs fournis avec Tracteur. Rien n'est remplac&eacute;.</span></p></form>

<p class="outils-boites"><button type="button" class="btn btn-sec btn-petit" id="toutDeplier">Tout d&eacute;plier</button> <button type="button" class="btn btn-sec btn-petit" id="toutReplier">Tout replier</button></p>
<details class="boite" data-id="elements" open><summary><span class="titre">Mes &eacute;l&eacute;ments</span><span class="etat"><?= count($els) ?> &eacute;l&eacute;ment<?= count($els) > 1 ? 's' : '' ?></span></summary><div class="boite-corps">
<?php if (!$els): ?><p>La banque est vide : ajoutez un premier &eacute;l&eacute;ment ci-dessous.</p><?php endif; ?>
<ul class="grille">
<?php foreach ($els as $n => $e): $i = 'e' . $n; ?>
  <li>
    <div class="vignette"><img src="<?= h($e['src']) ?>" alt="<?= h($e['deco'] ? '' : $e['alt']) ?>"></div>
    <div class="nom-el"><?= h($e['nom']) ?></div>
    <div class="aide"><?= h((string)($e['categorie'] ?: 'Sans cat&eacute;gorie')) ?> &middot; <?= $e['deco'] ? 'd&eacute;coratif' : 'alt : ' . h($e['alt']) ?> &middot; <?= TAILLES_BANQUE[$e['taille']] ?? '' ?></div>
    <details><summary>Modifier<span style="<?= $cache ?>"> &laquo;&nbsp;<?= h($e['nom']) ?>&nbsp;&raquo;</span></summary>
      <form method="post" action="banque.php" enctype="multipart/form-data">
        <?= champ_csrf() ?><input type="hidden" name="action" value="modifier"><input type="hidden" name="elem" value="<?= h($e['id']) ?>">
        <label for="<?= $i ?>n">Nom</label><input id="<?= $i ?>n" name="nom" type="text" value="<?= h($e['nom']) ?>" required maxlength="80">
        <label for="<?= $i ?>c">Cat&eacute;gorie</label><input id="<?= $i ?>c" name="categorie" type="text" value="<?= h((string)$e['categorie']) ?>" list="cats" maxlength="40">
        <label for="<?= $i ?>t">Mots-cl&eacute;s (s&eacute;par&eacute;s par des virgules)</label><input id="<?= $i ?>t" name="tags" type="text" value="<?= h(implode(', ', (array)$e['tags'])) ?>">
        <label for="<?= $i ?>a">Texte alternatif</label><input id="<?= $i ?>a" name="alt" type="text" value="<?= h((string)$e['alt']) ?>" maxlength="200">
        <label class="case"><input type="checkbox" name="deco" value="1"<?= $e['deco'] ? ' checked' : '' ?>> D&eacute;coratif (aucun texte alternatif)</label>
        <label for="<?= $i ?>s">Taille propos&eacute;e &agrave; l'insertion</label><?= $selectTaille($i . 's', (string)$e['taille']) ?>
        <label for="<?= $i ?>f">Remplacer l'image (facultatif)</label><input id="<?= $i ?>f" name="fichier" type="file" accept=".svg,image/svg+xml,image/png,image/jpeg">
        <button class="btn btn-petit" type="submit">Enregistrer</button>
      </form>
      <form method="post" action="banque.php"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="elem" value="<?= h($e['id']) ?>">
        <button class="btn btn-danger btn-petit" type="submit" onclick="return confirm('Supprimer cet &eacute;l&eacute;ment de la banque ?')">Supprimer<span style="<?= $cache ?>"> &laquo;&nbsp;<?= h($e['nom']) ?>&nbsp;&raquo;</span></button></form>
    </details>
  </li>
<?php endforeach; ?>
</ul>
</div></details>
<datalist id="cats"><?php foreach ($cats as $c): ?><option value="<?= h($c) ?>"><?php endforeach; ?></datalist>

<?php if ($lot): $nbLot = count($lot); $lot = array_slice($lot, 0, LOT_PAR_PAGE); ?>
<details class="boite" id="lot" data-id="lot" open><summary><span class="titre">Compl&eacute;ter les fichiers re&ccedil;us</span><span class="etat"><?= $nbLot ?> en attente</span></summary><div class="boite-corps">
<?php if ($nbLot > LOT_PAR_PAGE): ?><p class="msg msg-ok" role="status">Les <?= LOT_PAR_PAGE ?> premiers sont affich&eacute;s ; les <?= $nbLot - LOT_PAR_PAGE ?> autres suivront apr&egrave;s validation. Si les noms, cat&eacute;gories et textes alternatifs pr&eacute;remplis vous conviennent, utilisez plut&ocirc;t le bouton &laquo;&nbsp;Tout ajouter tel quel&nbsp;&raquo; en bas.</p><?php endif; ?>
<p>Les fichiers sont conserv&eacute;s sur le serveur : vous pouvez prendre votre temps, et rien n'est perdu si un champ est incomplet. Le nom est pr&eacute;rempli d'apr&egrave;s le nom du fichier. Il reste &agrave; indiquer la <strong>cat&eacute;gorie</strong> et le <strong>texte alternatif</strong> de chaque image.</p>
<form method="post" action="banque.php#lot" id="lotForm">
  <?= champ_csrf() ?><input type="hidden" name="action" value="ajouter_lot">
  <fieldset class="lot-commun"><legend>Gagner du temps (facultatif)</legend>
    <label for="catTous">M&ecirc;me cat&eacute;gorie pour tous les fichiers</label>
    <input id="catTous" type="text" list="cats" maxlength="40">
    <label for="tailleTous">M&ecirc;me taille pour tous</label>
    <select id="tailleTous"><?php foreach (TAILLES_BANQUE as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select>
    <p><button class="btn btn-sec btn-petit" type="button" id="appliquerTous">Appliquer &agrave; tous les fichiers</button></p>
  </fieldset>
  <?php foreach ($lot as $n => $it): $i = 'l' . $n; $p = 'lot[' . h($it['uid']) . ']'; ?>
  <fieldset class="lot-item">
    <legend><?= h($it['fichier']) ?></legend>
    <?php if (!empty($it['erreurs'])): ?><div class="msg msg-err" role="alert"><?php foreach ($it['erreurs'] as $m): ?><p><?= $m ?></p><?php endforeach; ?></div><?php endif; ?>
    <div class="lot-corps">
      <div class="vignette"><img src="<?= h($it['src']) ?>" alt="Aper&ccedil;u de <?= h($it['fichier']) ?>" style="max-width:140px;max-height:110px"></div>
      <div class="lot-champs">
        <label for="<?= $i ?>n">Nom</label><input id="<?= $i ?>n" name="<?= $p ?>[nom]" type="text" maxlength="80" value="<?= h((string)$it['nom']) ?>">
        <label for="<?= $i ?>c">Cat&eacute;gorie</label><input id="<?= $i ?>c" class="cat-lot" name="<?= $p ?>[categorie]" type="text" list="cats" maxlength="40" value="<?= h((string)$it['categorie']) ?>">
        <label for="<?= $i ?>a">Texte alternatif</label><input id="<?= $i ?>a" name="<?= $p ?>[alt]" type="text" maxlength="200" value="<?= h((string)$it['alt']) ?>">
        <label class="case"><input type="checkbox" name="<?= $p ?>[deco]" value="1"<?= $it['deco'] ? ' checked' : '' ?>> D&eacute;coratif (aucun texte alternatif)</label>
        <label for="<?= $i ?>t">Mots-cl&eacute;s (facultatif, s&eacute;par&eacute;s par des virgules)</label><input id="<?= $i ?>t" name="<?= $p ?>[tags]" type="text" value="<?= h((string)$it['tags']) ?>">
        <label for="<?= $i ?>s">Taille propos&eacute;e &agrave; l'insertion</label><select id="<?= $i ?>s" class="taille-lot" name="<?= $p ?>[taille]"><?php foreach (TAILLES_BANQUE as $k => $l): ?><option value="<?= $k ?>"<?= $k === $it['taille'] ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
        <label class="case"><input type="checkbox" name="<?= $p ?>[ignorer]" value="1"> Ne pas ajouter ce fichier<span style="<?= $cache ?>"> (<?= h($it['fichier']) ?>)</span></label>
      </div>
    </div>
  </fieldset>
  <?php endforeach; ?>
  <p><button class="btn" type="submit">Ajouter ces <?= count($lot) ?> fichier<?= count($lot) > 1 ? 's' : '' ?> &agrave; la banque</button></p>
</form>
<form method="post" action="banque.php"><?= champ_csrf() ?><input type="hidden" name="action" value="ajouter_tout">
  <p><button class="btn btn-sec" type="submit">Tout ajouter tel quel (<?= $nbLot ?> fichier<?= $nbLot > 1 ? 's' : '' ?>)</button> <span class="aide">Les fichiers dont le texte alternatif est vide restent en attente.</span></p></form>
<form method="post" action="banque.php"><?= champ_csrf() ?><input type="hidden" name="action" value="abandonner_lot">
  <button class="btn btn-danger btn-petit" type="submit" onclick="return confirm('Abandonner ces fichiers ? Ils ne seront pas ajout&eacute;s &agrave; la banque.')">Abandonner ce lot</button></form>
<script>
document.getElementById('appliquerTous').addEventListener('click', function () {
  var c = document.getElementById('catTous').value, t = document.getElementById('tailleTous').value;
  if (c) document.querySelectorAll('.cat-lot').forEach(function (x) { x.value = c; });
  document.querySelectorAll('.taille-lot').forEach(function (x) { x.value = t; });
});
</script>
</div></details>
<?php endif; ?>

<details class="boite" data-id="ajouter" open><summary><span class="titre">Ajouter des &eacute;l&eacute;ments</span><span class="etat">images, ou dossier d'ic&ocirc;nes</span></summary><div class="boite-corps">
<div id="etat-envoi" role="status" aria-live="polite"></div>
<form method="post" action="banque.php#lot" enctype="multipart/form-data">
  <?= champ_csrf() ?><input type="hidden" name="action" value="preparer">
  <label for="fichiers">Images (SVG, PNG ou JPEG) : vous pouvez en choisir plusieurs d'un coup</label>
  <input id="fichiers" name="fichiers[]" type="file" multiple accept=".svg,image/svg+xml,image/png,image/jpeg" required aria-describedby="f-a">
  <p class="aide" id="f-a">Les fichiers sont d'abord envoy&eacute;s, puis vous compl&eacute;tez pour chacun la cat&eacute;gorie et le texte alternatif avant de valider. Pour un SVG, convertissez d'abord les textes en trac&eacute;s ; les scripts et ressources externes sont supprim&eacute;s automatiquement. 400 Ko maximum par SVG, 3 Mo par image, 20 fichiers par envoi.</p>
  <p><button class="btn" type="submit" id="btnEnvoi">Envoyer les fichiers</button></p>
  <details id="blocDossier"><summary>J'ai un dossier (ou plusieurs sous-dossiers) d'ic&ocirc;nes</summary>
    <label for="dossier">Choisir un dossier</label>
    <input id="dossier" type="file" webkitdirectory multiple aria-describedby="d-a">
    <p class="aide" id="d-a">Tous les SVG, PNG et JPEG du dossier et de ses sous-dossiers sont envoy&eacute;s. <strong>Le nom du sous-dossier devient la cat&eacute;gorie</strong> (par exemple le style &laquo;&nbsp;brouillon&nbsp;&raquo;) et <strong>le nom du fichier devient le nom</strong> de l'&eacute;l&eacute;ment, avec un texte alternatif propos&eacute; que vous pouvez corriger.</p>
    <p><button class="btn" type="button" id="btnDossier">Envoyer le dossier</button></p>
  </details>
</form>
<script>
(function () {
  var f = document.getElementById('fichiers').form, etat = document.getElementById('etat-envoi'), d = document.getElementById('dossier');
  document.getElementById('btnDossier').addEventListener('click', async function () {
    var tous = Array.prototype.filter.call(d.files, function (x) { return /\.(svg|png|jpe?g)$/i.test(x.name); });
    if (!tous.length) { etat.innerHTML = '<p class="msg msg-err" role="alert">Aucun fichier SVG, PNG ou JPEG dans ce dossier.</p>'; return; }
    var btn = this; btn.disabled = true;
    try {
      for (var i = 0; i < tous.length; i += 10) {
        var fd = new FormData();
        ['csrf', '_csrf', 'csrf_token'].forEach(function (k) { if (f.elements[k]) fd.append(k, f.elements[k].value); });
        Array.prototype.forEach.call(f.querySelectorAll('input[type=hidden]'), function (h) { if (h.name !== 'action') fd.set(h.name, h.value); });
        fd.set('action', 'preparer'); fd.set('paquet', '1');
        tous.slice(i, i + 10).forEach(function (x) { fd.append('fichiers[]', x, x.name); fd.append('chemins[]', x.webkitRelativePath || x.name); });
        etat.textContent = 'Envoi en cours : ' + Math.min(i + 10, tous.length) + ' fichier(s) sur ' + tous.length + '...';
        var rep = await fetch('banque.php', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } });
        if (!rep.ok) throw new Error('http ' + rep.status);
      }
      location.assign('banque.php#lot'); location.reload();
    } catch (e) { btn.disabled = false; etat.innerHTML = '<p class="msg msg-err" role="alert">L\'envoi s\'est interrompu. Les fichiers d\u00e9j\u00e0 re\u00e7us sont conserv\u00e9s : rechargez la page pour les retrouver.</p>'; }
  });
})();
</script>
</div></details>

<details class="boite" data-id="importer"><summary><span class="titre">Importer un fichier banque.js existant</span><span class="etat">sauvegarde ou ancienne banque</span></summary><div class="boite-corps">
<form method="post" action="banque.php" enctype="multipart/form-data">
  <?= champ_csrf() ?><input type="hidden" name="action" value="importer">
  <label for="banquejs">Fichier banque.js (produit par le Constructeur de banque ou t&eacute;l&eacute;charg&eacute; ici)</label>
  <input id="banquejs" name="banquejs" type="file" accept=".js,.json,text/javascript" required aria-describedby="i-a">
  <p class="aide" id="i-a">Les &eacute;l&eacute;ments sont <strong>ajout&eacute;s</strong> &agrave; votre banque ; ceux dont l'identifiant existe d&eacute;j&agrave; sont ignor&eacute;s. Les SVG sont nettoy&eacute;s au passage.</p>
  <p><button class="btn btn-sec" type="submit">Importer</button></p>
</form>
</div></details>
<script>
(function () {
  var CLE = 'tracteur-banque-boites', etat = {}, aTraiter = !!document.getElementById('lot');
  try { etat = JSON.parse(localStorage.getItem(CLE) || '{}'); } catch (e) {}
  document.querySelectorAll('details.boite[data-id]').forEach(function (d) {
    if (d.dataset.id in etat) d.open = !!etat[d.dataset.id];
    if (d.dataset.id === 'lot' || (aTraiter && d.dataset.id === 'ajouter')) d.open = true;       // des fichiers attendent : on ne les cache jamais
    d.addEventListener('toggle', function () { etat[d.dataset.id] = d.open; try { localStorage.setItem(CLE, JSON.stringify(etat)); } catch (e) {} });
  });
  document.getElementById('toutDeplier').onclick = function () { document.querySelectorAll('details.boite').forEach(function (d) { d.open = true; }); };
  document.getElementById('toutReplier').onclick = function () { document.querySelectorAll('details.boite').forEach(function (d) { d.open = false; }); };
  document.addEventListener('invalid', function (e) { var d = e.target.closest('details'); while (d) { d.open = true; d = d.parentElement.closest('details'); } }, true);
})();
</script>
<?php page_fin();

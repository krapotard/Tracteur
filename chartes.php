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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exiger_post();
    $action = (string)($_POST['action'] ?? '');
    $cible = null;
    if (isset($_POST['id'])) {   // la charte doit appartenir au compte connecte
        $s = $pdo->prepare('SELECT id, nom, defaut, contenu, protegee, supprimee_le FROM chartes WHERE id = ? AND compte_id = ?'); $s->execute([(int)$_POST['id'], $cid]);
        $cible = $s->fetch() ?: null;
    }
    $nom = mb_substr(trim((string)($_POST['nom'] ?? '')), 0, 80);
    $active = $cible && $cible['supprimee_le'] === null;
    if ($action === 'creer') {
        $src = (int)($_POST['source'] ?? 0);
        if ($nom === '') flash('err', 'Donnez un nom &agrave; la nouvelle charte.');
        else {
            $c = $src ? charte_du_compte($cid, $src) : null;
            if ($c) { unset($c['id'], $c['compte']); } else $c = charte_vierge($u['nom']);
            $pdo->prepare('INSERT INTO chartes (compte_id, nom, defaut, contenu) VALUES (?,?,0,?)')->execute([$cid, $nom, json_encode($c, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            flash('ok', 'Charte &laquo;&nbsp;' . h($nom) . '&nbsp;&raquo; cr&eacute;&eacute;e. Vous pouvez maintenant la modifier.');
            redirige('charte.php?id=' . (int)$pdo->lastInsertId());
        }
    } elseif ($action === 'importer') {                                     // ajout seulement : rien n'est remplace
        $f = $_FILES['fichier'] ?? null;
        $j = ($f && $f['error'] === UPLOAD_ERR_OK) ? json_decode((string)file_get_contents($f['tmp_name']), true) : null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) flash('err', 'Choisissez un fichier de chartes (.json) export&eacute; depuis Tracteur.');
        elseif (!is_array($j) || ($j['format'] ?? '') !== 'tracteur-chartes' || !is_array($j['chartes'] ?? null)) flash('err', 'Ce fichier n\'est pas un export de chartes Tracteur.');
        else {
            $n = 0; $refus = [];
            $ex = $pdo->prepare('SELECT COUNT(*) FROM chartes WHERE compte_id = ? AND supprimee_le IS NULL AND nom = ?');
            foreach (array_slice($j['chartes'], 0, 30) as $ch) {
                $nomI = mb_substr(trim((string)($ch['nom'] ?? '')), 0, 80);
                if ($nomI === '' || !is_array($ch['contenu'] ?? null)) { $refus[] = 'une charte sans nom ou illisible'; continue; }
                [$propre, $errV] = valider_charte($ch['contenu']);
                if ($errV) { $refus[] = '&laquo;&nbsp;' . h($nomI) . '&nbsp;&raquo; (' . implode(' ', $errV) . ')'; continue; }
                $base = $nomI; $k = 2;
                while (true) { $ex->execute([$cid, $nomI]); if (!(int)$ex->fetchColumn()) break; $nomI = mb_substr($base, 0, 74) . ' (' . $k++ . ')'; }
                $pdo->prepare('INSERT INTO chartes (compte_id, nom, defaut, contenu) VALUES (?,?,0,?)')->execute([$cid, $nomI, json_encode($propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                $n++;
            }
            if ($n) flash('ok', $n . ' charte' . ($n > 1 ? 's' : '') . ' import&eacute;e' . ($n > 1 ? 's' : '') . '. Les chartes existantes n\'ont pas &eacute;t&eacute; touch&eacute;es (un nom d&eacute;j&agrave; pris re&ccedil;oit un num&eacute;ro).');
            foreach ($refus as $m) flash('err', 'Non import&eacute;e : ' . $m);
            if (!$n && !$refus) flash('err', 'Le fichier ne contient aucune charte.');
        }
    } elseif ($active && $action === 'defaut') {
        $pdo->prepare('UPDATE chartes SET defaut = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE compte_id = ?')->execute([$cible['id'], $cid]);
        flash('ok', 'Charte par d&eacute;faut : &laquo;&nbsp;' . h($cible['nom']) . '&nbsp;&raquo;.');
    } elseif ($active && $action === 'supprimer') {                           // corbeille : rien n'est efface, la charte reste restaurable
        $n = (int)$pdo->query('SELECT COUNT(*) FROM chartes WHERE supprimee_le IS NULL AND compte_id = ' . $cid)->fetchColumn();
        if ($cible['protegee']) flash('err', 'Cette charte est prot&eacute;g&eacute;e : levez d\'abord la protection pour la supprimer.');
        elseif ($n <= 1) flash('err', 'Impossible de supprimer la derni&egrave;re charte du compte.');
        else {
            $pdo->prepare("UPDATE chartes SET supprimee_le = datetime('now'), defaut = 0 WHERE id = ? AND compte_id = ?")->execute([$cible['id'], $cid]);
            if ($cible['defaut']) $pdo->prepare('UPDATE chartes SET defaut = 1 WHERE id = (SELECT MIN(id) FROM chartes WHERE supprimee_le IS NULL AND compte_id = ?)')->execute([$cid]);
            flash('ok', 'Charte &laquo;&nbsp;' . h($cible['nom']) . '&nbsp;&raquo; plac&eacute;e dans la corbeille. Vous pouvez la restaurer pendant 30 jours (en bas de cette page).');
        }
    } elseif ($cible && $cible['supprimee_le'] !== null && $action === 'restaurer') {
        $pdo->prepare('UPDATE chartes SET supprimee_le = NULL WHERE id = ? AND compte_id = ?')->execute([$cible['id'], $cid]);
        flash('ok', 'Charte &laquo;&nbsp;' . h($cible['nom']) . '&nbsp;&raquo; restaur&eacute;e.');
    } elseif ($active && in_array($action, ['proteger', 'deproteger'], true)) {
        $pdo->prepare('UPDATE chartes SET protegee = ? WHERE id = ? AND compte_id = ?')->execute([$action === 'proteger' ? 1 : 0, $cible['id'], $cid]);
        flash('ok', $action === 'proteger' ? 'Charte &laquo;&nbsp;' . h($cible['nom']) . '&nbsp;&raquo; prot&eacute;g&eacute;e : plus de modification ni de suppression possible.' : 'Protection lev&eacute;e pour &laquo;&nbsp;' . h($cible['nom']) . '&nbsp;&raquo;.');
    } else flash('err', 'Action inconnue.');
    redirige('chartes.php');
}

$pdo->exec("DELETE FROM chartes WHERE supprimee_le IS NOT NULL AND supprimee_le < datetime('now', '-30 days')");      // la corbeille se vide apres 30 jours
$st = $pdo->prepare('SELECT id, nom, defaut, contenu, maj_le, protegee FROM chartes WHERE compte_id = ? AND supprimee_le IS NULL ORDER BY defaut DESC, id'); $st->execute([$cid]);
$chartes = $st->fetchAll();
$st = $pdo->prepare('SELECT id, nom, supprimee_le FROM chartes WHERE compte_id = ? AND supprimee_le IS NOT NULL ORDER BY supprimee_le DESC'); $st->execute([$cid]);
$corbeille = $st->fetchAll();

page_debut('Chartes graphiques', $u, 'chartes');
?>
<p>Une charte regroupe le nom de l'organisation, le logo, les couleurs, les coordonn&eacute;es et le pied de page de vos tracts et de vos mails. Chaque tract utilise la charte choisie dans l'&eacute;diteur ; la charte <strong>par d&eacute;faut</strong> est utilis&eacute;e &agrave; l'ouverture.</p>
<table>
  <caption>Chartes de <?= h($u['nom']) ?></caption>
  <thead><tr><th scope="col">Nom</th><th scope="col">Organisation</th><th scope="col">Couleur</th><th scope="col">Modifi&eacute;e</th><th scope="col">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($chartes as $c): $d = json_decode($c['contenu'], true) ?: []; $cache = 'position:absolute;left:-9999px'; ?>
    <tr>
      <th scope="row"><?= h($c['nom']) ?><?= $c['defaut'] ? ' <span class="etat-ok">(par d&eacute;faut)</span>' : '' ?><?= $c['protegee'] ? ' <span class="etat-ok">(prot&eacute;g&eacute;e)</span>' : '' ?></th>
      <td><?= h((string)($d['orgNom'] ?? '')) ?></td>
      <td><span style="display:inline-block;width:18px;height:18px;border:1px solid #1a1a1a;vertical-align:middle;background:<?= h((string)($d['couleurs']['rouge'] ?? '#C00000')) ?>"></span> <code><?= h((string)($d['couleurs']['rouge'] ?? '')) ?></code></td>
      <td><?= h((string)$c['maj_le']) ?></td>
      <td class="actions">
        <a class="btn btn-petit" href="charte.php?id=<?= (int)$c['id'] ?>">Modifier<span style="<?= $cache ?>"> la charte <?= h($c['nom']) ?></span></a>
        <a class="btn btn-sec btn-petit" href="app.php?charte=<?= (int)$c['id'] ?>">Ouvrir dans l'&eacute;diteur<span style="<?= $cache ?>"> avec la charte <?= h($c['nom']) ?></span></a>
        <?php if (!$c['defaut']): ?>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="defaut"><button class="btn btn-sec btn-petit" type="submit">D&eacute;finir par d&eacute;faut<span style="<?= $cache ?>"> : <?= h($c['nom']) ?></span></button></form>
        <?php endif; ?>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="<?= $c['protegee'] ? 'deproteger' : 'proteger' ?>"><button class="btn btn-sec btn-petit" type="submit"><?= $c['protegee'] ? 'Lever la protection' : 'Prot&eacute;ger' ?><span style="<?= $cache ?>"> la charte <?= h($c['nom']) ?></span></button></form>
        <?php if (count($chartes) > 1 && !$c['protegee']): ?>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="supprimer"><button class="btn btn-danger btn-petit" type="submit" onclick="return confirm('Mettre cette charte &agrave; la corbeille ? Elle restera r&eacute;cup&eacute;rable pendant 30 jours.')">Supprimer<span style="<?= $cache ?>"> la charte <?= h($c['nom']) ?></span></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<p class="aide">Plusieurs personnes peuvent utiliser ce compte. <strong>Prot&eacute;ger</strong> une charte la verrouille : personne ne peut la modifier ni la supprimer tant que la protection n'est pas lev&eacute;e. Une charte supprim&eacute;e va &agrave; la corbeille, et chaque enregistrement garde la version pr&eacute;c&eacute;dente (bouton &laquo;&nbsp;Modifier&nbsp;&raquo;, puis &laquo;&nbsp;Historique des versions&nbsp;&raquo;).</p>

<?php if ($corbeille): ?>
<h2>Corbeille</h2>
<table>
  <caption>Chartes supprim&eacute;es (conserv&eacute;es 30 jours)</caption>
  <thead><tr><th scope="col">Nom</th><th scope="col">Supprim&eacute;e le</th><th scope="col">Action</th></tr></thead>
  <tbody>
  <?php foreach ($corbeille as $c): ?>
    <tr><th scope="row"><?= h($c['nom']) ?></th><td><?= h((string)$c['supprimee_le']) ?></td>
      <td><form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input type="hidden" name="action" value="restaurer"><button class="btn btn-petit" type="submit">Restaurer<span style="position:absolute;left:-9999px"> la charte <?= h($c['nom']) ?></span></button></form></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Sauvegarder ou transf&eacute;rer mes chartes</h2>
<p><a class="btn btn-sec" href="chartes_export.php">T&eacute;l&eacute;charger toutes mes chartes (fichier .json)</a></p>
<form method="post" action="chartes.php" enctype="multipart/form-data">
  <?= champ_csrf() ?><input type="hidden" name="action" value="importer">
  <label for="fichierChartes">Ajouter des chartes depuis un fichier export&eacute; par Tracteur</label>
  <input id="fichierChartes" name="fichier" type="file" accept=".json,application/json" required aria-describedby="imp-a">
  <p class="aide" id="imp-a">Les chartes du fichier sont <strong>ajout&eacute;es</strong> &agrave; celles de ce compte ; rien n'est remplac&eacute;. Utile pour sauvegarder, ou pour copier vos chartes d'une installation &agrave; une autre.</p>
  <p><button class="btn btn-sec" type="submit">Importer</button></p>
</form>

<h2>Cr&eacute;er une charte</h2>
<form method="post" action="chartes.php">
  <?= champ_csrf() ?><input type="hidden" name="action" value="creer">
  <label for="nom">Nom de la nouvelle charte</label>
  <input id="nom" name="nom" type="text" required maxlength="80" aria-describedby="a1">
  <p class="aide" id="a1">Nom interne, pour vous y retrouver. Exemple : &laquo;&nbsp;Charte 1er mai&nbsp;&raquo;.</p>
  <label for="source">Point de d&eacute;part</label>
  <select id="source" name="source"><option value="0">Charte vierge</option>
    <?php foreach ($chartes as $c): ?><option value="<?= (int)$c['id'] ?>">Copie de &laquo;&nbsp;<?= h($c['nom']) ?>&nbsp;&raquo;</option><?php endforeach; ?></select>
  <p><button class="btn" type="submit">Cr&eacute;er la charte</button></p>
</form>
<?php page_fin();

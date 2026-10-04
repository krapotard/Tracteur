<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/charte.php';
require __DIR__ . '/src/amorce.php';
require __DIR__ . '/src/vue.php';
$u = exiger_admin();
$pdo = db();

function mdp_provisoire(): string {
    $mots = ['tracteur','tract','greve','mairie','soleil','ruche','canal','blason','foret','rivage','ardoise','moulin','bruyere','sentier','horizon','tulipe'];
    return $mots[random_int(0, 15)] . '-' . $mots[random_int(0, 15)] . '-' . random_int(1000, 9999) . '-' . $mots[random_int(0, 15)];
}
function annonce_mdp(string $identifiant, string $mdp): string {
    return 'Compte <code>' . h($identifiant) . '</code>. Mot de passe provisoire (affich&eacute; une seule fois, &agrave; transmettre au syndicat) : <span class="mdp-affiche">' . h($mdp) . '</span><br>Il sera &agrave; changer &agrave; la premi&egrave;re connexion.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exiger_post();
    $action = (string)($_POST['action'] ?? '');
    $cible = null;
    if (isset($_POST['id'])) {
        $s = $pdo->prepare("SELECT id, identifiant, nom, actif FROM comptes WHERE id = ? AND role = 'syndicat'"); $s->execute([(int)$_POST['id']]);
        $cible = $s->fetch() ?: null;
    }
    if ($action === 'creer') {
        $id = strtolower(trim((string)($_POST['identifiant'] ?? ''))); $nom = mb_substr(trim((string)($_POST['nom'] ?? '')), 0, 120);
        $copie = (int)($_POST['copie_de'] ?? 0);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,29}$/', $id)) flash('err', 'Identifiant invalide : 3 &agrave; 30 caract&egrave;res, lettres minuscules sans accent, chiffres et tirets.');
        elseif ($nom === '') flash('err', 'Le nom du syndicat est obligatoire.');
        else {
            $e = $pdo->prepare('SELECT 1 FROM comptes WHERE identifiant = ?'); $e->execute([$id]);
            if ($e->fetch()) flash('err', 'Cet identifiant existe d&eacute;j&agrave;.');
            else {
                $mdp = trim((string)($_POST['mdp'] ?? ''));
                if ($mdp !== '' && strlen($mdp) < 12) flash('err', 'Le mot de passe saisi est trop court (12 caract&egrave;res minimum), ou laissez le champ vide pour en g&eacute;n&eacute;rer un.');
                else {
                    $mdp = $mdp !== '' ? $mdp : mdp_provisoire();
                    $pdo->beginTransaction();
                    $pdo->prepare("INSERT INTO comptes (identifiant, nom, mdp_hash, role, mdp_a_changer) VALUES (?,?,?, 'syndicat', 1)")->execute([$id, $nom, password_hash($mdp, PASSWORD_DEFAULT)]);
                    $cid = (int)$pdo->lastInsertId();
                    $charte = $copie ? charte_du_compte($copie) : null;
                    if ($charte) { unset($charte['id'], $charte['compte']); [$charte] = valider_charte($charte); } else $charte = charte_vierge($nom);
                    $pdo->prepare('INSERT INTO chartes (compte_id, nom, defaut, contenu) VALUES (?,?,1,?)')->execute([$cid, 'Charte ' . $nom, json_encode($charte, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
                    ajouter_banque_par_defaut($pdo, $cid);                // la banque de pictogrammes par defaut est toujours livree
                    if ($copie && !empty($_POST['copier_banque']))
                        $pdo->prepare('INSERT OR IGNORE INTO banque_elements (compte_id, elem_id, contenu) SELECT ?, elem_id, contenu FROM banque_elements WHERE compte_id = ?')->execute([$cid, $copie]);
                    $pdo->commit();
                    flash('ok', annonce_mdp($id, $mdp));
                }
            }
        }
    } elseif ($cible && $action === 'basculer') {
        $pdo->prepare('UPDATE comptes SET actif = 1 - actif WHERE id = ?')->execute([$cible['id']]);
        flash('ok', 'Compte <code>' . h($cible['identifiant']) . '</code> ' . ($cible['actif'] ? 'd&eacute;sactiv&eacute; (connexion impossible)' : 'r&eacute;activ&eacute;') . '.');
    } elseif ($cible && $action === 'reinit') {
        $mdp = mdp_provisoire();
        $pdo->prepare('UPDATE comptes SET mdp_hash = ?, mdp_a_changer = 1 WHERE id = ?')->execute([password_hash($mdp, PASSWORD_DEFAULT), $cible['id']]);
        flash('ok', annonce_mdp($cible['identifiant'], $mdp));
    } elseif ($cible && $action === 'supprimer') {
        if (trim((string)($_POST['confirmation'] ?? '')) !== $cible['identifiant']) flash('err', 'Confirmation incorrecte : saisissez exactement l\'identifiant <code>' . h($cible['identifiant']) . '</code>.');
        else { $pdo->prepare('DELETE FROM comptes WHERE id = ?')->execute([$cible['id']]); flash('ok', 'Compte <code>' . h($cible['identifiant']) . '</code> supprim&eacute; avec ses chartes et sa banque.'); }
    } else flash('err', 'Action inconnue.');
    redirige('admin.php');
}

$rows = $pdo->query("SELECT c.id, c.identifiant, c.nom, c.role, c.actif, c.derniere_connexion, c.mdp_a_changer,
  (SELECT COUNT(*) FROM chartes WHERE compte_id = c.id) AS nb_chartes,
  (SELECT COUNT(*) FROM banque_elements WHERE compte_id = c.id) AS nb_elements
  FROM comptes c ORDER BY c.role, c.identifiant")->fetchAll();
$syndicats = array_values(array_filter($rows, fn($r) => $r['role'] === 'syndicat'));
$cache = 'position:absolute;left:-9999px';

page_debut('Comptes', $u, 'comptes');
?>
<table>
  <caption>Comptes existants</caption>
  <thead><tr><th scope="col">Identifiant</th><th scope="col">Nom</th><th scope="col">&Eacute;tat</th><th scope="col">Chartes</th><th scope="col">&Eacute;l&eacute;ments</th><th scope="col">Derni&egrave;re connexion</th><th scope="col">Actions</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <th scope="row"><?= h($r['identifiant']) ?><?= $r['role'] === 'admin' ? ' <span class="aide">(administrateur)</span>' : '' ?></th>
      <td><?= h($r['nom']) ?></td>
      <td><?= $r['actif'] ? '<span class="etat-ok">Actif</span>' : '<span class="etat-ko">D&eacute;sactiv&eacute;</span>' ?><?= $r['mdp_a_changer'] ? '<br><span class="aide">mot de passe provisoire</span>' : '' ?></td>
      <td><?= (int)$r['nb_chartes'] ?></td><td><?= (int)$r['nb_elements'] ?></td>
      <td><?= h((string)($r['derniere_connexion'] ?? 'jamais')) ?></td>
      <td class="actions">
      <?php if ($r['role'] === 'syndicat'): ?>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="basculer">
          <button class="btn btn-sec btn-petit" type="submit"><?= $r['actif'] ? 'D&eacute;sactiver' : 'R&eacute;activer' ?><span style="<?= $cache ?>"> le compte <?= h($r['identifiant']) ?></span></button></form>
        <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="reinit">
          <button class="btn btn-sec btn-petit" type="submit" onclick="return confirm('G&eacute;n&eacute;rer un nouveau mot de passe provisoire pour <?= h($r['identifiant']) ?> ? L\'ancien ne fonctionnera plus.')">Nouveau mot de passe<span style="<?= $cache ?>"> pour <?= h($r['identifiant']) ?></span></button></form>
        <details><summary>Supprimer&hellip;<span style="<?= $cache ?>"> le compte <?= h($r['identifiant']) ?></span></summary>
          <form method="post"><?= champ_csrf() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="supprimer">
            <label for="conf<?= (int)$r['id'] ?>">Saisir <code><?= h($r['identifiant']) ?></code> pour confirmer la suppression d&eacute;finitive (chartes et banque comprises)</label>
            <input id="conf<?= (int)$r['id'] ?>" name="confirmation" type="text" autocomplete="off" required>
            <button class="btn btn-danger btn-petit" type="submit">Supprimer d&eacute;finitivement</button></form></details>
      <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<h2>Cr&eacute;er un compte syndicat</h2>
<form method="post" action="admin.php">
  <?= champ_csrf() ?><input type="hidden" name="action" value="creer">
  <label for="identifiant">Identifiant de connexion</label>
  <input id="identifiant" name="identifiant" type="text" autocapitalize="none" autocomplete="off" required pattern="[a-z0-9][a-z0-9\-]{2,29}" aria-describedby="a1">
  <p class="aide" id="a1">3 &agrave; 30 caract&egrave;res : lettres minuscules sans accent, chiffres, tirets. Exemple : <code>mon-syndicat</code>.</p>
  <label for="nom">Nom du syndicat</label>
  <input id="nom" name="nom" type="text" required maxlength="120" aria-describedby="a2">
  <p class="aide" id="a2">Exemple : Syndicat des services publics de Quelquepart. Il servira de nom de charte de d&eacute;part.</p>
  <label for="mdp">Mot de passe provisoire (facultatif)</label>
  <input id="mdp" name="mdp" type="text" autocomplete="off" aria-describedby="a3">
  <p class="aide" id="a3">Laiss&eacute; vide, un mot de passe est g&eacute;n&eacute;r&eacute; et affich&eacute; une fois. Dans tous les cas, le syndicat devra le changer &agrave; sa premi&egrave;re connexion.</p>
  <label for="copie_de">Partir de la charte d'un compte existant (facultatif)</label>
  <select id="copie_de" name="copie_de"><option value="0">Charte vierge (couleurs et logo par d&eacute;faut)</option>
    <?php foreach ($syndicats as $s): ?><option value="<?= (int)$s['id'] ?>"><?= h($s['nom']) ?></option><?php endforeach; ?></select>
  <label class="case"><input type="checkbox" name="copier_banque" value="1"> Ajouter aussi les &eacute;l&eacute;ments personnels de la banque de ce compte</label>
  <p class="aide">La banque de pictogrammes par d&eacute;faut est toujours incluse dans un nouveau compte.</p>
  <p><button class="btn" type="submit">Cr&eacute;er le compte</button></p>
</form>
<?php page_fin();

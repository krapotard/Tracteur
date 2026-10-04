<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/vue.php';
$u = exiger_connexion();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exiger_post();
    $st = db()->prepare('SELECT mdp_hash FROM comptes WHERE id = ?'); $st->execute([$u['id']]);
    $hash = (string)$st->fetchColumn();
    $actuel = (string)($_POST['actuel'] ?? ''); $n1 = (string)($_POST['nouveau'] ?? ''); $n2 = (string)($_POST['nouveau2'] ?? '');
    if (!password_verify($actuel, $hash)) flash('err', 'Le mot de passe actuel est incorrect.');
    elseif (strlen($n1) < 12) flash('err', 'Le nouveau mot de passe doit faire au moins 12 caract&egrave;res.');
    elseif ($n1 !== $n2) flash('err', 'Les deux saisies du nouveau mot de passe sont diff&eacute;rentes.');
    elseif ($n1 === $actuel) flash('err', 'Le nouveau mot de passe doit &ecirc;tre diff&eacute;rent de l\'ancien.');
    else {
        db()->prepare('UPDATE comptes SET mdp_hash = ?, mdp_a_changer = 0 WHERE id = ?')->execute([password_hash($n1, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash('ok', 'Mot de passe modifi&eacute;.');
        redirige($u['role'] === 'admin' ? 'admin.php' : 'chartes.php');
    }
    redirige('motdepasse.php');
}

page_debut('Mot de passe', $u, 'mdp');
if ($u['mdp_a_changer']) echo '<p class="msg msg-err" role="alert"><strong>Mot de passe provisoire.</strong> Choisissez un mot de passe personnel pour continuer.</p>';
?>
<form method="post" action="motdepasse.php">
  <?= champ_csrf() ?>
  <label for="actuel">Mot de passe actuel</label>
  <input id="actuel" name="actuel" type="password" autocomplete="current-password" required>
  <label for="nouveau">Nouveau mot de passe</label>
  <input id="nouveau" name="nouveau" type="password" autocomplete="new-password" minlength="12" required aria-describedby="aide-mdp">
  <p class="aide" id="aide-mdp">12 caract&egrave;res minimum. Une phrase de plusieurs mots est plus facile &agrave; retenir qu'un mot de passe compliqu&eacute;.</p>
  <label for="nouveau2">Confirmer le nouveau mot de passe</label>
  <input id="nouveau2" name="nouveau2" type="password" autocomplete="new-password" minlength="12" required>
  <p><button class="btn" type="submit">Changer le mot de passe</button></p>
</form>
<?php page_fin();

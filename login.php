<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
demarrer_session();

if (utilisateur()) { header('Location: index.php'); exit; }

$erreur = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifier_csrf($_POST['csrf'] ?? null)) {
        $erreur = 'Session expir&eacute;e, veuillez r&eacute;essayer.';
    } else {
        $erreur = connecter((string)($_POST['identifiant'] ?? ''), (string)($_POST['mdp'] ?? '')) ?? '';
        if ($erreur === '') { header('Location: index.php'); exit; }
    }
}
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion &ndash; Tracteur, &eacute;diteur de tracts accessibles</title>
<?= liens_marque() ?>
<style>
:root{--texte:#1a1a1a;--fond:#f3f3f3;--rouge:#C00000;--bord:#767676}
*{box-sizing:border-box}
body{margin:0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.5;color:var(--texte);background:var(--fond);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px}
main{background:#fff;border:1px solid #c9c9c9;border-radius:6px;padding:28px;width:100%;max-width:400px}
.marque{display:flex;align-items:center;gap:14px;margin:0 0 22px}
.marque img{display:block;width:64px;height:64px;border-radius:14px;flex:none}
h1{font-size:16px;font-weight:normal;line-height:1.3;margin:0}
h1 .nom{display:block;font-size:30px;font-weight:bold;color:var(--rouge);line-height:1.1}
label{display:block;font-weight:bold;margin:14px 0 4px}
input{width:100%;font:inherit;padding:9px 10px;border:2px solid var(--bord);border-radius:4px}
input:focus-visible,button:focus-visible{outline:3px solid #0b4f9c;outline-offset:2px}
button{margin-top:20px;width:100%;font:inherit;font-weight:bold;padding:11px;border:0;border-radius:4px;background:var(--rouge);color:#fff;cursor:pointer}
.err{background:#fde8e8;border:2px solid var(--rouge);border-radius:4px;padding:10px 12px;margin:0 0 8px}
</style>
</head>
<body>
<main>
  <div class="marque">
    <img src="assets/logo/tracteur-app.svg" alt="" width="64" height="64">
    <h1><span class="nom">Tracteur</span> &eacute;diteur de tracts accessibles</h1>
  </div>
  <?php if ($erreur !== ''): ?><p class="err" role="alert"><?= $erreur ?></p><?php endif; ?>
  <form method="post" action="login.php">
    <input type="hidden" name="csrf" value="<?= h(jeton_csrf()) ?>">
    <label for="identifiant">Identifiant du syndicat</label>
    <input id="identifiant" name="identifiant" type="text" autocomplete="username" autocapitalize="none" required autofocus>
    <label for="mdp">Mot de passe</label>
    <input id="mdp" name="mdp" type="password" autocomplete="current-password" required>
    <button type="submit">Se connecter</button>
  </form>
</main>
</body>
</html>

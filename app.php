<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/charte.php';

$u = exiger_connexion();
if ($u['role'] === 'admin') { header('Location: admin.php'); exit; }

$charte = charte_du_compte((int)$u['id'], isset($_GET['charte']) ? (int)$_GET['charte'] : null);
if (!$charte) { http_response_code(409); exit('Aucune charte n\'est configur&eacute;e pour ce compte. Contactez l\'administrateur.'); }
$charteId = (int)$charte["id"];
[$charte, $erreurs] = valider_charte($charte + []);   // garantit toutes les cles attendues par l'editeur
$charte = adapter_pour_editeur($charte);
$charte["id"] = $charteId;
$charte['compte'] = (int)$u['id'];

$version = static fn(string $f) => (string)@filemtime(__DIR__ . '/' . $f);
$json = json_encode($charte, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$vars = ':root{--rouge:' . $charte['couleurs']['rouge'] . ';--jaune:' . $charte['couleurs']['jaune'] . ';--texte:' . $charte['couleurs']['texte'] . ';--lien:' . $charte['couleurs']['lien'] . '}';

$vue = file_get_contents(__DIR__ . '/src/vue_editeur.html');
$liste = db()->prepare('SELECT id, nom FROM chartes WHERE compte_id = ? AND supprimee_le IS NULL ORDER BY defaut DESC, id'); $liste->execute([(int)$u['id']]);
$toutes = $liste->fetchAll();
// Selecteur de charte : en tete de l'encadre "Tract", au-dessus du modele (le changement recharge la page, le brouillon est conserve).
$choixCharte = '<label for="choixCharte">Charte</label><select id="choixCharte" aria-describedby="aideCharte">';
foreach ($toutes as $t) $choixCharte .= '<option value="' . (int)$t['id'] . '"' . ((int)$t['id'] === $charteId ? ' selected' : '') . '>' . h($t['nom']) . '</option>';
$choixCharte .= '</select><p class="aide" id="aideCharte">Identit&eacute; du tract : logo, couleurs, coordonn&eacute;es, pied de page. <a href="chartes.php">G&eacute;rer mes chartes</a></p>';
$deconnexion = '<a class="btn" href="aide.php" target="_blank" rel="noopener" style="margin-left:auto;text-decoration:none">Aide<span style="position:absolute;left:-9999px"> (s\'ouvre dans un nouvel onglet)</span></a><a class="btn" href="chartes.php" style="text-decoration:none">Mon espace</a>'
    . '<form method="post" action="logout.php" style="margin:0"><input type="hidden" name="csrf" value="' . h(jeton_csrf()) . '"><button class="btn" type="submit">D&eacute;connexion</button></form>';
$vue = str_replace('<input type="file" id="fProjet" accept=".json,application/json" hidden>', '<input type="file" id="fProjet" accept=".json,application/json" hidden>' . $deconnexion, $vue, $n);
if ($n !== 1) { http_response_code(500); exit('Gabarit inattendu.'); }
$vue = str_replace('<label for="modele">', $choixCharte . '<label for="modele">', $vue, $n);
if ($n !== 1) { http_response_code(500); exit('Gabarit inattendu (modele).'); }

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tracteur &ndash; &eacute;diteur de tracts accessibles &ndash; <?= h($charte["orgNom"]) ?></title>
<?= liens_marque() ?>
<link rel="stylesheet" href="assets/polices.css?v=<?= $version('assets/polices.css') ?>">
<style id="css-principal">
<?= $vars . "\n" . file_get_contents(__DIR__ . '/assets/editeur.css') ?>
</style>
</head>
<body>
<?= $vue ?>
<script>window.__CHARTE__ = <?= $json ?>; window.__CSRF__ = <?= json_encode(jeton_csrf()) ?>;</script>
<script src="assets/editeur.js?v=<?= $version('assets/editeur.js') ?>"></script>
</body>
</html>

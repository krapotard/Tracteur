<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
/**
 * Assistant d'installation (a supprimer du serveur une fois termine).
 * Acces : creer le fichier data/cle_installation.txt (une phrase aleatoire d'au moins 16 caracteres),
 * puis ouvrir installation.php et saisir cette phrase.
 */
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/charte.php';
require __DIR__ . '/src/pdf.php';
require __DIR__ . '/src/chrome_install.php';
require __DIR__ . '/src/amorce.php';
demarrer_session();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$cleFichier = RACINE . '/data/cle_installation.txt';
$verrou = RACINE . '/data/installation.lock';
$sortie = '';   // messages de l'action en cours

function page(string $contenu): never {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Installation &ndash; &Eacute;diteur de tracts</title>'
       . '<style>body{font-family:Arial,sans-serif;max-width:760px;margin:24px auto;padding:0 16px;line-height:1.5;color:#1a1a1a}h1{font-size:24px}h2{font-size:18px;margin-top:28px;border-top:1px solid #c9c9c9;padding-top:16px}'
       . 'label{display:block;font-weight:bold;margin:12px 0 4px}input{font:inherit;padding:8px;border:2px solid #767676;border-radius:4px;width:100%;max-width:420px}'
       . 'button{font:inherit;font-weight:bold;padding:9px 16px;margin-top:12px;border:0;border-radius:4px;background:#C00000;color:#fff;cursor:pointer}'
       . 'pre{background:#f3f3f3;padding:10px;overflow:auto;white-space:pre-wrap}.ok{color:#0b6b2b;font-weight:bold}.ko{color:#a00000;font-weight:bold}.cadre{border:2px solid #767676;border-radius:6px;padding:10px 14px}'
       . ':focus-visible{outline:3px solid #0b4f9c;outline-offset:2px}</style></head><body><h1>Installation de l\'&eacute;diteur de tracts</h1>' . $contenu . '</body></html>';
    exit;
}

if (is_file($verrou)) page('<p class="cadre"><strong>Installation termin&eacute;e et verrouill&eacute;e.</strong> Supprimez <code>installation.php</code> et <code>data/cle_installation.txt</code> du serveur.</p><p>Connexion : identifiant <code>admin</code> (administrateur) ou l\'identifiant du syndicat (par exemple <code>demo</code>), avec le mot de passe choisi &agrave; l\'installation.</p><p><a href="login.php">Aller &agrave; la connexion</a></p>');
if (!is_file($cleFichier) || strlen(trim((string)file_get_contents($cleFichier))) < 16)
    page('<p class="cadre">Pour s&eacute;curiser l\'installation, cr&eacute;ez sur le serveur le fichier <code>data/cle_installation.txt</code> contenant une phrase al&eacute;atoire d\'au moins 16 caract&egrave;res (et rien d\'autre), puis rechargez cette page.</p>');

// ---- Authentification de l'installateur ----
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($post && ($_POST['action'] ?? '') === 'cle') {
    if (hash_equals(trim((string)file_get_contents($cleFichier)), trim((string)($_POST['cle'] ?? '')))) { session_regenerate_id(true); $_SESSION['inst'] = true; $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    else { usleep(800000); }
    header('Location: installation.php'); exit;
}
if (empty($_SESSION['inst'])) {
    page('<form method="post"><input type="hidden" name="action" value="cle"><label for="cle">Phrase du fichier data/cle_installation.txt</label><input id="cle" name="cle" type="password" autocomplete="off" required autofocus><br><button type="submit">Continuer</button></form>');
}
if ($post && !verifier_csrf($_POST['csrf'] ?? null)) page('<p class="ko">Session expir&eacute;e. <a href="installation.php">Recharger</a></p>');

$msgs = [];
$ajout = function (string $m) use (&$msgs) { $msgs[] = $m; };
$action = $post ? (string)($_POST['action'] ?? '') : '';

if ($action === 'base') {
    $mdp = (string)($_POST['mdp_admin'] ?? '');
    if (strlen($mdp) < 12 || $mdp !== (string)($_POST['mdp_admin2'] ?? '')) { $ajout('ERREUR : le mot de passe administrateur doit faire au moins 12 caract&egrave;res et &ecirc;tre identique dans les deux champs.'); }
    else {
        $pdo = db();
        $st = $pdo->prepare('SELECT id FROM comptes WHERE identifiant = ?'); $st->execute(['admin']);
        if ($st->fetch()) $ajout('Le compte admin existe d&eacute;j&agrave;.');
        else { $pdo->prepare("INSERT INTO comptes (identifiant, nom, mdp_hash, role) VALUES ('admin','Administrateur',?, 'admin')")->execute([password_hash($mdp, PASSWORD_DEFAULT)]); $ajout('Base cr&eacute;&eacute;e et compte <strong>admin</strong> cr&eacute;&eacute;.'); }
        $amorce = RACINE . '/src/amorce.json';
        if (!empty($_POST['importer']) && is_file($amorce)) {
            $m2 = (string)($_POST['mdp_syndicat'] ?? '');
            if (strlen($m2) < 12) $ajout('ERREUR : mot de passe du syndicat trop court (12 caract&egrave;res minimum), import ignor&eacute;.');
            else $ajout(h(importer_amorce($pdo, json_decode((string)file_get_contents($amorce), true), $m2)));
        }
    }
}
if ($action === 'chrome') {
    $lignes = [];
    $ok = installer_chrome(function (string $l) use (&$lignes) { $lignes[] = $l; });
    $ajout(($ok ? '<span class="ok">Moteur PDF install&eacute;.</span>' : '<span class="ko">Installation incompl&egrave;te.</span>') . '<pre>' . h(implode("\n", $lignes)) . '</pre>');
}
if ($action === 'test') {
    try { $r = tester_pdf(); $ajout('<span class="' . ($r['balise'] ? 'ok' : 'ko') . '">PDF cr&eacute;&eacute; : ' . $r['octets'] . ' octets en ' . $r['secondes'] . ' s &ndash; ' . ($r['balise'] ? 'balis&eacute; (accessible)' : 'NON balis&eacute;') . '.</span>'); }
    catch (Throwable $e) { $ajout('<span class="ko">&Eacute;chec du PDF : ' . h($e->getMessage()) . '</span>'); }
}
if ($action === 'verrouiller') {
    file_put_contents($verrou, date('c'));
    header('Location: installation.php'); exit;
}

// ---- Diagnostic ----
$diag = [];
$diag[] = ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.1', '>=')];
foreach (['pdo_sqlite', 'mbstring', 'dom', 'curl', 'zip', 'gd'] as $e) $diag[] = ["Extension $e", extension_loaded($e)];
$diag[] = ['Dossier data/ inscriptible', is_writable(RACINE . '/data')];
$off = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$diag[] = ['Lancement de programmes (proc_open)', function_exists('proc_open') && !in_array('proc_open', $off, true)];
$diag[] = ['Python 3 (bibliotheques de Chrome)', DIRECTORY_SEPARATOR === '\\' || trim((string)@exec('command -v python3')) !== ''];
// Le dossier data/ ne doit pas etre lisible depuis le web
$schema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$urlData = $schema . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/data/cle_installation.txt';
$ch = curl_init($urlData); curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 10, CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false]);
curl_exec($ch); $codeData = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$diag[] = ['Dossier data/ ferm&eacute; au web (doit r&eacute;pondre 403/404, re&ccedil;u : ' . $codeData . ')', $codeData >= 400 || $codeData === 0];

$cfg = config_pdf();
$aBase = is_file(FICHIER_BASE) && (int)db()->query('SELECT COUNT(*) FROM comptes')->fetchColumn() > 0;
$aAmorce = is_file(RACINE . '/src/amorce.json');
$jeton = h(jeton_csrf());

$html = '<h2>1. Diagnostic</h2><ul>';
foreach ($diag as [$l, $ok]) $html .= '<li>' . $l . ' : ' . ($ok ? '<span class="ok">OK</span>' : '<span class="ko">PROBL&Egrave;ME</span>') . '</li>';
$html .= '</ul>';
if ($msgs) $html .= '<div class="cadre" role="status">' . implode('<br>', $msgs) . '</div>';

$html .= '<h2>2. Base de donn&eacute;es et administrateur</h2>';
if ($aBase) $html .= '<p class="ok">La base contient d&eacute;j&agrave; des comptes.</p>';
else {
    $html .= '<form method="post"><input type="hidden" name="csrf" value="' . $jeton . '"><input type="hidden" name="action" value="base">'
        . '<p class="cadre">Pour vous connecter, il faudra un <strong>identifiant</strong> et un mot de passe. L\'identifiant de l\'administrateur est toujours : <code>admin</code>.</p>'
        . '<label for="m1">Mot de passe du compte <code>admin</code> (12 caract&egrave;res minimum)</label><input id="m1" name="mdp_admin" type="password" autocomplete="new-password" minlength="12" required>'
        . '<label for="m2">Confirmation</label><input id="m2" name="mdp_admin2" type="password" autocomplete="new-password" minlength="12" required>';
    if ($aAmorce) $html .= '<p><label style="display:inline"><input type="checkbox" name="importer" value="1" checked style="width:auto"> Cr&eacute;er le compte de d&eacute;monstration (charte g&eacute;n&eacute;rique et banque d\'&eacute;l&eacute;ments)</label></p>'
        . '<label for="m3">Mot de passe du compte de d&eacute;monstration, identifiant : <code>' . h((string)(json_decode((string)file_get_contents(RACINE . '/src/amorce.json'), true)['compte']['identifiant'] ?? '')) . '</code> (12 caract&egrave;res minimum)</label><input id="m3" name="mdp_syndicat" type="password" autocomplete="new-password" minlength="12">';
    $html .= '<br><button type="submit">Cr&eacute;er la base</button></form>';
}

$html .= '<h2>3. Moteur PDF</h2><p>Navigateur d&eacute;tect&eacute; : <code>' . h((string)($cfg['chrome'] ?: 'aucun')) . '</code></p>';
if (DIRECTORY_SEPARATOR === '/') $html .= '<form method="post" onsubmit="this.querySelector(\'button\').disabled=true;this.querySelector(\'button\').textContent=\'Installation en cours (1 &agrave; 3 minutes)&hellip;\'"><input type="hidden" name="csrf" value="' . $jeton . '"><input type="hidden" name="action" value="chrome"><button type="submit">Installer Chrome (environ 115 Mo)</button></form>';
$html .= '<form method="post"><input type="hidden" name="csrf" value="' . $jeton . '"><input type="hidden" name="action" value="test"><button type="submit">Tester la cr&eacute;ation d\'un PDF</button></form>';

$html .= '<h2>4. Terminer</h2><p>Une fois les &eacute;tapes 2 et 3 r&eacute;ussies, verrouillez l\'installation, puis <strong>supprimez du serveur</strong> <code>installation.php</code> et <code>data/cle_installation.txt</code>.</p>'
    . '<form method="post"><input type="hidden" name="csrf" value="' . $jeton . '"><input type="hidden" name="action" value="verrouiller"><button type="submit">Verrouiller l\'installation</button></form>';
page($html);

<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);

// ---------- Messages ponctuels (affiches apres une redirection) ----------
function flash(string $type, string $html): void {
    demarrer_session();
    $_SESSION['flash'][] = [$type, $html];
}
function flashs(): string {
    demarrer_session();
    $out = '';
    foreach ($_SESSION['flash'] ?? [] as [$type, $html]) {
        $pre = $type === 'ok' ? 'Succ&egrave;s : ' : 'Erreur : ';
        $out .= '<div class="msg msg-' . ($type === 'ok' ? 'ok' : 'err') . '" role="' . ($type === 'ok' ? 'status' : 'alert') . '"><strong>' . $pre . '</strong>' . $html . '</div>';
    }
    unset($_SESSION['flash']);
    return $out;
}
function champ_csrf(): string { return '<input type="hidden" name="csrf" value="' . h(jeton_csrf()) . '">'; }
function exiger_post(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifier_csrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        exit('Requ&ecirc;te refus&eacute;e (session expir&eacute;e ?). Rechargez la page.');
    }
}
function redirige(string $url): never { header('Location: ' . $url); exit; }

// ---------- Squelette de page ----------
function page_debut(string $titre, array $u, string $actif = ''): void {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $menu = $u['role'] === 'admin'
        ? ['admin.php' => ['comptes', 'Comptes'], 'aide.php' => ['aide', 'Aide'], 'motdepasse.php' => ['mdp', 'Mot de passe']]
        : ['app.php' => ['editeur', '&Eacute;diteur'], 'chartes.php' => ['chartes', 'Chartes'], 'banque.php' => ['banque', 'Banque d\'&eacute;l&eacute;ments'], 'aide.php' => ['aide', 'Aide'], 'motdepasse.php' => ['mdp', 'Mot de passe']];
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . $titre . ' &ndash; Tracteur</title>' . liens_marque()
        . '<link rel="stylesheet" href="assets/admin.css?v=' . (int)@filemtime(RACINE . '/assets/admin.css') . '"></head><body>'
        . '<a class="evitement" href="#contenu">Aller au contenu</a>'
        . '<header class="entete"><div class="marque"><img src="assets/logo/tracteur-app.svg" alt="" width="40" height="40">'
        . '<p class="nom-appli"><span class="nom">Tracteur</span><span class="desc"> &ndash; &eacute;diteur de tracts accessibles</span></p></div>'
        . '<nav aria-label="Principal"><ul>';
    foreach ($menu as $page => [$cle, $lib]) echo '<li><a href="' . $page . '"' . ($cle === $actif ? ' aria-current="page"' : '') . '>' . $lib . '</a></li>';
    echo '<li><form method="post" action="logout.php">' . champ_csrf() . '<button type="submit" class="lien-bouton">D&eacute;connexion</button></form></li>'
        . '</ul></nav><p class="qui">Connect&eacute; : <strong>' . h($u['nom']) . '</strong></p></header>'
        . '<main id="contenu"><h1>' . $titre . '</h1>' . flashs();
}
function page_fin(): void { echo '</main></body></html>'; }

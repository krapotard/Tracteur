<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
// Installation en ligne de commande (poste de developpement ou serveur avec acces SSH).
//   php tools/install.php
// Cree la base, le compte administrateur et un compte "demo" (charte de demonstration + banque d'elements par defaut), d'apres src/amorce.json.
// Les mots de passe generes sont ecrits dans data/identifiants_initiaux.txt (a supprimer apres lecture).
if (PHP_SAPI !== 'cli') exit('CLI uniquement.');
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/charte.php';
require __DIR__ . '/../src/amorce.php';

$pdo = db();
function mdp(): string { $a = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRTUVWXYZ2346789'; $s = ''; for ($i = 0; $i < 14; $i++) $s .= $a[random_int(0, strlen($a) - 1)]; return $s; }
$creds = [];
$creer = function (string $id, string $nom, string $role) use ($pdo, &$creds) {
    $st = $pdo->prepare('SELECT id FROM comptes WHERE identifiant = ?'); $st->execute([$id]);
    if ($r = $st->fetch()) { echo "compte '$id' deja present\n"; return (int)$r['id']; }
    $m = mdp(); $creds[$id] = $m;
    $pdo->prepare('INSERT INTO comptes (identifiant, nom, mdp_hash, role) VALUES (?,?,?,?)')->execute([$id, $nom, password_hash($m, PASSWORD_DEFAULT), $role]);
    echo "compte '$id' cree\n";
    return (int)$pdo->lastInsertId();
};
$a = json_decode((string)file_get_contents(__DIR__ . '/../src/amorce.json'), true);
if (!is_array($a)) exit("src/amorce.json illisible\n");
$creer('admin', 'Administrateur', 'admin');
$demo = $creer($a['compte']['identifiant'], $a['compte']['nom'], 'syndicat');

if (!$pdo->query("SELECT 1 FROM chartes WHERE compte_id = $demo")->fetch()) {
    foreach ($a['chartes'] as $i => $ch) {
        [$propre, $err] = valider_charte($ch['contenu']);
        if ($err) { echo "charte '" . $ch['nom'] . "' invalide : " . implode(' / ', $err) . "\n"; continue; }
        $pdo->prepare('INSERT INTO chartes (compte_id, nom, defaut, contenu) VALUES (?,?,?,?)')
            ->execute([$demo, $ch['nom'], $i === 0 ? 1 : 0, json_encode($propre, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        echo "charte '" . $ch['nom'] . "' creee\n";
    }
}
echo ajouter_banque_par_defaut($pdo, $demo) . " element(s) de banque ajoute(s)\n";

if ($creds) {
    $t = "Identifiants initiaux (a supprimer apres lecture) :\n";
    foreach ($creds as $i => $p) $t .= "  $i : $p\n";
    file_put_contents(__DIR__ . '/../data/identifiants_initiaux.txt', $t);
    echo "Mots de passe ecrits dans data/identifiants_initiaux.txt\n";
}

<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
// Exporte les chartes et la banque d'un compte dans src/amorce.json (pour amorcer une autre installation).
//   php tools/exporter_amorce.php [identifiant]      (defaut : demo)
if (PHP_SAPI !== 'cli') exit('CLI uniquement.');
require __DIR__ . '/../src/bootstrap.php';
$id = $argv[1] ?? 'demo';
$pdo = db();
$st = $pdo->prepare('SELECT id, identifiant, nom FROM comptes WHERE identifiant = ?'); $st->execute([$id]);
$c = $st->fetch() ?: exit("Compte introuvable\n"); $st->closeCursor();
$q = $pdo->prepare('SELECT nom, defaut, contenu FROM chartes WHERE compte_id = ? AND supprimee_le IS NULL ORDER BY defaut DESC, id'); $q->execute([$c['id']]);
$chartes = array_map(fn($r) => ['nom' => $r['nom'], 'defaut' => (int)$r['defaut'], 'contenu' => json_decode($r['contenu'], true)], $q->fetchAll());
$q->closeCursor();
if (!$chartes) exit("Aucune charte\n");
$els = [];
$q = $pdo->prepare('SELECT contenu FROM banque_elements WHERE compte_id = ? ORDER BY id'); $q->execute([$c['id']]);
foreach ($q->fetchAll() as $r) $els[] = json_decode($r['contenu'], true);
$a = ['compte' => ['identifiant' => $c['identifiant'], 'nom' => $c['nom']], 'charte' => ['nom' => $chartes[0]['nom'], 'contenu' => $chartes[0]['contenu']], 'chartes' => $chartes, 'elements' => $els];
file_put_contents(__DIR__ . '/../src/amorce.json', json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo "src/amorce.json : " . count($chartes) . " charte(s), " . count($els) . " element(s), " . round(filesize(__DIR__ . '/../src/amorce.json') / 1024) . " Ko\n";

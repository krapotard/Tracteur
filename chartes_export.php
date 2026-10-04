<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$u = exiger_connexion();
if ($u['role'] === 'admin') { http_response_code(403); exit; }
$st = db()->prepare('SELECT nom, defaut, contenu FROM chartes WHERE compte_id = ? AND supprimee_le IS NULL ORDER BY defaut DESC, id');
$st->execute([(int)$u['id']]);
$liste = [];
foreach ($st->fetchAll() as $r) {
    $c = json_decode($r['contenu'], true);
    if (is_array($c)) $liste[] = ['nom' => $r['nom'], 'defaut' => (int)$r['defaut'], 'contenu' => $c];
}
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="chartes_' . preg_replace('/[^a-z0-9-]/', '', $u['identifiant']) . '_' . date('Ymd') . '.json"');
header('Cache-Control: no-store');
echo json_encode(['format' => 'tracteur-chartes', 'version' => 1, 'compte' => $u['nom'], 'chartes' => $liste], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

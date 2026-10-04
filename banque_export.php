<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$u = exiger_connexion();
if ($u['role'] === 'admin') { http_response_code(403); exit; }
$b = banque_du_compte((int)$u['id'], 'Banque ' . $u['nom']);
header('Content-Type: text/javascript; charset=utf-8');
header('Content-Disposition: attachment; filename="banque.js"');
header('Cache-Control: no-store');
echo 'window.BANQUE = ' . json_encode($b, JSON_UNESCAPED_SLASHES) . ";\n";   // ASCII pur (é...) : lisible quel que soit l'encodage du serveur

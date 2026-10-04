<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/charte.php';
$u = exiger_connexion(true);
$c = charte_du_compte((int)$u['id'], isset($_GET['id']) ? (int)$_GET['id'] : null);
if (!$c) sortie_json(['erreur' => 'introuvable'], 404);
[$c] = valider_charte($c);
$c = adapter_pour_editeur($c);
sortie_json($c);

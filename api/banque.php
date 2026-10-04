<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
// Banque d'elements du compte connecte. Le compte vient de la session, jamais de la requete.
$u = exiger_connexion(true);
sortie_json(banque_du_compte((int)$u['id'], 'Banque ' . $u['nom']));

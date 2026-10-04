<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
$u = exiger_connexion();
header('Location: ' . ($u['role'] === 'admin' ? 'admin.php' : 'app.php'));

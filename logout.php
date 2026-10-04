<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
demarrer_session();
// Deconnexion par POST + jeton, pour qu'un lien externe ne puisse pas deconnecter l'utilisateur.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifier_csrf($_POST['csrf'] ?? null)) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}
header('Location: login.php');

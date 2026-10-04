<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);

const RACINE = __DIR__ . '/..';
const FICHIER_BASE = RACINE . '/data/tracteur.sqlite';

// ---------- Session ----------
function demarrer_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_name('tracteur_sid');
    session_start();
}

// ---------- Base de donnees ----------
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('sqlite:' . FICHIER_BASE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    schema($pdo);
    return $pdo;
}

function schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS comptes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        identifiant TEXT NOT NULL UNIQUE,
        nom TEXT NOT NULL,
        mdp_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'syndicat' CHECK (role IN ('admin','syndicat')),
        actif INTEGER NOT NULL DEFAULT 1,
        cree_le TEXT NOT NULL DEFAULT (datetime('now')),
        derniere_connexion TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS chartes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        compte_id INTEGER NOT NULL REFERENCES comptes(id) ON DELETE CASCADE,
        nom TEXT NOT NULL,
        defaut INTEGER NOT NULL DEFAULT 0,
        contenu TEXT NOT NULL,
        maj_le TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS banque_elements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        compte_id INTEGER NOT NULL REFERENCES comptes(id) ON DELETE CASCADE,
        elem_id TEXT NOT NULL,
        contenu TEXT NOT NULL,
        maj_le TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE (compte_id, elem_id)
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tentatives (
        cle TEXT NOT NULL,
        ts INTEGER NOT NULL
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tentatives ON tentatives (cle, ts)");
    // Migrations simples : colonnes ajoutees apres la premiere version
    $cols = array_column($pdo->query('PRAGMA table_info(comptes)')->fetchAll(), 'name');
    if (!in_array('mdp_a_changer', $cols, true)) $pdo->exec('ALTER TABLE comptes ADD COLUMN mdp_a_changer INTEGER NOT NULL DEFAULT 0');
    $cc = array_column($pdo->query('PRAGMA table_info(chartes)')->fetchAll(), 'name');
    if (!in_array('supprimee_le', $cc, true)) $pdo->exec('ALTER TABLE chartes ADD COLUMN supprimee_le TEXT');          // corbeille : NULL = charte active
    if (!in_array('protegee', $cc, true)) $pdo->exec('ALTER TABLE chartes ADD COLUMN protegee INTEGER NOT NULL DEFAULT 0');
    $pdo->exec("CREATE TABLE IF NOT EXISTS chartes_versions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        charte_id INTEGER NOT NULL REFERENCES chartes(id) ON DELETE CASCADE,
        nom TEXT NOT NULL,
        contenu TEXT NOT NULL,
        cree_le TEXT NOT NULL DEFAULT (datetime('now'))
    )");
}

// ---------- Authentification ----------
function utilisateur(): ?array {
    demarrer_session();
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return null;
    $st = db()->prepare('SELECT id, identifiant, nom, role, mdp_a_changer FROM comptes WHERE id = ? AND actif = 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function exiger_connexion(bool $json = false): array {
    $u = utilisateur();
    if ($u) {
        // Mot de passe provisoire : rien d'autre n'est accessible tant qu'il n'est pas change.
        if ($u['mdp_a_changer'] && !in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['motdepasse.php', 'logout.php'], true)) {
            if ($json) sortie_json(['erreur' => 'mot_de_passe_a_changer'], 403);
            header('Location: motdepasse.php');
            exit;
        }
        return $u;
    }
    if ($json) { sortie_json(['erreur' => 'non_connecte'], 401); }
    header('Location: login.php');
    exit;
}

function exiger_admin(bool $json = false): array {
    $u = exiger_connexion($json);
    if ($u['role'] !== 'admin') {
        if ($json) sortie_json(['erreur' => 'interdit'], 403);
        http_response_code(403);
        exit('Acces reserve a l\'administrateur.');
    }
    return $u;
}

// Limite les essais de connexion : 5 echecs par 15 minutes pour un meme identifiant + adresse.
function trop_d_essais(string $cle): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM tentatives WHERE cle = ? AND ts > ?');
    $st->execute([$cle, time() - 900]);
    return (int)$st->fetchColumn() >= 5;
}
function noter_echec(string $cle): void {
    $pdo = db();
    $pdo->prepare('DELETE FROM tentatives WHERE ts < ?')->execute([time() - 86400]);
    $pdo->prepare('INSERT INTO tentatives (cle, ts) VALUES (?, ?)')->execute([$cle, time()]);
}

function connecter(string $identifiant, string $mdp): ?string {
    $cle = strtolower($identifiant) . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (trop_d_essais($cle)) return 'Trop d\'essais. R&eacute;essayez dans 15 minutes.';
    $st = db()->prepare('SELECT id, mdp_hash FROM comptes WHERE identifiant = ? AND actif = 1');
    $st->execute([strtolower(trim($identifiant))]);
    $c = $st->fetch();
    // verification a temps constant meme si le compte n'existe pas
    static $faux = null;
    $faux ??= password_hash('inexistant', PASSWORD_DEFAULT);
    $ok = password_verify($mdp, $c['mdp_hash'] ?? $faux);
    if (!$c || !$ok) { noter_echec($cle); return 'Identifiant ou mot de passe incorrect.'; }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$c['id'];
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    db()->prepare("UPDATE comptes SET derniere_connexion = datetime('now') WHERE id = ?")->execute([$c['id']]);
    return null;
}

// ---------- CSRF ----------
function jeton_csrf(): string {
    demarrer_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function verifier_csrf(?string $jeton): bool {
    demarrer_session();
    return !empty($_SESSION['csrf']) && is_string($jeton) && hash_equals($_SESSION['csrf'], $jeton);
}

// ---------- Utilitaires ----------
function sortie_json($donnees, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($donnees, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    exit;
}
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ---------- Chartes et banque d'un compte ----------
// La charte ne peut jamais venir d'un autre compte : le filtre compte_id est impose ici.
function charte_du_compte(int $compteId, ?int $charteId = null): ?array {
    $pdo = db();
    if ($charteId) {
        $st = $pdo->prepare('SELECT id, nom, contenu FROM chartes WHERE id = ? AND compte_id = ? AND supprimee_le IS NULL');
        $st->execute([$charteId, $compteId]);
    } else {
        $st = $pdo->prepare('SELECT id, nom, contenu FROM chartes WHERE compte_id = ? AND supprimee_le IS NULL ORDER BY defaut DESC, id LIMIT 1');
        $st->execute([$compteId]);
    }
    $r = $st->fetch();
    if (!$r) return null;
    $c = json_decode($r['contenu'], true);
    if (!is_array($c)) return null;
    $c['id'] = (int)$r['id'];
    $c['compte'] = $compteId;
    return $c;
}

function banque_du_compte(int $compteId, string $nom): array {
    $st = db()->prepare('SELECT contenu FROM banque_elements WHERE compte_id = ? ORDER BY id');
    $st->execute([$compteId]);
    $els = [];
    foreach ($st->fetchAll() as $r) { $e = json_decode($r['contenu'], true); if (is_array($e)) $els[] = $e; }
    return ['nom' => $nom, 'version' => 1, 'elements' => $els];
}

// Icones, manifeste (installation comme application) et couleur de la barre du navigateur.
function liens_marque(): string {
    return '<link rel="icon" href="assets/logo/tracteur-app.svg" type="image/svg+xml">'
        . '<link rel="icon" href="assets/logo/favicon-32.png" type="image/png" sizes="32x32">'
        . '<link rel="icon" href="assets/logo/favicon-16.png" type="image/png" sizes="16x16">'
        . '<link rel="apple-touch-icon" href="assets/logo/apple-touch-icon.png">'
        . '<link rel="manifest" href="manifest.webmanifest">'
        . '<meta name="theme-color" content="#C00000">';
}

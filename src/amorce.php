<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
/** Cree un compte syndicat a partir d'une amorce (charte + banque) exportee depuis une autre installation. */
function importer_amorce(PDO $pdo, array $a, string $mdp): string {
    $id = strtolower($a['compte']['identifiant']);
    $st = $pdo->prepare('SELECT id FROM comptes WHERE identifiant = ?'); $st->execute([$id]);
    if ($st->fetch()) return "Le compte '$id' existe deja : rien importe.";
    $pdo->prepare('INSERT INTO comptes (identifiant, nom, mdp_hash, role) VALUES (?,?,?,?)')
        ->execute([$id, $a['compte']['nom'], password_hash($mdp, PASSWORD_DEFAULT), 'syndicat']);
    $cid = (int)$pdo->lastInsertId();
    $liste = $a['chartes'] ?? [['nom' => $a['charte']['nom'], 'defaut' => 1, 'contenu' => $a['charte']['contenu']]];
    foreach ($liste as $i => $ch)
        $pdo->prepare('INSERT INTO chartes (compte_id, nom, defaut, contenu) VALUES (?,?,?,?)')
            ->execute([$cid, $ch['nom'], $i === 0 ? 1 : 0, json_encode($ch['contenu'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    $n = 0;
    foreach ($a['elements'] as $e) {
        $s = $pdo->prepare('INSERT OR IGNORE INTO banque_elements (compte_id, elem_id, contenu) VALUES (?,?,?)');
        $s->execute([$cid, (string)$e['id'], json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $n += $s->rowCount();
    }
    return "Compte '$id' cree avec " . count($liste) . " charte(s) et $n element(s) de banque.";
}

/** Banque par defaut (src/amorce.json) : ajoutee a un compte sans jamais remplacer un element existant. Renvoie le nombre d'elements ajoutes. */
function ajouter_banque_par_defaut(PDO $pdo, int $cid): int {
    $a = json_decode((string)@file_get_contents(__DIR__ . '/amorce.json'), true);
    $n = 0;
    $s = $pdo->prepare('INSERT OR IGNORE INTO banque_elements (compte_id, elem_id, contenu) VALUES (?,?,?)');
    foreach ((array)($a['elements'] ?? []) as $e) {
        if (!is_array($e) || !isset($e['id'])) continue;
        $s->execute([$cid, (string)$e['id'], json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $n += $s->rowCount();
    }
    return $n;
}
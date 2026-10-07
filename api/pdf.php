<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/charte.php';
require __DIR__ . '/../src/pdf.php';
require __DIR__ . '/../src/pdfua.php';

$u = exiger_connexion(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') sortie_json(['erreur' => 'methode'], 405);
if (!verifier_csrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) sortie_json(['erreur' => 'jeton'], 403);

// Prechauffage : apres une longue inactivite, le premier lancement de Chrome peut durer pres d'une minute sur un hebergement mutualise
// (fichiers du navigateur hors du cache du serveur). L'editeur le declenche des son ouverture, pendant que l'on redige : l'export est alors rapide.
$marque = RACINE . '/data/tmp/prechauffage.txt';
if (isset($_GET['prechauffage'])) {
    ignore_user_abort(true); @set_time_limit(180);
    @mkdir(dirname($marque), 0700, true);
    if (is_file($marque) && time() - filemtime($marque) < 600) sortie_json(['ok' => true, 'deja' => true]);
    @touch($marque);                                                  // marque tout de suite : un seul prechauffage a la fois
    try { generer_pdf('<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Prechauffage</title></head><body><p>.</p></body></html>'); } catch (Throwable $e) { }
    @touch($marque);
    sortie_json(['ok' => true]);
}

$c = config_pdf();
$taille = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($taille <= 0 || $taille > $c['max_html'] + 100000) sortie_json(['erreur' => 'taille'], 413);
$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in) || !is_string($in['html'] ?? null) || strlen($in['html']) > $c['max_html']) sortie_json(['erreur' => 'requete'], 400);

// Les couleurs viennent de la charte du compte (serveur), pas de la requete.
$charte = charte_du_compte((int)$u['id'], isset($in['charte']) ? (int)$in['charte'] : null);
if (!$charte) sortie_json(['erreur' => 'charte'], 404);
[$charte] = valider_charte($charte);

$titre = mb_substr(trim((string)($in['titre'] ?? '')), 0, 200) ?: 'Tract';
$familles = array_values(array_filter((array)($in['familles'] ?? []), 'is_string'));

try {
    limiter_debit((int)$u['id'], $c['par_minute']);
    $doc = document_pdf($titre, nettoyer_html($in['html']), $charte['couleurs'], $familles);
    $pdf = generer_pdf($doc);
    // PDF/UA : corrections du fichier ; la mention PDF/UA-1 n'est inscrite que si les controles de l'editeur n'ont releve aucune erreur
    $pdf = pdf_ua_corriger($pdf, $titre, 'fr', $rapportUa, !empty($in['conforme']));
} catch (ErreurPdf $e) {
    sortie_json(['erreur' => $e->getMessage()], $e->statut);
}
@touch($marque);                                                     // Chrome est « chaud » : inutile de prechauffer avant un moment
header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
echo $pdf;

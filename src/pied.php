<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require_once __DIR__ . '/banque.php';   // assainir_svg()

/**
 * Bandeau "compose" (en-tete ou pied de page) : un SVG cree dans Inkscape (ou autre) dont on separe
 *  - le DECOR (formes, icones) : une image decorative ;
 *  - les TEXTES (position, police, taille, couleur) : reecrits en vrai texte, accessible, et cliquable pour l'adresse web et le mail.
 * Resultat : ['svg' => data URI du decor, 'w' => mm, 'h' => mm, 'cote' => gauche|droite, 'textes' => [[texte, x, y, taille, couleur, police, graisse, interlettrage, actif], ...]]
 */

const NS_SVG = 'http://www.w3.org/2000/svg';

const POLICES_SVG = [   // nom de famille (minuscules) -> identifiant de police de l'editeur
    'permanent marker' => 'permanent-marker', 'barlow semi condensed' => 'barlow-semi-condensed', 'barlow condensed' => 'barlow-condensed', 'barlow' => 'barlow',
    'atkinson hyperlegible' => 'atkinson', 'lexend' => 'lexend', 'open sans' => 'open-sans', 'roboto' => 'roboto', 'montserrat' => 'montserrat', 'oswald' => 'oswald',
    'lora' => 'lora', 'bebas neue' => 'bebas', 'fredericka the great' => 'fredericka', 'concert one' => 'concert-one', 'lobster two' => 'lobster-two', 'arial' => 'arial',
    'fira sans' => 'fira-sans', 'federo' => 'federo', 'caveat brush' => 'caveat-brush',
];
const POLICES_APPROCHEES = [   // police absente de l'editeur -> [equivalent proche, nom affiche]
    'lobster' => ['lobster-two', 'Lobster Two'], 'roboto condensed' => ['roboto', 'Roboto'],
];

function mat_mul(array $m, array $n): array {   // m x n (n applique d'abord)
    return [$m[0] * $n[0] + $m[2] * $n[1], $m[1] * $n[0] + $m[3] * $n[1], $m[0] * $n[2] + $m[2] * $n[3], $m[1] * $n[2] + $m[3] * $n[3], $m[0] * $n[4] + $m[2] * $n[5] + $m[4], $m[1] * $n[4] + $m[3] * $n[5] + $m[5]];
}
function lire_transform(string $t): array {
    $m = [1, 0, 0, 1, 0, 0];
    if (!preg_match_all('/(matrix|translate|scale|rotate)\s*\(([^)]*)\)/', $t, $all, PREG_SET_ORDER)) return $m;
    foreach ($all as [$_, $nom, $args]) {
        $v = array_map('floatval', preg_split('/[\s,]+/', trim($args)) ?: []);
        $n = match ($nom) {
            'matrix' => count($v) === 6 ? $v : [1, 0, 0, 1, 0, 0],
            'translate' => [1, 0, 0, 1, $v[0] ?? 0, $v[1] ?? 0],
            'scale' => [$v[0] ?? 1, 0, 0, $v[1] ?? ($v[0] ?? 1), 0, 0],
            'rotate' => (function ($v) { $a = deg2rad($v[0] ?? 0); $c = cos($a); $s = sin($a); $r = [$c, $s, -$s, $c, 0, 0]; if (count($v) === 3) $r = mat_mul(mat_mul([1, 0, 0, 1, $v[1], $v[2]], $r), [1, 0, 0, 1, -$v[1], -$v[2]]); return $r; })($v),
        };
        $m = mat_mul($m, $n);
    }
    return $m;
}
function lire_style(?string $s): array {
    $o = [];
    foreach (explode(';', (string)$s) as $d) { $p = strpos($d, ':'); if ($p !== false) $o[strtolower(trim(substr($d, 0, $p)))] = trim(substr($d, $p + 1)); }
    return $o;
}
function longueur_mm(string $v, float $defautUnite = 25.4 / 96): ?float {   // "201.79mm", "100px", "5cm", "2in", "12" (px)
    if (!preg_match('/^\s*([0-9]*\.?[0-9]+)\s*(mm|cm|in|px|pt)?\s*$/', $v, $m)) return null;
    $n = (float)$m[1];
    return match ($m[2] ?? '') { 'mm' => $n, 'cm' => $n * 10, 'in' => $n * 25.4, 'pt' => $n * 25.4 / 72, default => $n * $defautUnite };
}
function taille_px(string $v): ?float {
    if (!preg_match('/^\s*([0-9]*\.?[0-9]+)\s*(px|pt|mm)?\s*$/', $v, $m)) return null;
    $n = (float)$m[1];
    return match ($m[2] ?? '') { 'pt' => $n * 96 / 72, 'mm' => $n * 96 / 25.4, default => $n };
}
function couleur_hex(?string $c): ?string {
    $c = strtolower(trim((string)$c));
    if (preg_match('/^#([0-9a-f]{6})$/', $c, $m)) return '#' . $m[1];
    if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $c, $m)) return "#$m[1]$m[1]$m[2]$m[2]$m[3]$m[3]";
    return ['white' => '#ffffff', 'black' => '#000000'][$c] ?? null;
}

/** Segments de texte d'un element : [[texte, style herite et fusionne]], en descendant dans les tspan imbriques. */
function segments_texte(DOMElement $el, array $st): array {
    $st = array_merge($st, lire_style($el->getAttribute('style')));
    $out = [];
    foreach ($el->childNodes as $c) {
        if ($c instanceof DOMElement && strtolower($c->localName) === 'tspan') { foreach (segments_texte($c, $st) as $s) $out[] = $s; }
        elseif ($c instanceof DOMText && trim($c->nodeValue) !== '') $out[] = [trim((string)preg_replace('/\s+/u', ' ', $c->nodeValue)), $st];
    }
    return $out;
}

function extraire_pied_compose(string $svg, array &$err, array &$notes = [], string $coteDefaut = 'gauche', bool $titreAutorise = false): ?array {
    if (strlen($svg) > 600000) { $err[] = 'Fichier SVG trop lourd (600 Ko maximum).'; return null; }
    if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg)) { $err[] = 'Ce SVG contient une d&eacute;claration DOCTYPE/ENTITY, refus&eacute;e par s&eacute;curit&eacute;.'; return null; }
    $d = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $d->loadXML($svg, LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    $racine = $ok ? $d->documentElement : null;
    if (!$racine || strtolower($racine->localName) !== 'svg') { $err[] = 'Fichier SVG illisible.'; return null; }

    $wMm = longueur_mm($racine->getAttribute('width')); $hMm = longueur_mm($racine->getAttribute('height'));
    $vb = preg_split('/[\s,]+/', trim($racine->getAttribute('viewBox')));
    if (!$wMm || !$hMm || count($vb) !== 4 || (float)$vb[2] <= 0 || (float)$vb[3] <= 0) { $err[] = 'Le SVG doit avoir une largeur, une hauteur et un viewBox (cr&eacute;&eacute;s par Inkscape).'; return null; }
    if ($wMm < 50 || $wMm > 250 || $hMm < 5 || $hMm > 80) { $err[] = 'Dimensions inattendues : ' . round($wMm) . ' &times; ' . round($hMm) . ' mm (largeur 50 &agrave; 250 mm, hauteur 5 &agrave; 80 mm).'; return null; }
    $mm = $wMm / (float)$vb[2];                                   // millimetres par unite utilisateur
    $m0 = [1, 0, 0, 1, -(float)$vb[0], -(float)$vb[1]];

    $textes = []; $aSupprimer = [];
    $visiter = function (DOMElement $n, array $ctm) use (&$visiter, &$textes, &$aSupprimer, $mm, &$notes, &$err, $titreAutorise) {
        $t = strtolower($n->localName);
        if ($t === 'defs' || $t === 'clippath' || $t === 'mask') return;
        if ($n->hasAttribute('transform')) $ctm = mat_mul($ctm, lire_transform($n->getAttribute('transform')));
        if ($t === 'text') {
            $aSupprimer[] = $n;
            $stText = lire_style($n->getAttribute('style'));
            $lignes = [];
            foreach ($n->childNodes as $c) if ($c instanceof DOMElement && strtolower($c->localName) === 'tspan') $lignes[] = $c;
            foreach ($lignes ?: [$n] as $src) {
                $segs = $src === $n ? segments_texte($n, []) : segments_texte($src, $stText);
                if (!$segs) continue;
                $txt = trim(implode(' ', array_column($segs, 0)));
                $st = $segs[0][1];
                $x = (float)($src->getAttribute('x') !== '' ? $src->getAttribute('x') : $n->getAttribute('x'));
                $y = (float)($src->getAttribute('y') !== '' ? $src->getAttribute('y') : $n->getAttribute('y'));
                $px = mat_mul($ctm, [1, 0, 0, 1, $x, $y]);
                $echelle = sqrt($ctm[0] ** 2 + $ctm[1] ** 2) * $mm;
                $taille = taille_px($st['font-size'] ?? '16') ?? 16;
                $fam = strtolower(trim(explode(',', $st['font-family'] ?? 'Barlow')[0], " '\""));
                $id = POLICES_SVG[$fam] ?? null;
                if (!$id && isset(POLICES_APPROCHEES[$fam])) { [$id, $nomProche] = POLICES_APPROCHEES[$fam]; $notes[] = 'Police &laquo;&nbsp;' . h($fam) . '&nbsp;&raquo; absente de l\'&eacute;diteur : remplac&eacute;e par la plus proche, ' . $nomProche . '.'; }
                if (!$id) { $notes[] = "Police &laquo;&nbsp;" . h($fam) . "&nbsp;&raquo; indisponible : remplac&eacute;e par Barlow."; $id = 'barlow'; }
                $poids = $st['font-weight'] ?? '400'; $poids = $poids === 'bold' ? 700 : ($poids === 'normal' ? 400 : (int)$poids);
                $couleur = couleur_hex($st['fill'] ?? '#000000') ?? '#000000';
                $ls = isset($st['letter-spacing']) ? (taille_px($st['letter-spacing']) ?? 0) * $echelle : 0;
                $titre = (bool)preg_match('/^(\{titre\}|\[titre\]|\{\{titre\}\}|titre du tract)$/iu', $txt);
                if ($titre && !$titreAutorise) { $err[] = 'Le pied de page ne peut pas contenir l\'emplacement du titre ({titre}) : il se place dans l\'en-t&ecirc;te.'; $titre = false; }
                $extra = [];
                if ($titre) {
                    $anc = strtolower($st['text-anchor'] ?? $src->getAttribute('text-anchor') ?: $n->getAttribute('text-anchor') ?: 'start');
                    $lh = (float)($st['line-height'] ?? 1.15); if ($lh <= 0 || $lh > 3) $lh = 1.15;
                    $isz = isset($st['inline-size']) ? (float)$st['inline-size'] * $echelle : null;      // largeur d'une zone de texte Inkscape
                    $extra = ['role' => 'titre', 'ancre' => $anc === 'middle' ? 'milieu' : ($anc === 'end' ? 'fin' : 'debut'), 'interligne' => round($lh, 2), 'largeur' => $isz ? round($isz, 1) : null];
                }
                $textes[] = $extra + ['texte' => mb_substr($txt, 0, 120), 'x' => round($px[4] * $mm, 2), 'y' => round($px[5] * $mm, 2), 'taille' => round($taille * $echelle, 2),
                             'couleur' => $couleur, 'police' => $id, 'graisse' => max(100, min(900, $poids)), 'interlettrage' => round($ls, 3), 'actif' => true];
            }
            return;
        }
        foreach (iterator_to_array($n->childNodes) as $c) if ($c instanceof DOMElement) $visiter($c, $ctm);
    };
    $visiter($racine, $m0);
    foreach ($aSupprimer as $n) if ($n->parentNode) $n->parentNode->removeChild($n);

    // metadonnees d'editeur (Inkscape, Sodipodi...) : inutiles dans une image
    $xp = new DOMXPath($d);
    foreach (iterator_to_array($xp->query('//*[namespace-uri()!="' . NS_SVG . '"]')) as $e) if ($e->parentNode) $e->parentNode->removeChild($e);
    foreach (iterator_to_array($xp->query('//*')) as $e) foreach (iterator_to_array($e->attributes) as $a) if ($a->namespaceURI !== null && $a->namespaceURI !== NS_SVG && $a->namespaceURI !== 'http://www.w3.org/1999/xlink' && $a->namespaceURI !== 'http://www.w3.org/XML/1998/namespace' && $a->namespaceURI !== 'http://www.w3.org/2000/xmlns/') $e->removeAttributeNode($a);

    if (!$textes) { $err[] = 'Aucun texte trouv&eacute; dans ce SVG. Gardez les textes en <em>texte</em> (ne les convertissez pas en trac&eacute;s) : ils seront lus et r&eacute;&eacute;crits en vrai texte accessible.'; return null; }
    if (count($textes) > 16) { $err[] = 'Trop de textes (16 maximum).'; return null; }
    $e2 = []; $r = assainir_svg((string)$d->saveXML($d->documentElement), $e2);
    if (!$r) { foreach ($e2 as $m) $err[] = $m; return null; }
    return ['svg' => 'data:image/svg+xml;base64,' . base64_encode($r[0]), 'w' => round($wMm, 2), 'h' => round($hMm, 2), 'cote' => $coteDefaut, 'textes' => $textes];
}

/** Revalidation d'un bandeau compose deja enregistre (donnees de confiance limitee). */
function valider_pied_compose($p, array &$err, string $coteDefaut = 'gauche', bool $titreAutorise = false): ?array {
    if (!is_array($p) || !is_string($p['svg'] ?? null)) return null;
    $e2 = [];
    if (!preg_match('#^data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)$#', $p['svg'], $m) || !($r = assainir_svg((string)base64_decode($m[1], true), $e2))) { $err[] = 'Bandeau compos&eacute; invalide.'; return null; }
    $w = (float)($p['w'] ?? 0); $h = (float)($p['h'] ?? 0);
    if ($w < 50 || $w > 250 || $h < 5 || $h > 80) { $err[] = 'Dimensions du bandeau compos&eacute; invalides.'; return null; }
    $textes = [];
    foreach (array_slice((array)($p['textes'] ?? []), 0, 16) as $t) {
        if (!is_array($t) || trim((string)($t['texte'] ?? '')) === '') continue;
        $police = isset(POLICES_DISPO[(string)($t['police'] ?? '')]) ? $t['police'] : 'barlow';
        $taille = (float)($t['taille'] ?? 0); $actif = !array_key_exists('actif', $t) || !empty($t['actif']);
        $estTitre = ($t['role'] ?? '') === 'titre' && !array_filter($textes, fn($x) => isset($x['role']));
        if ($estTitre && $taille < 4.2) { $err[] = 'Le titre est trop petit dans l\'en-t&ecirc;te (' . round($taille / 25.4 * 72, 1) . ' pt, minimum 12 pt).'; continue; }
        if (!$estTitre && $actif && $taille < 2.8) { $err[] = 'Texte trop petit : &laquo;&nbsp;' . h(mb_substr((string)$t['texte'], 0, 40)) . '&nbsp;&raquo; (' . round($taille / 25.4 * 72, 1) . ' pt, minimum 8 pt).'; continue; }
        $textes[] = ['texte' => mb_substr(trim((string)$t['texte']), 0, 120), 'x' => round((float)($t['x'] ?? 0), 2), 'y' => round((float)($t['y'] ?? 0), 2), 'taille' => round(min($taille, 40), 2),
                     'couleur' => couleur_hex((string)($t['couleur'] ?? '')) ?? '#000000', 'police' => $police, 'graisse' => max(100, min(900, (int)($t['graisse'] ?? 400))), 'interlettrage' => round((float)($t['interlettrage'] ?? 0), 3), 'actif' => $actif]
                    + ($estTitre ? ['role' => 'titre', 'ancre' => in_array($t['ancre'] ?? '', ['debut', 'milieu', 'fin'], true) ? $t['ancre'] : 'debut', 'interligne' => max(0.9, min(2.0, round((float)($t['interligne'] ?? 1.15), 2))), 'largeur' => !empty($t['largeur']) ? max(20, min(200, round((float)$t['largeur'], 1))) : null] : []);
    }
    if (!array_filter($textes, fn($t) => $t['actif'])) { $err[] = 'Le bandeau compos&eacute; ne contient aucun texte affich&eacute;.'; return null; }
    $cote = in_array($p['cote'] ?? '', ['gauche', 'droite'], true) ? $p['cote'] : $coteDefaut;
    return ['svg' => 'data:image/svg+xml;base64,' . base64_encode($r[0]), 'w' => round($w, 2), 'h' => round($h, 2), 'cote' => $cote, 'textes' => $textes];
}

<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/vue.php';
$u = exiger_connexion();

/** Convertisseur minimal du Markdown du manuel (titres, listes, gras, italique, code, liens, citations) en HTML. */
function md_en_html(string $md): array {
    $en = function (string $t): string {
        $t = h($t);
        $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t);
        $t = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $t);
        $t = preg_replace('/(?<![\w*])\*([^*\s][^*]*?)\*(?![\w*])/', '<em>$1</em>', $t);
        return (string)preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', fn($m) => '<a href="' . $m[2] . '">' . $m[1] . '</a>', $t);
    };
    $out = ''; $toc = []; $liste = null; $para = []; $cit = [];
    $ferme = function () use (&$out, &$liste, &$para, &$cit, $en) {
        if ($para) { $out .= '<p>' . $en(implode(' ', $para)) . '</p>'; $para = []; }
        if ($cit) { $out .= '<p class="note">' . $en(implode(' ', $cit)) . '</p>'; $cit = []; }
        if ($liste) { $out .= '</' . $liste . '>'; $liste = null; }
    };
    foreach (preg_split('/\R/', $md) as $l) {
        if (preg_match('/^(#{1,3})\s+(.*)$/', $l, $m)) {
            $ferme(); $n = strlen($m[1]);
            if ($n === 1) continue;                                          // le titre de la page est deja affiche
            $id = trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower((string)iconv('UTF-8', 'ASCII//TRANSLIT', $m[2]))), '-');
            if ($n === 2) $toc[] = [$id, $m[2]];
            $out .= '<h' . $n . ' id="' . $id . '">' . $en($m[2]) . '</h' . $n . '>'; continue;
        }
        if (preg_match('/^\s*[-*]\s+(.*)$/', $l, $m)) { if ($liste !== 'ul') { $ferme(); $out .= '<ul>'; $liste = 'ul'; } $out .= '<li>' . $en($m[1]) . '</li>'; continue; }
        if (preg_match('/^\s*\d+\.\s+(.*)$/', $l, $m)) { if ($liste !== 'ol') { $ferme(); $out .= '<ol>'; $liste = 'ol'; } $out .= '<li>' . $en($m[1]) . '</li>'; continue; }
        if (preg_match('/^>\s?(.*)$/', $l, $m)) { if ($liste || $para) $ferme(); $cit[] = $m[1]; continue; }
        if (trim($l) === '') { $ferme(); continue; }
        if ($liste) $ferme();
        $para[] = trim($l);
    }
    $ferme();
    return [$out, $toc];
}

[$corps, $toc] = md_en_html((string)file_get_contents(__DIR__ . '/docs/MANUEL.md'));
page_debut('Aide', $u, 'aide');
echo '<nav aria-labelledby="sommaire"><h2 id="sommaire" style="font-size:18px">Sommaire</h2><ul>';
foreach ($toc as [$id, $t]) echo '<li><a href="#' . $id . '">' . h($t) . '</a></li>';
echo '</ul></nav><div class="manuel">' . $corps . '</div>';
page_fin();

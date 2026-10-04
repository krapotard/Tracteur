<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);

/**
 * Installe chrome-headless-shell (officiel, Chrome for Testing) et les bibliotheques systeme
 * manquantes (extraites de paquets AlmaLinux 8, sans installation systeme) dans data/chrome.
 * Teste sur PlanetHoster (CloudLinux 8).
 */

function _telecharger(string $url, ?string $dest = null): string|bool|null {
    $fp = $dest ? fopen($dest, 'wb') : null;
    $ch = curl_init($url);
    $opts = [CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 20];
    $opts += $fp ? [CURLOPT_FILE => $fp] : [CURLOPT_RETURNTRANSFER => true];
    curl_setopt_array($ch, $opts);
    $r = curl_exec($ch);
    $ok = $r !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    if ($fp) { fclose($fp); return $ok; }
    return $ok ? $r : null;
}
function _sh(string $c, ?int &$code = null): string {
    $out = []; $code = 0;
    @exec('sh -c ' . escapeshellarg($c) . ' 2>&1', $out, $code);
    return trim(implode("\n", $out));
}

function installer_chrome(callable $log): bool {
    if (DIRECTORY_SEPARATOR !== '/') { $log('Installation automatique prevue pour Linux uniquement (ici, un navigateur local est utilise).'); return true; }
    set_time_limit(340);
    $base = RACINE . '/data/chrome';
    $bin = $base . '/chrome-headless-shell-linux64/chrome-headless-shell';
    $libs = $base . '/libs'; $rpms = $base . '/rpms';
    @mkdir($libs, 0755, true); @mkdir($rpms, 0755, true);

    if (!is_file($bin)) {
        $log('Recherche de la derniere version stable de Chrome (Chrome for Testing)...');
        $j = _telecharger('https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions-with-downloads.json');
        $d = $j ? (json_decode($j, true)['channels']['Stable'] ?? null) : null;
        $url = null;
        foreach (($d['downloads']['chrome-headless-shell'] ?? []) as $x) if ($x['platform'] === 'linux64') $url = $x['url'];
        if (!$url) { $log('ECHEC : impossible de joindre le site de telechargement de Chrome.'); return false; }
        $log("Telechargement de Chrome {$d['version']} (environ 115 Mo)...");
        $zip = $base . '/chs.zip';
        if (_telecharger($url, $zip) !== true) { $log('ECHEC du telechargement.'); @unlink($zip); return false; }
        $z = new ZipArchive();
        if ($z->open($zip) !== true) { $log('ECHEC : archive illisible.'); return false; }
        $z->extractTo($base); $z->close(); @unlink($zip);
        @chmod($bin, 0755);
    }
    $log('Chrome present : ' . (is_file($bin) ? 'oui' : 'NON'));
    if (!is_file($bin)) return false;

    $manquantes = _sh('ldd ' . escapeshellarg($bin) . ' | grep -i "not found"');
    if ($manquantes === '') $log('Aucune bibliotheque manquante.');
    if ($manquantes !== '' || !_dernier_ok($libs)) {
        $log('Bibliotheques manquantes, recuperation dans les paquets AlmaLinux 8...');
        $noms = ['alsa-lib', 'at-spi2-atk', 'at-spi2-core', 'mesa-libgbm', 'libdrm', 'libwayland-server', 'mesa-libglapi', 'libxshmfence'];
        $depots = ['https://repo.almalinux.org/almalinux/8/BaseOS/x86_64/os/Packages/', 'https://repo.almalinux.org/almalinux/8/AppStream/x86_64/os/Packages/'];
        $choisis = [];
        foreach ($depots as $dep) {
            $idx = _telecharger($dep);
            if (!$idx) continue;
            foreach ($noms as $n) {
                if (!preg_match_all('/href="(' . preg_quote($n, '/') . '-\d[^"]*\.x86_64\.rpm)"/', $idx, $m)) continue;
                $f = $m[1]; usort($f, 'version_compare'); $choisis[$n] = [$dep, end($f)];
            }
        }
        $fichiers = [];
        foreach ($choisis as [$dep, $f]) {
            $dest = $rpms . '/' . $f;
            if (!is_file($dest) && _telecharger($dep . $f, $dest) !== true) { $log("ECHEC : $f"); @unlink($dest); continue; }
            $fichiers[] = escapeshellarg($dest);
        }
        $log(_sh('python3 ' . escapeshellarg(RACINE . '/src/extraire_rpm.py') . ' ' . escapeshellarg($libs) . ' ' . implode(' ', $fichiers)));
        foreach (glob($rpms . '/*.rpm') ?: [] as $r) @unlink($r);
        @rmdir($rpms);
        $reste = _sh('LD_LIBRARY_PATH=' . escapeshellarg($libs) . ' ldd ' . escapeshellarg($bin) . ' | grep -i "not found"');
        if ($reste !== '') { $log("ECHEC : dependances encore manquantes :\n$reste"); return false; }
        file_put_contents($libs . '/.ok', date('c'));
    }
    $c = 0;
    $v = _sh('LD_LIBRARY_PATH=' . escapeshellarg($libs) . ' ' . escapeshellarg($bin) . ' --version', $c);
    $log("Chrome : $v");
    return $c === 0;
}
function _dernier_ok(string $libs): bool { return is_file($libs . '/.ok'); }

/** Produit un PDF d'essai et renvoie un compte rendu. */
function tester_pdf(): array {
    $t = microtime(true);
    $frag = '<div class="tract t-greve al-gauche barre-on"><header class="tr-head"><div><p class="org">TEST</p></div></header><main class="tr-main"><div class="tr-title"><h1>Titre d\'essai</h1></div><div class="tr-corps"><div class="bl"><h2>Intertitre</h2></div><div class="bl"><p>Paragraphe avec accents : &eacute;&agrave;&ccedil; &laquo;&nbsp;test&nbsp;&raquo;.</p><ul><li>un</li><li>deux</li></ul></div></div></main></div>';
    $doc = document_pdf('Essai', $frag, ['rouge' => '#C00000', 'jaune' => '#FCC953', 'texte' => '#1a1a1a', 'lien' => '#0b4f9c'], ['Barlow']);
    $pdf = generer_pdf($doc);
    return ['octets' => strlen($pdf), 'balise' => str_contains($pdf, 'StructTreeRoot'), 'secondes' => round(microtime(true) - $t, 1)];
}

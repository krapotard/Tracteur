<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
// Cree le zip a televerser sur le serveur : tout le projet SAUF la base, les identifiants, les journaux et les outils de developpement.
//   php tools/empaqueter.php [fichier_zip]
if (PHP_SAPI !== 'cli') exit('CLI uniquement.');
$racine = realpath(__DIR__ . '/..');
$zipPath = $argv[1] ?? $racine . '/dist/tracteur_deploiement.zip';
@mkdir(dirname($zipPath), 0755, true);
@unlink($zipPath);
$z = new ZipArchive();
$z->open($zipPath, ZipArchive::CREATE) or exit("zip impossible\n");
$exclus = ['#^data/(?!\.htaccess$)#', '#^tools/#', '#^_ref/#', '#^dist/#', '#^tests/#', '#^\\.git#', '#^docs/captures/#', '#^sonde#', '#\.sqlite$#'];
$n = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine, FilesystemIterator::SKIP_DOTS)) as $f) {
    $rel = str_replace(chr(92), '/',substr($f->getPathname(), strlen($racine) + 1));
    foreach ($exclus as $re) if (preg_match($re, $rel)) continue 2;
    $z->addFile($f->getPathname(), $rel); $n++;
}
$z->addEmptyDir('data');
$z->close();
echo "$n fichiers -> $zipPath (" . round(filesize($zipPath) / 1048576, 2) . " Mo)\n";

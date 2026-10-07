<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);

/**
 * Moteur PDF : HTML du tract -> PDF balise, via Chrome/Edge headless.
 * Le HTML vient du navigateur de l'utilisateur : il est donc reduit a une liste blanche de balises
 * et d'attributs, sans script ni ressource externe, avant d'etre confie a Chrome.
 */

class ErreurPdf extends RuntimeException {
    public function __construct(string $message, public int $statut = 500) { parent::__construct($message); }
}

function config_pdf(): array {
    static $c = null;
    if ($c) return $c;
    $c = [
        'chrome'  => null,            // chemin du binaire ; detecte si absent
        'libs'    => null,            // dossier de bibliotheques supplementaires (LD_LIBRARY_PATH)
        'tmp'     => RACINE . '/data/tmp',
        'timeout' => 90,              // secondes
        'max_html' => 6000000,        // octets
        'simultanes' => 2,
        'par_minute' => 8,            // PDF par compte et par minute
        'options_chrome' => [],       // options supplementaires de Chrome (voir la page Moteur PDF de l'administration)
    ];
    $local = RACINE . '/data/config.local.php';
    if (is_file($local)) $c = array_replace($c, (array)(require $local));
    if (!$c['chrome']) {
        $candidats = [
            RACINE . '/data/chrome/chrome-headless-shell-linux64/chrome-headless-shell',
            'C:/Program Files/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
            'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
            'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
            '/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome',
        ];
        foreach ($candidats as $p) if (is_file($p)) { $c['chrome'] = $p; break; }
    }
    if (!$c['libs'] && is_dir(RACINE . '/data/chrome/libs')) $c['libs'] = RACINE . '/data/chrome/libs';
    return $c;
}

// ---------- Nettoyage du HTML (liste blanche) ----------
const BALISES_OK = ['div','span','p','br','a','img','figure','figcaption','h1','h2','h3','h4','h5','h6','ul','ol','li','strong','em','b','i','u','small','sup','sub',
    'header','main','footer','section','article','aside','nav','blockquote','table','thead','tbody','tfoot','tr','td','th','caption','hr'];
const ATTRS_OK = ['class','id','lang','title','alt','role','width','height','colspan','rowspan','scope','dir'];

function nettoyer_noeud(DOMNode $n): void {
    for ($enf = iterator_to_array($n->childNodes), $i = 0; $i < count($enf); $i++) {
        $e = $enf[$i];
        if ($e instanceof DOMElement) {
            $t = strtolower($e->tagName);
            if (!in_array($t, BALISES_OK, true)) { $n->removeChild($e); continue; }
            foreach (iterator_to_array($e->attributes) as $a) {
                $nom = strtolower($a->name); $v = $a->value; $ok = false;
                if (in_array($nom, ATTRS_OK, true) || str_starts_with($nom, 'aria-') || $nom === 'data-b' || $nom === 'data-rot') $ok = true;
                elseif ($nom === 'href' && $t === 'a') $ok = (bool)preg_match('#^(https?://|mailto:)#i', trim($v));
                elseif ($nom === 'src' && $t === 'img') $ok = (bool)preg_match('#^data:image/(png|jpeg|gif|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$#', $v);
                elseif ($nom === 'style') $ok = !preg_match('/url\s*\(|@import|expression|behavior|javascript:|<|\\\\/i', $v);
                if (!$ok) $e->removeAttribute($a->name);
            }
            nettoyer_noeud($e);
        } elseif (!($e instanceof DOMText)) {
            $n->removeChild($e);        // commentaires, instructions de traitement, etc.
        }
    }
}

function nettoyer_html(string $fragment): string {
    $d = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $d->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $fragment . '</body></html>', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    $body = $d->getElementsByTagName('body')->item(0);
    if (!$body) throw new ErreurPdf('HTML illisible.', 400);
    nettoyer_noeud($body);
    $out = '';
    foreach ($body->childNodes as $e) $out .= $d->saveHTML($e);
    return $out;
}

// ---------- Document complet ----------
function document_pdf(string $titre, string $fragment, array $couleurs, array $familles): string {
    $css = (string)file_get_contents(RACINE . '/assets/editeur.css');
    $a = strpos($css, '/* ---------- Tract (apercu et impression) ---------- */');
    $b = strpos($css, '#printRoot{display:none}');
    if ($a === false || $b === false) throw new ErreurPdf('Feuille de style introuvable.');
    $cssTract = substr($css, $a, $b - $a);

    $faces = [];
    foreach (explode("\n", (string)file_get_contents(RACINE . '/assets/polices.css')) as $l) {
        if (!preg_match("/^@font-face\\{font-family:'([^']+)'/", $l, $m)) continue;
        if (in_array($m[1], $familles, true)) $faces[] = $l;
    }
    $vars = ':root{--rouge:' . $couleurs['rouge'] . ';--jaune:' . $couleurs['jaune'] . ';--texte:' . $couleurs['texte'] . ';--lien:' . $couleurs['lien'] . '}';
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>' . htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') . '</title>'
        . '<style>' . implode("\n", $faces) . '</style>'
        . "<style>\nhtml,body{margin:0;padding:0;background:#fff}\n" . $vars . "\n" . $cssTract . "\n@page{size:A4;margin:0}\n.tract{-webkit-print-color-adjust:exact;print-color-adjust:exact}\n</style></head><body>"
        . $fragment . '</body></html>';
}

/** Une ligne par PDF dans data/logs/pdf_temps.log : taille du HTML, temps jusqu'au PDF, temps total, arret anticipe de Chrome (diagnostic de lenteur). */
function journal_temps(int $octetsHtml, ?float $apparu, float $total, bool $coupe): void {
    $f = RACINE . '/data/logs/pdf_temps.log';
    @mkdir(dirname($f), 0700, true);
    if (is_file($f) && filesize($f) > 200000) @unlink($f);
    @file_put_contents($f, sprintf("%s html=%dKo pdf_apres=%s total=%.1fs arret_anticipe=%s\n", date('c'), $octetsHtml / 1024, $apparu === null ? '-' : sprintf('%.1fs', $apparu), $total, $coupe ? 'oui' : 'non'), FILE_APPEND | LOCK_EX);
}

// ---------- Limites : simultanes et debit ----------
function prendre_place(array $c): mixed {
    @mkdir($c['tmp'], 0700, true);
    $debut = time();
    do {
        for ($i = 1; $i <= $c['simultanes']; $i++) {
            $f = fopen($c['tmp'] . "/slot$i.lock", 'c');
            if ($f && flock($f, LOCK_EX | LOCK_NB)) return $f;
            if ($f) fclose($f);
        }
        usleep(250000);
    } while (time() - $debut < 25);
    throw new ErreurPdf('Le serveur est occupe, reessayez dans un instant.', 429);
}

function limiter_debit(int $compteId, int $max): void {
    $pdo = db(); $cle = 'pdf|' . $compteId;
    $st = $pdo->prepare('SELECT COUNT(*) FROM tentatives WHERE cle = ? AND ts > ?');
    $st->execute([$cle, time() - 60]);
    if ((int)$st->fetchColumn() >= $max) throw new ErreurPdf('Trop de PDF demandes, patientez une minute.', 429);
    $pdo->prepare('DELETE FROM tentatives WHERE cle = ? AND ts < ?')->execute([$cle, time() - 3600]);
    $pdo->prepare('INSERT INTO tentatives (cle, ts) VALUES (?, ?)')->execute([$cle, time()]);
}

function supprimer_dossier(string $d): void {
    if (!is_dir($d)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f)
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    @rmdir($d);
}

// ---------- Generation ----------
/** Le fichier est-il un PDF termine (marque de fin presente) ? */
function pdf_complet(string $fichier): bool {
    $t = @filesize($fichier);
    if (!$t || $t < 200) return false;
    $f = @fopen($fichier, 'rb'); if (!$f) return false;
    fseek($f, max(0, $t - 64)); $fin = (string)fread($f, 64); fclose($f);
    return strpos($fin, '%%EOF') !== false;
}

/** Arrete Chrome et ses processus fils sans attendre sa fermeture « propre » (plusieurs secondes) une fois le PDF ecrit. */
function arreter_chrome($processus, int $pid, string $job): void {
    $exec = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);
    if ($exec) {
        $sortie = [];
        if (DIRECTORY_SEPARATOR === '\\') @exec('taskkill /F /T /PID ' . $pid . ' >NUL 2>&1', $sortie);
        else @exec('pkill -9 -f ' . escapeshellarg('[u]ser-data-dir=' . preg_quote($job, '/')) . ' >/dev/null 2>&1', $sortie);      // le crochet evite que pkill se reconnaisse lui-meme
    }
    @proc_terminate($processus, 9);
}

/**
 * $extra : options de Chrome ajoutees pour cet appel (essais de vitesse) ; $balise : false pour un PDF non balise (comparaison seulement) ;
 * $mesures recoit le temps jusqu'au PDF et le temps total.
 */
function generer_pdf(string $htmlComplet, array $extra = [], bool $balise = true, ?array &$mesures = null): string {
    $c = config_pdf();
    if (!$c['chrome']) throw new ErreurPdf('Aucun navigateur n\'est configure pour produire le PDF.', 503);
    $verrou = prendre_place($c);
    foreach (glob($c['tmp'] . '/job_*', GLOB_ONLYDIR) ?: [] as $vieux) if (filemtime($vieux) < time() - 3600) supprimer_dossier($vieux);   // menage des dossiers de travail oublies
    $job = $c['tmp'] . '/job_' . bin2hex(random_bytes(6));
    @mkdir($job, 0700, true);
    try {
        $entree = $job . '/tract.html';
        $sortie = $job . '/tract.pdf';
        file_put_contents($entree, $htmlComplet);

        $headlessShell = str_contains(basename($c['chrome']), 'headless-shell');
        $args = [$c['chrome']];
        if (!$headlessShell) $args[] = '--headless=new';
        if ($balise) array_push($args, '--export-tagged-pdf', '--generate-pdf-document-outline');
        array_push($args, '--disable-gpu', '--disable-dev-shm-usage', '--no-pdf-header-footer',
            '--disable-extensions', '--disable-background-networking', '--disable-sync', '--no-first-run', '--mute-audio',
            '--user-data-dir=' . $job . '/profil', '--print-to-pdf=' . $sortie);
        if (DIRECTORY_SEPARATOR === '/') $args[] = '--no-sandbox';
        foreach (array_merge((array)($c['options_chrome'] ?? []), $extra) as $o) if (is_string($o) && preg_match('/^--[a-z0-9-]+(=[A-Za-z0-9,._:-]*)?$/i', $o)) $args[] = $o;      // options verifiees : jamais de saisie libre
        $args[] = 'file://' . (DIRECTORY_SEPARATOR === '\\' ? '/' : '') . str_replace('\\', '/', $entree);

        $env = getenv() ?: [];
        $env['HOME'] = $env['XDG_CACHE_HOME'] = $env['XDG_CONFIG_HOME'] = $job . '/home';
        @mkdir($job . '/home', 0700, true);
        if ($c['libs']) $env['LD_LIBRARY_PATH'] = $c['libs'] . (empty($env['LD_LIBRARY_PATH']) ? '' : ':' . $env['LD_LIBRARY_PATH']);

        $nul = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $journal = $job . '/chrome.log';
        $p = proc_open($args, [0 => ['file', $nul, 'r'], 1 => ['file', $nul, 'w'], 2 => ['file', $journal, 'w']], $pipes, $job, $env);
        if (!is_resource($p)) throw new ErreurPdf('Impossible de lancer le navigateur.');
        $debut = microtime(true); $taille = -1; $stable = 0; $apparu = null; $coupe = false;
        while (true) {
            $s = proc_get_status($p);
            if (!$s['running']) break;
            $ecoule = microtime(true) - $debut;
            if ($ecoule > $c['timeout']) { arreter_chrome($p, (int)$s['pid'], $job); proc_close($p); throw new ErreurPdf('Delai depasse pour la generation du PDF.', 504); }
            // Le PDF est ecrit bien avant que Chrome ne se ferme : des qu'il est complet et ne bouge plus, on n'attend pas la fermeture.
            clearstatcache(true, $sortie);
            if (is_file($sortie)) {
                $apparu ??= $ecoule; $t = (int)filesize($sortie);
                if ($t > 200 && $t === $taille) { if (++$stable >= 3 && pdf_complet($sortie)) { $coupe = true; arreter_chrome($p, (int)$s['pid'], $job); break; } }
                else { $stable = 0; $taille = $t; }
            }
            usleep(60000);
        }
        proc_close($p);
        $total = microtime(true) - $debut;
        $mesures = ['apparu' => $apparu, 'total' => $total, 'coupe' => $coupe];
        journal_temps(strlen($htmlComplet), $apparu, $total, $coupe);

        if (!is_file($sortie) || filesize($sortie) < 200) {
            @mkdir(RACINE . '/data/logs', 0700, true);
            file_put_contents(RACINE . '/data/logs/pdf.log', date('c') . ' echec : ' . substr((string)@file_get_contents($journal), -800) . "\n", FILE_APPEND);
            throw new ErreurPdf('Le navigateur n\'a pas produit de PDF.');
        }
        return (string)file_get_contents($sortie);
    } finally {
        supprimer_dossier($job);
        if (is_dir($job)) { usleep(500000); supprimer_dossier($job); }      // Chrome arrete de force peut encore tenir un fichier quelques instants
        flock($verrou, LOCK_UN); fclose($verrou);
    }
}

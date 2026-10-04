<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);

const TAILLES_BANQUE = [
    'huitieme' => 'Minuscule (1/8)', 'sixieme' => 'Tr&egrave;s petite (1/6)', 'petite' => 'Petite (1/4)',
    'moyenne' => 'Moyenne (1/2)', 'grande' => 'Grande (3/4)', 'pleine' => 'Pleine largeur',
];
const MAX_ELEMENTS = 800;

// ---------- SVG : liste noire stricte, aucun script, aucune ressource externe ----------
function assainir_svg(string $svg, array &$err): ?array {
    if (strlen($svg) > 400000) { $err[] = 'Fichier SVG trop lourd (400 Ko maximum).'; return null; }
    if (preg_match('/<!(DOCTYPE|ENTITY)/i', $svg)) { $err[] = 'Ce SVG contient une d&eacute;claration DOCTYPE/ENTITY, refus&eacute;e par s&eacute;curit&eacute;.'; return null; }
    $d = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $d->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
    libxml_clear_errors(); libxml_use_internal_errors($prev);
    $racine = $ok ? $d->documentElement : null;
    if (!$racine || strtolower($racine->localName) !== 'svg') { $err[] = 'Fichier SVG illisible ou invalide.'; return null; }

    $interdits = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'audio', 'video', 'animate', 'animatemotion', 'animatetransform', 'set', 'handler', 'listener', 'canvas', 'applet'];
    $dataImg = '#^data:image/(png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$#';
    $attributs = function (DOMElement $e) use ($dataImg): void {
        $t = strtolower($e->localName);
        foreach (iterator_to_array($e->attributes) as $a) {
            $nom = strtolower($a->nodeName); $v = trim($a->value);
            if (str_starts_with($nom, 'on')) { $e->removeAttributeNode($a); continue; }
            if ($nom === 'href' || $nom === 'xlink:href') {
                if (!(str_starts_with($v, '#') || preg_match($dataImg, $v)) || ($t === 'image' && !preg_match($dataImg, $v))) $e->removeAttributeNode($a);
                continue;
            }
            if (preg_match('/javascript:|expression\s*\(|@import/i', $v) || preg_match('/url\s*\(\s*[\'"]?(?!#)/i', $v)) $e->removeAttributeNode($a);
        }
    };
    $nettoyer = function (DOMNode $n) use (&$nettoyer, $interdits, $attributs): void {
        foreach (iterator_to_array($n->childNodes) as $e) {
            if (!($e instanceof DOMElement)) { if (!($e instanceof DOMText) && !($e instanceof DOMCdataSection)) $n->removeChild($e); continue; }
            $t = strtolower($e->localName);
            if (in_array($t, $interdits, true)) { $n->removeChild($e); continue; }
            if ($t === 'style' && preg_match('/@import|url\s*\(\s*[\'"]?(?!#)|expression/i', $e->textContent)) { $n->removeChild($e); continue; }
            $attributs($e);
            $nettoyer($e);
        }
    };
    $attributs($racine);      // l'element racine porte aussi des attributs (onload...)
    $nettoyer($racine);

    $num = fn(?string $v) => ($v !== null && preg_match('/^\s*([0-9]*\.?[0-9]+)\s*(px)?\s*$/', $v, $m)) ? (float)$m[1] : null;
    $w = $num($racine->getAttribute('width')); $h = $num($racine->getAttribute('height'));
    if (!$w || !$h) {
        $vb = preg_split('/[\s,]+/', trim($racine->getAttribute('viewBox')));
        if (count($vb) === 4 && (float)$vb[2] > 0 && (float)$vb[3] > 0) { $w = (float)$vb[2]; $h = (float)$vb[3]; }
    }
    if (!$w || !$h) { $err[] = 'Ce SVG n\'a ni dimensions (width/height) ni viewBox : impossible de le dimensionner.'; return null; }
    if (!$racine->hasAttribute('viewBox')) $racine->setAttribute('viewBox', '0 0 ' . $w . ' ' . $h);
    $racine->setAttribute('width', (string)round($w)); $racine->setAttribute('height', (string)round($h));
    if (!$racine->hasAttribute('xmlns')) $racine->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    return [(string)$d->saveXML($racine), (int)round($w), (int)round($h)];
}

// ---------- Fichier telecharge -> [src (data URI), w, h] ----------
function traiter_fichier_element(string $tmp, array &$err): ?array {
    if (!is_file($tmp)) { $err[] = 'Fichier introuvable.'; return null; }
    if (filesize($tmp) > 3000000) { $err[] = 'Fichier trop lourd (3 Mo maximum).'; return null; }
    $brut = (string)file_get_contents($tmp);
    if (!@getimagesizefromstring($brut) && preg_match('/<svg[\s>]/i', substr($brut, 0, 8192))) {
        $r = assainir_svg($brut, $err);
        return $r ? ['src' => 'data:image/svg+xml;base64,' . base64_encode($r[0]), 'w' => $r[1], 'h' => $r[2]] : null;
    }
    $info = @getimagesizefromstring($brut);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) { $err[] = 'Format non accept&eacute; : SVG, PNG ou JPEG uniquement.'; return null; }
    $im = @imagecreatefromstring($brut);
    if (!$im) { $err[] = 'Image illisible.'; return null; }
    [$w, $h] = [imagesx($im), imagesy($im)];
    $nw = min(1200, $w); $nh = max(1, (int)round($h * $nw / $w));
    $dst = imagecreatetruecolor($nw, $nh);
    $jpeg = $info[2] === IMAGETYPE_JPEG;
    if (!$jpeg) { imagealphablending($dst, false); imagesavealpha($dst, true); imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127)); }
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); $jpeg ? imagejpeg($dst, null, 88) : imagepng($dst, null, 9); $bin = (string)ob_get_clean();
    return ['src' => 'data:image/' . ($jpeg ? 'jpeg' : 'png') . ';base64,' . base64_encode($bin), 'w' => $nw, 'h' => $nh];
}

// ---------- Champs textuels d'un element ----------
function champs_element(array $in, array &$err): array {
    $t = fn($k, $max) => mb_substr(trim((string)($in[$k] ?? '')), 0, $max);
    $deco = !empty($in['deco']);
    $e = ['nom' => $t('nom', 80), 'categorie' => $t('categorie', 40), 'alt' => $deco ? '' : $t('alt', 200), 'deco' => $deco,
          'taille' => isset(TAILLES_BANQUE[$in['taille'] ?? '']) ? $in['taille'] : 'moyenne'];
    $e['tags'] = array_slice(array_values(array_filter(array_map(fn($x) => mb_substr(trim($x), 0, 30), preg_split('/[,;]+/', (string)($in['tags'] ?? '')) ?: []))), 0, 12);
    if ($e['nom'] === '') $err[] = 'Le nom est obligatoire.';
    if (!$deco && $e['alt'] === '') $err[] = 'Le texte alternatif est obligatoire (ou cochez &laquo;&nbsp;D&eacute;coratif&nbsp;&raquo; si l\'image n\'apporte aucune information).';
    return $e;
}

function id_element_libre(PDO $pdo, int $cid, string $nom): string {
    $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)iconv('UTF-8', 'ASCII//TRANSLIT', $nom)), '-')) ?: 'element';
    $base = substr($base, 0, 40); $id = $base; $i = 2;
    $s = $pdo->prepare('SELECT 1 FROM banque_elements WHERE compte_id = ? AND elem_id = ?');
    while (true) { $s->execute([$cid, $id]); if (!$s->fetch()) return $id; $id = $base . '-' . $i++; }
}
function nb_elements(PDO $pdo, int $cid): int { $s = $pdo->prepare('SELECT COUNT(*) FROM banque_elements WHERE compte_id = ?'); $s->execute([$cid]); return (int)$s->fetchColumn(); }

function enregistrer_element(PDO $pdo, int $cid, array $e): void {
    $pdo->prepare("INSERT INTO banque_elements (compte_id, elem_id, contenu) VALUES (?,?,?) ON CONFLICT(compte_id, elem_id) DO UPDATE SET contenu = excluded.contenu, maj_le = datetime('now')")
        ->execute([$cid, $e['id'], json_encode($e, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
}

/** Fusionne un ancien fichier banque.js dans la banque du compte : ajoute seulement, n'ecrase rien. */
function importer_banque_js(PDO $pdo, int $cid, string $contenu): array {
    $j = trim((string)preg_replace('/^\xEF\xBB\xBF/', '', $contenu));
    $j = trim((string)preg_replace('/^\s*window\.BANQUE\s*=\s*/', '', $j), " \t\r\n;");
    $b = json_decode($j, true);
    if (!is_array($b) || !is_array($b['elements'] ?? null)) return [0, 0, ['Fichier de banque invalide (attendu : un fichier banque.js produit par le Constructeur de banque).']];
    $ajoutes = 0; $ignores = 0; $msgs = [];
    foreach ($b['elements'] as $x) {
        if (!is_array($x)) { $ignores++; continue; }
        if (nb_elements($pdo, $cid) >= MAX_ELEMENTS) { $msgs[] = 'Limite de ' . MAX_ELEMENTS . ' &eacute;l&eacute;ments atteinte.'; break; }
        $err = [];
        $src = (string)($x['src'] ?? '');
        if (!preg_match('#^data:image/(png|jpeg|svg\+xml);base64,([A-Za-z0-9+/=]+)$#', $src, $m)) { $ignores++; continue; }
        $w = (int)($x['w'] ?? 0); $h = (int)($x['h'] ?? 0);
        if ($m[1] === 'svg+xml') {
            $r = assainir_svg((string)base64_decode($m[2], true), $err);
            if (!$r) { $ignores++; $msgs[] = 'Un SVG a &eacute;t&eacute; refus&eacute; (' . h((string)($x['nom'] ?? '?')) . ').'; continue; }
            $src = 'data:image/svg+xml;base64,' . base64_encode($r[0]); $w = $r[1]; $h = $r[2];
        } elseif (!@getimagesizefromstring((string)base64_decode($m[2], true))) { $ignores++; continue; }
        if ($w <= 0 || $h <= 0) { $ignores++; continue; }
        $e = champs_element(['nom' => $x['nom'] ?? '', 'categorie' => $x['categorie'] ?? '', 'tags' => implode(',', (array)($x['tags'] ?? [])),
                              'alt' => $x['alt'] ?? '', 'deco' => !empty($x['deco']), 'taille' => $x['taille'] ?? 'moyenne'], $err);
        if ($err) { $ignores++; continue; }
        $id = preg_match('/^[a-z0-9-]{1,50}$/', (string)($x['id'] ?? '')) ? (string)$x['id'] : id_element_libre($pdo, $cid, $e['nom']);
        $s = $pdo->prepare('SELECT 1 FROM banque_elements WHERE compte_id = ? AND elem_id = ?'); $s->execute([$cid, $id]);
        if ($s->fetch()) { $ignores++; continue; }       // deja present : on ne remplace jamais
        enregistrer_element($pdo, $cid, $e + ['id' => $id, 'w' => $w, 'h' => $h, 'src' => $src]);
        $ajoutes++;
    }
    return [$ajoutes, $ignores, $msgs];
}

// ---------- Image recue mais ajout refuse (texte alternatif oublie...) : gardee cote serveur, liee a la session ----------
function fichier_attente_banque(int $compteId): string {
    $dir = RACINE . '/data/tmp'; if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/attente_banque_' . hash('sha256', session_id() . '|' . $compteId) . '.json';
}
function banque_attente_lire(int $compteId): ?array {
    $f = fichier_attente_banque($compteId);
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return (is_array($d) && isset($d['src'], $d['w'], $d['h'])) ? ['src' => (string)$d['src'], 'w' => (int)$d['w'], 'h' => (int)$d['h']] : null;
}
function banque_attente_ecrire(int $compteId, array $img): void {
    foreach (glob(RACINE . '/data/tmp/attente_banque_*.json') ?: [] as $v) if (filemtime($v) < time() - 10800) @unlink($v);
    @file_put_contents(fichier_attente_banque($compteId), json_encode(['src' => $img['src'], 'w' => $img['w'], 'h' => $img['h']], JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function banque_attente_effacer(int $compteId): void { @unlink(fichier_attente_banque($compteId)); }

// ---------- Lot : plusieurs fichiers televerses d'un coup, a completer ensuite (garde cote serveur, jamais perdu) ----------
const MAX_LOT = 500;
const LOT_PAR_PAGE = 40;
function fichier_lot_banque(int $compteId): string {
    $dir = RACINE . '/data/tmp'; if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/lot_banque_' . hash('sha256', session_id() . '|' . $compteId) . '.json';
}
function lot_lire(int $compteId): array {
    $f = fichier_lot_banque($compteId);
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($d) ? array_values(array_filter($d, fn($x) => is_array($x) && isset($x['uid'], $x['src'], $x['w'], $x['h']))) : [];
}
function lot_ecrire(int $compteId, array $lot): void {
    foreach (glob(RACINE . '/data/tmp/lot_banque_*.json') ?: [] as $v) if (filemtime($v) < time() - 86400) @unlink($v);
    if (!$lot) { @unlink(fichier_lot_banque($compteId)); return; }
    @file_put_contents(fichier_lot_banque($compteId), json_encode(array_values($lot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
}
/** Nom propose a partir du nom de fichier : "porte-voix_rouge.svg" -> "Porte voix rouge". */
function nom_depuis_fichier(string $fichier): string {
    $n = preg_replace('/\.[A-Za-z0-9]{2,5}$/', '', basename($fichier));
    $n = trim((string)preg_replace('/[\s_\-]+/u', ' ', $n));
    return mb_strtoupper(mb_substr($n, 0, 1)) . mb_substr($n, 1);
}
/** Traite $_FILES['fichiers'] (multiple) et ajoute chaque fichier valide au lot ; renvoie les messages d'erreur par fichier. */
function lot_preparer(int $compteId, array $files, array &$msgs, array $chemins = []): int {
    $lot = lot_lire($compteId); $ajoutes = 0;
    $noms = (array)($files['name'] ?? []); 
    foreach ($noms as $i => $nom) {
        $code = $files['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($code === UPLOAD_ERR_NO_FILE) continue;
        $nomH = h((string)$nom);
        if ($code !== UPLOAD_ERR_OK) { $msgs[] = '&laquo;&nbsp;' . $nomH . '&nbsp;&raquo; n\'a pas pu &ecirc;tre t&eacute;l&eacute;vers&eacute; (trop gros ?).'; continue; }
        if (count($lot) >= MAX_LOT) { $msgs[] = 'Un lot ne peut pas d&eacute;passer ' . MAX_LOT . ' fichiers : ajoutez d\'abord ceux-ci, puis recommencez avec les suivants.'; break; }
        $e = []; $img = traiter_fichier_element($files['tmp_name'][$i], $e);
        if (!$img) { $msgs[] = '&laquo;&nbsp;' . $nomH . '&nbsp;&raquo; : ' . implode(' ', $e); continue; }
        // dossier envoye : le nom du dossier parent donne le style (categorie), le nom du fichier donne le nom et un texte alternatif propose
        $parts = array_values(array_filter(explode('/', str_replace('\\', '/', (string)($chemins[$i] ?? '')))));
        $style = count($parts) >= 2 ? mb_substr(trim(str_replace(['_', '-'], ' ', $parts[count($parts) - 2])), 0, 40) : '';
        $style = $style !== '' ? mb_strtoupper(mb_substr($style, 0, 1)) . mb_substr($style, 1) : '';
        $nm = nom_depuis_fichier((string)$nom);
        $lot[] = ['uid' => bin2hex(random_bytes(6)), 'fichier' => ($style !== '' ? $style . ' / ' : '') . mb_substr((string)$nom, 0, 100), 'nom' => $nm, 'categorie' => $style, 'tags' => $style, 'alt' => $style !== '' ? $nm : '', 'deco' => false, 'taille' => 'moyenne', 'erreurs' => []] + $img;
        $ajoutes++;
    }
    lot_ecrire($compteId, $lot);
    return $ajoutes;
}

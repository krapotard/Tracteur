<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require_once __DIR__ . '/banque.php';   // assainir_svg()
require_once __DIR__ . '/pied.php';     // pied de page compose (SVG)

const POLICES_DISPO = [
    'barlow' => 'Barlow', 'barlow-condensed' => 'Barlow Condensed', 'barlow-semi-condensed' => 'Barlow Semi Condensed', 'atkinson' => 'Atkinson Hyperlegible (tr&egrave;s lisible)',
    'lexend' => 'Lexend (lecture facilit&eacute;e)', 'open-sans' => 'Open Sans', 'roboto' => 'Roboto', 'montserrat' => 'Montserrat',
    'oswald' => 'Oswald', 'lora' => 'Lora (avec empattements)', 'arial' => 'Arial (police du syst&egrave;me)',
    'fira-sans' => 'Fira Sans', 'federo' => 'Federo (titres courts)', 'caveat-brush' => 'Caveat Brush (manuscrit, titres courts)', 'bebas' => 'Bebas Neue (titres courts)', 'permanent-marker' => 'Permanent Marker (titres courts)', 'fredericka' => 'Fredericka the Great (titres courts)',
    'concert-one' => 'Concert One (titres)', 'lobster-two' => 'Lobster Two (titres courts)',
];

// ---------- Contrastes (WCAG 2.x) ----------
function luminance(string $hex): float {
    $c = array_map(fn($h) => hexdec($h) / 255, str_split(ltrim($hex, '#'), 2));
    $c = array_map(fn($v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function ratio_contraste(string $a, string $b): float {
    $la = luminance($a); $lb = luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}
/** Les paires de couleurs que le tract et le mail utilisent reellement. [libelle, avant-plan, arriere-plan] */
function paires_contraste(array $k): array {
    return [
        ['texte courant sur fond blanc', $k['texte'], '#ffffff'],
        ['liens sur fond blanc', $k['lien'], '#ffffff'],
        ['texte blanc sur la couleur principale (bandeau, encadr&eacute; color&eacute;)', '#ffffff', $k['rouge']],
        ['texte courant sur la couleur d\'accent (encadr&eacute;)', $k['texte'], $k['jaune']],
        ['liens sur la couleur d\'accent (encadr&eacute;)', $k['lien'], $k['jaune']],
        ['liens sur l\'encadr&eacute; gris', $k['lien'], '#ededed'],
    ];
}
function verifier_contrastes(array $k): array {
    $res = [];
    foreach (paires_contraste($k) as [$lib, $av, $ar]) $res[] = ['libelle' => $lib, 'ratio' => ratio_contraste($av, $ar), 'ok' => ratio_contraste($av, $ar) >= 4.5];
    return $res;
}

// ---------- Logo ----------
/** Image telechargee (PNG, JPEG, WebP, GIF) -> PNG nettoye (sans metadonnees), 400 px de large maximum. */
function traiter_logo(string $fichier, array &$err): ?string {
    if (!is_uploaded_file($fichier) && !is_file($fichier)) { $err[] = 'Fichier de logo introuvable.'; return null; }
    if (filesize($fichier) > 3000000) { $err[] = 'Le logo est trop lourd (3 Mo maximum).'; return null; }
    $brut = (string)file_get_contents($fichier);
    $info = @getimagesizefromstring($brut);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) { $err[] = 'Le logo doit &ecirc;tre une image PNG ou JPEG.'; return null; }
    if ($info[0] > 6000 || $info[1] > 6000) { $err[] = 'Image trop grande.'; return null; }
    $src = @imagecreatefromstring($brut);
    if (!$src) { $err[] = 'Image illisible.'; return null; }
    [$w, $h] = [imagesx($src), imagesy($src)];
    $nw = min(400, $w); $nh = max(1, (int)round($h * $nw / $w));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagepng($dst, null, 9); $png = (string)ob_get_clean();
    return 'data:image/png;base64,' . base64_encode($png);
}

/** Image de bandeau (photo ou illustration) : 1400 px maximum, aplatie sur fond blanc (le tract et le mail ont un fond blanc),
 *  puis PNG pour les aplats et illustrations legeres (nets), JPEG pour les photos (PNG trop lourd). */
function traiter_banniere(string $fichier, array &$err): ?string {
    if (!is_file($fichier) || filesize($fichier) > 6000000) { $err[] = 'Image trop lourde (6 Mo maximum).'; return null; }
    $brut = (string)file_get_contents($fichier);
    $info = @getimagesizefromstring($brut);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true) || $info[0] > 8000 || $info[1] > 8000) { $err[] = 'L\'image doit &ecirc;tre un PNG ou un JPEG.'; return null; }
    $src = @imagecreatefromstring($brut);
    if (!$src) { $err[] = 'Image illisible.'; return null; }
    [$w, $h] = [imagesx($src), imagesy($src)];
    $nw = min(1400, $w); $nh = max(1, (int)round($h * $nw / $w));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));     // fond blanc sous la transparence
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagepng($dst, null, 9); $png = (string)ob_get_clean();
    if (strlen($png) <= 400000) return 'data:image/png;base64,' . base64_encode($png);
    ob_start(); imagejpeg($dst, null, 86); $jpg = (string)ob_get_clean();
    return 'data:image/jpeg;base64,' . base64_encode($jpg);
}

/** Decor image : ['png' => data URI (mail), 'svg' => data URI facultatif (tract/PDF), 'alt' => texte alternatif]. */
function valider_decor($d, string $libelle, array &$err): ?array {
    if (!is_array($d) || trim((string)($d['png'] ?? '')) === '') return null;
    $png = (string)$d['png'];
    if (!preg_match('#^data:image/(png|jpeg);base64,[A-Za-z0-9+/=]+$#', $png) || strlen($png) > 1700000) { $err[] = "Image $libelle invalide ou trop lourde."; return null; }
    $svg = (string)($d['svg'] ?? '');
    if ($svg !== '') {
        $e2 = []; $r = preg_match('#^data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)$#', $svg, $m) ? assainir_svg((string)base64_decode($m[1], true), $e2) : null;
        $svg = $r ? 'data:image/svg+xml;base64,' . base64_encode($r[0]) : '';
        if (!$r) $err[] = "Version vectorielle (SVG) $libelle invalide : $libelle conserve la version image.";
    }
    $alt = mb_substr(trim((string)($d['alt'] ?? '')), 0, 250);
    if ($alt === '') { $err[] = "Le texte alternatif de l'image $libelle est obligatoire : il d&eacute;crit ce que dit ou montre l'image (par exemple le texte qu'elle contient)."; return null; }
    $dim = @getimagesizefromstring((string)base64_decode(substr($png, strpos($png, ',') + 1), true));
    if (!$dim || $dim[0] < 1) { $err[] = "Image $libelle illisible."; return null; }
    return ['png' => $png, 'svg' => $svg, 'alt' => $alt, 'w' => $dim[0], 'h' => $dim[1]];   // dimensions : la mise en page ne depend pas du chargement
}

/** Contraste d'une couleur de texte sur une image de fond : part des points de l'image (grille 30x30) qui atteignent 4,5:1. */
function part_contrastee(string $uri, string $couleur): ?float {
    $bin = base64_decode(substr($uri, (int)strpos($uri, ',') + 1), true);
    $im = $bin ? @imagecreatefromstring($bin) : false;
    if (!$im) return null;
    [$w, $h] = [imagesx($im), imagesy($im)];
    $ok = 0; $tot = 0;
    for ($i = 0; $i < 30; $i++) for ($j = 0; $j < 30; $j++) {
        $c = imagecolorat($im, (int)($i * ($w - 1) / 29), (int)($j * ($h - 1) / 29));
        $a = ($c >> 24) & 0x7F; $k = 1 - $a / 127;                        // transparence : composition sur blanc
        $rgb = sprintf('#%02x%02x%02x', (int)(((($c >> 16) & 255) * $k) + 255 * (1 - $k)), (int)(((($c >> 8) & 255) * $k) + 255 * (1 - $k)), (int)((($c & 255) * $k) + 255 * (1 - $k)));
        $tot++; if (ratio_contraste($couleur, $rgb) >= 4.5) $ok++;
    }
    return $tot ? $ok / $tot : null;
}

/** Fond decoratif (aucun texte alternatif) : ['png' => data URI, 'svg' => facultatif, 'w', 'h']. Le texte qui le recouvre doit rester lisible. */
function valider_fond($d, string $libelle, array $couleursTexte, array &$err): ?array {
    if (!is_array($d) || trim((string)($d['png'] ?? '')) === '') return null;
    $png = (string)$d['png'];
    if (!preg_match('#^data:image/(png|jpeg);base64,[A-Za-z0-9+/=]+$#', $png) || strlen($png) > 1700000) { $err[] = "Image de fond $libelle invalide ou trop lourde."; return null; }
    $svg = (string)($d['svg'] ?? '');
    if ($svg !== '') {
        $e2 = []; $r = preg_match('#^data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)$#', $svg, $m) ? assainir_svg((string)base64_decode($m[1], true), $e2) : null;
        $svg = $r ? 'data:image/svg+xml;base64,' . base64_encode($r[0]) : '';
    }
    $dim = @getimagesizefromstring((string)base64_decode(substr($png, strpos($png, ',') + 1), true));
    if (!$dim || $dim[0] < 1) { $err[] = "Image de fond $libelle illisible."; return null; }
    foreach ($couleursTexte as $nom => $couleur) {
        $p = part_contrastee($png, $couleur);
        if ($p !== null && $p < 0.9) $err[] = "Le texte $nom ($couleur) n'est pas assez lisible sur l'image de fond $libelle : " . round($p * 100) . ' % de l\'image seulement atteint le contraste 4,5:1 (90 % requis). Utilisez un fond plus uni, plus fonc&eacute; ou plus clair sous le texte.';
    }
    return ['png' => $png, 'svg' => $svg, 'w' => $dim[0], 'h' => $dim[1]];
}

// ---------- Validation d'une charte ----------
/**
 * Valide et complete une charte. Renvoie [charte_nettoyee, erreurs[]].
 * Utilisee a l'enregistrement et avant de servir l'editeur.
 */
function valider_charte(array $c): array {
    $err = [];
    $txt = function ($v, int $max = 300) { return mb_substr(trim((string)$v), 0, $max); };
    $url = function ($v) use (&$err, $txt) {
        $v = $txt($v, 300);
        if ($v !== '' && !preg_match('#^https?://[^\s"<>]+$#i', $v)) { $err[] = 'Adresse web invalide : ' . htmlspecialchars($v, ENT_QUOTES); return ''; }
        return $v;
    };
    $couleur = function ($v, string $nom, string $defaut) use (&$err) {
        $v = trim((string)$v);
        if ($v === '') return $defaut;
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $v)) { $err[] = "Couleur invalide ($nom) : " . htmlspecialchars($v, ENT_QUOTES) . ' (format attendu : #RRGGBB).'; return $defaut; }
        return $v;
    };

    $o = [];
    $o['orgNom'] = $txt($c['orgNom'] ?? '');
    if ($o['orgNom'] === '') $err[] = 'Le nom de l\'organisation est obligatoire.';
    $o['orgNomMaj'] = $txt($c['orgNomMaj'] ?? '') ?: mb_strtoupper($o['orgNom'], 'UTF-8');

    $s = $c['site'] ?? [];
    $o['site'] = ['url' => $url($s['url'] ?? ''), 'texte' => $txt($s['texte'] ?? ''), 'libelle' => $txt($s['libelle'] ?? '')];
    $f = $c['facebook'] ?? [];
    $o['facebook'] = ['url' => $url($f['url'] ?? ''), 'texte' => $txt($f['texte'] ?? ''), 'libelle' => $txt($f['libelle'] ?? '')];
    foreach (['site' => 'du site', 'facebook' => 'de la page Facebook'] as $cle => $lib) {
        if ($o[$cle]['url'] !== '' && ($o[$cle]['texte'] === '' || $o[$cle]['libelle'] === ''))
            $err[] = "Pour le lien $lib, renseignez aussi le texte affich&eacute; et l'intitul&eacute; (lu par les lecteurs d'&eacute;cran).";
    }

    $o['mail'] = $txt($c['mail'] ?? '', 200);
    if ($o['mail'] !== '' && !filter_var($o['mail'], FILTER_VALIDATE_EMAIL)) { $err[] = 'Adresse mail invalide.'; $o['mail'] = ''; }

    $o['adresses'] = [];
    foreach (($c['adresses'] ?? []) as $a) {
        if (!is_array($a)) continue;
        $lignes = array_values(array_filter(array_map(fn($l) => $txt($l, 150), $a), fn($l) => $l !== ''));
        if ($lignes) $o['adresses'][] = array_slice($lignes, 0, 6);
    }
    $o['adresses'] = array_slice($o['adresses'], 0, 6);
    $o['appel'] = $txt($c['appel'] ?? '', 120);

    $k = $c['couleurs'] ?? [];
    $o['couleurs'] = [
        'rouge' => $couleur($k['rouge'] ?? '', 'couleur principale', '#C00000'),
        'jaune' => $couleur($k['jaune'] ?? '', 'couleur d\'accent', '#FCC953'),
        'texte' => $couleur($k['texte'] ?? '', 'couleur du texte', '#1a1a1a'),
        'lien'  => $couleur($k['lien'] ?? '', 'couleur des liens', '#0b4f9c'),
    ];

    $logo = (string)($c['logo'] ?? '');
    $ratio = 1.0;
    if ($logo === '') { /* aucun logo : autorise */ }
    elseif (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $logo)) { $err[] = 'Le logo doit &ecirc;tre une image PNG.'; $logo = ''; }
    elseif (strlen($logo) > 700000) { $err[] = 'Le logo est trop lourd (500 Ko maximum).'; $logo = ''; }
    else {
        $i = @getimagesizefromstring((string)base64_decode(substr($logo, 22), true));
        if ($i && $i[0] > 0) $ratio = $i[1] / $i[0]; else { $err[] = 'Logo illisible.'; $logo = ''; }
    }
    $o['logo'] = $logo;
    $o['logoRatio'] = round($ratio, 4);

    $o['police'] = isset(POLICES_DISPO[(string)($c['police'] ?? '')]) ? $c['police'] : 'barlow';
    // Logo vectoriel facultatif (utilise dans le tract/PDF ; le mail garde le PNG). Reassaini a chaque lecture.
    $svg = (string)($c['logoSvg'] ?? '');
    if ($svg !== '') {
        $e2 = []; $r = preg_match('#^data:image/svg\+xml;base64,([A-Za-z0-9+/=]+)$#', $svg, $m) ? assainir_svg((string)base64_decode($m[1], true), $e2) : null;
        if ($r) $svg = 'data:image/svg+xml;base64,' . base64_encode($r[0]); else { $err[] = 'Logo vectoriel (SVG) invalide.'; $svg = ''; }
    }
    $o['logoSvg'] = $svg;
    $o['enteteImage'] = valider_decor($c['enteteImage'] ?? null, 'd\'en-t&ecirc;te', $err);
    $o['piedImage'] = valider_decor($c['piedImage'] ?? null, 'de pied de page', $err);
    $o['iconesPied'] = !empty($c['iconesPied']);
    $o['piedTexte'] = in_array($c['piedTexte'] ?? '', ['sombre', 'clair'], true) ? $c['piedTexte'] : 'sombre';
    // Fonds decoratifs : lisibilite verifiee sur l'image, avec les couleurs reellement utilisees par le texte qui la recouvre
    $kk = $o['couleurs'];
    $o['enteteFond'] = ($c['enteteStyle'] ?? 'bandeau') === 'filet' ? null : valider_fond($c['enteteFond'] ?? null, 'd\'en-t&ecirc;te', ['du bandeau' => '#ffffff', 'du nom de l\'organisation' => '#FFE066'], $err);
    $o['enteteCompose'] = valider_pied_compose($c['enteteCompose'] ?? null, $err, 'droite', true);   // en-tete cree dans Inkscape (colle au bord droit par defaut)
    $o['piedCompose'] = valider_pied_compose($c['piedCompose'] ?? null, $err, 'gauche');   // pied de page cree dans Inkscape : remplace tout le pied
    $o['piedFond'] = valider_fond($c['piedFond'] ?? null, 'de pied de page', $o['piedTexte'] === 'clair'
        ? ['du pied de page' => '#ffffff'] : ['de l\'appel' => $kk['rouge'], 'des liens' => $kk['lien']], $err);
    $choix = fn(string $k, array $ok, string $def) => in_array($c[$k] ?? '', $ok, true) ? $c[$k] : $def;
    $o['logoCote'] = $choix('logoCote', ['gauche', 'droite'], 'droite');
    $o['enteteStyle'] = $choix('enteteStyle', ['bandeau', 'filet'], 'bandeau');
    $o['intertitres'] = $choix('intertitres', ['barre', 'souligne', 'aucun'], 'barre');
    $o['encadres'] = $choix('encadres', ['carre', 'arrondi'], 'carre');
    // Mode choisi pour construire l'en-tete et le pied (les donnees des autres modes restent enregistrees mais ne sont pas utilisees)
    $o['enteteMode'] = in_array($c['enteteMode'] ?? '', ['standard', 'image', 'svg'], true) ? $c['enteteMode'] : ($o['enteteCompose'] ? 'svg' : ($o['enteteImage'] ? 'image' : 'standard'));
    $o['piedMode'] = in_array($c['piedMode'] ?? '', ['standard', 'svg'], true) ? $c['piedMode'] : ($o['piedCompose'] ? 'svg' : 'standard');
    if ($o['enteteMode'] === 'svg' && !$o['enteteCompose']) $err[] = 'En-t&ecirc;te : vous avez choisi &laquo;&nbsp;J\'ai d&eacute;j&agrave; dessin&eacute; mon en-t&ecirc;te&nbsp;&raquo; : d&eacute;posez votre fichier SVG, ou choisissez une autre fa&ccedil;on de le construire.';
    if ($o['enteteMode'] === 'image' && !$o['enteteImage']) $err[] = 'En-t&ecirc;te : vous avez choisi &laquo;&nbsp;J\'ai d&eacute;j&agrave; un bandeau en image&nbsp;&raquo; : d&eacute;posez votre image (avec son texte alternatif), ou choisissez une autre fa&ccedil;on de construire l\'en-t&ecirc;te.';
    if ($o['piedMode'] === 'svg' && !$o['piedCompose']) $err[] = 'Pied de page : vous avez choisi &laquo;&nbsp;J\'ai d&eacute;j&agrave; dessin&eacute; mon pied de page&nbsp;&raquo; : d&eacute;posez votre fichier SVG, ou choisissez l\'autre option.';
    $o['espaceEntete'] = max(0, min(20, (int)($c['espaceEntete'] ?? 6)));   // mm entre le bas de l'en-tete (image, dessin, ou sans logo) et le titre
    $o['depart'] = ($c['depart'] ?? '') === 'exemple' ? 'exemple' : 'vide';
    $o['largeurMail'] = max(480, min(800, (int)($c['largeurMail'] ?? 680)));
    return [$o, $err];
}

/** Charte de depart pour un nouveau syndicat (logo provisoire : celui de l'application). */
function charte_vierge(string $orgNom): array {
    $logo = '';   // pas de logo impose : le syndicat ajoute le sien
    return [
        'orgNom' => $orgNom, 'orgNomMaj' => mb_strtoupper($orgNom, 'UTF-8'),
        'site' => ['url' => '', 'texte' => '', 'libelle' => ''], 'facebook' => ['url' => '', 'texte' => '', 'libelle' => ''],
        'mail' => '', 'adresses' => [], 'appel' => 'Rejoignez-nous, syndiquez-vous !',
        'couleurs' => ['rouge' => '#C00000', 'jaune' => '#FCC953', 'texte' => '#1a1a1a', 'lien' => '#0b4f9c'],
        'logo' => $logo, 'police' => 'barlow', 'depart' => 'exemple', 'largeurMail' => 680,
    ];
}

/** Charte allegee pour l'editeur : seules les donnees du mode choisi sont envoyees au navigateur (page plus legere, rendu sans ambiguite). */
function adapter_pour_editeur(array $c): array {
    $m = $c['enteteMode'] ?? 'standard';
    if ($m !== 'svg') $c['enteteCompose'] = null;
    if ($m !== 'image') $c['enteteImage'] = null;
    if ($m !== 'standard') $c['enteteFond'] = null;
    if (($c['piedMode'] ?? 'standard') !== 'svg') $c['piedCompose'] = null;
    else { $c['piedImage'] = null; $c['piedFond'] = null; }
    return $c;
}

// ---------- Fichiers recus mais pas encore enregistres ----------
// Quand l'enregistrement d'une charte est refuse (texte alternatif oublie, couleur trop pale...), le navigateur vide les champs
// de fichier. Les fichiers deja recus (et nettoyes) sont donc gardes cote serveur, lies a la session et a la charte, jusqu'a
// l'enregistrement reussi ou jusqu'a ce que la page soit rouverte.
const CLES_FICHIERS_CHARTE = ['logo', 'logoSvg', 'enteteImage', 'piedImage', 'enteteFond', 'piedFond', 'enteteCompose', 'piedCompose'];
function fichier_attente(int $compteId, int $charteId): string {
    $dir = RACINE . '/data/tmp'; if (!is_dir($dir)) @mkdir($dir, 0700, true);
    return $dir . '/attente_' . hash('sha256', session_id() . '|' . $compteId . '|' . $charteId) . '.json';
}
function attente_lire(int $compteId, int $charteId): array {
    $f = fichier_attente($compteId, $charteId);
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    return is_array($d) ? array_intersect_key($d, array_flip(CLES_FICHIERS_CHARTE)) : [];
}
function attente_ecrire(int $compteId, int $charteId, array $donnees): void {
    $dir = RACINE . '/data/tmp';
    foreach (glob($dir . '/attente_*.json') ?: [] as $v) if (filemtime($v) < time() - 10800) @unlink($v);   // menage : 3 h
    @file_put_contents(fichier_attente($compteId, $charteId), json_encode(array_intersect_key($donnees, array_flip(CLES_FICHIERS_CHARTE)), JSON_UNESCAPED_SLASHES), LOCK_EX);
}
function attente_effacer(int $compteId, int $charteId): void { @unlink(fichier_attente($compteId, $charteId)); }

/** Garde l'etat actuel d'une charte dans l'historique (15 versions conservees) : appele avant toute modification ou restauration. */
function charte_sauver_version(PDO $pdo, int $charteId): void {
    $pdo->prepare('INSERT INTO chartes_versions (charte_id, nom, contenu) SELECT id, nom, contenu FROM chartes WHERE id = ?')->execute([$charteId]);
    $pdo->prepare('DELETE FROM chartes_versions WHERE charte_id = ? AND id NOT IN (SELECT id FROM chartes_versions WHERE charte_id = ? ORDER BY id DESC LIMIT 15)')->execute([$charteId, $charteId]);
}
<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);

/**
 * Post-traitement d'un PDF produit par Chrome (Skia/PDF) pour le rapprocher de PDF/UA-1 (ISO 14289-1).
 * Le fichier de Chrome est simple (PDF 1.4, table xref classique, sans flux d'objets) : on le relit en entier,
 * on corrige les points connus, puis on le reecrit. Voir pdf_ua_corriger().
 * Securite : si le fichier n'est pas relu a l'identique apres correction, le PDF d'origine est rendu tel quel.
 */

const PDFUA_TYPES_STANDARD = ['Document','Part','Art','Sect','Div','BlockQuote','Caption','TOC','TOCI','Index','NonStruct','Private','P','H','H1','H2','H3','H4','H5','H6',
    'L','LI','Lbl','LBody','Table','TR','TH','TD','THead','TBody','TFoot','Span','Quote','Note','Reference','BibEntry','Code','Link','Annot','Ruby','RB','RT','RP','Warichu','WT','WP','Figure','Formula','Form'];

/** Lit un PDF "simple" : [num => ['d' => dictionnaire (ou valeur), 's' => flux brut|null]]. */
function pdfua_lire(string $pdf): ?array {
    if (!preg_match('/^%PDF-(\d\.\d)/', $pdf, $m)) return null;
    if (!preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $s)) return null;
    $x = (int)$s[1];
    if (substr($pdf, $x, 4) !== 'xref') return null;
    $pos = $x + 4;
    $trailerPos = strpos($pdf, 'trailer', $pos);
    if ($trailerPos === false) return null;
    $table = substr($pdf, $pos, $trailerPos - $pos);
    $offsets = [];
    if (!preg_match_all('/(\d+) (\d+)\r?\n((?:\d{10} \d{5} [nf][ \r\n]{1,2})+)/', $table, $secs, PREG_SET_ORDER)) return null;
    foreach ($secs as $sec) {
        $n = (int)$sec[1];
        foreach (preg_split('/\r?\n/', trim($sec[3])) as $ligne) {
            if (preg_match('/^(\d{10}) \d{5} n/', $ligne, $l)) $offsets[$n] = (int)$l[1];
            $n++;
        }
    }
    $objs = [];
    foreach ($offsets as $num => $off) {
        if (!preg_match('/\G' . $num . ' 0 obj\s*/', $pdf, $mm, 0, $off)) return null;
        $debut = $off + strlen($mm[0]);
        $fin = strpos($pdf, 'endobj', $debut);
        if ($fin === false) return null;
        $corps = substr($pdf, $debut, $fin - $debut);
        if (preg_match('/^(<<.*?>>)\s*stream\r?\n/s', $corps, $d)) {
            $dict = $d[1];
            $dStart = strlen($d[0]);
            if (preg_match('#/Length (\d+)(?! \d+ R)#', $dict, $L)) $flux = substr($corps, $dStart, (int)$L[1]);
            else { $e = strrpos($corps, 'endstream'); $flux = rtrim(substr($corps, $dStart, $e - $dStart), "\r\n"); }
            $objs[$num] = ['d' => $dict, 's' => $flux];
        } else $objs[$num] = ['d' => trim($corps), 's' => null];
    }
    preg_match('/trailer\s*(<<.*>>)\s*startxref/s', $pdf, $t);
    return ['version' => $m[1], 'objs' => $objs, 'trailer' => $t[1] ?? ''];
}

/** Reecrit les objets (table xref comprise). */
function pdfua_ecrire(array $objs, string $trailer): string {
    ksort($objs);
    $out = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n"; $off = [];
    foreach ($objs as $num => $o) {
        $off[$num] = strlen($out);
        $out .= $num . " 0 obj\n" . $o['d'];
        if ($o['s'] !== null) $out .= "\nstream\n" . $o['s'] . "\nendstream";
        $out .= "\nendobj\n";
    }
    $max = max(array_keys($objs));
    $xref = strlen($out);
    $out .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) $out .= isset($off[$i]) ? sprintf("%010d 00000 n \n", $off[$i]) : "0000000000 65535 f \n";
    $trailer = preg_replace('#/Size \d+#', '/Size ' . ($max + 1), $trailer);
    return $out . "trailer\n" . $trailer . "\nstartxref\n" . $xref . "\n%%EOF\n";
}

/** Chaine PDF "texte" : ASCII simple, sinon UTF-16BE en hexadecimal. */
function pdfua_texte(string $s): string {
    if (preg_match('/^[\x20-\x7E]*$/', $s)) return '(' . strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']) . ')';
    return '<FEFF' . strtoupper(bin2hex((string)mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
}

/** Saute une chaine (...) a partir de $i (sur la parenthese ouvrante) ; renvoie la position apres la parenthese fermante. */
function pdfua_saute_chaine(string $c, int $i): int {
    $n = strlen($c); $niv = 1; $i++;
    while ($i < $n && $niv > 0) { if ($c[$i] === '\\') $i++; elseif ($c[$i] === '(') $niv++; elseif ($c[$i] === ')') $niv--; $i++; }
    return $i;
}

/** Decoupe un flux de contenu en instructions : [debut des operandes, debut de l'operateur, fin, operateur]. */
function pdfua_instructions(string $c): array {
    $n = strlen($c); $i = 0; $out = []; $dep = null;
    $ws = " \t\r\n\f\0"; $stop = $ws . '()<>[]{}/%';
    while ($i < $n) {
        $ch = $c[$i];
        if (strpos($ws, $ch) !== false) { $i++; continue; }
        if ($dep === null) $dep = $i;
        if ($ch === '%') { while ($i < $n && $c[$i] !== "\n" && $c[$i] !== "\r") $i++; continue; }
        if ($ch === '(') { $i = pdfua_saute_chaine($c, $i); continue; }
        if ($ch === '<') {
            if (($c[$i + 1] ?? '') === '<') {
                $niv = 1; $i += 2;
                while ($i < $n && $niv > 0) {
                    if (substr($c, $i, 2) === '<<') { $niv++; $i += 2; }
                    elseif (substr($c, $i, 2) === '>>') { $niv--; $i += 2; }
                    elseif ($c[$i] === '(') $i = pdfua_saute_chaine($c, $i);
                    else $i++;
                }
            } else { $e = strpos($c, '>', $i); $i = $e === false ? $n : $e + 1; }
            continue;
        }
        if ($ch === '[') {
            $niv = 1; $i++;
            while ($i < $n && $niv > 0) {
                if ($c[$i] === '[') $niv++;
                elseif ($c[$i] === ']') $niv--;
                elseif ($c[$i] === '(') { $i = pdfua_saute_chaine($c, $i); continue; }
                elseif ($c[$i] === '<') { $e = strpos($c, '>', $i); $i = $e === false ? $n : $e; }
                $i++;
            }
            continue;
        }
        if ($ch === '/') { $i++; while ($i < $n && strpos($stop, $c[$i]) === false) $i++; continue; }
        if ($ch === ')' || $ch === '>' || $ch === ']' || $ch === '{' || $ch === '}') { $i++; continue; }
        $d = $i; while ($i < $n && strpos($stop, $c[$i]) === false) $i++;
        $mot = substr($c, $d, $i - $d);
        if ($mot === '') { $i++; continue; }
        if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)$/', $mot) || $mot === 'true' || $mot === 'false' || $mot === 'null') continue;   // operande
        if ($mot === 'BI') { $e = strpos($c, "\nEI", $i); $i = $e === false ? $n : $e + 3; $out[] = [$dep, $d, $i, 'BI']; $dep = null; continue; }
        $out[] = [$dep, $d, $i, $mot]; $dep = null;
    }
    return $out;
}

/** Enveloppe en /Artifact ce qui est dessine hors du contenu balise (fonds, decors, images decoratives). Renvoie [nouveau flux, nombre d'enveloppes]. */
function pdfua_artefacts(string $c): array {
    $res = ''; $pos = 0; $n = 0; $prof = 0;
    $constr = ['m', 'l', 'c', 'v', 'y', 'h', 're']; $paint = ['f', 'F', 'f*', 'B', 'B*', 'b', 'b*', 'S', 's'];
    $chemin = null; $btDebut = null; $btTexte = false;
    $emballer = function (int $a, int $b) use (&$res, &$pos, &$n, $c) {
        $res .= substr($c, $pos, $a - $pos) . "/Artifact BMC\n" . substr($c, $a, $b - $a) . "\nEMC\n"; $pos = $b; $n++;
    };
    foreach (pdfua_instructions($c) as [$dep, $d, $fin, $op]) {
        if ($op === 'BDC' || $op === 'BMC') { $prof++; $chemin = null; continue; }
        if ($op === 'EMC') { $prof = max(0, $prof - 1); $chemin = null; continue; }
        if ($prof > 0) continue;
        if ($btDebut !== null) {
            if (in_array($op, ['Tj', 'TJ', "'", '"'], true)) $btTexte = true;
            if ($op === 'ET') { if ($btTexte) $emballer($btDebut, $fin); $btDebut = null; $btTexte = false; }
            continue;
        }
        if ($op === 'BT') { $btDebut = $dep ?? $d; $btTexte = false; continue; }
        if (in_array($op, $constr, true)) { if ($chemin === null) $chemin = $dep ?? $d; continue; }
        if (in_array($op, $paint, true)) { if ($chemin !== null) $emballer($chemin, $fin); $chemin = null; continue; }
        if ($op === 'n') { $chemin = null; continue; }                 // trace de decoupe : ce n'est pas du contenu
        if ($op === 'Do' || $op === 'sh' || $op === 'BI') { $emballer($dep ?? $d, $fin); $chemin = null; }
    }
    return [$res . substr($c, $pos), $n];
}

/** Texte de description d'un lien (champ /Contents) d'apres son adresse. */
function pdfua_description_lien(string $uri): string {
    if (stripos($uri, 'mailto:') === 0) return 'Écrire à ' . substr($uri, 7);
    $h = parse_url($uri, PHP_URL_HOST);
    if ($h) { $p = trim((string)parse_url($uri, PHP_URL_PATH), '/'); return 'Lien vers ' . preg_replace('/^www\./', '', $h) . ($p !== '' ? ' (' . $p . ')' : ''); }
    return 'Lien';
}

/** Paquet XMP : titre, langue et identification PDF/UA-1. */
function pdfua_xmp(string $titre, string $langue, bool $declarer = true): string {
    $t = htmlspecialchars($titre, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    return "<?xpacket begin=\"\xEF\xBB\xBF\" id=\"W5M0MpCehiHzreSzNTczkc9d\"?>\n"
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:pdfuaid="http://www.aiim.org/pdfua/ns/id/" xmlns:pdf="http://ns.adobe.com/pdf/1.3/" xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . $t . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:language><rdf:Bag><rdf:li>' . htmlspecialchars($langue, ENT_XML1) . '</rdf:li></rdf:Bag></dc:language>'
        . '<xmp:CreatorTool>Tracteur</xmp:CreatorTool>' . ($declarer ? '<pdfuaid:part>1</pdfuaid:part>' : '')
        . '</rdf:Description></rdf:RDF></x:xmpmeta>' . "\n<?xpacket end=\"w\"?>";
}

/**
 * Autocontrole du PDF corrige : renvoie la liste des points de PDF/UA que ce moteur sait verifier et qui ne sont pas respectes.
 * Ce n'est pas une validation complete (voir veraPDF) : la mention PDF/UA n'est inscrite que si cette liste est vide.
 */
function pdfua_controle(array $objs, ?int $racine): array {
    $pb = [];
    $cat = $racine !== null ? $objs[$racine]['d'] : '';
    if (!preg_match('#/Marked true#', $cat)) $pb[] = 'document non balise';
    if (!preg_match('#/Lang \(#', $cat) && !preg_match('#/Lang <#', $cat)) $pb[] = 'langue absente';
    if (!preg_match('#/DisplayDocTitle true#', $cat)) $pb[] = 'titre non affiche';
    $reste = 0; $figures = 0; $liens = 0; $zero = 0; $police = 0;
    foreach ($objs as $o) {
        $d = $o['d'];
        if (preg_match('#/Type /Page(?![a-z])#', $d)) {
            if (strpos($d, '/Tabs /S') === false) $pb[] = 'ordre de tabulation non structure';
            if (preg_match('#/Contents (\d+) 0 R#', $d, $m) && isset($objs[(int)$m[1]]) && $objs[(int)$m[1]]['s'] !== null) {
                $c = @gzuncompress($objs[(int)$m[1]]['s']);
                if ($c === false) $reste++; else $reste += pdfua_artefacts($c)[1];
            }
        }
        if (strpos($d, '/S /Figure') !== false && strpos($d, '/Type /StructElem') !== false && (!preg_match('#/Alt (\(([^)]+)\)|<(?!FEFF>)[0-9A-Fa-f]+>)#', $d))) $figures++;
        if (strpos($d, '/Subtype /Link') !== false && strpos($d, '/Contents') === false) $liens++;
        if (strpos($d, '/Type /FontDescriptor') !== false && strpos($d, '/Ascent') !== false && !preg_match('#/FontFile[23]? #', $d)) $police++;   // (les descripteurs sans /Ascent sont des attributs de structure, pas des polices)
        if ($o['s'] !== null && strpos($d, '/FlateDecode') !== false) {
            $c = @gzuncompress($o['s']);
            if ($c !== false && strpos($c, 'begincmap') !== false && preg_match('/<[0-9A-Fa-f]{4}>\s*<(?:0000|FEFF|FFFE)>/i', $c)) $zero++;
        }
    }
    if ($reste) $pb[] = 'contenu ni balise ni artefact (' . $reste . ')';
    if ($figures) $pb[] = 'image sans texte alternatif (' . $figures . ')';
    if ($liens) $pb[] = 'lien sans description (' . $liens . ')';
    if ($zero) $pb[] = 'table de caracteres invalide';
    if ($police) $pb[] = 'police non incorporee (' . $police . ')';
    return array_values(array_unique($pb));
}

/**
 * Corrige un PDF de Chrome : XMP + identification PDF/UA, types de balises non standard, liens decrits, contenu decoratif en artefact.
 * $rapport recoit le detail ; en cas de doute, le PDF d'origine est rendu sans modification.
 */
function pdf_ua_corriger(string $pdf, string $titre, string $langue = 'fr', ?array &$rapport = null, bool $declarer = true): string {
    $rapport = ['applique' => false, 'declare' => $declarer, 'artefacts' => 0, 'liens' => 0, 'roles' => []];
    try {
        $p = pdfua_lire($pdf);
        if (!$p) return $pdf;
        $objs = $p['objs'];
        $racine = null; $struct = null; $roles = [];
        // contenu : tout ce qui n'est pas balise devient artefact
        foreach ($objs as $num => $o) {
            if (!preg_match('#/Type /Page(?![a-z])#', $o['d']) || !preg_match('#/Contents (\d+) 0 R#', $o['d'], $m)) continue;
            $k = (int)$m[1];
            if (!isset($objs[$k]) || $objs[$k]['s'] === null || strpos($objs[$k]['d'], '/FlateDecode') === false) continue;
            $c = @gzuncompress($objs[$k]['s']);
            if ($c === false) continue;
            [$c2, $n] = pdfua_artefacts($c);
            if (!$n) continue;
            $z = gzcompress($c2, 6);
            $objs[$k]['s'] = $z; $objs[$k]['d'] = preg_replace('#/Length \d+#', '/Length ' . strlen($z), $objs[$k]['d']);
            $rapport['artefacts'] += $n;
        }
        // tables ToUnicode : une valeur nulle (glyphe sans caractere connu, par exemple dans un SVG) est interdite par PDF/UA
        foreach ($objs as $num => $o) {
            if ($o['s'] === null || strpos($o['d'], '/FlateDecode') === false) continue;
            $c = @gzuncompress($o['s']);
            if ($c === false || strpos($c, 'begincmap') === false) continue;
            $c2 = preg_replace('/(<[0-9A-Fa-f]{4}>\s*<)(?:0000|FEFF|FFFE)(>)/i', '${1}0020${2}', $c, -1, $nb);
            if (!$nb) continue;
            $z = gzcompress($c2, 6);
            $objs[$num]['s'] = $z; $objs[$num]['d'] = preg_replace('#/Length \d+#', '/Length ' . strlen($z), $o['d']);
            $rapport['unicode'] = ($rapport['unicode'] ?? 0) + $nb;
        }
        foreach ($objs as $num => $o) {
            $d = $o['d'];
            if (strpos($d, '/Type /Catalog') !== false) $racine = $num;
            elseif (strpos($d, '/Type /StructTreeRoot') !== false) $struct = $num;
            elseif (strpos($d, '/Subtype /Link') !== false && strpos($d, '/Contents') === false) {           // lien : description obligatoire
                $uri = preg_match('#/URI \(((?:[^()\\\\]|\\\\.)*)\)#', $d, $u) ? stripslashes($u[1]) : '';
                $objs[$num]['d'] = preg_replace('#>>\s*$#', ' /Contents ' . pdfua_texte(pdfua_description_lien($uri)) . '>>', $d);
                $rapport['liens']++;
            } elseif (strpos($d, '/Type /StructElem') !== false && preg_match('#/S /([A-Za-z0-9_]+)#', $d, $s)) {
                if (!in_array($s[1], PDFUA_TYPES_STANDARD, true)) $roles[$s[1]] = true;
                // figure enveloppe sans texte alternatif ni contenu propre : simple conteneur
                if ($s[1] === 'Figure' && strpos($d, '/Alt') === false && strpos($d, '/Pg') === false && preg_match('#/K \d+ 0 R#', $d))
                    $objs[$num]['d'] = str_replace('/S /Figure', '/S /NonStruct', $d);
            }
        }
        if ($racine === null || $struct === null) return $pdf;
        if ($roles) {
            $map = ''; foreach (array_keys($roles) as $r) $map .= '/' . $r . ' /' . (in_array($r, ['Strong', 'Em', 'B', 'I', 'Span'], true) ? 'Span' : 'Div') . ' ';
            $objs[$struct]['d'] = preg_replace('#>>\s*$#', ' /RoleMap <<' . trim($map) . '>>>>', $objs[$struct]['d']);
            $rapport['roles'] = array_keys($roles);
        }
        $rapport['problemes'] = pdfua_controle($objs, $racine);
        $declarer = $declarer && !$rapport['problemes'];                // pas de mention PDF/UA si l'autocontrole releve quelque chose
        $rapport['declare'] = $declarer;
        $xmp = pdfua_xmp($titre, $langue, $declarer); $nouv = max(array_keys($objs)) + 1;
        $objs[$nouv] = ['d' => '<</Type /Metadata /Subtype /XML /Length ' . strlen($xmp) . '>>', 's' => $xmp];
        $objs[$racine]['d'] = preg_replace('#>>\s*$#', ' /Metadata ' . $nouv . ' 0 R>>', $objs[$racine]['d']);
        if (strpos($objs[$racine]['d'], '/Lang') === false) $objs[$racine]['d'] = preg_replace('#>>\s*$#', ' /Lang ' . pdfua_texte($langue) . '>>', $objs[$racine]['d']);
        $sortie = pdfua_ecrire($objs, $p['trailer']);
        $verif = pdfua_lire($sortie);                                  // le fichier corrige doit se relire entierement
        if (!$verif || count($verif['objs']) !== count($objs) || strncmp($sortie, '%PDF-', 5) !== 0) return $pdf;
        $rapport['applique'] = true;
        return $sortie;
    } catch (Throwable $e) {
        return $pdf;
    }
}

<?php
// Test bout en bout de l'etape 2 contre http://localhost/tracteur (base locale de developpement).
$base = rtrim((string)($argv[1] ?? getenv('TRACTEUR_URL') ?: 'http://localhost/tracteur/'), '/') . '/';
$ok = 0; $ko = 0;
function t(string $nom, bool $cond, string $detail = ''): void { global $ok, $ko; if ($cond) $ok++; else $ko++; echo ($cond ? '  ok  ' : ' ECHEC') . " $nom" . ($cond || !$detail ? '' : "  -> $detail") . "\n"; }

class Nav {
    public string $jar; public ?string $derniere = null; public int $code = 0; public string $url = '';
    function __construct() { $this->jar = tempnam(sys_get_temp_dir(), 'cj'); }
    function req(string $url, ?array $post = null, array $files = [], bool $suivre = true): string {
        global $base;
        $ch = curl_init(str_starts_with($url, 'http') ? $url : $base . $url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => $suivre, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 60];
        if ($post !== null || $files) {
            $d = $post ?? [];
            foreach ($files as $k => [$chemin, $type, $nom]) $d[$k] = new CURLFile($chemin, $type, $nom);
            $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = $d;
        }
        curl_setopt_array($ch, $opts);
        $r = (string)curl_exec($ch);
        $this->code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $this->url = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return $r;
    }
    function csrf(string $page): string { $h = $this->req($page); preg_match('/name="csrf" value="([a-f0-9]+)"/', $h, $m); return $m[1] ?? ''; }
    function login(string $id, string $mdp): string {
        $t = $this->csrf('login.php');
        return $this->req('login.php', ['csrf' => $t, 'identifiant' => $id, 'mdp' => $mdp]);
    }
}
$txt = fn(string $h) => html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8');

// --- identifiants admin locaux (fichier d'amorcage de dev) ---
preg_match('/admin : (\S+)/', (string)file_get_contents(dirname(__DIR__) . '/data/identifiants_initiaux.txt'), $m);
$mdpAdmin = $m[1] ?? '';

echo "== Administrateur ==\n";
$adm = new Nav();
$h = $adm->login('admin', $mdpAdmin);
t('connexion admin -> page Comptes', str_contains($h, 'Comptes existants'), substr($txt($h), 0, 120));
t('demo listee', str_contains($h, 'demo'));

// nettoyage d'un eventuel essai precedent
require dirname(__DIR__) . '/src/bootstrap.php';
db()->exec("DELETE FROM comptes WHERE identifiant IN ('exemple','bzh2')");

$tok = $adm->csrf('admin.php');
$h = $adm->req('admin.php', ['csrf' => 'faux', 'action' => 'creer', 'identifiant' => 'exemple', 'nom' => 'X']);
t('creation sans bon jeton CSRF refusee', $adm->code === 403);
$h = $adm->req('admin.php', ['csrf' => $tok, 'action' => 'creer', 'identifiant' => 'Bad Id!', 'nom' => 'X']);
t('identifiant invalide refuse', str_contains($txt($h), 'Identifiant invalide'));
$h = $adm->req('admin.php', ['csrf' => $tok, 'action' => 'creer', 'identifiant' => 'exemple', 'nom' => 'Syndicat Exemple', 'copie_de' => (string)db()->query("SELECT id FROM comptes WHERE identifiant='demo'")->fetchColumn(), 'copier_banque' => '1']);
t('compte exemple cree', str_contains($h, 'Mot de passe provisoire'));
preg_match('/mdp-affiche">([^<]+)</', $h, $m); $mdpProv = $m[1] ?? '';
t('mot de passe provisoire affiche', strlen($mdpProv) > 12);
$h = $adm->req('admin.php', ['csrf' => $tok, 'action' => 'creer', 'identifiant' => 'exemple', 'nom' => 'Doublon']);
t('doublon refuse', str_contains($txt($h), 'existe'));

echo "== Syndicat : premiere connexion ==\n";
$s = new Nav();
$h = $s->login('exemple', $mdpProv);
t('redirige vers changement de mot de passe', str_contains($h, 'Mot de passe provisoire') && str_contains($s->url, 'motdepasse.php'), $s->url);
$h = $s->req('chartes.php');
t('chartes bloquees tant que mdp provisoire', str_contains($s->url, 'motdepasse.php'));
$s->req('api/banque.php'); t('API bloquee aussi (403)', $s->code === 403);
$tk = $s->csrf('motdepasse.php');
$h = $s->req('motdepasse.php', ['csrf' => $tk, 'actuel' => 'faux', 'nouveau' => 'nouveau-mot-de-passe-1', 'nouveau2' => 'nouveau-mot-de-passe-1']);
t('mauvais mdp actuel refuse', str_contains($txt($h), 'actuel est incorrect'));
$h = $s->req('motdepasse.php', ['csrf' => $tk, 'actuel' => $mdpProv, 'nouveau' => 'court', 'nouveau2' => 'court']);
t('mdp trop court refuse', str_contains($txt($h), '12'));
$h = $s->req('motdepasse.php', ['csrf' => $tk, 'actuel' => $mdpProv, 'nouveau' => 'nouveau-mot-de-passe-1', 'nouveau2' => 'nouveau-mot-de-passe-1']);
t('changement accepte -> page Chartes', str_contains($h, 'Chartes graphiques'), substr($txt($h), 0, 100));
t('charte copiee d\'demo presente', str_contains($h, 'Charte Syndicat Exemple'));

echo "== Chartes ==\n";
$st = db()->prepare("SELECT id FROM chartes WHERE compte_id = (SELECT id FROM comptes WHERE identifiant='exemple')"); $st->execute(); $idCh = (int)$st->fetchColumn(); $st->closeCursor(); unset($st);
$h = $s->req("charte.php?id=$idCh");
t('formulaire de charte affiche', str_contains($h, 'Contraste des combinaisons') && str_contains($h, 'Conforme'));
$charteOcc = (int)db()->query("SELECT id FROM chartes WHERE compte_id = (SELECT id FROM comptes WHERE identifiant='demo')")->fetchColumn();
$h = $s->req("charte.php?id=$charteOcc");
t('charte d\'un autre compte inaccessible', str_contains($s->url, 'chartes.php') && str_contains($txt($h), 'introuvable'));
$tk = $s->csrf("charte.php?id=$idCh");
$base_post = ['csrf' => $tk, 'id' => $idCh, 'nom_charte' => 'Charte Exemple', 'orgNom' => 'Syndicat Exemple', 'orgNomMaj' => '', 'appel' => 'Rejoignez-nous !',
  'site_url' => 'https://exemple.org', 'site_texte' => 'exemple.org', 'site_libelle' => 'Site du syndicat exemple', 'fb_url' => '', 'fb_texte' => '', 'fb_libelle' => '', 'mail' => 'contact@exemple.org',
  'adresse' => ["1 rue du Port\n35000 RENNES", ''], 'couleur_rouge' => '#005AA7', 'couleur_jaune' => '#FFD100', 'couleur_texte' => '#1a1a1a', 'couleur_lien' => '#0b4f9c', 'police' => 'lexend'];
$h = $s->req("charte.php?id=$idCh", array_merge($base_post, ['couleur_lien' => '#cccccc']));
t('contraste insuffisant refuse', str_contains($txt($h), 'Contraste insuffisant') && str_contains($txt($h), 'Rien n\'a'), substr($txt($h), 300, 200));
$h = $s->req("charte.php?id=$idCh", array_merge($base_post, ['couleur_rouge' => 'rouge']));
t('couleur mal formee refusee', str_contains($txt($h), 'Couleur invalide'));
$h = $s->req("charte.php?id=$idCh", array_merge($base_post, ['site_libelle' => '']));
t('lien sans intitule refuse', str_contains($txt($h), 'intitul'));
$png = tempnam(sys_get_temp_dir(), 'lg') . '.png'; $im = imagecreatetruecolor(300, 200); imagefill($im, 0, 0, imagecolorallocate($im, 0, 90, 167)); imagepng($im, $png);
$h = $s->req("charte.php?id=$idCh", $base_post, ['logo' => [$png, 'image/png', 'logo.png']]);
t('charte valide enregistree (avec logo)', str_contains($txt($h), 'Charte enregistr'), substr($txt($h), 0, 150));
$c = json_decode((string)db()->query("SELECT contenu FROM chartes WHERE id = $idCh")->fetchColumn(), true);
t('valeurs stockees (couleur, police, logo, ratio)', $c['couleurs']['rouge'] === '#005AA7' && $c['police'] === 'lexend' && abs($c['logoRatio'] - 0.6667) < 0.01 && str_starts_with($c['logo'], 'data:image/png'), json_encode([$c['couleurs']['rouge'], $c['police'], $c['logoRatio']]));
$h = $s->req("charte.php?id=$idCh", array_merge($base_post, ['nom_charte' => 'Charte Exemple']), ['logo' => [__FILE__, 'text/plain', 'x.png']]);
t('fichier non image refuse comme logo', str_contains($txt($h), 'PNG ou JPEG'));
$tk = $s->csrf('chartes.php');
$h = $s->req('chartes.php', ['csrf' => $tk, 'action' => 'creer', 'nom' => 'Charte 1er mai', 'source' => (string)$idCh]);
t('nouvelle charte creee (copie)', str_contains($txt($h), 'Charte 1er mai') || str_contains($s->url, 'charte.php?id='));
$n = (int)db()->query("SELECT COUNT(*) FROM chartes WHERE compte_id = (SELECT id FROM comptes WHERE identifiant='exemple')")->fetchColumn();
t('2 chartes pour exemple', $n === 2, (string)$n);
$h = $s->req('app.php');
t('editeur : selecteur de charte + Mon espace', str_contains($h, 'id="choixCharte"') && str_contains($h, 'Mon espace'));
t('editeur : charte par defaut = couleur exemple', str_contains($h, '--rouge:#005AA7') && str_contains($h, '"orgNom":"Syndicat Exemple"'));
$dup = (int)db()->query("SELECT id FROM chartes WHERE nom='Charte 1er mai'")->fetchColumn();
$tk = $s->csrf('chartes.php');
$s->req('chartes.php', ['csrf' => $tk, 'action' => 'supprimer', 'id' => $charteOcc]);
t('suppression de la charte d\'un autre compte sans effet', (int)db()->query("SELECT COUNT(*) FROM chartes WHERE id = $charteOcc")->fetchColumn() === 1);
$s->req('chartes.php', ['csrf' => $tk, 'action' => 'supprimer', 'id' => $dup]);
t('suppression de sa propre charte (corbeille, restaurable)', (int)db()->query("SELECT COUNT(*) FROM chartes WHERE id = $dup AND supprimee_le IS NOT NULL")->fetchColumn() === 1);

echo "== Banque ==\n";
$h = $s->req('banque.php');
t('banque copiee d\'demo (35 elements : banque par defaut + copie)', str_contains($h, '35 &eacute;l&eacute;ment'), substr($txt($h), 60, 80));
$tk = $s->csrf('banque.php');
$svg = tempnam(sys_get_temp_dir(), 'sv') . '.svg';
file_put_contents($svg, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 100 60" onload="alert(1)"><script>alert(2)</script><foreignObject><div>x</div></foreignObject><a xlink:href="javascript:alert(3)"><rect width="100" height="60" fill="#c00" style="background:url(http://evil/x)" onclick="alert(4)"/></a><image href="http://evil/i.png"/><use href="http://evil/s.svg#a"/><circle cx="30" cy="30" r="10" fill="url(#g)"/></svg>');
$h = $s->req('banque.php', ['csrf' => $tk, 'action' => 'ajouter', 'nom' => 'Rectangle test', 'categorie' => 'Tests', 'tags' => 'a, b', 'alt' => 'Rectangle rouge', 'taille' => 'petite'], ['fichier' => [$svg, 'image/svg+xml', 'r.svg']]);
t('SVG ajoute', str_contains($txt($h), 'ajout'), substr($txt($h), 0, 120));
$e = json_decode((string)db()->query("SELECT contenu FROM banque_elements WHERE elem_id = 'rectangle-test' AND compte_id = (SELECT id FROM comptes WHERE identifiant='exemple')")->fetchColumn(), true);
$sv = $e ? (string)base64_decode(substr($e['src'], strlen('data:image/svg+xml;base64,'))) : '';
$dangers = ['script', 'onload', 'onclick', 'foreignObject', 'javascript:', 'evil', 'http://evil'];
$reste = array_values(array_filter($dangers, fn($d) => stripos($sv, $d) !== false));
t('SVG nettoye (aucun script / ressource externe)', $e && !$reste, implode(',', $reste) . ' :: ' . $sv);
t('SVG : formes legitimes conservees', $e && str_contains($sv, '<rect') && str_contains($sv, 'url(#g)') && $e['w'] === 100 && $e['h'] === 60, $sv);
$h = $s->req('banque.php', ['csrf' => $tk, 'action' => 'ajouter', 'nom' => 'Sans alt', 'categorie' => '', 'tags' => '', 'alt' => '', 'taille' => 'petite'], ['fichier' => [$png, 'image/png', 'p.png']]);
t('alt obligatoire sauf decoratif', str_contains($txt($h), 'texte alternatif est obligatoire'));
$h = $s->req('banque.php', ['csrf' => $tk, 'action' => 'ajouter', 'nom' => 'Filet deco', 'categorie' => 'Tests', 'tags' => '', 'alt' => '', 'deco' => '1', 'taille' => 'pleine'], ['fichier' => [$png, 'image/png', 'p.png']]);
t('PNG decoratif accepte', str_contains($txt($h), 'ajout'));
$mauvais = tempnam(sys_get_temp_dir(), 'ex') . '.svg'; file_put_contents($mauvais, '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "x">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1">&a;</svg>');
$h = $s->req('banque.php', ['csrf' => $tk, 'action' => 'ajouter', 'nom' => 'Entite', 'alt' => 'x', 'taille' => 'petite'], ['fichier' => [$mauvais, 'image/svg+xml', 'e.svg']]);
t('SVG avec DOCTYPE/ENTITY refuse', str_contains($txt($h), 'DOCTYPE'));
$exe = tempnam(sys_get_temp_dir(), 'ex') . '.png'; file_put_contents($exe, "MZ\x90\x00 pas une image");
$h = $s->req('banque.php', ['csrf' => $tk, 'action' => 'ajouter', 'nom' => 'Faux', 'alt' => 'x', 'taille' => 'petite'], ['fichier' => [$exe, 'image/png', 'f.png']]);
t('faux fichier image refuse', str_contains($txt($h), 'Format non accept'));
// import : n'ecrase rien, ajoute seulement
$exp = $s->req('banque_export.php');
t('export banque.js (ASCII, window.BANQUE)', str_starts_with($exp, 'window.BANQUE = ') && !preg_match('/[^\x00-\x7F]/', $exp));
$nouveau = json_decode(trim(substr($exp, 16), " ;\n"), true);
$nouveau['elements'][] = ['id' => 'importe-1', 'nom' => 'Import un', 'categorie' => 'Imports', 'tags' => [], 'alt' => 'Un import', 'deco' => false, 'taille' => 'petite', 'w' => 10, 'h' => 10, 'src' => 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>1</script><rect width="10" height="10"/></svg>')];
$fj = tempnam(sys_get_temp_dir(), 'bj') . '.js'; file_put_contents($fj, 'window.BANQUE = ' . json_encode($nouveau) . ';');
$avant = (int)db()->query("SELECT COUNT(*) FROM banque_elements WHERE compte_id = (SELECT id FROM comptes WHERE identifiant='exemple')")->fetchColumn();
$h = $s->req('banque.php', ['csrf' => $tk, 'action' => 'importer'], ['banquejs' => [$fj, 'text/javascript', 'banque.js']]);
$apres = (int)db()->query("SELECT COUNT(*) FROM banque_elements WHERE compte_id = (SELECT id FROM comptes WHERE identifiant='exemple')")->fetchColumn();
t('import : 1 seul element ajoute, les existants ignores', $apres === $avant + 1 && str_contains($txt($h), '1 '), "$avant -> $apres");
$imp = (string)db()->query("SELECT contenu FROM banque_elements WHERE elem_id = 'importe-1'")->fetchColumn();
t('import : SVG nettoye', $imp !== '' && !stripos((string)base64_decode(substr(json_decode($imp, true)['src'], 26)), 'script'));
// isolation : la banque d'demo n'a pas change
t('banque du compte demo intacte (35)', (int)db()->query("SELECT COUNT(*) FROM banque_elements WHERE compte_id = (SELECT id FROM comptes WHERE identifiant='demo')")->fetchColumn() === 35);
// modifier / supprimer
$h = $s->req('banque.php', ['csrf' => $tk, 'action' => 'modifier', 'elem' => 'rectangle-test', 'nom' => 'Rectangle renomme', 'categorie' => 'Tests', 'tags' => 'x', 'alt' => 'Rectangle', 'taille' => 'grande']);
t('modification d\'un element', str_contains($txt($h), 'modifi'));
$s->req('banque.php', ['csrf' => $tk, 'action' => 'supprimer', 'elem' => 'num-1']);
t('suppression d\'un element', (int)db()->query("SELECT COUNT(*) FROM banque_elements WHERE elem_id='num-1' AND compte_id = (SELECT id FROM comptes WHERE identifiant='exemple')")->fetchColumn() === 0);
t('... sans toucher la banque demo', (int)db()->query("SELECT COUNT(*) FROM banque_elements WHERE elem_id='num-1' AND compte_id = (SELECT id FROM comptes WHERE identifiant='demo')")->fetchColumn() === 1);
$api = $s->req('api/banque.php'); $j = json_decode($api, true);
t('API banque = banque du compte connecte', count($j['elements']) === $apres - 1, (string)count($j['elements']));

echo "== PDF avec la charte Exemple ==\n";
$ch = curl_init($base . 'api/pdf.php');
$h = $s->req('app.php'); preg_match('/__CSRF__ = "([a-f0-9]+)"/', $h, $mm);
curl_close($ch);

echo "== Admin : actions ==\n";
$tok = $adm->csrf('admin.php');
$idB = (int)db()->query("SELECT id FROM comptes WHERE identifiant='exemple'")->fetchColumn();
$h = $adm->req('admin.php', ['csrf' => $tok, 'action' => 'basculer', 'id' => $idB]);
t('desactivation', str_contains($txt($h), 'sactiv'));
$s2 = new Nav(); $h = $s2->login('exemple', 'nouveau-mot-de-passe-1');
t('compte desactive : connexion refusee', str_contains($txt($h), 'incorrect'));
$sess = $s->req('chartes.php'); t('session ouverte coupee des la desactivation', str_contains($s->url, 'login.php'), $s->url);
$adm->req('admin.php', ['csrf' => $tok, 'action' => 'basculer', 'id' => $idB]);
$h = $adm->req('admin.php', ['csrf' => $tok, 'action' => 'reinit', 'id' => $idB]);
preg_match('/mdp-affiche">([^<]+)</', $h, $m); $mdp2 = $m[1] ?? '';
$s3 = new Nav(); $h = $s3->login('exemple', 'nouveau-mot-de-passe-1');
t('ancien mot de passe invalide apres reinitialisation', str_contains($txt($h), 'incorrect'));
$h = $s3->login('exemple', $mdp2); t('nouveau provisoire -> changement force', str_contains($s3->url, 'motdepasse.php'));
$h = $adm->req('admin.php', ['csrf' => $tok, 'action' => 'supprimer', 'id' => $idB, 'confirmation' => 'faux']);
t('suppression sans bonne confirmation refusee', (int)db()->query("SELECT COUNT(*) FROM comptes WHERE id = $idB")->fetchColumn() === 1);
$adm->req('admin.php', ['csrf' => $tok, 'action' => 'supprimer', 'id' => $idB, 'confirmation' => 'exemple']);
t('suppression avec confirmation', (int)db()->query("SELECT COUNT(*) FROM comptes WHERE id = $idB")->fetchColumn() === 0);
t('... et en cascade : plus de chartes ni de banque', (int)db()->query("SELECT COUNT(*) FROM chartes WHERE compte_id = $idB")->fetchColumn() === 0 && (int)db()->query("SELECT COUNT(*) FROM banque_elements WHERE compte_id = $idB")->fetchColumn() === 0);
$adm->req('admin.php', ['csrf' => $tok, 'action' => 'basculer', 'id' => 1]);
t('l\'administrateur ne peut pas etre desactive', (int)db()->query("SELECT actif FROM comptes WHERE id = 1")->fetchColumn() === 1);
$occ = new Nav(); $occ->req('admin.php'); t('un syndicat n\'accede pas a admin.php', in_array($occ->code, [302, 200], true) && !str_contains($occ->req('admin.php'), 'Comptes existants'));
$sy = new Nav(); preg_match('/demo : (\S+)/', (string)file_get_contents(dirname(__DIR__) . '/data/identifiants_initiaux.txt'), $mo);
$sy->login('demo', $mo[1] ?? ''); $hh = $sy->req('admin.php'); t('compte demo : admin.php refuse (403)', $sy->code === 403, (string)$sy->code);

echo "\n$ok reussis, $ko echec(s)\n";

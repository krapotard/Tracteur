<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 Thomas Garnier <thomas.garnier83@gmail.com>
declare(strict_types=1);
require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/pdf.php';
require __DIR__ . '/src/vue.php';
$u = exiger_admin();

/** Essais de vitesse : chaque essai produit un PDF d'essai avec des options de Chrome differentes. */
const ESSAIS_PDF = [
    'vide'          => ['Page vide : démarrage de Chrome seul', [], true, true],
    'actuel'        => ['Tract d\'essai, réglage actuel', [], true, false],
    'nozygote'      => ['Tract d\'essai, sans processus « zygote » (--no-zygote)', ['--no-zygote'], true, false],
    'isolation'     => ['Tract d\'essai, isolation des sites réduite (moins de processus)', ['--disable-features=site-per-process,IsolateOrigins', '--renderer-process-limit=1'], true, false],
    'unique'        => ['Tract d\'essai, processus unique (--single-process, peut échouer)', ['--single-process'], true, false],
    'sansbalisage'  => ['Tract d\'essai sans balisage (comparaison seulement)', [], false, false],
];

function fragment_essai(): string {
    $svg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"><circle cx="60" cy="60" r="50" fill="#C00000"/><rect x="40" y="40" width="40" height="40" fill="#FCC953"/></svg>');
    $lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.';
    $corps = '';
    for ($i = 1; $i <= 4; $i++) {
        $corps .= '<div class="bl"><h2>Intertitre de la partie ' . $i . '</h2></div><div class="bl"><p>' . $lorem . '</p><p>' . $lorem . ' <strong>Un passage en gras.</strong></p></div>'
            . '<div class="bl"><ul><li>Premier élément de la liste</li><li>Deuxième élément avec <em>italique</em></li><li>Troisième élément</li></ul></div>'
            . '<div class="bl"><div class="enc enc-jaune"><p><strong>Un encadré pour mettre une information en valeur.</strong></p></div></div>';
    }
    return '<div class="tract t-greve al-justifie barre-on" style="font-family:\'Barlow\',Arial,sans-serif"><header class="tr-head"><div><p class="org">ORGANISATION D\'ESSAI</p></div><img class="tr-logo" src="data:image/svg+xml;base64,' . $svg . '" alt=""></header>'
        . '<main class="tr-main"><div class="tr-title"><h1>Tract d\'essai pour mesurer la vitesse</h1></div><div class="tr-corps">' . $corps . '</div></main>'
        . '<footer><div class="tr-foot"><p class="appel">Texte d\'appel du pied de page</p></div><div class="tr-bar"></div></footer></div>';
}

function lire_journal(string $nom, int $n): array {
    $f = RACINE . '/data/logs/' . $nom;
    if (!is_file($f)) return [];
    $l = array_values(array_filter(explode("\n", (string)file_get_contents($f))));
    return array_slice($l, -$n);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exiger_post();
    $cle = (string)($_POST['essai'] ?? '');
    if (!isset(ESSAIS_PDF[$cle])) { flash('err', 'Essai inconnu.'); redirige('admin_pdf.php'); }
    [$libelle, $extra, $balise, $vide] = ESSAIS_PDF[$cle];
    @set_time_limit(200);
    $couleurs = ['rouge' => '#C00000', 'jaune' => '#FCC953', 'texte' => '#1a1a1a', 'lien' => '#0b4f9c'];
    $doc = $vide ? '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Essai</title></head><body><p>Essai</p></body></html>'
                 : document_pdf('Essai', fragment_essai(), $couleurs, ['Barlow']);
    $charge = function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? null) : null;
    $ligne = ['date' => date('Y-m-d H:i:s'), 'essai' => $libelle, 'html_ko' => (int)round(strlen($doc) / 1024), 'charge' => $charge === null ? null : round((float)$charge, 2)];
    try {
        $m = null; $pdf = generer_pdf($doc, $extra, $balise, $m);
        $ligne += ['pdf_apres' => $m['apparu'] === null ? null : round($m['apparu'], 1), 'total' => round($m['total'], 1), 'arret_anticipe' => $m['coupe'], 'pdf_ko' => (int)round(strlen($pdf) / 1024)];
        flash('ok', 'Essai « ' . h($libelle) . ' » : PDF disponible après ' . ($m['apparu'] === null ? '?' : round($m['apparu'], 1)) . ' s, durée totale ' . round($m['total'], 1) . ' s.');
    } catch (Throwable $e) {
        $ligne += ['erreur' => mb_substr($e->getMessage(), 0, 160)];
        flash('err', 'Essai « ' . h($libelle) . ' » : échec (' . h($e->getMessage()) . ').');
    }
    $f = RACINE . '/data/logs/diag_pdf.log'; @mkdir(dirname($f), 0700, true);
    if (is_file($f) && filesize($f) > 100000) @unlink($f);
    @file_put_contents($f, json_encode($ligne, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    redirige('admin_pdf.php');
}

$cfg = config_pdf();
$essais = array_reverse(array_filter(array_map(fn($l) => json_decode($l, true), lire_journal('diag_pdf.log', 15))));
page_debut('Moteur PDF', $u, 'moteurpdf');
?>
<p>Cette page sert &agrave; <strong>mesurer la vitesse de cr&eacute;ation des PDF sur ce serveur</strong> et &agrave; comparer quelques r&eacute;glages de Chrome. Chaque essai produit un PDF d'essai, comme le ferait un export, et peut durer jusqu'&agrave; une minute sur un h&eacute;bergement mutualis&eacute; : ne fermez pas la page pendant l'essai.</p>
<p>Navigateur utilis&eacute; : <code><?= h((string)($cfg['chrome'] ?: 'aucun')) ?></code>
<?php if (!empty($cfg['options_chrome'])): ?> &middot; options suppl&eacute;mentaires actuelles : <code><?= h(implode(' ', (array)$cfg['options_chrome'])) ?></code><?php endif; ?></p>

<p class="aide"><strong>Le premier PDF apr&egrave;s une longue inactivit&eacute; peut &ecirc;tre tr&egrave;s lent</strong> (d&eacute;marrage &laquo;&nbsp;&agrave; froid&nbsp;&raquo; de Chrome sur un h&eacute;bergement mutualis&eacute;), puis les suivants sont presque instantan&eacute;s. L'&eacute;diteur pr&eacute;chauffe donc Chrome d&egrave;s son ouverture. Pour comparer des r&eacute;glages, <strong>refaites chaque essai deux fois</strong> : seul le second est r&eacute;v&eacute;lateur.</p>

<h2>Lancer un essai</h2>
<form method="post" action="admin_pdf.php" id="formEssai">
  <?= champ_csrf() ?>
  <label for="essai">Essai &agrave; r&eacute;aliser</label>
  <select id="essai" name="essai">
    <?php foreach (ESSAIS_PDF as $k => [$lib]): ?><option value="<?= h($k) ?>"><?= h($lib) ?></option><?php endforeach; ?>
  </select>
  <p class="aide">Conseil : commencez par la page vide (elle mesure le d&eacute;marrage de Chrome seul), puis le r&eacute;glage actuel, puis les variantes. Une variante nettement plus rapide peut &ecirc;tre activ&eacute;e dans <code>data/config.local.php</code> (voir plus bas).</p>
  <p><button class="btn" type="submit" id="btnEssai">Lancer l'essai</button> <span id="etatEssai" role="status" aria-live="polite"></span></p>
</form>
<script>
document.getElementById('formEssai').addEventListener('submit', function () {
  var b = document.getElementById('btnEssai'); b.disabled = true;
  document.getElementById('etatEssai').textContent = 'Essai en cours : cela peut prendre jusqu’à une minute…';
});
</script>

<h2>R&eacute;sultats des essais</h2>
<?php if (!$essais): ?><p>Aucun essai pour le moment.</p><?php else: ?>
<table>
  <caption>Quinze derniers essais (le plus r&eacute;cent en premier)</caption>
  <thead><tr><th scope="col">Date</th><th scope="col">Essai</th><th scope="col">PDF apr&egrave;s</th><th scope="col">Total</th><th scope="col">Charge du serveur</th><th scope="col">R&eacute;sultat</th></tr></thead>
  <tbody>
  <?php foreach ($essais as $e): ?>
    <tr><td><?= h((string)($e['date'] ?? '')) ?></td><td><?= h((string)($e['essai'] ?? '')) ?></td>
      <td><?= isset($e['pdf_apres']) ? h((string)$e['pdf_apres']) . ' s' : '&ndash;' ?></td><td><?= isset($e['total']) ? h((string)$e['total']) . ' s' : '&ndash;' ?></td>
      <td><?= isset($e['charge']) ? h((string)$e['charge']) : '&ndash;' ?></td>
      <td><?= isset($e['erreur']) ? 'Échec : ' . h((string)$e['erreur']) : 'PDF produit (' . (int)($e['pdf_ko'] ?? 0) . ' Ko)' ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<h2>Derniers exports r&eacute;els</h2>
<?php $exp = array_reverse(lire_journal('pdf_temps.log', 10)); if (!$exp): ?><p>Aucun export enregistr&eacute; depuis la mise en place du journal.</p><?php else: ?>
<pre><?php foreach ($exp as $l) echo h($l), "\n"; ?></pre>
<p class="aide">&laquo;&nbsp;html&nbsp;&raquo; est la taille du tract envoy&eacute; &agrave; Chrome ; &laquo;&nbsp;pdf_apres&nbsp;&raquo; le temps jusqu'&agrave; l'&eacute;criture du PDF ; &laquo;&nbsp;total&nbsp;&raquo; la dur&eacute;e c&ocirc;t&eacute; serveur. Si votre chronom&egrave;tre affiche beaucoup plus, le temps perdu est ailleurs (file d'attente, envoi du tract, navigateur).</p>
<?php endif; ?>

<h2>Activer un r&eacute;glage plus rapide</h2>
<p>Si un essai est nettement plus rapide que le r&eacute;glage actuel, ajoutez ses options dans le fichier <code>data/config.local.php</code> du serveur, puis refaites un essai. Exemple :</p>
<pre>&lt;?php
return ['options_chrome' =&gt; ['--no-zygote']];</pre>
<p class="aide">Si le fichier contient d&eacute;j&agrave; d'autres r&eacute;glages, ajoutez seulement la ligne <code>'options_chrome' =&gt; [...]</code> au tableau. Pour revenir en arri&egrave;re, retirez-la.</p>
<?php page_fin();

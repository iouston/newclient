<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2017 Mikael Carlavan <contact@mika-carl.fr>
 * Copyright (C) 2019-2026 Julien Marchand <julien.marchand@iouston.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/newclient/admin/about.php
 *  \ingroup    newclient
 *  \brief      Page A propos du module newclient
 */


$res=@include("../../main.inc.php");                   // For root directory
if (! $res) $res=@include("../../../main.inc.php");    // For "custom" directory

// --- Variables pour feedback ---
$FEEDBACK_ENDPOINT = 'https://iouston.space/properdol/reponse/public/quicksubmit.php';
$FEEDBACK_FORM_ID  = 3;             // id du questionnaire "Suggestion" sur l'instance centrale
$MODULE_NAME       = 'Suivi nouveaux clients';   // nom de CE module (a changer par module)
$MODULE_LANGKEY    = 'newclient@newclient';


// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

dol_include_once("/newclient/lib/newclient.lib.php");
dol_include_once("/newclient/core/modules/modNewClient.class.php");

// Translations
$langs->load("newclient@newclient");

// Translations
$langs->load("errors");
$langs->load("admin");
$langs->load("other");

// Access control
if (! $user->admin) {
	accessforbidden();
}

$versions = array(
	array('version' => '1.0.2', 'date' => '27/10/2026', 'updates' => $langs->trans('Version102')),
	array('version' => '1.0.1', 'date' => '04/09/2026', 'updates' => $langs->trans('Version101')),
	array('version' => '1.0.0', 'date' => '25/09/2025', 'updates' => $langs->trans('FirstVersion')),
);
// --- Informations lues depuis le descripteur du module ---
$modDesc = new modNewClient($db);
$moduleInfo = array(
    'version'    => $modDesc->version,
    'editor'     => !empty($modDesc->editor_name) ? $modDesc->editor_name : '',
    'editor_url' => !empty($modDesc->editor_url) ? $modDesc->editor_url : '',
    // Pas de propriete standard pour la licence dans DolibarrModules : repli sur GPL v3+
    'license'    => !empty($modDesc->license) ? $modDesc->license : 'GPL v3+',
    'dolmin'     => (!empty($modDesc->need_dolibarr_version) && is_array($modDesc->need_dolibarr_version)) ? implode('.', $modDesc->need_dolibarr_version) : '',
    'phpmin'     => (!empty($modDesc->phpmin) && is_array($modDesc->phpmin)) ? implode('.', $modDesc->phpmin) : '',
);

// --- Informations de depannage ---
/**
 * Liste les modules externes (hors htdocs principal) en lisant leurs descripteurs
 * par expression reguliere : on n'instancie pas les classes pour eviter les
 * collisions de noms ou effets de bord d'un module tiers mal ecrit.
 *
 * @return array Liste de array('name'=>, 'version'=>, 'enabled'=>bool)
 */
function newclient_about_list_external_modules()
{
    global $conf;

    $result = array();
    $roots = (!empty($conf->file->dol_document_root) && is_array($conf->file->dol_document_root)) ? $conf->file->dol_document_root : array();
    foreach ($roots as $key => $root) {
        if ($key == 'main' || !is_dir($root)) continue;
        $dirs = glob(rtrim($root, '/\\').'/*', GLOB_ONLYDIR);
        if (!is_array($dirs)) continue;
        foreach ($dirs as $dir) {
            $files = glob($dir.'/core/modules/mod*.class.php');
            if (!is_array($files)) continue;
            foreach ($files as $file) {
                $classname = basename($file, '.class.php');
                $name = preg_replace('/^mod/i', '', $classname);
                $version = '?';
                $content = @file_get_contents($file);
                if ($content !== false && preg_match('/\$this->version\s*=\s*[\'"]([^\'"]+)[\'"]/', $content, $m)) {
                    $version = $m[1];
                }
                $constname = 'MAIN_MODULE_'.strtoupper($name);
                $result[strtolower($name)] = array(
                    'name'    => $name,
                    'dir'     => basename($dir),
                    'version' => $version,
                    'enabled' => !empty($conf->global->$constname),
                );
            }
        }
    }
    ksort($result);
    return $result;
}

$dbVersion = '';
if (method_exists($db, 'getVersion')) {
    $dbVersion = $db->getVersion();
}
$dbLabel = (!empty($db->type) ? $db->type : 'db');

$debugLines = array();
$debugLines[] = '=== Informations de dépannage ===';
$debugLines[] = 'Date : '.dol_print_date(dol_now(), 'dayhour');
$debugLines[] = 'Module : '.$MODULE_NAME.' '.$moduleInfo['version'];
$debugLines[] = 'Dolibarr : '.DOL_VERSION;
$debugLines[] = 'PHP : '.PHP_VERSION;
$debugLines[] = 'Base de données : '.$dbLabel.' '.$dbVersion;
$debugLines[] = 'Adresse Dolibarr : '.DOL_MAIN_URL_ROOT;
$debugLines[] = 'Entité : '.((int) $conf->entity);
$debugLines[] = '';
$debugLines[] = 'Modules externes installés :';
$extModules = newclient_about_list_external_modules();
if (empty($extModules)) {
    $debugLines[] = '  (aucun)';
} else {
    foreach ($extModules as $mod) {
        $debugLines[] = '  - '.$mod['name'].' '.$mod['version'].' ['.($mod['enabled'] ? 'activé' : 'désactivé').']';
    }
}
$debugText = implode("\n", $debugLines);

/*
 * View
 */

$form = new Form($db);
llxHeader('', $langs->trans('NewClientAbout'));
print '<script src="https://cdn.tailwindcss.com"></script>';
// Preflight reste actif (comportement par defaut de Tailwind) : les bordures,
// les halos ring-*, la suppression des puces de liste et les marges de
// paragraphe fonctionnent nativement. Preflight ne fait que neutraliser le
// style des <h1> a <h6> : on redonne donc un style aux titres de section via
// une classe dediee plutot que de tout desactiver.
print '<style>
.iou-about-title {
    font-size: 1.4em;
    font-weight: bold;
    margin: 1em 0 0.5em;
}
/* Cartes encadrees : classe dediee plutot que "border" de Tailwind, car le
   selecteur div.border du theme Dolibarr est plus specifique et ecrase le padding */
div.iou-card, details.iou-card {
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 1.5rem 1.5rem 1.5rem 3rem;
}
/* Blocs repliables */
details.iou-card > summary {
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    gap: .6em;
}
details.iou-card > summary::-webkit-details-marker { display: none; }
details.iou-card > summary .iou-chevron { transition: transform .2s; color: #9ca3af; }
details.iou-card[open] > summary .iou-chevron { transform: rotate(90deg); }
details.iou-card > summary .iou-about-title { margin: 0; }
/* Table des informations du module */
table.iou-info-table td { border: none !important; padding: 6px 12px 6px 0; }
table.iou-info-table td:first-child { color: #6b7280; width: 40%; }
/* Zone de texte de depannage */
textarea.iou-debug {
    width: 100%;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: .375rem;
    padding: .75rem;
}
/* Table du formulaire de feedback : pas de bordure */
.iou-feedback-table, .iou-feedback-table tr, .iou-feedback-table td {
    border: none !important;
}
.iou-feedback-table td {
    padding: 6px 4px;
    vertical-align: top;
}
/* Notation par etoiles (CSS pur) : ordre inverse pour que ~ allume les etoiles precedentes */
.iou-stars {
    display: inline-flex;
    flex-direction: row-reverse;
    justify-content: flex-end;
    gap: 2px;
}
.iou-stars input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.iou-stars label {
    font-size: 1.8em;
    line-height: 1;
    color: #d1d5db;
    cursor: pointer;
    transition: color .15s;
}
.iou-stars input:checked ~ label,
.iou-stars label:hover,
.iou-stars label:hover ~ label {
    color: #f59e0b;
}
.iou-stars input:focus-visible + label {
    outline: 2px solid #f59e0b;
    outline-offset: 2px;
}
</style>';

// --- Message de retour apres soumission (redirige ici via backurl) ---
$quicksubmit = GETPOST('quicksubmit', 'alpha');
if ($quicksubmit == 'ok')
{
    setEventMessages($langs->trans('NewClientFeedbackThanks'), null, 'mesgs');
}
elseif ($quicksubmit == 'error')
{
    $errmsg = GETPOST('message', 'alpha');
    setEventMessages($errmsg ? $errmsg : $langs->trans('NewClientFeedbackError'), null, 'errors');
}

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'. $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans('NewClientAbout'), $linkback);

// Configuration header
$head = newclient_prepare_admin_head();
dol_fiche_head(
	$head,
	'about',
	$langs->trans("ModuleNewClientName"),
	0,
	'newclient@newclient'
);

// Conteneur general : decolle le contenu de la bordure de l'onglet
print '<div class="px-4 md:px-6">';


// --- Comment ca marche (replie par defaut) ---
print '<details class="iou-card" style="margin-top: 20px;">';
print '<summary><i class="fa fa-chevron-right iou-chevron"></i><h2 class="iou-about-title">'.$langs->trans("NewClientHowItWorks").'</h2></summary>';
print '<p class="mt-4 mb-4">'.$langs->trans("NewClientHowItWorksIntro").'</p>';
print '<ol class="list-decimal ps-6 space-y-2 text-gray-700">';
$howItWorksSteps = array('NewClientHowItWorksStep1', 'NewClientHowItWorksStep2', 'NewClientHowItWorksStep3', 'NewClientHowItWorksStep4', 'NewClientHowItWorksStep5');
foreach ($howItWorksSteps as $stepKey) {
    print '<li>'.$langs->trans($stepKey).'</li>';
}
print '</ol>';
print '<p class="mt-4 text-sm text-gray-500">'.$langs->trans("NewClientHowItWorksNote").'</p>';
print '</details>'; // fin bloc Comment ca marche

// --- Fonctionnalites (replie par defaut) ---
$features = array(
    'NewClientFeatureFirstSignature',
    'NewClientFeatureCongrats',
    'NewClientFeatureEachSignature',
    'NewClientFeatureGifList',
    'NewClientFeatureMassUpdate',
    'NewClientFeatureStatsByMonth',
    'NewClientFeatureStatsCA',
    'NewClientFeatureStatsFilters',
    'NewClientFeatureRefusalReason',
    'NewClientFeatureSafeClose',
    'NewClientFeatureMultiEntity',
    'NewClientFeatureMenu',
);
print '<details class="iou-card" style="margin-top: 20px;">';
print '<summary><i class="fa fa-chevron-right iou-chevron"></i><h2 class="iou-about-title">'.$langs->trans("NewClientFeatures").'</h2></summary>';
print '<ul class="list-disc ps-6 space-y-2 text-gray-700 mt-4">';
foreach ($features as $featureKey) {
    print '<li>'.$langs->trans($featureKey).'</li>';
}
print '</ul>';
if ($langs->trans("NewClientFeaturesRequires") != "NewClientFeaturesRequires") {
    print '<p class="mt-4 text-sm text-gray-500">'.$langs->trans("NewClientFeaturesRequires").'</p>';
}
print '</details>'; // fin bloc Fonctionnalites


// --- Formulaire de feedback centralise ---
print '<div class="flex flex-col md:flex-row gap-6 items-start" style="margin-top: 30px;">';

// --- Colonne de gauche : Informations sur le module + Depannage + ChangeLog ---
print '<div class="flex-1 w-full flex flex-col gap-6">';

// --- Informations sur le module (lues depuis le descripteur, replie par defaut) ---
print '<details class="iou-card">';
print '<summary><i class="fa fa-chevron-right iou-chevron"></i><h2 class="iou-about-title">'.$langs->trans("NewClientModuleInfo").'</h2></summary>';
print '<table class="iou-info-table mt-4">';
print '<tr><td>'.$langs->trans("Version").'</td><td><strong>'.dol_escape_htmltag($moduleInfo['version']).'</strong></td></tr>';
print '<tr><td>'.$langs->trans("NewClientEditor").'</td><td>';
if ($moduleInfo['editor_url']) {
    print '<a href="'.dol_escape_htmltag($moduleInfo['editor_url']).'" target="_blank" rel="noopener noreferrer">'.dol_escape_htmltag($moduleInfo['editor']).'</a>';
} else {
    print dol_escape_htmltag($moduleInfo['editor']);
}
print '</td></tr>';
print '<tr><td>'.$langs->trans("NewClientLicense").'</td><td>'.dol_escape_htmltag($moduleInfo['license']).'</td></tr>';
print '<tr><td>'.$langs->trans("NewClientMinDolibarr").'</td><td>'.dol_escape_htmltag($moduleInfo['dolmin']).'</td></tr>';
print '<tr><td>'.$langs->trans("NewClientMinPHP").'</td><td>'.dol_escape_htmltag($moduleInfo['phpmin']).'</td></tr>';
print '</table>';
print '</details>'; // fin bloc Informations module

// --- Informations de depannage (replie, colonne de gauche) ---
print '<details class="iou-card">';
print '<summary><i class="fa fa-chevron-right iou-chevron"></i><h2 class="iou-about-title">'.$langs->trans("NewClientDebugInfo").'</h2></summary>';
print '<p class="mt-4 mb-3 text-sm text-gray-600">'.$langs->trans("NewClientDebugInfoHelp").'</p>';
print '<textarea id="iou-debug-text" class="iou-debug" rows="'.min(30, count($debugLines) + 1).'" readonly>'.htmlspecialchars($debugText, ENT_QUOTES, 'UTF-8').'</textarea>'; // pas dol_escape_htmltag : il supprime les retours a la ligne
print '<div class="mt-3 flex items-center gap-3">';
print '<button type="button" class="button" id="iou-debug-copy"><i class="fa fa-copy"></i> '.$langs->trans("NewClientDebugCopy").'</button>';
print '<span id="iou-debug-copied" class="text-sm text-green-700" style="display:none;"><i class="fa fa-check"></i> '.$langs->trans("NewClientCopied").'</span>';
print '</div>';
print '</details>'; // fin bloc Depannage

// --- ChangeLog (replie par defaut) ---
print '<details class="iou-card">';
print '<summary><i class="fa fa-chevron-right iou-chevron"></i><h2 class="iou-about-title">'.$langs->trans("ChangeLog").'</h2></summary>';


print '<div class="max-w-3xl py-8">';

print '<ol class="relative border-s border-gray-300 ms-6">';

foreach ($versions as $index => $version)
{
    // Badge automatique (version x.y.z) :
    //   x.y.0         -> New   (1.4.0, 1.5.0...)
    //   x.y.z, z > 0  -> Minor (1.4.1, 1.4.2...)
    $parts = array_map('intval', array_pad(explode('.', $version['version']), 3, 0));

    if ($parts[2] > 0) {
        $badge = 'Minor';
        $badgeColor = 'bg-yellow-100 text-yellow-700';
    } else {
        $badge = 'New';
        $badgeColor = 'bg-green-100 text-green-700';
    }

    print '<li class="mb-10 ms-6">';
    
    // Dot
    print '<span class="absolute flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full -start-3 ring-8 ring-white">';
    print '<svg class="w-3 h-3 text-gray-600" fill="currentColor" viewBox="0 0 20 20">';
    print '<circle cx="10" cy="10" r="10"/>';
    print '</svg>';
    print '</span>';

    // Date
    print '<time class="inline-block mb-2 text-xs font-medium px-2 py-0.5 rounded bg-gray-100 text-gray-600">';
    print $version['date'];
    print '</time>';

    // Title
    print '<p class="flex items-center mb-1 text-lg font-semibold text-gray-900">';
    print 'Version '.$version['version'];
    
    print '<span class="ms-3 text-xs font-medium px-2 py-0.5 rounded '.$badgeColor.'">';
    print $badge;
    print '</span>';

    print '</p>';

    // Description
    print '<div class="text-sm text-gray-600 leading-relaxed">';
    print $version['updates'];
    print '</div>';

    print '</li>';
}

print '</ol>';
print '</div>';
print '</details>'; // fin bloc ChangeLog

print '</div>'; // fin colonne de gauche

// --- Colonne de droite : Liens utiles + Formulaire de feedback ---
print '<div class="flex-1 w-full flex flex-col gap-6">';

// --- Editeur : iouston, Preferred Partner Dolibarr ---
$logoRelPath    = '/newclient/img/iouston.png';
$partnerRelPath = '/newclient/img/dolibarr_preferred_partner.png';
$partnersUrl    = 'https://partners.dolibarr.org';
print '<div class="iou-card flex items-center gap-6">';

// Colonne logos : iouston puis Preferred Partner juste en dessous
print '<div class="shrink-0 flex flex-col items-center gap-3">';
print '<a href="https://www.iouston.com" target="_blank" rel="noopener noreferrer">';
if (file_exists(dol_buildpath($logoRelPath, 0))) {
    print '<img src="'.dol_buildpath($logoRelPath, 1).'" alt="iouston informatique" style="max-height: 60px; width: auto;">';
} else {
    print '<span class="text-2xl font-bold text-gray-800">iouston</span>';
}
print '</a>';
print '<a href="'.$partnersUrl.'" target="_blank" rel="noopener noreferrer" title="Dolibarr Preferred Partners">';
if (file_exists(dol_buildpath($partnerRelPath, 0))) {
    print '<img src="'.dol_buildpath($partnerRelPath, 1).'" alt="Dolibarr Preferred Partner" style="max-height: 45px; width: auto;">';
} else {
    print '<span class="inline-block text-xs font-medium px-2 py-0.5 rounded bg-blue-100 text-blue-700">Dolibarr Preferred Partner</span>';
}
print '</a>';
print '</div>';

// Colonne texte
print '<div>';
print '<p class="font-semibold text-gray-900">'.$langs->trans("NewClientEditorBy").'</p>';
print '<p class="text-sm text-gray-600 mt-1">'.$langs->trans("NewClientIoustonSpecialist").'</p>';
print '<p class="text-sm text-gray-600 mt-1">'.$langs->trans("NewClientPreferredPartner").'</p>';
// "Fait avec <coeur> en <drapeau>" : str_replace plutot que des parametres de trans(), qui echappe le HTML des parametres. SVG inline pour le drapeau (l'emoji drapeau ne s'affiche pas sous Windows)
$heartIcon = '<i class="fa fa-heart" style="color:#e11d48;" aria-label="'.dol_escape_htmltag($langs->trans('NewClientLove')).'" title="'.dol_escape_htmltag($langs->trans('NewClientLove')).'"></i>';
$flagIcon  = '<svg role="img" aria-label="France" width="18" height="12" viewBox="0 0 3 2" style="display:inline-block; vertical-align:-1px; border-radius:2px; box-shadow:0 0 0 1px rgba(0,0,0,.1);"><title>France</title><rect width="1" height="2" x="0" fill="#002654"/><rect width="1" height="2" x="1" fill="#ffffff"/><rect width="1" height="2" x="2" fill="#ce1126"/></svg>';
print '<p class="text-sm text-gray-700 mt-2">'.str_replace(array('{heart}', '{flag}'), array($heartIcon, $flagIcon), $langs->trans("NewClientMadeWithLove")).'</p>';
print '<p class="text-sm text-gray-700 mt-1"><i class="fa fa-check-circle" style="color:#16a34a;"></i> '.$langs->trans("NewClientPurchaseIncludes").'</p>';
print '<p class="text-sm mt-2"><a href="'.$partnersUrl.'" target="_blank" rel="noopener noreferrer">'.$langs->trans("NewClientSeePreferredPartners").' <i class="fa fa-external-link-alt"></i></a></p>';
print '</div>';

print '</div>'; // fin bloc Editeur

// --- Liens utiles ---
print '<div class="iou-card">';
print '<h2 class="iou-about-title">'.$langs->trans('NewClientUsefulLinks').'</h2>';
print '<ul class="list-none p-0 m-0" style="line-height: 2;">';
print '<li><i class="fa fa-book" style="width:20px; display:inline-block;"></i> <a href="https://www.iouston.com/module-dolibarr/suivi-nouveaux-clients/" target="_blank" rel="noopener noreferrer">'.$langs->trans('NewClientDocumentation').'</a></li>';
print '<li><i class="fa fa-life-ring" style="width:20px; display:inline-block;"></i> <a href="https://iouston.space/gestion/public/ticket" target="_blank" rel="noopener noreferrer">'.$langs->trans('NewClientSupport').'</a></li>';
print '</ul>';
print '</div>'; // fin bloc Liens utiles

// --- Formulaire de feedback ---
print '<div class="iou-card">';
print '<h2 class="iou-about-title">'.$langs->trans('NewClientIdeaSuggestion').'</h2>';
print '<p>'.$langs->trans('NewClientFeedbackIntro').'</p>';
print '<form action="'.$FEEDBACK_ENDPOINT.'" method="POST">';
// Cible du formulaire. Pas besoin de préciser de backurl : quicksubmit.php
// utilise par défaut le Referer envoyé par le navigateur pour revenir ici.
print '<input type="hidden" name="fk_questionnaire" value="'.((int) $FEEDBACK_FORM_ID).'">';
// Champ caché : jamais saisi par l'utilisateur, recalculé ici à chaque affichage
// (idprof1/idprof2 selon le pays : à vérifier / adapter selon ce que $mysoc contient chez toi)
print '<input type="hidden" name="suggestion_siret" value="'.dol_escape_htmltag($mysoc->idprof2).'">';
print '<table class="centpercent iou-feedback-table">';
print '<tr><td class="titlefieldcreate">'.$langs->trans('NewClientFeedbackCompany').'</td><td>';
print '<input type="text" class="minwidth300" name="suggestion_societe" value="'.dol_escape_htmltag($mysoc->name).'" required>';
print '</td></tr>';
print '<tr><td>'.$langs->trans('Email').'</td><td>';
print '<input type="email" class="minwidth300" name="suggestion_mail" value="'.dol_escape_htmltag($user->email).'" required>';
print '</td></tr>';
print '<tr><td>'.$langs->trans('Module').'</td><td>';
// Lecture seule : c'est ce module-ci qui envoie le retour, pas à l'utilisateur de le changer
print '<input type="text" class="minwidth300" name="suggestion_module_name" value="'.dol_escape_htmltag($MODULE_NAME).'" readonly>';
print '</td></tr>';
print '<tr><td>'.$langs->trans('NewClientRating').'</td><td>';
// 5 = meilleure note. Ordre inverse dans le DOM (5 -> 1) pour l'effet CSS, affiche 1 -> 5 grace a row-reverse
print '<div class="iou-stars" role="radiogroup" aria-label="'.dol_escape_htmltag($langs->trans('NewClientRating')).'">';
for ($i = 5; $i >= 1; $i--) {
    print '<input type="radio" id="suggestion_note_'.$i.'" name="suggestion_note" value="'.$i.'">';
    print '<label for="suggestion_note_'.$i.'" title="'.$i.'/5">&#9733;</label>';
}
print '</div>';
print '</td></tr>';
print '<tr><td>'.$langs->trans('NewClientFeedbackDetail').'</td><td>';
print '<textarea name="suggestion_detail" class="centpercent" rows="5" required placeholder="'.dol_escape_htmltag($langs->trans('NewClientFeedbackPlaceholder')).'"></textarea>';
print '<div class="text-xs text-gray-500 mt-1">'.$langs->trans('NewClientProblemsGoToSupport', 'https://iouston.space/gestion/public/ticket').'</div>';
print '</td></tr>';
print '</table>';
print '<div class="center" style="margin-top: 15px;">';
print '<input type="submit" class="button" value="'.dol_escape_htmltag($langs->trans('NewClientSend')).'">';
print '</div>';
print '</form>';
print '</div>'; // fin bloc Feedback

print '</div>'; // fin colonne de droite

print '</div>'; // fin conteneur flex ChangeLog / Feedback

print '<script>
(function () {
    var btn = document.getElementById("iou-debug-copy");
    if (!btn) return;
    btn.addEventListener("click", function () {
        var ta = document.getElementById("iou-debug-text");
        var done = function () {
            var ok = document.getElementById("iou-debug-copied");
            ok.style.display = "inline";
            setTimeout(function () { ok.style.display = "none"; }, 2500);
        };
        // API Clipboard (contexte HTTPS) sinon repli execCommand (HTTP)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(ta.value).then(done, function () { ta.select(); document.execCommand("copy"); done(); });
        } else {
            ta.select();
            document.execCommand("copy");
            done();
        }
    });
})();
</script>';

print '</div>'; // fin conteneur general (padding)

// Page end
dol_fiche_end();
llxFooter();
$db->close();

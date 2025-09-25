<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2017 Mikael Carlavan <contact@mika-carl.fr>
 * Copyright (C) 2019 Julien Marchand <julien.marchand@iouston.com>
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
 *  \file       htdocs/newclient/index.php
 *  \ingroup    newclient
 *  \brief      Page to show product set
 */


$res=@include("../../main.inc.php");                   // For root directory
if (! $res) $res=@include("../../../main.inc.php");    // For "custom" directory


// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

dol_include_once("/newclient/lib/newclient.lib.php");

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
	array('version' => '1.0.8', 'date' => '23/08/2024', 'updates' => $langs->trans('update108')),
	array('version' => '1.0.7', 'date' => '16/02/2023', 'updates' => $langs->trans('update107')),
    array('version' => '1.0.6', 'date' => '25/01/2023', 'updates' => $langs->trans('20230125update')),
    array('version' => '1.0.5', 'date' => '06/05/2022', 'updates' => $langs->trans('20220506update')),
    array('version' => '1.0.4', 'date' => '21/02/2022', 'updates' => $langs->trans('20220221update')),
    array('version' => '1.0.3', 'date' => '29/06/2021', 'updates' => $langs->trans('20210629update')),
    array('version' => '1.0.2', 'date' => '28/09/2020', 'updates' => $langs->trans('20200928Update')),
    array('version' => '1.0.1', 'date' => '04/06/2020', 'updates' => $langs->trans('20200604Update')),
    array('version' => '1.0.0', 'date' => '29/08/2018', 'updates' => $langs->trans('FirstVersion')),
);

/*
 * View
 */

$form = new Form($db);

llxHeader('', $langs->trans('FraisAutoAbout'));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'. $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans('FraisAutoAbout'), $linkback);

// Configuration header
$head = newclient_prepare_admin_head();
dol_fiche_head(
	$head,
	'about',
	$langs->trans("ModuleFraisAutoName"),
	0,
	'newclient@newclient'
);

// About page goes here
echo $langs->trans("FraisAutoAboutPage");

echo '<br />';

$url = 'http://www.iouston.com/systeme-gestion-entreprise-dolibarr/modules-dolibarr/module-dolibarr-frais-auto';

print '<h2>'.$langs->trans("About").'</h2>';
print $langs->transnoentities("FraisAutoAboutDescLong", $url, $url);

print '<h2>'.$langs->trans("MaintenanceAndSupportTitle").'</h2>';
print $langs->transnoentities("MaintenanceAndSupportDescLong");

print '<h2>'.$langs->trans("UpdateTitle").'</h2>';
print $langs->transnoentities("UpdateDescLong");

print '<h2>'.$langs->trans("ModulesTitle").'</h2>';
print $langs->transnoentities("ModulesDescLong");

echo '<br />';

print '<a href="http://www.dolistore.com">'.img_picto('dolistore', dol_buildpath('/newclient/img/dolistore.png', 1), '', 1).'</a>';

print '<hr />';

print '<a href="http://www.iouston.com">'.img_picto('iouston', dol_buildpath('/newclient/img/iouston.png', 1), '', 1).'</a>';

echo '<br />';

print $langs->trans("IoustonDesc");

print '<hr />';
print '<h2>'.$langs->trans("ChangeLog").'</h2>';


print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("ChangeLogVersion").'</td>';
print '<td>'.$langs->trans("ChangeLogDate").'</td>';
print '<td>'.$langs->trans("ChangeLogUpdates").'</td>';
print "</tr>\n";

foreach ($versions as $version)
{
	print '<tr class="oddeven">';
	print '<td>';
	print $version['version'];
	print '</td>';
	print '<td>';
	print $version['date'];
	print '</td>';
	print '<td>';
	print $version['updates'];
	print '</td>';
	print '</tr>';
}


print '</table>';

// Page end
dol_fiche_end();
llxFooter();
$db->close();

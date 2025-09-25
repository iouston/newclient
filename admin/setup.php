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
 *  \file       htdocs/newclient/admin/setup.php
 *  \ingroup    newclient
 *  \brief      Admin page
 */


$res=@include("../../main.inc.php");                   // For root directory
if (! $res) $res=@include("../../../main.inc.php");    // For "custom" directory

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
dol_include_once("/newclient/lib/newclient.lib.php");
include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

// Translations
$langs->load("newclient@newclient");

// Access control
if (! $user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */

// Action mise a jour ou ajout d'une constante
if ($action == 'updateconstante')
{
	$constname=GETPOST('constname','alpha');
	$consttype=GETPOST('consttype','alpha');
	$constvalue=GETPOST('constvalue','restricthtml');

$res = dolibarr_set_const($db,$constname,$constvalue,'chaine',0,$constnote,$conf->entity);

if (! $res > 0) $error++;

	if (! $error)
	{
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	}
	else
	{
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}


// MAJ en masse de tous les clients
if ($action == 'updateall')
{
	$db->begin();
    $sql = "UPDATE ".MAIN_DB_PREFIX."societe_extrafields se
            JOIN (
                SELECT p.fk_soc AS socid,
                       MIN(p.date_signature) AS date_premier_devis_signe
                FROM ".MAIN_DB_PREFIX."propal p
                WHERE p.fk_statut = 2
                  AND p.entity = ".((int) $conf->entity)."
                GROUP BY p.fk_soc
            ) x ON x.socid = se.fk_object
            SET se.date_signature_premier_devis = x.date_premier_devis_signe";

    dol_syslog("newclient updateall sql=".$sql, LOG_DEBUG);

    $resql = $db->query($sql);
    if ($resql) {
        $db->commit();
        setEventMessage($langs->trans("NewClientMassUpdateDone"));
    } else {
        $db->rollback();
        setEventMessage($langs->trans("NewClientErrorMassUpdate").' : '.$db->lasterror(), 'errors');
    }
}

if (preg_match('/set_(.*)/',$action,$reg))
{
	$code=$reg[1];
	$value=(GETPOST($code) ? GETPOST($code) : 1);
	if (dolibarr_set_const($db, $code, $value, 'chaine', 0, '', $conf->entity) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

else if (preg_match('/del_(.*)/',$action,$reg))
{
	$code=$reg[1];
	if (dolibarr_del_const($db, $code, $conf->entity) > 0)
	{
		Header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	}
	else
	{
		dol_print_error($db);
	}
}

/*
 * View
 */
llxHeader('', $langs->trans('NewClientSetup'));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans('NewClientSetup'), $linkback);

// Configuration header
$head = newclient_prepare_admin_head();
dol_fiche_head(
	$head,
	'settings',
	$langs->trans("ModuleNewClientName"),
	0,
	"newclient@newclient"
);

// Setup page goes here
echo $langs->trans("NewClientSetupPage");
print load_fiche_titre($langs->trans("Settingslist"),'','');

print '<table class="noborder" width="100%">';
print '<tbody>';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Libellé").'</td>';
print '<td>'.$langs->trans("Valeur").'</td>';
print "</tr>\n";
print '<tr class="oddeven">';
print '<td>'.$langs->trans("NewClientUpdateAll").'</td>';
print '<td><a class="button" href="'.$_SERVER['PHP_SELF'].'?action=updateall&token='.newtoken().'">'.$langs->trans('GoUpdateAll').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("NewClientShowRandomGiff").'</td>';
print '<td>';
    if (empty($conf->global->NEWCLIENT_SHOW_RANDOM_GIF))
    {
        print '<a href="'.$_SERVER['PHP_SELF'].'?action=set_NEWCLIENT_SHOW_RANDOM_GIF&token='.newToken().'">'.img_picto($langs->trans("Disabled"),'switch_off').'</a>';
    }
    else
    {
        print '<a href="'.$_SERVER['PHP_SELF'].'?action=del_NEWCLIENT_SHOW_RANDOM_GIF&token='.newToken().'">'.img_picto($langs->trans("Enabled"),'switch_on').'</a>';
    }
    print '</td>';
print '</tr>';

//Img list
print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<tr class="oddeven">';
print '<td>';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="updateconstante">';
print '<input type="hidden" name="constname" value="NEWCLIENT_LIST_GIF">';
print '<input type="hidden" name="constnote" value="">';
print $langs->trans('DescNEWCLIENT_LIST_GIF');
print '</td>';
print '<td>';
print '<textarea rows="25" cols="100" name="constvalue">'.(isset($conf->global->NEWCLIENT_LIST_GIF) ? $conf->global->NEWCLIENT_LIST_GIF : '').'</textarea>';
print '</td>';
print '<td align="center">';
print '<input type="submit" class="button" value="'.$langs->trans("Update").'" name="Button">';
print '</td>';
print '</tr>';
print '</form>';

print '</tbody>';
print '</table>';


// Page end
dol_fiche_end();
llxFooter();

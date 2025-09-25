<?php
/* Copyright (C) 2003-2006 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (c) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2012      Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2013      Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2015      Jean-François Ferry  <jfefe@aternatik.fr>
 * Copyright (C) 2020      Maxime DEMAREST      <maxime@indelog.fr>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/compta/facture/stats/index.php
 *  \ingroup    facture
 *  \brief      Page des stats factures
 */

// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/newclient/class/newclient.class.php';


$WIDTH = DolGraph::getDefaultGraphSizeForStats('width');
$HEIGHT = DolGraph::getDefaultGraphSizeForStats('height');

// Load translation files required by the page
$langs->loadLangs(array('bills', 'companies', 'other','newclient@newclient'));

$mode = 'customer';

$object_status = GETPOST('object_status', 'intcomma');
$typent_id = GETPOST('typent_id', 'int');

$userid = GETPOST('userid', 'int');
$socid = GETPOST('socid', 'int');
$custcats = GETPOST('custcats', 'array');
// Security check
if ($user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

$nowyear = dol_print_date(dol_now('gmt'), "%Y", 'gmt');
$year = GETPOST('year') > 0 ? GETPOST('year', 'int') : $nowyear;
$nowmonth = dol_print_date(dol_now('gmt'), "%m", 'gmt');
$month = GETPOST('month') > 0 ? GETPOST('month', 'int') : $nowmonth;

// $startyear = $year - (empty($conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS) ? 2 : max(1, min(10, $conf->global->MAIN_STATS_GRAPHS_SHOW_N_YEARS)));
// $endyear = $year;


/*
 * View
 */
$form = new Form($db);
$formcompany = new FormCompany($db);
$formother = new FormOther($db);
$newclient = new NewClient($db);

llxHeader();

$picto = 'newclient@newclient';
$title = $langs->trans("NewClientStatistics");
$dir = $conf->facture->dir_temp;

print load_fiche_titre($title, '', $picto);

dol_mkdir($dir);
$y = date('Y');
$data=array();
$arrayyears=array();
$arraymonths = array();

$out= '<table class="noborder centpercent">';
$out.= '<tr class="liste_titre">';
$out.= '<td>Année</td>';
for ($m = 1; $m <= 12; $m++) {
    $out.= '<td align="center">'.dol_print_date(dol_mktime(12,0,0,$m,1,$y), '%b').'</td>';
}
$out.= '<td>Total</td>';
$out.= '</tr>';
// ligne de l'année
for ($i = 0; $i < 3; $i++) {
	$year = $y - $i;
	$arrayyears[]=$year;
	$stats = $newclient->getNbByMonthYear($year);
	
	$out.= '<tr>';
	$out.= '<td>'.$year.'</td>';
	
	for ($m = 1; $m <= 12; $m++) {
	    $val = isset($stats[$m]) ? $stats[$m] : 0;
	    $data[$year][$m]=$val;
	    $total +=$val;
	    $out.= '<td align="center">'.$val.'</td>';
	}
	$out.= '<td>'.$total.'</td>';
	$out.= '</tr>';
}
$out.= '</table>';

$datatransposed = array();

foreach ($data as $year => $months) {
    for ($m = 1; $m <= 12; $m++) {
        $val = isset($months[$m]) ? $months[$m] : 0;
        $datatransposed[$m][$year] = $val;
        $arraymonths[sprintf('%02d', $m)] = sprintf('%02d', $m);
    }
}
$legend = array_keys($data);
sort($legend);
$filenamenb = $dir."/newclient-".$year.".png";
$fileurlnb = DOL_URL_ROOT.'/viewimage.php?modulepart=nexclient&file=newclientnbinyear-'.$year.'.png';

$datagraph = array();
foreach ($datatransposed as $monthdata => $years) {
    $line = array($monthdata);
    foreach ($legend as $year) {
        $line[] = isset($years[$year]) ? $years[$year] : 0;
    }
    $datagraph[] = $line;
}

$px1 = new DolGraph();
$mesg = $px1->isGraphKo();
if (!$mesg) {
	$px1->SetData($datatransposed);
	$i = $startyear;
	$legend = array();
	while ($i <= $endyear) {
		$legend[] = $i;
		$i++;
	}
$px1 = new DolGraph();
$px1->SetData($datagraph);
$px1->SetLegend($legend);
$px1->SetYLabel($langs->trans("NumberOfNewClient"));
$px1->SetHorizTickIncrement(1);
$px1->SetMaxValue($px1->GetCeilMaxValue());
$px1->SetTitle($langs->trans("NumberOfNewClientByMonth"));
$px1->draw($filenamenb, $fileurlnb);
}

complete_head_from_modules($conf, $langs, null, $head, $h, $type);

print dol_get_fiche_head($head, 'byyear', $langs->trans("Statistics"), -1);

print '<div class="fichecenter"><div class="fichethirdleft">';

// Show filter box
print '<form name="stats" method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="liste_titre" colspan="2">'.$langs->trans("Filter").'</td></tr>';

// Category - Pourquoi pas ? à prévoir dans le futur
// if (isModEnabled('categorie')) {
// 	if ($mode == 'customer') {
// 		$cat_type = Categorie::TYPE_CUSTOMER;
// 		$cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("Customer"));
// 	}
// 	if ($mode == 'supplier') {
// 		$cat_type = Categorie::TYPE_SUPPLIER;
// 		$cat_label = $langs->trans("Category").' '.lcfirst($langs->trans("Supplier"));
// 	}
// 	print '<tr><td>'.$cat_label.'</td><td>';
// 	$cate_arbo = $form->select_all_categories($cat_type, null, 'parent', null, null, 1);
// 	print img_picto('', 'category', 'class="pictofixedwidth"');
// 	print $form->multiselectarray('custcats', $cate_arbo, GETPOST('custcats', 'array'), 0, 0, 'widthcentpercentminusx maxwidth300');
// 	//print $formother->select_categories($cat_type, $categ_id, 'categ_id', true);
// 	print '</td></tr>';
// }

// User
print '<tr><td>'.$langs->trans("CreatedBy").'</td><td>';
print img_picto('', 'user', 'class="pictofixedwidth"');
print $form->select_dolusers($userid, 'userid', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'widthcentpercentminusx maxwidth300');
print '</td></tr>';

// Month
print '<tr><td>'.$langs->trans("Month").'</td><td>';

print $form->selectarray('month', $arraymonths, $month, 0, 0, 0, '', 0, 0, 0, '', 'width75');
print '</td></tr>';

// Year
print '<tr><td>'.$langs->trans("Year").'</td><td>';
if (!in_array($year, $arrayyears)) {
	$arrayyears[$year] = $year;
}
if (!in_array($nowyear, $arrayyears)) {
	$arrayyears[$nowyear] = $nowyear;
}
arsort($arrayyears);
print $form->selectarray('year', $arrayyears, $year, 0, 0, 0, '', 0, 0, 0, '', 'width75');
print '</td></tr>';
print '<tr><td class="center" colspan="2"><input type="submit" name="submit" class="button small" value="'.$langs->trans("Refresh").'"></td></tr>';
print '</table>';
print '</form>';
print '<br><br>';


print '</div><div class="fichetwothirdright">';


// Show graphs
print '<table class="border centpercent"><tr class="pair nohover"><td align="center">';
if ($mesg) {
	print $mesg;
} else {
	print $px1->show();
}
print '</td></tr></table>';


print '</div></div>';
print '<div class="clearboth"></div>';

print $out;

print load_fiche_titre($langs->trans('CAGeneratedByNewClient').$year.'-'.$month, '', $picto);

$infosCA = $newclient->getCAFromNewClient($year,$month);
$out = '<table class="noborder centpercent">';
$out.= '<tr class="liste_titre">';
$out.= '<td>'.$langs->trans('Customer').'</td>';
$out.= '<td>'.$langs->trans('Zip').'</td>';
$out.= '<td>'.$langs->trans('Town').'</td>';
$out.= '<td align="right">'.$langs->trans('NBFac').'</td>';
$out.= '<td align="right">'.$langs->trans('CAGenerated').'</td>';
$out .='</tr>';

foreach($infosCA as $key=>$soc){
	if(is_numeric($key)){
		$out.= '<tr class="oddeven">';
		$out.='<td><a href="'.dol_buildpath('compta/facture/list.php?socid='.$soc['rowid'],2).'" target="_blank">'.$soc['nom'].'</a></td>';
		$out.='<td>'.$soc['zip'].'</td>';
		$out.='<td>'.$soc['town'].'</td>';
			$nbfac = count($soc['fac']);
			$totalnbfac+=$nbfac;
			$totalnbsoc++;
		$out.='<td align="right">'.$nbfac.'</td>';
		
		$out.='<td align="right">'.price($soc['societe_total_ht']).'</td>';
		$out .='</tr>';
	}	else{
		continue;
	}
	
}
$out.'<tr>';
$out.= '<td><b>'.$langs->trans('Total').'</b></td>';
$out.= '<td colspan=2><b>'.$totalnbsoc.' '.$langs->trans('NewClientGeneratedCA').'</b></td>';
$out.= '<td align="right"><b>'.$totalnbfac.'</b></td>';
$out.= '<td align="right"><b>'.price($infosCA['total_total_ht']).'</b></td>';
$out.'</tr>';
$out .='</table>';


print $out;

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();

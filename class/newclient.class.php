<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2017 Mikael Carlavan <contact@mika-carl.fr>
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
 *  \file       htdocs/newclient/class/newclient.class.php
 *  \ingroup    newclient
 *  \brief      File of class to manage predefined products sets
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class to manage products or services
 */
class NewClient extends CommonObject
{
	public $element='newclientstats';
	public $table_element='societe_extrafields';
	public $fk_element='fk_object';
	public $picto = 'generic';
	public $ismultientitymanaged = 1;	// 0=No test on entity, 1=Test with field entity, 2=Test with link by societe

	/**
	 * {@inheritdoc}
	 */
	protected $table_ref_field = 'id';


	/**
     * Product set ref
     * @var string
     */
	public $mods = array();

	/**
	 *  Constructor
	 *
	 *  @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		global $langs;

		$this->db = $db;
	}

	/**
 *  Return number of customers by month for a given year
 *
 *  @param int $year     Year to analyze
 *  @param int $fk_user  User ID of proposal author (0 = all users)
 *  @return array        Array with key = month, value = nb of customers
 */
public function getNbByMonthYear($year, $fk_user = 0)
{
    global $db;

    $sql = "SELECT MONTH(se.date_signature_premier_devis) as month,";
    $sql .= " COUNT(DISTINCT s.rowid) as nb";
    $sql .= " FROM ".MAIN_DB_PREFIX."societe_extrafields se";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = se.fk_object";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."propal p ON p.fk_soc = s.rowid";
    $sql .= " WHERE se.date_signature_premier_devis  IS NOT NULL";
    $sql .= " AND YEAR(se.date_signature_premier_devis ) = ".((int) $year);

    if ($fk_user > 0) {
        $sql .= " AND p.fk_user_author = ".((int) $fk_user);
    }
    $sql .= " GROUP BY MONTH(se.date_signature_premier_devis )";
    $sql .= " ORDER BY month";

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(get_class($this)."::getNbByMonthYear sql=".$sql." - ".$db->lasterror(), LOG_ERR);
        return array();
    }

    $result = array();

    while ($obj = $db->fetch_object($resql)) {
        $result[(int) $obj->month] = (int) $obj->nb;
    }

    $db->free($resql);

    return $result;
}

	function getCAFromNewClient($year,$month){
		global $db, $conf;
		$result = array();
	    $sql = "SELECT f.fk_soc as socid, f.total_ht as total_ht, f.ref as facref, f.rowid as facid, s.nom as snom, s.zip as szip, s.town as stown ";
	    $sql.= "FROM llx_facture as f ";
	    $sql.= "JOIN llx_societe as s ON s.rowid=fk_soc ";
	    $sql.= "WHERE f.fk_soc IN ( ";
	    $sql.= "SELECT se.fk_object as ids FROM llx_societe_extrafields as se WHERE se.date_signature_premier_devis >= '".$year."-".$month."-01')";
		$sql.= " AND f.datef>='".$year."-01-01' AND f.entity=".$conf->entity." and f.fk_statut IN(1,2)"; 
			
		$resql = $db->query($sql);
	    if (!$resql) {
	        dol_syslog(get_class($this)."::getCAFromNewClient sql=".$sql." - ".$db->lasterror(), LOG_ERR);
	        return $result;
	    }
	    
	    while ($obj = $db->fetch_object($resql)) {
	        $result[$obj->socid]['rowid']=$obj->socid;
	        $result[$obj->socid]['nom']=$obj->snom;
	        $result[$obj->socid]['zip']=$obj->szip;
	        $result[$obj->socid]['town']=$obj->stown;
	        $result[$obj->socid]['societe_total_ht']+=$obj->total_ht;
	        $result[$obj->socid]['fac'][$obj->facid] = array('rowid'=>$obj->facid,'ref'=>$obj->facref,'total_ht'=>$obj->total_ht);
	        $result['total_total_ht']+=$obj->total_ht;
	    }

	    $total_total_ht = $result['total_total_ht'];
		unset($result['total_total_ht']);

		// Tri décroissant sur societe_total_ht
		uasort($result, function($a, $b) {
    	return $b['societe_total_ht'] <=> $a['societe_total_ht'];
		});

		// On remet total_total_ht à la fin
		$result['total_total_ht'] = $total_total_ht;


	    $db->free($resql);
	    return $result;
	}
	
}

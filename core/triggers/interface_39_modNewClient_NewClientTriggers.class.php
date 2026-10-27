<?php
/* Copyright (C) 2017 Mikael Carlavan <contact@mika-carl.fr>
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modNewClient_NewClientTriggers.class.php
 * \ingroup newclient
 * \brief   Example trigger.
 *
 */


require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
dol_include_once("/newclient/class/newclient.class.php");
dol_include_once("newclient/class/produit.newclient.class.php");

/**
 *  Class of triggers for NewClient module
 */
class InterfaceNewClientTriggers extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "products";
        $this->description = "NewClient triggers.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = '1.0.0';
        $this->picto = 'newclient@newclient';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }


    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string $action Event action code
     * @param CommonObject $object Object
     * @param User $user Object user
     * @param Translate $langs Object langs
     * @param Conf $conf Object conf
     * @return int                    <0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($conf->newclient->enabled)) return 0;     // Module not active, we do nothing
        if ($action != 'PROPAL_CLOSE_SIGNED') return 0;

        // Pas de felicitations si specialtriggers refuse la signature (devis sans projet) :
        // selon la version de Dolibarr, les triggers suivants peuvent s'executer malgre son -1
        if (!empty($conf->specialtriggers->enabled)
            && getDolGlobalString('SPECIALTRIGGER_PROPAL_CLOSE_SIGNED_ADD_PROJECT_BUDGET')
            && (empty($object->fk_project) || (int) $object->fk_project <= 0)) {
            return 0;
        }

        $langs->loadLangs(array("other", "newclient@newclient"));

        $socid = (int) $object->socid;
        $date_signature = $object->date_signature;
        if ($socid <= 0 || empty($date_signature)) return 0;

        if (empty($object->thirdparty)) $object->fetch_thirdparty();

        // Image aleatoire de felicitations (array_rand sur un tableau vide = erreur fatale en PHP 8)
        $gifUrl = '';
        $gifs = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', getDolGlobalString('NEWCLIENT_LIST_GIF')))));
        if (count($gifs)) $gifUrl = $gifs[array_rand($gifs)];

        $sql = "SELECT date_signature_premier_devis FROM ".MAIN_DB_PREFIX."societe_extrafields WHERE fk_object = ".$socid;
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->errors[] = $this->db->lasterror();
            return -1;
        }
        $obj = $this->db->fetch_object($resql);

        $clientName = dol_escape_htmltag($object->thirdparty->name);
        $devisRef   = dol_escape_htmltag($object->ref);

        if (empty($obj->date_signature_premier_devis)) {
            $sql = "UPDATE ".MAIN_DB_PREFIX."societe_extrafields SET date_signature_premier_devis = '".$this->db->idate($date_signature)."' WHERE fk_object = ".$socid;
            if (!$this->db->query($sql)) {
                $this->errors[] = $this->db->lasterror();
                return -1;
            }
            // transnoentitiesnoconv : les parametres sont deja echappes, trans() les re-encoderait (et laisserait passer '<a ')
            $msgtxt  = $langs->transnoentitiesnoconv('NewClientCongratsFirstPropal', $clientName, $devisRef);
            $showGif = getDolGlobalInt('NEWCLIENT_SHOW_RANDOM_GIF');
        } else {
            $msgtxt  = $langs->transnoentitiesnoconv('NewClientCongratsPropal', $clientName, $devisRef);
            $showGif = getDolGlobalInt('NEWCLIENT_ON_EACH_PROPAL_SIGNED_SHOW_RANDOM_GIF');
        }

        if ($showGif && $gifUrl) {
            $message = "<div style='display:flex; align-items:center; gap:15px;'>"
                ."<img src=\"".dol_escape_htmltag($gifUrl)."\" alt=\"".dol_escape_htmltag($langs->transnoentitiesnoconv('NewClientWellDone'))."\" style=\"max-height:250px; border-radius:10px;\" />"
                ."<div style='font-size:16px;'>".$msgtxt."</div></div>";
        } else {
            $message = $msgtxt;
        }

        setEventMessage($message, 'mesgs');

        return 1;
    }
}
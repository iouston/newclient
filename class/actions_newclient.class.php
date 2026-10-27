<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2017 Mikael Carlavan <contact@mika-carl.fr>
 * Copyright (C) 2024 Julien Marchand <julien.marchand@iouston.com>
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
 *  \file       htdocs/bonlivraison/class/actions_bonlivraison.class.php
 *  \ingroup    bonlivraison
 *  \brief      File of class to manage actions
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/usergroup.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/usergroup.class.php';
dol_include_once('/comm/propal/class/propal.class.php');


class ActionsNewClient
{
    //Qualifier le type de perte de devis
    function formConfirm($parameters, &$object, &$action, $hookmanager)
        {
            global $db, $langs;
                    
       if ($object->element=='propal' && $action == 'closeas') {
                // Récupérer les options de l'extrafield type_refus
                $extrafields = new ExtraFields($db);
                $extrafields->fetch_name_optionals_label('propal');
                $formconfirm = null;
                $form = new Form($db);          

                $formquestions = array();                      
                
                //Statut
                $langs->loadLangs(array('propal', 'newclient@newclient'));
                $options=array(2=>$langs->trans('PropalStatusSigned'),3=>$langs->trans('PropalStatusNotSigned'));
                $formquestions[] = array(
                    'type'   => 'select',
                    'name'   => 'status',
                    'label'  => $langs->trans('SetStatus'),
                    'values' => $options,
                );

                $formquestions[] = array(
                    'type'   => 'text',
                    'name'   => 'note',
                    'label'  => $langs->trans('SetNote'),
                );
                
                //Raison du refus
                $options = $extrafields->attributes['propal']['param']['type_refus']['options'] ?? [];
                $formquestions[] = array(
                    'type'   => 'select',
                    'name'   => 'options_type_refus',
                    'label'  => $langs->trans('SetTypeRefus'),
                    'values' => $options,
                );

                $formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id . '#closeas', $langs->trans('CloseWithReason'), $langs->trans('ConfirmCloseAsPropal'), 'closepropalwithreason', $formquestions, 'yes', 0);
        
        $this->resprints = $formconfirm;
        return 1;
        }
    }  

    function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $db, $conf, $user;

        $langs->load('newclient@newclient');

        if ($object->element == 'propal' && $action == 'closepropalwithreason') {
            $status = GETPOSTINT('status');
            $note   = GETPOST('note', 'restricthtml');
            $reason = GETPOST('options_type_refus', 'alphanohtml');

            $noteconcat = $object->note_private.chr(10).$note;
            $object->array_options['options_type_refus'] = $reason;

            // Une seule transaction : si un trigger bloque la cloture (ex : devis sans projet),
            // le type de refus n'est pas enregistre non plus
            $db->begin();
            $result = $object->update($user);
            if ($result > 0) {
                $result = $object->closeProposal($user, $status, $noteconcat);
            }

            if ($result < 0) {
                $db->rollback();
                // Affiche les erreurs remontees par les triggers (closeProposal les fusionne dans $object->errors)
                setEventMessages($object->error, $object->errors, 'errors');
                $action = '';
                return 0;
            }

            $db->commit();
            header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
            exit;
        }

        $this->resprints = '';
        return 0;
    }

}
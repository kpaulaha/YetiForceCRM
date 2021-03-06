<?php
/*+***********************************************************************************************************************************
 * The contents of this file are subject to the YetiForce Public License Version 1.1 (the "License"); you may not use this file except
 * in compliance with the License.
 * Software distributed under the License is distributed on an "AS IS" basis, WITHOUT WARRANTY OF ANY KIND, either express or implied.
 * See the License for the specific language governing rights and limitations under the License.
 * The Original Code is YetiForce.
 * The Initial Developer of the Original Code is YetiForce. Portions created by YetiForce are Copyright (C) www.yetiforce.com.
 * All Rights Reserved.
 *************************************************************************************************************************************/
require_once('modules/Vtiger/CRMEntity.php');
require_once('include/Tracker.php');

class LettersIn extends CRMEntity {
    var $db, $log; // Used in class functions of CRMEntity

    var $table_name = 'vtiger_lettersin';
    var $table_index= 'lettersinid';
    var $column_fields = Array();

    /** Indicator if this is a custom module or standard module */
    var $IsCustomModule = true;

    /**
     * Mandatory table for supporting custom fields.
     */
    var $customFieldTable = Array('vtiger_lettersincf', 'lettersinid');

    /**
     * Mandatory for Saving, Include tables related to this module.
     */
    var $tab_name = Array('vtiger_crmentity', 'vtiger_lettersin', 'vtiger_lettersincf');

    /**
     * Mandatory for Saving, Include tablename and tablekey columnname here.
     */
    var $tab_name_index = Array(
        'vtiger_crmentity' => 'crmid',
        'vtiger_lettersin'   => 'lettersinid',
        'vtiger_lettersincf' => 'lettersinid');

    /**
     * Mandatory for Listing (Related listview)
     */
    var $list_fields = Array (
        /* Format: Field Label => Array(tablename, columnname) */
        // tablename should not have prefix 'vtiger_'
        'Number'=> Array('lettersin', 'number'),
        'Title'=> Array('lettersin', 'title'),
		'Assigned To' => Array('crmentity','smownerid'),
        'Created Time'=>Array('crmentity','createdtime'),
    );
    var $list_fields_name = Array(
        /* Format: Field Label => fieldname */
        'Number'=> 'number',
        'Title'=>'title',
		'Assigned To' => 'assigned_user_id',
        'Created Time'=>'createdtime',
    );

    // Make the field link to detail view from list view (Fieldname)
    var $list_link_field = 'title';

    // For Popup listview and UI type support
    var $search_fields = Array(
        'Number'=> Array('lettersin', 'number'),
        'Title'=> Array('lettersin', 'title'),
		'Assigned To' => Array('crmentity','smownerid'),
        'Created Time'=>Array('crmentity','createdtime'),
    );
    var $search_fields_name = Array(
        'Number'=> 'number',
        'Title'=>'title',
		'Assigned To' => 'assigned_user_id',
        'Created Time'=>'createdtime',
    );

    // For Popup window record selection
    var $popup_fields = Array('title');

    // Placeholder for sort fields - All the fields will be initialized for Sorting through initSortFields
    var $sortby_fields = Array();

    // For Alphabetical search
    var $def_basicsearch_col = 'title';

    // Column value to use on detail view record text display
    var $def_detailview_recname = 'title';

    // Required Information for enabling Import feature
    var $required_fields = Array('title'=>1);

    // Callback function list during Importing
    var $special_functions = Array('set_import_assigned_user');

    var $default_order_by = 'title';
    var $default_sort_order='ASC';
    // Used when enabling/disabling the mandatory fields for the module.
    // Refers to vtiger_field.fieldname values.
    var $mandatory_fields = Array('createdtime', 'modifiedtime', 'title', 'assigned_user_id');

    function __construct() {
        global $log, $currentModule;
        $this->column_fields = getColumnFields(get_class($this));
        $this->db = PearDatabase::getInstance();
        $this->log = $log;
    }

   function save_module($module) {
    }

    /**
     * Return query to use based on given modulename, fieldname
     * Useful to handle specific case handling for Popup
     */
    function getQueryByModuleField($module, $fieldname, $srcrecord) {
        // $srcrecord could be empty
    }

    /**
     * Get list view query (send more WHERE clause condition if required)
     */
    function getListQuery($module, $where='') {
		$query = "SELECT vtiger_crmentity.*, $this->table_name.*";

		// Keep track of tables joined to avoid duplicates
		$joinedTables = array();

		// Select Custom Field Table Columns if present
		if(!empty($this->customFieldTable)) $query .= ", " . $this->customFieldTable[0] . ".* ";

		$query .= " FROM $this->table_name";

		$query .= "	INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = $this->table_name.$this->table_index";

		$joinedTables[] = $this->table_name;
		$joinedTables[] = 'vtiger_crmentity';

		// Consider custom table join as well.
		if(!empty($this->customFieldTable)) {
			$query .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index";
			$joinedTables[] = $this->customFieldTable[0];
		}
		$query .= " LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid";
		$query .= " LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";

		$joinedTables[] = 'vtiger_users';
		$joinedTables[] = 'vtiger_groups';

		$linkedModulesQuery = $this->db->pquery("SELECT distinct fieldname, columnname, relmodule FROM vtiger_field" .
				" INNER JOIN vtiger_fieldmodulerel ON vtiger_fieldmodulerel.fieldid = vtiger_field.fieldid" .
				" WHERE uitype='10' AND vtiger_fieldmodulerel.module=?", array($module));
		$linkedFieldsCount = $this->db->num_rows($linkedModulesQuery);

		for($i=0; $i<$linkedFieldsCount; $i++) {
			$related_module = $this->db->query_result($linkedModulesQuery, $i, 'relmodule');
			$fieldname = $this->db->query_result($linkedModulesQuery, $i, 'fieldname');
			$columnname = $this->db->query_result($linkedModulesQuery, $i, 'columnname');

			$other =  CRMEntity::getInstance($related_module);
			vtlib_setup_modulevars($related_module, $other);

			if(!in_array($other->table_name, $joinedTables)) {
				$query .= " LEFT JOIN $other->table_name ON $other->table_name.$other->table_index = $this->table_name.$columnname";
				$joinedTables[] = $other->table_name;
			}
		}

		global $current_user;
		$query .= $this->getNonAdminAccessControlQuery($module,$current_user);
		$query .= "	WHERE vtiger_crmentity.deleted = 0 ".$usewhere;
		return $query;
    }

    /**
     * Apply security restriction (sharing privilege) query part for List view.
     */
    function getListViewSecurityParameter($module) {
        global $current_user;
        require('user_privileges/user_privileges_'.$current_user->id.'.php');
        require('user_privileges/sharing_privileges_'.$current_user->id.'.php');

        $sec_query = '';
        $tabid = getTabid($module);

        if($is_admin==false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1
            && $defaultOrgSharingPermission[$tabid] == 3) {

                $sec_query .= " AND (vtiger_crmentity.smownerid in($current_user->id) OR vtiger_crmentity.smownerid IN
                    (
                        SELECT vtiger_user2role.userid FROM vtiger_user2role
                        INNER JOIN vtiger_users ON vtiger_users.id=vtiger_user2role.userid
                        INNER JOIN vtiger_role ON vtiger_role.roleid=vtiger_user2role.roleid
                        WHERE vtiger_role.parentrole LIKE '".$current_user_parent_role_seq."::%'
                    )
                    OR vtiger_crmentity.smownerid IN
                    (
                        SELECT shareduserid FROM vtiger_tmp_read_user_sharing_per
                        WHERE userid=".$current_user->id." AND tabid=".$tabid."
                    )
                    OR
                        (";

                    // Build the query based on the group association of current user.
                    if(sizeof($current_user_groups) > 0) {
                        $sec_query .= " vtiger_groups.groupid IN (". implode(",", $current_user_groups) .") OR ";
                    }
                    $sec_query .= " vtiger_groups.groupid IN
                        (
                            SELECT vtiger_tmp_read_group_sharing_per.sharedgroupid
                            FROM vtiger_tmp_read_group_sharing_per
                            WHERE userid=".$current_user->id." and tabid=".$tabid."
                        )";
                $sec_query .= ")
                )";
        }
        return $sec_query;
    }

    /**
     * Create query to export the records.
     */
    function create_export_query($where)
    {
		global $current_user;

		include("include/utils/ExportUtils.php");

		//To get the Permitted fields query and the permitted fields list
		$sql = getPermittedFieldsQuery('LettersIn', "detail_view");

		$fields_list = getFieldsListFromQuery($sql);

		$query = "SELECT $fields_list, vtiger_users.user_name AS user_name
					FROM vtiger_crmentity INNER JOIN $this->table_name ON vtiger_crmentity.crmid=$this->table_name.$this->table_index";

		if(!empty($this->customFieldTable)) {
			$query .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index";
		}

		$query .= " LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";
		$query .= " LEFT JOIN vtiger_users ON vtiger_crmentity.smownerid = vtiger_users.id and vtiger_users.status='Active'";

		$linkedModulesQuery = $this->db->pquery("SELECT distinct fieldname, columnname, relmodule FROM vtiger_field" .
				" INNER JOIN vtiger_fieldmodulerel ON vtiger_fieldmodulerel.fieldid = vtiger_field.fieldid" .
				" WHERE uitype='10' AND vtiger_fieldmodulerel.module=?", array($thismodule));
		$linkedFieldsCount = $this->db->num_rows($linkedModulesQuery);

		for($i=0; $i<$linkedFieldsCount; $i++) {
			$related_module = $this->db->query_result($linkedModulesQuery, $i, 'relmodule');
			$fieldname = $this->db->query_result($linkedModulesQuery, $i, 'fieldname');
			$columnname = $this->db->query_result($linkedModulesQuery, $i, 'columnname');

			$other = CRMEntity::getInstance($related_module);
			vtlib_setup_modulevars($related_module, $other);

			$query .= " LEFT JOIN $other->table_name ON $other->table_name.$other->table_index = $this->table_name.$columnname";
		}

		$query .= $this->getNonAdminAccessControlQuery($thismodule,$current_user);
		$where_auto = " vtiger_crmentity.deleted=0";

		if($where != '') $query .= " WHERE ($where) AND $where_auto";
		else $query .= " WHERE $where_auto";

		return $query;
    }

    /**
     * Transform the value while exporting
     */
    function transform_export_value($key, $value) {
        return parent::transform_export_value($key, $value);
    }

    /**
     * Function which will give the basic query to find duplicates
     */
    function getDuplicatesQuery($module,$table_cols,$field_values,$ui_type_arr,$select_cols='') {
		$select_clause = "SELECT ". $this->table_name .".".$this->table_index ." AS recordid, vtiger_users_last_import.deleted,".$table_cols;

		// Select Custom Field Table Columns if present
		if(isset($this->customFieldTable)) $query .= ", " . $this->customFieldTable[0] . ".* ";

		$from_clause = " FROM $this->table_name";

		$from_clause .= "	INNER JOIN vtiger_crmentity ON vtiger_crmentity.crmid = $this->table_name.$this->table_index";

		// Consider custom table join as well.
		if(isset($this->customFieldTable)) {
			$from_clause .= " INNER JOIN ".$this->customFieldTable[0]." ON ".$this->customFieldTable[0].'.'.$this->customFieldTable[1] .
				      " = $this->table_name.$this->table_index";
		}
		$from_clause .= " LEFT JOIN vtiger_users ON vtiger_users.id = vtiger_crmentity.smownerid
						LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";

		$where_clause = "	WHERE vtiger_crmentity.deleted = 0";
		$where_clause .= $this->getListViewSecurityParameter($module);

		if (isset($select_cols) && trim($select_cols) != '') {
			$sub_query = "SELECT $select_cols FROM  $this->table_name AS t " .
				" INNER JOIN vtiger_crmentity AS crm ON crm.crmid = t.".$this->table_index;
			// Consider custom table join as well.
			if(isset($this->customFieldTable)) {
				$sub_query .= " LEFT JOIN ".$this->customFieldTable[0]." tcf ON tcf.".$this->customFieldTable[1]." = t.$this->table_index";
			}
			$sub_query .= " WHERE crm.deleted=0 GROUP BY $select_cols HAVING COUNT(*)>1";
		} else {
			$sub_query = "SELECT $table_cols $from_clause $where_clause GROUP BY $table_cols HAVING COUNT(*)>1";
		}


		$query = $select_clause . $from_clause .
					" LEFT JOIN vtiger_users_last_import ON vtiger_users_last_import.bean_id=" . $this->table_name .".".$this->table_index .
					" INNER JOIN (" . $sub_query . ") AS temp ON ".get_on_clause($field_values,$ui_type_arr,$module) .
					$where_clause .
					" ORDER BY $table_cols,". $this->table_name .".".$this->table_index ." ASC";

		return $query;
	}

    /**
     * Invoked when special actions are performed on the module.
     * @param String Module name
     * @param String Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
     */
    function vtlib_handler($modulename, $event_type) {
		global $adb;
        if($event_type == 'module.postinstall') {
			$ModuleInstance = CRMEntity::getInstance($modulename);
			$ModuleInstance->setModuleSeqNumber("configure",$modulename,'LI','1'); 
			
			$modcommentsModuleInstance = Vtiger_Module::getInstance('ModComments');
			if($modcommentsModuleInstance && file_exists('modules/ModComments/ModComments.php')) {
				include_once 'modules/ModComments/ModComments.php';
				if(class_exists('ModComments')) ModComments::addWidgetTo(array('LettersIn'));
			}
			$Instance = Vtiger_Module::getInstance($modulename);
			$nModule = Vtiger_Module::getInstance('Accounts');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('add'),'get_dependents_list');
			}
			$nModule = Vtiger_Module::getInstance('Contacts');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('add'),'get_dependents_list');
			}
			$nModule = Vtiger_Module::getInstance('Leads');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('add'),'get_dependents_list');
			}
			$nModule = Vtiger_Module::getInstance('Potentials');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('add'),'get_dependents_list');
			}
			$nModule = Vtiger_Module::getInstance('HelpDesk');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('add'),'get_dependents_list');
			}
			$nModule = Vtiger_Module::getInstance('Campaigns');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('add'),'get_dependents_list');
			}
			$nModule = Vtiger_Module::getInstance('Project');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('add'),'get_dependents_list');
			}
			$nModule = Vtiger_Module::getInstance('OSSCosts');
			if($nModule){
				$nModule->setRelatedList($Instance, $modulename, array('ADD','SELECT'),'get_related_list');
			}
			$modcommentsModuleInstance = Vtiger_Module::getInstance('ModTracker');
			if($modcommentsModuleInstance && file_exists('modules/ModTracker/ModTracker.php')) {
				include_once('vtlib/Vtiger/Module.php');
				include_once 'modules/ModTracker/ModTracker.php';
				$tabid = Vtiger_Functions::getModuleId($modulename);
				$moduleModTrackerInstance = new ModTracker();
				if(!$moduleModTrackerInstance->isModulePresent($tabid)){
					$res=$adb->pquery("INSERT INTO vtiger_modtracker_tabs VALUES(?,?)",array($tabid,1));
					$moduleModTrackerInstance->updateCache($tabid,1);
				} else{
					$updatevisibility = $adb->pquery("UPDATE vtiger_modtracker_tabs SET visible = 1 WHERE tabid = ?", array($tabid));
					$moduleModTrackerInstance->updateCache($tabid,1);
				}
				if(!$moduleModTrackerInstance->isModTrackerLinkPresent($tabid)){
					$moduleInstance=Vtiger_Module::getInstance($tabid);
					$moduleInstance->addLink('DETAILVIEWBASIC', 'View History', "javascript:ModTrackerCommon.showhistory('\$RECORD\$')",'','',
											array('path'=>'modules/ModTracker/ModTracker.php','class'=>'ModTracker','method'=>'isViewPermitted'));
				}
			}
			$adb->pquery('UPDATE vtiger_tab SET customized=0 WHERE name=?', array($modulename));
			$adb->pquery('UPDATE vtiger_field SET summaryfield=1 WHERE tablename=? AND columnname=?', array('vtiger_lettersin','title'));
			$adb->pquery('UPDATE vtiger_field SET summaryfield=1 WHERE tablename=? AND columnname=?', array('vtiger_lettersin','smownerid'));
			$adb->pquery('UPDATE vtiger_field SET summaryfield=1 WHERE tablename=? AND columnname=?', array('vtiger_lettersin','lin_type_ship'));
			$adb->pquery('UPDATE vtiger_field SET summaryfield=1 WHERE tablename=? AND columnname=?', array('vtiger_lettersin','lin_type_doc'));
			$adb->pquery('UPDATE vtiger_field SET summaryfield=1 WHERE tablename=? AND columnname=?', array('vtiger_lettersin','date_adoption'));
			$adb->pquery('UPDATE vtiger_field SET summaryfield=1 WHERE tablename=? AND columnname=?', array('vtiger_lettersin','relatedid'));
        } else if($event_type == 'module.disabled') {
            // TODO Handle actions when this module is disabled.
        } else if($event_type == 'module.enabled') {
            // TODO Handle actions when this module is enabled.
        } else if($event_type == 'module.preuninstall') {
            // TODO Handle actions when this module is about to be deleted.
        } else if($event_type == 'module.preupdate') {
            // TODO Handle actions before this module is updated.
        } else if($event_type == 'module.postupdate') {
		
        }
    }
	function get_activities($id, $cur_tab_id, $rel_tab_id, $actions=false) {
		global $log, $singlepane_view,$currentModule,$current_user;
		$log->debug("Entering get_activities(".$id.") method ...");
		$this_module = $currentModule;

        $related_module = vtlib_getModuleNameById($rel_tab_id);
		require_once("modules/$related_module/Activity.php");
		$other = new Activity();
        vtlib_setup_modulevars($related_module, $other);
		$singular_modname = vtlib_toSingular($related_module);

		$parenttab = getParentTab();

		if($singlepane_view == 'true')
			$returnset = '&return_module='.$this_module.'&return_action=DetailView&return_id='.$id;
		else
			$returnset = '&return_module='.$this_module.'&return_action=CallRelatedList&return_id='.$id;

		$button = '';

		$button .= '<input type="hidden" name="activity_mode">';

		if($actions) {
			if(is_string($actions)) $actions = explode(',', strtoupper($actions));
			if(in_array('ADD', $actions) && isPermitted($related_module,1, '') == 'yes') {
				if(getFieldVisibilityPermission('Calendar',$current_user->id,'parent_id', 'readwrite') == '0') {
					$button .= "<input title='".getTranslatedString('LBL_NEW'). " ". getTranslatedString('LBL_TODO', $related_module) ."' class='crmbutton small create'" .
						" onclick='this.form.action.value=\"EditView\";this.form.module.value=\"$related_module\";this.form.return_module.value=\"$this_module\";this.form.activity_mode.value=\"Task\";' type='submit' name='button'" .
						" value='". getTranslatedString('LBL_ADD_NEW'). " " . getTranslatedString('LBL_TODO', $related_module) ."'>&nbsp;";
				}
				if(getFieldVisibilityPermission('Events',$current_user->id,'parent_id', 'readwrite') == '0') {
					$button .= "<input title='".getTranslatedString('LBL_NEW'). " ". getTranslatedString('LBL_TODO', $related_module) ."' class='crmbutton small create'" .
						" onclick='this.form.action.value=\"EditView\";this.form.module.value=\"$related_module\";this.form.return_module.value=\"$this_module\";this.form.activity_mode.value=\"Events\";' type='submit' name='button'" .
						" value='". getTranslatedString('LBL_ADD_NEW'). " " . getTranslatedString('LBL_EVENT', $related_module) ."'>";
				}
			}
		}

		$userNameSql = getSqlForNameInDisplayFormat(array('first_name'=>
							'vtiger_users.first_name', 'last_name' => 'vtiger_users.last_name'), 'Users');
		$query = "SELECT vtiger_activity.activityid as 'tmp_activity_id',vtiger_activity.*,vtiger_seactivityrel.crmid as parent_id, vtiger_contactdetails.lastname,vtiger_contactdetails.firstname,
					vtiger_crmentity.crmid, vtiger_crmentity.smownerid, vtiger_crmentity.modifiedtime,
					case when (vtiger_users.user_name not like '') then $userNameSql else vtiger_groups.groupname end as user_name,
					vtiger_recurringevents.recurringtype from vtiger_activity
					inner join vtiger_seactivityrel on vtiger_seactivityrel.activityid=vtiger_activity.activityid
					inner join vtiger_crmentity on vtiger_crmentity.crmid=vtiger_activity.activityid
					left join vtiger_cntactivityrel on vtiger_cntactivityrel.activityid = vtiger_activity.activityid
					left join vtiger_contactdetails on vtiger_contactdetails.contactid = vtiger_cntactivityrel.contactid
					inner join vtiger_lettersin on vtiger_lettersin.lettersinid=vtiger_seactivityrel.crmid
					left join vtiger_users on vtiger_users.id=vtiger_crmentity.smownerid
					left join vtiger_groups on vtiger_groups.groupid=vtiger_crmentity.smownerid
					left outer join vtiger_recurringevents on vtiger_recurringevents.activityid=vtiger_activity.activityid
					where vtiger_seactivityrel.crmid=".$id." and vtiger_crmentity.deleted=0
					and ((vtiger_activity.activitytype='Task' and vtiger_activity.status not in ('Completed','Deferred'))
					or (vtiger_activity.activitytype NOT in ('Emails','Task') and  vtiger_activity.eventstatus not in ('','Held'))) ";

		$return_value = GetRelatedList($this_module, $related_module, $other, $query, $button, $returnset);

		if($return_value == null) $return_value = Array();
		$return_value['CUSTOM_BUTTON'] = $button;

		$log->debug("Exiting get_activities method ...");
		return $return_value;
	}
}
?>
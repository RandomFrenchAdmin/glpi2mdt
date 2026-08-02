<?php
/*
 -------------------------------------------------------------------------
 glpi2mdt plugin for GLPI
 Copyright (C) 2017 by Blaise Thauvin

 https://github.com/RandomFrenchAdmin/glpi2mdt
 -------------------------------------------------------------------------

 LICENSE

 This file is part of glpi2mdt.

 glpi2mdt is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 glpi2mdt is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with glpi2mdt. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
*/

// ----------------------------------------------------------------------
// Original Author of file: Blaise Thauvin
// Contributors : Enzo Lefrancois
// Purpose of file: Class to manipulate additional computer data
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
	die(__("Sorry. You can't access directly to this file", 'glpi2mdt'));
}

class PluginGlpi2mdtComputer extends PluginGlpi2mdtMdt {

	/**
		* The right name for this class
		*
		* @var string
	*/
	static $rightname = 'computer';

    /**
		* This function is called from GLPI to allow the plugin to insert one or more items
		*  inside the left menu of a Itemtype.
		*
		*  While we're there, if in Master-Master mode, update computer data just in case
	*/
	function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
		global $DB;
		$mode = $DB->request([
			'SELECT' => ['value_char AS mode'],
			'FROM'   => 'glpi_plugin_glpi2mdt_parameters',
			'WHERE'  => [
				'scope'     => 'global',
				'parameter' => 'Mode'
			]
		])->current();
		if ($mode && $mode['mode'] == 'Master') {
			try {
				PluginGlpi2mdtCrontask::cronSyncMasterAndStrict(null, $item->getID());
			} catch (Exception $e) {
				Session::addMessageAfterRedirect(__('Error while synchronizing computer data with MDT. Please check the logs.', 'glpi2mdt'), false, ERROR);
			}
		}
		$id = $item->getID();
		$isAutoInstallEnabled = $DB->request([
			'SELECT' => ['value'],
			'FROM'   => 'glpi_plugin_glpi2mdt_settings',
			'WHERE'  => [
				'type'     => 'C',
				'category' => 'C',
				'key'      => 'OSInstall',
				'id'       => $id
			]
		])->current();
		if ($isAutoInstallEnabled && $isAutoInstallEnabled['value'] == 'YES') {
			return self::createTabEntry(__('Auto Install', 'glpi2mdt'), __('YES', 'glpi2mdt'));
		} else {
			return self::createTabEntry(__('Auto Install', 'glpi2mdt'), __('NO', 'glpi2mdt'));
		}
	}

	/**
		* This function is called by the computer form for glpi2mdt when pressing "save"
		* It parses the post variables and stores them in the GLPI database
		*
		* @param  $post, the full list of post items
		* @return nothing
	*/
	function updateValue($post) {
		global $DB;

		// Only update if user has rights to do so.
		if (!PluginGlpi2mdtComputer::canUpdate()) {
			return false;
		}

		// Retreive array of valid variables for post variables
		$variables = $this->globalconfig['variables'];

		if (isset($post['id']) and ($post['id'] > 0)) {
			$id = $post['id'];
			// Delete all computer configuration entries
			$DB->delete('glpi_plugin_glpi2mdt_settings', [
				'id' => $id,
				'type' => 'C'
			]);
			$apprank = 0;
			foreach ($post as $key => $value) {
				// only valid variables may be inserted. The full list is in the "descriptions" database
				if (isset($variables[$key])) {
					$exists = $DB->request([
						'COUNT' => 'c',
						'FROM' => 'glpi_plugin_glpi2mdt_settings',
						'WHERE' => [
							'id' => $id,
							'category' => 'C',
							'type' => 'C',
							'key' => $key
						]
					])->current()['c'] > 0;
					if ($exists) {
						$DB->update(
							'glpi_plugin_glpi2mdt_settings',
							[
								'value' => $value,
								'is_in_sync' => true
							],
							[
								'id' => $id,
								'category' => 'C',
								'type' => 'C',
								'key' => $key
							]
						);
					} else {
						$DB->insert(
							'glpi_plugin_glpi2mdt_settings',
							[
								'id' => $id,
								'category' => 'C',
								'type' => 'C',
								'key' => $key,
								'value' => $value,
								'is_in_sync' => true
							]
						);
					}
				}
				// Applications
				if (substr($key, 0, 4) == 'App-' && $value != 'none' && strlen($key) == 42 && $value == 'on') {
					$guid = substr($key, 4);
					$apprank++;
					$exists = $DB->request([
						'COUNT' => 'c',
						'FROM' => 'glpi_plugin_glpi2mdt_settings',
						'WHERE' => [
							'id' => $id,
							'category' => 'A',
							'type' => 'C',
							'key' => $guid
						]
					])->current()['c'] > 0;
					if ($exists) {
						$DB->update(
							'glpi_plugin_glpi2mdt_settings',
							[
								'value' => $apprank,
								'is_in_sync' => true
							],
							[
								'id' => $id,
								'category' => 'A',
								'type' => 'C',
								'key' => $guid
							]
						);
					} else {
						$DB->insert(
							'glpi_plugin_glpi2mdt_settings',
							[
								'id' => $id,
								'category' => 'A',
								'type' => 'C',
								'key' => $guid,
								'value' => $apprank,
								'is_in_sync' => true
							]
						);
					}
				}
				// Rôles
				if (substr($key, 0, 6) == 'Roles-' && $value != 'none' && $value == 'on') {
					$guid = substr($key, 6);
					$role = $DB->request([
						'SELECT' => ['role'],
						'FROM' => 'glpi_plugin_glpi2mdt_roles',
						'WHERE' => ['id' => $guid]
					])->current();
					if ($role) {
						$exists = $DB->request([
							'COUNT' => 'c',
							'FROM' => 'glpi_plugin_glpi2mdt_settings',
							'WHERE' => [
								'id' => $id,
								'category' => 'R',
								'type' => 'C',
								'key' => $guid
							]
						])->current()['c'] > 0;
						if ($exists) {
							$DB->update(
								'glpi_plugin_glpi2mdt_settings',
								[
									'value' => $role['role'],
									'is_in_sync' => true
								],
								[
									'id' => $id,
									'category' => 'R',
									'type' => 'C',
									'key' => $guid
								]
							);
						} else {
							$DB->insert(
								'glpi_plugin_glpi2mdt_settings',
								[
									'id' => $id,
									'category' => 'R',
									'type' => 'C',
									'key' => $guid,
									'value' => $role['role'],
									'is_in_sync' => true
								]
							);
						}
					}
				}
				if ($key == 'OSInstallExpire') {
					$timestamp = strtotime($value);
					if ($timestamp > 0) {
						$exists = $DB->request([
							'COUNT' => 'c',
							'FROM' => 'glpi_plugin_glpi2mdt_settings',
							'WHERE' => [
								'id' => $id,
								'category' => 'C',
								'type' => 'C',
								'key' => $key
							]
						])->current()['c'] > 0;
						if ($exists) {
							$DB->update(
								'glpi_plugin_glpi2mdt_settings',
								[
									'value' => $timestamp,
									'is_in_sync' => true
								],
								[
									'id' => $id,
									'category' => 'C',
									'type' => 'C',
									'key' => $key
								]
							);
						} else {
							$DB->insert(
								'glpi_plugin_glpi2mdt_settings',
								[
									'id' => $id,
									'category' => 'C',
									'type' => 'C',
									'key' => $key,
									'value' => $timestamp,
									'is_in_sync' => true
								]
							);
						}
					}
				}
			}
		}
	}

	/**
		* Updates the MDT MSSQL database with information contained in GLPI's database
		*
		* @param  GLPI object ID, here a computer
		* @param  Expire: will only reset "OSInstall" flag set to true and coupling mode is not "strict master slave"
		* @return nothing
	*/
	function updateMDT($id) {
		global $DB;
		$globalconfig = $this->globalconfig;

		// Only update if user has rights to do so.
		if (!PluginGlpi2mdtComputer::canUpdate()) {
			return false;
		}

		// Build array of valid variables
		$variables = $this->globalconfig['variables'];

		//Get IDs to work on
		$mdt = $this->getMdtIds($id);
		$macs = $mdt['macs'];
		$values = $mdt['values'];
		$mdtids = $mdt['mdtids'];
		$name = $mdt['name'];
		$uuid = $mdt['uuid'];
		$serial = $mdt['serial'];
		$otherserial = $mdt['otherserial']; //asset tag
		$nbrows = $mdt['nbrows'];

		// Build password according to rules
		if ($globalconfig['Complexity'] == 'Trivial') {
			$adminpasscomposite = $name;
		} else if ($globalconfig['Complexity'] == 'Unique') {
			$adminpasscomposite = $globalconfig['LocalAdmin'].'-'.$name;
		} else { // Default case, Basic
			$adminpasscomposite = $globalconfig['LocalAdmin'];
		}

		// Check if the computer entries in MDT are the ones expected by GLPI.
		// If not, delete everything and recreate
		// If yes, depending on coupling mode, delete and recreate or simply update
		$uuid = str_replace("'", "''", $uuid);
		$name = str_replace("'", "''", $name);
		$serial = str_replace("'", "''", $serial);
		$otherserial = str_replace("'", "''", $otherserial);
		$query = "SELECT ID FROM dbo.ComputerIdentity WHERE UUID='$uuid' AND Description='$name' AND SerialNumber='$serial' AND AssetTag='$otherserial' AND $macs";
		$result = $this->query($query);
		$nbrowsactual = $this->numrows($result);
		if ($nbrows != $nbrowsactual) {
			try {
				$this->queryOrDie("DELETE FROM dbo.ComputerIdentity WHERE $mdtids");
				$this->queryOrDie("INSERT INTO dbo.ComputerIdentity (Description, UUID, SerialNumber, AssetTag, MacAddress) VALUES $values");
			} catch (Exception $e) {
				session::addMessageAfterRedirect(__('Error syncing ComputerIdentity. Please check the logs.', 'glpi2mdt'), false, ERROR);
			}
		}
		// Delete corresponding records in side tables depending on coupling mode
		if (($nbrows != $nbrowsactual) OR ($globalconfig['Mode'] == 'Strict')) {
			try {
				$this->queryOrDie("DELETE FROM dbo.Settings WHERE Type='C' and $mdtids");
				$this->queryOrDie("DELETE FROM dbo.Settings_Applications WHERE Type='C' and $mdtids");
				$this->queryOrDie("DELETE FROM dbo.Settings_Administrators WHERE Type='C' and $mdtids");
				$this->queryOrDie("DELETE FROM dbo.Settings_Packages WHERE Type='C' and $mdtids");
				$this->queryOrDie("DELETE FROM dbo.Settings_Roles WHERE Type='C' and $mdtids");
			} catch (Exception $e) {
				session::addMessageAfterRedirect(__('Error deleting from MDT side tables. Please check the logs.', 'glpi2mdt'), false, ERROR);
			}
		}
		// Retreive (newly created or not) entries ids in order to add the settings.
		$mdt = $this->getMdtIds($id);
		$macs = $mdt['macs'];
		$mdtids = $mdt['mdtids'];
		$arraymdtids = $mdt['arraymdtids'];
		$name = $mdt['name'];
		$uuid = $mdt['uuid'];
		$serial = $mdt['serial'];
		$otherserial = $mdt['otherserial']; //asset tag
		$nbrows = $mdt['nbrows'];
		foreach ($arraymdtids as $mdtid) {
			$values = "('C', $mdtid, '" . str_replace("'", "''", $name) . "', '" . str_replace("'", "''", $name) . "', '" . str_replace("'", "''", $name) . "', '" . str_replace("'", "''", $adminpasscomposite) . "') ";
			try {
				$exists = $this->queryOrDie("SELECT ID FROM dbo.Settings WHERE Type='C' AND ID=$mdtid;");
				if ($this->numrows($exists) == 1) {
					$query = "UPDATE dbo.Settings SET ComputerName='" . str_replace("'", "''", $name) . "', OSDComputerName='" . str_replace("'", "''", $name) . "', FullName='" . str_replace("'", "''", $name) . "', AdminPassword='" . str_replace("'", "''", $adminpasscomposite) . "' WHERE Type='C' and ID=$mdtid";
				} else {
					$query = "INSERT INTO dbo.Settings (Type, ID, ComputerName, OSDComputerName, FullName, AdminPassword) VALUES $values;";
				}
				$this->queryOrDie($query);
			} catch (Exception $e) {
				session::addMessageAfterRedirect(__('Error updating settings for computer in MDT. Please check the logs.', 'glpi2mdt'), false, ERROR);
			}
		}

		// Update settings with additional variables
		
		$iterator = $DB->request([
			'SELECT' => ['key', 'value'],
			'FROM'   => 'glpi_plugin_glpi2mdt_settings',
			'WHERE'  => [
				'id'       => $id,
				'category' => 'C',
				'type'     => 'C'
			]
		]);
		foreach ($iterator as $pair) {
			$key = $pair['key'];
			$value = ($pair['value'] == '*undef*') ? '' : $pair['value'];
			$value = str_replace("'", "''", $value);
			try {
				// Check if key is a valid field for database "settings" in order to filter OSInstallExpire for example
				if (isset($variables[$key])) {
					$query = "UPDATE dbo.Settings SET $key='$value' WHERE $mdtids;";
					$this->queryOrDie($query);
				}
			} catch (Exception $e) {
				session::addMessageAfterRedirect(__('Error updating settings for computer in MDT. Please check the logs.', 'glpi2mdt'), false, ERROR);
			}
		}
		
		// Update applications table  
		$iterator = $DB->request([
			'SELECT' => ['key', 'value'],
			'FROM'   => 'glpi_plugin_glpi2mdt_settings',
			'WHERE'  => [
				'id'       => $id,
				'category' => 'A',
				'type'     => 'C'
			]
		]);

		foreach ($iterator as $pair) {
			$key = $pair['key'];
			$value = $pair['value'];
			reset($arraymdtids);
			foreach ($arraymdtids as $mdtid) {
				try {
					// GLPI2MDT does not manage ranks, so keep the existing one if any
					$ranks = $this->queryOrDie("SELECT Sequence FROM dbo.Settings_Applications WHERE ID=$mdtid AND type='C' AND Applications='$key';");
				} catch (Exception $e) {
					session::addMessageAfterRedirect(__('Error checking application rank in MDT. Please check the logs.', 'glpi2mdt'), false, ERROR);
				}
				if ($this->numrows($ranks) == 0) {
					$this->queryOrDie("INSERT INTO dbo.Settings_Applications (Type, ID, Sequence, Applications) VALUES ('C', '$mdtid', $value, '$key');");
				}
			}
		}
	  
		// Update Roles table
		try {
			$iterator = $DB->request([
				'SELECT' => ['key', 'value'],
				'FROM' => 'glpi_plugin_glpi2mdt_settings',
				'WHERE' => [
					'id' => $id,
					'category' => 'R',
					'type' => 'C',
				],
			]);
			foreach ($iterator as $pair) {
				$key = $pair['key'];
				$value = $pair['value'];
				$value = str_replace("'", "''", $value);
				reset($arraymdtids);
				foreach ($arraymdtids as $mdtid) {
					// GLPI2MDT does not manage ranks, so keep the existing one if any
					$rank = $this->queryOrDie("SELECT Sequence FROM dbo.Settings_Roles WHERE ID=$mdtid AND type='C' AND Role='$value';");
					if ($this->numrows($ranks) == 0) {
						// Add after existing roles in MDT, mainly for loose and master coupling modes
						$next = $this->queryOrDie("SELECT ISNULL(MAX(Sequence),0)+1 as next FROM dbo.Settings_Roles WHERE ID=$mdtid AND type='C';");
						$rank = $this->fetch_array($next)['next'];
						$this->queryOrDie("INSERT INTO dbo.Settings_Roles (Type, ID, Sequence, Role) VALUES ('C', '$mdtid', '$rank', '$value');");
					}
				}
			}
		} catch (Exception $e) {
			session::addMessageAfterRedirect(__('Error updating roles for computer in MDT. Please check the logs.', 'glpi2mdt'), false, ERROR);
		}
	}

	/**
		* This function is called from GLPI to render the form when the user clicks
		* on the menu item generated from getTabNameForItem()
	*/
	static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
		global $DB;

		$id = $item->getID();
		$osinstall = 'NO';
		$osinstallexpire = date('Y-m-d H:i', 300*ceil(time()/300) + (3600*24));
		$settings = [];
		$appvalues = [];
		$rolevalues = [];

		$yesno['*undef*'] = __('Default', 'glpi2mdt');
		$yesno['YES'] = __('YES', 'glpi2mdt');
		$yesno['NO'] = __('NO', 'glpi2mdt');

		/**
			* Internal function to factorise dropbox creation
			*
		*/
		function showSelectBox($message, $variable, $values, $settings) {
			echo '<td>';
			echo __($message, 'glpi2mdt');
			echo "</td><td>";
			Dropdown::showFromArray($variable, $values,
			array('value' => (isset($settings[$variable])?$settings[$variable]:'*undef*')));
			echo '</td>';
		}

		$iterator = $DB->request([
			'SELECT' => ['category', 'key', 'value'],
			'FROM'   => 'glpi_plugin_glpi2mdt_settings',
			'WHERE'  => [
				'type' => 'C',
				'id'   => $id
			]
		]);

		foreach ($iterator as $row) {
			switch ($row['category']) {
				case 'C':
					$settings[$row['key']] = $row['value'];
					break;
				case 'A':
					$appvalues[$row['key']] = true;
					break;
				case 'R':
					$rolevalues[$row['key']] = true;
					break;
			}
		}

		if (isset($settings['OSInstallExpire'])) {
			$osinstallexpire = date('Y-m-d H:i', $settings['OSInstallExpire']);
		}

		echo '<form action="../plugins/glpi2mdt/front/computer.form.php" method="post">';
		echo Html::hidden('id', array('value' => $id));
		echo Html::hidden('_glpi_csrf_token', array('value' => Session::getNewCSRFToken()));
		echo '<div class="spaced" id="tabsbody">';
		echo '<table class="tab_cadre_fixe" width="100%">';
		echo '<tr class="headerRow"><th colspan="3">'.__('Automatic installation', 'glpi2mdt').'<br></th></tr>';
		echo '<tr class="tab_bg_1">';

		// OS Install
		showSelectBox('Enable automatic installation', 'OSInstall', $yesno, $settings);

		// Reset after...
		echo '<td>';
		echo __('Reset after (empty for permanent):', 'glpi2mdt');
		Html::showDateTimeField("OSInstallExpire", @array('value'      => $osinstallexpire,
			'timestep'   => 5,
			'mindate'    => date('Y-m-d H:i:s'),
			'maybeempty' => true));
		echo '</td></tr>';

		// Task sequences  
		echo '<tr class="tab_bg_1">';
		$tasksequenceids = ['*undef*' => __('Default task sequence', 'glpi2mdt')];

		$iterator = $DB->request([
			'SELECT' => ['id', 'name'],
			'FROM'   => 'glpi_plugin_glpi2mdt_task_sequences',
			'WHERE'  => [
				'is_deleted' => 0,
				'hide'       => false,
				'enable'     => true
			]
		]);

		foreach ($iterator as $row) {
			$tasksequenceids[$row['id']] = $row['name'];
		}
		showSelectBox('Default task sequence', 'TaskSequenceID', $tasksequenceids, $settings);
		echo '</tr>';

		// Operating system
		echo '<tr class="tab_bg_1">';
		$operatingsystemids = ['*undef*' => __('Default operating system', 'glpi2mdt')];

		$iterator = $DB->request([
			'SELECT' => ['id', 'guid', 'name'],
			'FROM'   => 'glpi_plugin_glpi2mdt_operating_systems',
			'WHERE'  => [
				'is_deleted' => 0,
				'enable'     => true
			]
		]);

		foreach ($iterator as $row) {
			$operatingsystemids[$row['guid']] = $row['id'];
		}
		showSelectBox('Default operating system', 'OSValue', $operatingsystemids, $settings);
		echo '</tr>';

		// Applications
		$iterator = $DB->request([
			'SELECT' => [
				'a.guid',
				'a.shortname',
				'g.name',
				'a.enable',
			],
			'FROM' => 'glpi_plugin_glpi2mdt_applications AS a',
			'INNER JOIN' => [
				'glpi_plugin_glpi2mdt_application_group_links AS l' => [
					'ON' => [
						'a' => 'guid',
						'l' => 'application_guid',
					],
				],
				'glpi_plugin_glpi2mdt_application_groups AS g' => [
					'ON' => [
						'g' => 'guid',
						'l' => 'group_guid',
					],
				],
			],
			'WHERE' => [
				'a.is_deleted' => false,
				'a.hide' => false,
				'g.is_deleted' => false,
				'g.hide' => false,
				'g.enable' => true,
				'l.is_deleted' => false,
			],
		]);

		$groupapplications = [];
		foreach ($iterator as $row) {
			$groupapplications[$row['guid']] = [
				'name' => $row['shortname'],
				'group' => $row['name'],
				'enable' => $row['enable'],
			];
		}
		PluginGlpi2mdtToolbox::showMultiSelect($groupapplications, $appvalues, __('Applications', 'glpi2mdt'), "App-");

		// Roles
		$roles = [];
		$iterator = $DB->request([
			'SELECT' => ['id', 'role'],
			'FROM' => 'glpi_plugin_glpi2mdt_roles',
			'WHERE' => ['is_deleted' => 0]
		]);

		foreach ($iterator as $row) {
			$roles[$row['id']] = [
				'name' => $row['role'],
				'group' => '',
				'enable' => true
			];
		}
		PluginGlpi2mdtToolbox::showMultiSelect($roles, $rolevalues, __('Roles', 'glpi2mdt'), "Roles-");

		// Assistant
		$skip['*undef*'] = __('Default', 'glpi2mdt');
		$skip['NO'] = __('Activate', 'glpi2mdt');
		$skip['YES'] = __('Skip', 'glpi2mdt');
		echo '<table class="tab_cadre_fixe" width="100%">';
		echo '<tr class="headerRow"><th colspan="4">'.__('Enable Installation Assistant dialogs', 'glpi2mdt').'<br></th></tr>';
		echo '<tr class="tab_bg_1">';

		// Applications
		showSelectBox('Applications dialog', 'SkipApplications', $skip, $settings);

		// BitLocker
		showSelectBox('BitLocker dialog', 'SkipBitLocker', $skip, $settings);

		echo '</tr><tr>';

		// Computer Backup
		showSelectBox('Computer backup dialog', 'SkipComputerBackup', $skip, $settings);

		// User Data
		showSelectBox('User data dialog', 'SkipUserData', $skip, $settings);

		echo '</tr><tr>';

		// Locale Selection
		showSelectBox('Locale selection dialog', 'SkipLocaleSelection', $skip, $settings);

		// Time Zone
		showSelectBox('TimeZone dialog', 'SkipTimeZone', $skip, $settings);

		echo '</tr><tr>';

		// Package Display
		showSelectBox('Package display dialog', 'SkipPackageDisplay', $skip, $settings);

		// Capture
		showSelectBox('Image capture dialog', 'SkipCapture', $skip, $settings);

		echo '</tr>';
		echo '</table>';

		// Show the save button only if user has rights to do so.
		if (PluginGlpi2mdtComputer::canUpdate()) {
			echo '<tr class="tab_bg_1">';
			echo '<td></td><td>';
			echo sprintf('<input type="submit" class="submit" value="%s" name="SAVE"/>', htmlspecialchars(__('Save'), ENT_QUOTES, 'UTF-8'));
			echo '</td>';
			echo '</tr>';
			echo '</tr>';
			echo '</table>';

			// Plugin version check
			$currentversion = PLUGIN_GLPI2MDT_VERSION;
			$latestversion = $DB->request([
				'SELECT' => ['value_char'],
				'FROM'   => 'glpi_plugin_glpi2mdt_parameters',
				'WHERE'  => [
					'parameter' => 'LatestVersion',
					'scope'     => 'global'
				]
			])->current();

			if ($latestversion && version_compare($currentversion, $latestversion['value_char'], '<')) {
				echo sprintf(
					'<br><br><div class="alert alert-warning text-center">%s</div>',
					htmlspecialchars(sprintf(__("A new version is available: v%s", 'glpi2mdt'), $latestversion['value_char']), ENT_QUOTES, 'UTF-8')
				);
			}

			echo '</div>';
			echo '</form>';
		}
		return true;
	}
}
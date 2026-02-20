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


/** @file
	* @brief This class extends the original GLPI crontask class and adds specific crons
	* for the gkpi2mdt plugin.
*/

if (!defined('GLPI_ROOT')) {
	die("Sorry. You can't access this file directly");
}


/**
	* Glpi2mdtcrontask class
**/
class PluginGlpi2mdtCronTask extends PluginGlpi2mdtMdt {

	/**
		* Check if new version is available
		*
		* @param $auto                  boolean: check done by cron ? (if not display result)
		*                                        (default true)
		* @param $messageafterredirect  boolean: use message after redirect instead of display
		*                                        (default false)
		*
		* @return integer if started in cron mode. Outputs HTML data otherwise
		*    >0 : done
		*    <0 : to be run again (not finished)
		*     0 : nothing to be done
	**/
	static function cronCheckGlpi2mdtUpdate($task, $cron=true, $messageafterredirect=false) {
		$currentversion = PLUGIN_GLPI2MDT_VERSION;
		global $DB;

		//parse github releases (get last version number)
		$error = "";
		$json_gh_releases = Toolbox::getURLContent("https://api.github.com/repos/randomfrenchadmin/glpi2mdt/releases", $error);
		$all_gh_releases = json_decode($json_gh_releases, true);
		$released_tags = array();
		foreach ($all_gh_releases as $release) {
			if ($release['prerelease'] == false) {
				$released_tags[] =  $release['tag_name'];
			}
		}
		usort($released_tags, 'version_compare');
		$latest_version = array_pop($released_tags);
		// Did we get something? Maybe not if the server has no internet access...
		if (strlen(trim($latest_version)) == 0) {
			if ($cron) {
				$task->log($error);
			} else {
				if ($messageafterredirect) {
					Session::addMessageAfterRedirect($error, true, INFO);
				} else {
					return $error;
				}
			}
		} else {
			$data = [
				'parameter'  => 'LatestVersion',
				'scope'      => 'global',
				'value_char' => $latest_version,
				'value_num'  => null,
				'is_deleted' => 0,
			];
			$condition = [
				'parameter' => 'LatestVersion',
				'scope'     => 'global',
			];
			$update_fields = [
				'value_char' => $latest_version,
				'value_num'  => null,
				'is_deleted' => 0,
			];
			$DB->updateOrInsert(
				'glpi_plugin_glpi2mdt_parameters',
				$data,
				$condition,
				$update_fields
			);
			if (version_compare($currentversion, $latest_version, '<')) {
				$message = sprintf(__("A new version is available: v%s", 'glpi2mdt'), $latest_version);
			} else {
				$message = sprintf(__("You have the latest available version"));
			}
			if ($cron) {
				$task->log($message);
			} else {
				if ($messageafterredirect) {
					Session::addMessageAfterRedirect($message);
				} else {
					return $message;
				}
			}
		}
		return 1;
   }


	/**
		* Task to initialise data, load local tables from MDT MSSQL server
		*
		* @param Flag for manual run or started by cron
		*
		* @return integer if started in cron mode. Outputs HTML data otherwise
		*    >0 : done
		*    <0 : to be run again (not finished)
		*     0 : nothing to be done
	**/
	static function cronUpdateBaseconfigFromMDT($task, $cron=true) {
		global $DB;
		$ok = 1;
		$MDT = new PluginGlpi2mdtMdt;
		$globalconfig = $MDT->globalconfig;

		if (!$cron) {
			echo '<table class="tab_cadre_fixe">';
		}

		// Add custom OS value into the "descriptions" table if it doesn't exist
		$CheckOSValue = $MDT->query("SELECT count(*) as nb FROM dbo.Descriptions WHERE ColumnName='OSValue'");
		$row = $MDT->fetch_assoc($CheckOSValue);
		$CheckOSValue = $row['nb'];
		if ($CheckOSValue == 0) {
			$AddValueDescriptions = $MDT->query("INSERT INTO dbo.Descriptions (ColumnName, CategoryOrder, Category, Description) VALUES ('OSValue', '8', 'Miscellaneous', 'Operating system GUID')");
			if ($AddValueDescriptions !== FALSE){echo "<tr class='tab_bg_1'><td>".__("Custom variable has been loaded into table", 'glpi2mdt')." 'dbo.Descriptions'.</td></tr>";}
		} else {echo "<tr class='tab_bg_1'><td>".__("Custom variable is already loaded into table", 'glpi2mdt')." 'dbo.Descriptions'.</td></tr>";}
		// Add custom OS value into the "settings" table if it doesn't exist
		$CheckOSValue = $MDT->query("SELECT count(*) as nb FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'Settings' AND COLUMN_NAME = 'OSValue'");
		$row = $MDT->fetch_assoc($CheckOSValue);
		$CheckOSValue = $row['nb'];
		if ($CheckOSValue == 0) {
			$AddValueSettings = $MDT->query('ALTER TABLE dbo.Settings ADD "OSValue" VARCHAR(38) NULL');
			if ($AddValueSettings !== FALSE){
				echo "<tr class='tab_bg_1'><td>".__("Custom variable has been loaded into table", 'glpi2mdt')." 'dbo.Settings'.</td>";
				$RefreshViewQuery = "
					EXECUTE sp_refreshview '[dbo].[ComputerSettings]'
					EXECUTE sp_refreshview '[dbo].[LocationSettings]'
					EXECUTE sp_refreshview '[dbo].[MakeModelSettings]'
					EXECUTE sp_refreshview '[dbo].[RoleSettings]'
				";
				$RefreshView = $MDT->query($RefreshViewQuery);
				if($RefreshView){echo "<td>SQL view has been refreshed</td></tr>";} else {echo "<td>ERROR : unable to refresh SQL view</td></tr>";}
			}
		} else {echo "<tr class='tab_bg_1'><td>".__("Custom variable is already loaded into table", 'glpi2mdt')." 'dbo.Settings'.</td>";}

		//
		// Load available settings fields and descriptions from MDT
		//
		$result = $MDT->queryOrDie('SELECT ColumnName, CategoryOrder, Category, Description FROM dbo.Descriptions');
		$nb = 0;
		// Mark lines in order to detect deleted ones in the source database
		$DB->update(
			'glpi_plugin_glpi2mdt_descriptions',
			['is_in_sync' => false],
			['is_deleted' => false]
		);
		// Hopefully there are less than 300 lines, do an atomic insert/update
		while ($row = $MDT->fetch_array($result)) {
			$column_name = $row['ColumnName'];
			$category_order = $row['CategoryOrder'];
			$category = $row['Category'];
			$description = $row['Description'];
			$nb++;
			$iterator = $DB->request([
				'FROM' => 'glpi_plugin_glpi2mdt_descriptions',
				'WHERE' => ['column_name' => $column_name],
			]);
			if (count($iterator) > 0) {
				$DB->update(
					'glpi_plugin_glpi2mdt_descriptions',
					[
						'category_order' => $category_order,
						'category' => $category,
						'description' => $description,
						'is_in_sync' => true,
						'is_deleted' => false,
					],
					['column_name' => $column_name]
				);
			} else {
				$DB->insert(
					'glpi_plugin_glpi2mdt_descriptions',
					[
						'column_name' => $column_name,
						'category_order' => $category_order,
						'category' => $category,
						'description' => $description,
						'is_in_sync' => true,
						'is_deleted' => false,
					]
				);
			}
		}
		if (!$cron) {
			if ($nb !== 0){echo "<tr class='tab_bg_1'><td>$nb ".__("lines loaded into table", 'glpi2mdt')." 'descriptions'.".'</td>';}
		}
		$iterator = $DB->request([
			'FROM' => 'glpi_plugin_glpi2mdt_descriptions',
			'COUNT' => 'nb',
			'WHERE' => [
				'is_in_sync' => true,
				'is_deleted' => true,
			],
		]);
		$row = $iterator->current();
		$nb = $row['nb']; 
		$DB->delete(
			'glpi_plugin_glpi2mdt_descriptions',
			[
				'is_in_sync' => true,
				'is_deleted' => true,
			]
		);
		if (!$cron) {
			if ($nb !== 0){echo "<td>$nb ".__("lines deleted from table", 'glpi2mdt')." 'descriptions'.".'</td></tr>';}
		}

		//
		// Load available roles from MDT
		//
		$result = $MDT->query('SELECT  ID, Role FROM dbo.RoleIdentity');

		// Mark lines in order to detect deleted ones in the source database
		$DB->update(
			'glpi_plugin_glpi2mdt_roles',
			['is_in_sync' => false],
			['is_deleted' => false]
		);
		while ($row = $MDT->fetch_array($result)) {
			$id = $row['ID'];
			$role = $row['Role'];

			$iterator = $DB->request([
				'FROM' => 'glpi_plugin_glpi2mdt_roles',
				'WHERE' => ['id' => $id],
			]);
			if (count($iterator) > 0) {
				$DB->update(
					'glpi_plugin_glpi2mdt_roles',
					[
						'role' => $role,
						'is_deleted' => false,
						'is_in_sync' => true,
					],
					['id' => $id]
				);
			} else {
				$DB->insert(
					'glpi_plugin_glpi2mdt_roles',
					[
						'id' => $id,
						'role' => $role,
						'is_deleted' => false,
						'is_in_sync' => true,
					]
				);
			}
		}

		// Mark lines which are not in MDT anymore as deleted
		$DB->update(
			'glpi_plugin_glpi2mdt_roles',
			['is_in_sync' => true, 'is_deleted' => true],
			[
				'is_in_sync' => false,
				'is_deleted' => false,
			]
		);

		$result = $MDT->query('SELECT  count(*) as nb FROM dbo.RoleIdentity');
		$row =$MDT->fetch_array($result);
		$nb = $row['nb'];
		if (!$cron) {
			if ($nb !== 0){echo "<tr class='tab_bg_1'><td>$nb ".__("lines loaded into table", 'glpi2mdt')." 'roles'.".'</td></tr>';}
		}

		$MDT->free_result($result);

		//
		// Load data from XML files in the deployment share
		//
		// Applications
		// Mark lines in order to detect deleted ones in the source database
		$dst = $MDT->globalconfig['FileShare'].'/Applications.xml';
		$applications = PluginGlpi2mdtCronTask::checkFile($dst, $task, $cron);

		if ($applications !== false) {
			$DB->update(
				'glpi_plugin_glpi2mdt_applications',
				['is_in_sync' => false],
				['is_deleted' => false]
			);
			$nb = 0;
			foreach ($applications->application as $application) {
				$name = $application->Name;
				$guid = $application['guid'];
				if (isset($application['enable']) and ($application['enable'] == 'True')) {$enable = true; } else {$enable = false;}
				if (isset($application['hide']) and ($application['hide'] == 'True')) {$hide = true; } else {$hide = false;}
				$shortname = $application->ShortName;
				$version = $application->Version;

				$iterator = $DB->request([
					'FROM' => 'glpi_plugin_glpi2mdt_applications',
					'WHERE' => ['guid' => $guid],
				]);
				if (count($iterator) > 0) {
					$DB->update(
						'glpi_plugin_glpi2mdt_applications',
						[
							'name' => $name,
							'shortname' => $shortname,
							'version' => $version,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						],
						['guid' => $guid]
					);
				} else {
					$DB->insert(
						'glpi_plugin_glpi2mdt_applications',
						[
							'guid' => $guid,
							'name' => $name,
							'shortname' => $shortname,
							'version' => $version,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						]
					);
				}
				$nb += 1;  
			}
			if (!$cron) {
				if ($nb !== 0){echo "<tr class='tab_bg_1'><td>$nb ".__("lines loaded into table", 'glpi2mdt')." 'applications'.</td>";}
			}
			// Mark lines which are not in MDT anymore as deleted
			$iterator = $DB->request([
				'FROM' => 'glpi_plugin_glpi2mdt_applications',
				'COUNT' => 'nb',
				'WHERE' => [
					'is_in_sync' => true,
					'is_deleted' => true,
				],
			]);
			$row = $iterator->current();
			$nb = $row['nb']; 
			$DB->delete(
				'glpi_plugin_glpi2mdt_applications',
				[
					'is_in_sync' => true,
					'is_deleted' => true,
				]
			);
			if (!$cron) {
				if ($nb !== 0){echo "<td>$nb ".__("lines deleted from table", 'glpi2mdt')." 'applications'.</td><tr>";}
			}
		} else {
			$ok = -1;
		}

		// Application groups
		// Mark lines in order to detect deleted ones in the source database
		$dst = $MDT->globalconfig['FileShare'].'/ApplicationGroups.xml';
		$groups = PluginGlpi2mdtCronTask::checkFile($dst, $task, $cron);

		if ($groups !== false) {
			$DB->update(
				'glpi_plugin_glpi2mdt_application_groups',
				['is_in_sync' => false],
				['is_deleted' => false]
			);
			$DB->update(
				'glpi_plugin_glpi2mdt_application_group_links',
				['is_in_sync' => false],
				['is_deleted' => false]
			);
			$nb = 0;
			foreach ($groups->group as $group) {
				$name = str_replace('\\', ' - ', $group->Name);
				$guid = $group['guid'];
				if (isset($group['enable']) and ($group['enable'] == 'True')) {$enable = true; } else {$enable = false;}
				if (isset($group['hide']) and ($group['hide'] == 'True') and ($name <> 'hidden')) {$hide = true; } else {$hide = false;}
				$iterator = $DB->request([
					'FROM' => 'glpi_plugin_glpi2mdt_application_groups',
					'WHERE' => ['guid' => $guid],
				]);
				if (count($iterator) > 0) {
					$DB->update(
						'glpi_plugin_glpi2mdt_application_groups',
						[
							'name' => $name,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						],
						['guid' => $guid]
					);
				} else {
					$DB->insert(
						'glpi_plugin_glpi2mdt_application_groups',
						[
							'guid' => $guid,
							'name' => $name,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						]
					);
				}
				$nb += 1;
				foreach ($group->Member as $application_guid) {
					$iterator = $DB->request([
						'FROM' => 'glpi_plugin_glpi2mdt_application_group_links',
						'WHERE' => [
							'group_guid' => $guid,
							'application_guid' => $application_guid,
						],
					]);
					if (count($iterator) > 0) {
						$DB->update(
							'glpi_plugin_glpi2mdt_application_group_links',
							[
								'is_deleted' => false,
								'is_in_sync' => true,
							],
							[
								'group_guid' => $guid,
								'application_guid' => $application_guid,
							]
						);
					} else {
						$DB->insert(
							'glpi_plugin_glpi2mdt_application_group_links',
							[
								'group_guid' => $guid,
								'application_guid' => $application_guid,
								'is_deleted' => false,
								'is_in_sync' => true,
							]
						);
					}
				}
			}
			if (!$cron) {
				if ($nb !== 0){echo "<tr class='tab_bg_1'><td>$nb ".__("lines loaded into table", 'glpi2mdt')." 'application groups'.</td>";}
			}
			// Mark lines which are not in MDT anymore as deleted
			$iterator = $DB->request([
				'FROM' => 'glpi_plugin_glpi2mdt_application_groups',
				'COUNT' => 'nb',
				'WHERE' => [
					'is_in_sync' => true,
					'is_deleted' => true,
				],
			]);
			$row = $iterator->current();
			$nb = $row['nb']; 
			$DB->delete(
				'glpi_plugin_glpi2mdt_application_groups',
				[
					'is_in_sync' => true,
					'is_deleted' => true,
				]
			);
			$DB->delete(
				'glpi_plugin_glpi2mdt_application_group_links',
				[
					'is_in_sync' => false,
					'is_deleted' => false,
				]
			);
			if (!$cron) {
				if ($nb !== 0){echo "<td>$nb ".__("lines deleted from table", 'glpi2mdt')." 'application_group_links'.</td></tr>";}
			}
		} else {
			$ok = -1;
		}
		// Task sequences
		// Mark lines in order to detect deleted ones in the source database
		$dst = $MDT->globalconfig['FileShare'].'/TaskSequences.xml';
		$tss = PluginGlpi2mdtCronTask::checkFile($dst, $task, $cron);

		if ($tss !== false) {
			$DB->update(
				'glpi_plugin_glpi2mdt_task_sequences',
				['is_in_sync' => false],
				['is_deleted' => false]
			);
			$nb = 0;
			foreach ($tss->ts as $ts) {
				$name = $ts->Name;
				$guid = $ts['guid'];
				$id = $ts->ID;
				if (isset($ts['enable']) and ($ts['enable'] == 'True')) {$enable = true; } else {$enable = false;}
				if (isset($ts['hide']) and ($ts['hide'] == 'True')) {$hide = true; } else {$hide = false;}

				$iterator = $DB->request([
					'FROM' => 'glpi_plugin_glpi2mdt_task_sequences',
					'WHERE' => ['guid' => $guid],
				]);
				if (count($iterator) > 0) {
					$DB->update(
						'glpi_plugin_glpi2mdt_task_sequences',
						[
							'id' => $id,
							'name' => $name,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						],
						['guid' => $guid]
					);
				} else {
					$DB->insert(
						'glpi_plugin_glpi2mdt_task_sequences',
						[
							'id' => $id,
							'guid' => $guid,
							'name' => $name,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						]
					);
				}
				$nb += 1;
			}
			if (!$cron) {
				if ($nb !== 0){echo "<tr class='tab_bg_1'><td>$nb ".__("lines loaded into table", 'glpi2mdt')." 'task_sequences'.</td>";}
			}
			// Mark lines which are not in MDT anymore as deleted
			$iterator = $DB->request([
				'FROM' => 'glpi_plugin_glpi2mdt_task_sequences',
				'COUNT' => 'nb',
				'WHERE' => [
					'is_in_sync' => true,
					'is_deleted' => true,
				],
			]);
			$row = $iterator->current();
			$nb = $row['nb']; 
			$DB->delete(
				'glpi_plugin_glpi2mdt_task_sequences',
				[
					'is_in_sync' => true,
					'is_deleted' => true,
				]
			);
			if (!$cron) {
				if ($nb !== 0){echo "<td>$nb ".__("lines deleted from table", 'glpi2mdt')." 'task_sequence'.</td></tr>";}
			}
		} else {
			$ok = -1;
		}
		// Task sequence groups
		// Mark lines in order to detect deleted ones in the source database
		$dst = $MDT->globalconfig['FileShare'].'/TaskSequenceGroups.xml';
		$groups = PluginGlpi2mdtCronTask::checkFile($dst, $task, $cron);

		if ($groups !== false) {
			$DB->update(
				'glpi_plugin_glpi2mdt_task_sequence_groups',
				['is_in_sync' => false],
				['is_deleted' => false]
			);
			$DB->update(
				'glpi_plugin_glpi2mdt_task_sequence_group_links',
				['is_in_sync' => false],
				['is_deleted' => false]
			);
			$nb = 0;
			foreach ($groups->group as $group) {
				$name = $group->Name;
				$guid = $group['guid'];
				if (isset($group['enable']) and ($group['enable'] == 'True')) {$enable = true; } else {$enable = false;}
				if (isset($group['hide']) and ($group['hide'] == 'True') and ($name <> 'hidden')) {$hide = true; } else {$hide = false;}
				$iterator = $DB->request([
					'FROM' => 'glpi_plugin_glpi2mdt_task_sequence_groups',
					'WHERE' => ['guid' => $guid],
				]);
				if (count($iterator) > 0) {
					$DB->update(
						'glpi_plugin_glpi2mdt_task_sequence_groups',
						[
							'name' => $name,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						],
						['guid' => $guid]
					);
				} else {
					$DB->insert(
						'glpi_plugin_glpi2mdt_task_sequence_groups',
						[
							'guid' => $guid,
							'name' => $name,
							'hide' => $hide,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						]
					);
				}
				$nb += 1;
				foreach ($group->member as $sequence_guid) {
					$iterator = $DB->request([
						'FROM' => 'glpi_plugin_glpi2mdt_task_sequence_group_links',
						'WHERE' => [
							'group_guid' => $guid,
							'sequence_guid' => $sequence_guid,
						],
					]);
					if (count($iterator) > 0) {
						$DB->update(
							'glpi_plugin_glpi2mdt_task_sequence_group_links',
							[
								'is_deleted' => false,
								'is_in_sync' => true,
							],
							[
								'group_guid' => $guid,
								'sequence_guid' => $sequence_guid,
							]
						);
					} else {
						$DB->insert(
							'glpi_plugin_glpi2mdt_task_sequence_group_links',
							[
								'group_guid' => $guid,
								'sequence_guid' => $sequence_guid,
								'is_deleted' => false,
								'is_in_sync' => true,
							]
						);
					}
				}
			}
			if (!$cron) {
				if ($nb !== 0){echo "<tr class='tab_bg_1'><td>$nb ".__("lines loaded into table", 'glpi2mdt')." 'task_sequence_groups'.</td>";}
			}
			// Mark lines which are not in MDT anymore as deleted
			$iterator = $DB->request([
				'FROM' => 'glpi_plugin_glpi2mdt_task_sequence_groups',
				'COUNT' => 'nb',
				'WHERE' => [
					'is_in_sync' => true,
					'is_deleted' => true,
				],
			]);
			$row = $iterator->current();
			$nb = $row['nb']; 
			$DB->delete(
				'glpi_plugin_glpi2mdt_task_sequence_groups',
				[
					'is_in_sync' => true,
					'is_deleted' => true,
				]
			);
			$DB->delete(
				'glpi_plugin_glpi2mdt_task_sequence_group_links',
				[
					'is_in_sync' => false,
					'is_deleted' => false,
				]
			);
			if (!$cron) {
				if ($nb !== 0){echo "<td>$nb ".__("lines deleted from table", 'glpi2mdt')." 'task_sequence_group_links'.</td></tr>";}
			}
		} else {
			$ok = -1;
		}

		// Operating systems
		// Mark lines in order to detect deleted ones in the source database
		$dst = $MDT->globalconfig['FileShare'].'/OperatingSystems.xml';
		$oss = PluginGlpi2mdtCronTask::checkFile($dst, $task, $cron);

		if ($oss !== false) {
			$DB->update(
				'glpi_plugin_glpi2mdt_operating_systems',
				['is_in_sync' => false],
				['is_deleted' => false]
			);
			$nb = 0;
			foreach ($oss->os as $os) {
				$name = $os->ImageName;
				$guid = $os['guid'];
				$id = $os->Name;
				if (isset($os['enable']) and ($os['enable'] == 'True')) {$enable = true; } else {$enable = false;}
				if (isset($os['hide']) and ($os['hide'] == 'True')) {$hide = true; } else {$hide = false;}
				$iterator = $DB->request([
					'FROM' => 'glpi_plugin_glpi2mdt_operating_systems',
					'WHERE' => ['guid' => $guid],
				]);
				if (count($iterator) > 0) {
					$DB->update(
						'glpi_plugin_glpi2mdt_operating_systems',
						[
							'id' => $id,
							'name' => $name,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						],
						['guid' => $guid]
					);
				} else {
					$DB->insert(
						'glpi_plugin_glpi2mdt_operating_systems',
						[
							'id' => $id,
							'guid' => $guid,
							'name' => $name,
							'enable' => $enable,
							'is_deleted' => false,
							'is_in_sync' => true,
						]
					);
				}
				$nb += 1;
			}
			if (!$cron) {
				if ($nb !== 0){echo "<tr class='tab_bg_1'><td>$nb ".__("lines loaded into table", 'glpi2mdt')." 'operating_systems'.</td>";}
			}
			// Mark lines which are not in MDT anymore as deleted
			$iterator = $DB->request([
				'FROM' => 'glpi_plugin_glpi2mdt_operating_systems',
				'COUNT' => 'nb',
				'WHERE' => [
					'is_in_sync' => true,
					'is_deleted' => true,
				],
			]);
			$row = $iterator->current();
			$nb = $row['nb']; 
			$DB->delete(
				'glpi_plugin_glpi2mdt_operating_systems',
				[
					'is_in_sync' => true,
					'is_deleted' => true,
				]
			);
			if (!$cron) {
				if ($nb !== 0){echo "<td>$nb ".__("lines deleted from table", 'glpi2mdt')." 'operating_systems'.</td></tr></table>";}
			}
		} else {
			$ok = -1;
		}   
		return $ok;
	}

	/**
		* Task to synchronize data between MDT and GLPI in Master-Master
		* and in Strict modes
		* Can be used atomically to update one machine, or globally by cron
		*
		* @param $task Object of CronTask class for log / stat
		* @param $id; computer ID to update, 0 means 'ALL'
		*
		* @return integer
		*    >0 : done
		*    <0 : to be run again (not finished)
		*     0 : nothing to be done
	*/
	static function cronSyncMasterAndStrict($task, $id = 0) {
		global $DB;
		$MDT = new PluginGlpi2mdtMdt();
		$globalconfig = $MDT->globalconfig;
		$mode = $globalconfig['Mode'];
		if ($mode == "Loose") {
			$task->log("This cron task does not run in loosely coupled mode");
			return 0;
		}
		// Build array of valid variables
		$variables = $globalconfig['variables'];
		if ($id > 0) {
			// We are working on one single computer
			$mdt = $MDT->getMdtIds($id);
			$mdtids = "AND c." . $mdt['mdtids'];
			$arraymdtids = $mdt['arraymdtids'];
		} else {
			$mdtids = '';
		}
		try {
			//GET computer(s) and settings
			$query = "SELECT * FROM dbo.ComputerIdentity c, dbo.Settings s WHERE c.id=s.id $mdtids";
			$result = $MDT->queryOrDie($query, "Cannot retrieve computers from MDT");
			if (isset($task)) {
				$task->log("Start data synchronisation in $mode mode");
				$task->setVolume($MDT->numrows($result));
				$task->log("Computer entries found in MDT database");
			}
			$correspondances = [];
			$deleted = 0;
			while ($row = $MDT->fetch_array($result)) {
				// Find correspondance in GLPI for all computers found in MDT
				$iterator = $DB->request([
					'SELECT' => ['DISTINCT c.id AS id'],
					'FROM' => 'glpi_computers AS c',
					'INNER JOIN' => [
						'glpi_networkports AS n' => [
							'ON' => [
								'c' => 'id',
								'n' => 'items_id',
							],
						],
					],
					'WHERE' => [
						'n.itemtype' => 'Computer',
						'n.instantiation_type' => 'NetworkPortEthernet',
						'c.is_deleted' => false,
						'n.is_deleted' => false,
						'c.name' => $row['Description'],
						'UPPER(n.mac)' => $row['MacAddress'],
						'c.serial' => $row['SerialNumber'],
						'c.otherserial' => $row['AssetTag'],
						'c.uuid' => $row['UUID'],
					],
					'ORDER' => ['c.id'],
				]);
				if (count($iterator) == 1) {
					$array = $iterator->current();
					$id = $array['id'];
					$correspondances[$id] = 0;
					if ($mode == "Master") {
						// Mark settings that may have to be deleted
						$DB->update(
							'glpi_plugin_glpi2mdt_settings',
							['is_in_sync' => false],
							[
								'type' => 'C',
								'category' => 'C',
								'id' => $id,
							]
						);
						// Update GLPI with data from MDT
						$fields = 0;
						foreach ($row as $key => $value) {
							if (isset($variables[$key]) && $value != '' && $value !== null) {
								try {
									$DB->insertOrUpdate(
										'glpi_plugin_glpi2mdt_settings',
										[
											'id' => $id,
											'type' => 'C',
											'category' => 'C',
											'key' => $key,
											'value' => $value,
											'is_in_sync' => true,
										],
										[
											'id' => $id,
											'type' => 'C',
											'category' => 'C',
											'key' => $key,
										]
									);
									$fields += 1;
								} catch (Exception $e) {
									$task->log("Can't insert/update setting for key '$key': " . $e->getMessage());
								}
							}
						}
						// Supprimer les paramètres non synchronisés
						$DB->delete(
							'glpi_plugin_glpi2mdt_settings',
							[
								'type' => 'C',
								'category' => 'C',
								'is_in_sync' => false,
								'id' => $id,
							]
						);
						// Keep the highest number of fields updated for one single comuputer in GLPI
						$correspondances[$id] = max($correspondances[$id], $fields);
					} else if ($mode == "Strict") {
						// Check if computer is active in GLPI. If not, remove from MDT
						$activeIterator = $DB->request([
							'SELECT' => ['value'],
							'FROM' => 'glpi_plugin_glpi2mdt_settings',
							'WHERE' => [
								'type' => 'C',
								'category' => 'C',
								'key' => 'OSInstall',
								'value' => 'YES',
								'id' => $id,
							],
						]);
						if (count($activeIterator) != 1) {
							try {
								$MDT->queryOrDie("DELETE FROM dbo.Settings WHERE Type='C' AND id=" . $row['ID'], "Can't delete from MDT Settings");
								$MDT->queryOrDie("DELETE FROM dbo.ComputerIdentity WHERE id=" . $row['ID'], "Can't delete from MDT ComputerIdentity");
								$deleted += 1;
							} catch (Exception $e) {
								$task->log("Error deleting from MDT: " . $e->getMessage());
							}
						}
					}
				}
			}
			if (isset($task)) {
				if ($mode == 'Master') {
					// Some computers in GLPI may have been updated several times (once per mac address)
					$task->setVolume(count($correspondances));
					$task->log("computers updated in GLPI");
					$task->setVolume(array_sum($correspondances));
					$task->log("settings updated in GLPI");
				} else if ($mode == 'Strict') {
					$task->setVolume(count($correspondances));
					$task->log("computers checked in GLPI");
					$task->setVolume($deleted);
					$task->log("Computers deleted in MDT");
				}
				return 1;
			}
		} catch (Exception $e) {
			$task->log("Fatal error during synchronisation: " . $e->getMessage());
			return 0;
		}
		return 0;
	}

	/**
		* Task to reset the OSinstall flag at specified time
		*
		* @param $task Object of CronTask class for log / stat
		*
		* @return integer
		*    >0 : done
		*    <0 : to be run again (not finished)
		*     0 : nothing to be done
	*/

	static function cronExpireOSInstallFlag($task) {
		global $DB;
		$MDT = new PluginGlpi2mdtMdt();
		$globalconfig = $MDT->globalconfig;

		try {
			$iterator = $DB->request([
				'SELECT' => ['id'],
				'FROM' => 'glpi_plugin_glpi2mdt_settings',
				'WHERE' => [
					'type' => 'C',
					'category' => 'C',
					'key' => 'OSInstallExpire',
					'value' => ['<=', time()],
				],
			]);
			if (count($iterator) === 0) {
				$task->log("No records to expire, exiting");
				return 0;
			}
			$nb = 0;
			foreach ($iterator as $row) {
				$nb += 1;
				$id = $row['id'];
				$ids = $MDT->getMdtIDs($id);
				// Cancel installation flag directly into MDT and MSSQL
				try {
					$query = "UPDATE dbo.Settings SET OSInstall='' WHERE type='C' AND " . $ids['mdtids'];
					if (!$MDT->query($query)) {
						$task->log("Can't reset OSInstall flag\n\nQuery: $query\n\nError: " . $MDT->dberror());
					}
				} catch (Exception $e) {
					$task->log("Error updating MDT/MSSQL: " . $e->getMessage());
				}
				// Do the same now on GLPI database
				try {
					$DB->delete('glpi_plugin_glpi2mdt_settings', [
						'type' => 'C',
						'category' => 'C',
						'id' => $id,
						[
							'OR' => [
								['key' => 'OSInstall', 'value' => 'YES'],
								['key' => 'OSInstallExpire'],
							],
						],
					]);
				} catch (Exception $e) {
					$task->log("Database error: " . $e->getMessage());
				}
			}
			$task->log("$nb record(s) expired");
			$task->setVolume($nb);
			return 1;
		} catch (Exception $e) {
			$task->log("Fatal error: " . $e->getMessage());
			return 0;
		}
	}

	/**
		* Get cron "type" information valid for all tasks in this file
		*
		* @param nb, no idea what it is used for.
		* @return string type for the cron list page
	*/
	static function getTypeName($nb=0) {
		return __('Glpi2mdt Plugin', 'glpi2mdt');
	}


	/**
		* get Cron descriptions for crons defined in this class
		*
		* @param $name string name of the task
		*
		* @return array of string
	**/
	static function cronInfo($name) {
		switch ($name) {
			case 'checkGlpi2mdtUpdate' :
				return array('description' => __('Check for new updates', 'glpi2mdt'));
			case 'updateBaseconfigFromMDT' :
				return array('description' => __('Update base data from MDT XML files and MS-SQL DB', 'glpi2mdt'));
			case 'syncMasterMaster' :
				return array('description' => __('Synchronize data between MDT and GLPI in Master-Master mode', 'glpi2mdt'));
			case 'expireOSInstallFlag' :
				return array('description' => __('Disable "OS Install" flag when expired', 'glpi2mdt'));
		}
	}

	/**
		* Check if xml file is accessible and valid
		*
		* @param $file file to check
		* $task task object is launched from cron
		* $cron flag "started by cron or interactively"
		*
		* @return "false" if failed, handle to XML if successful
	**/
	static function checkFile($file, $task, $cron) {
		if (!file_exists($file)) {
			if ($cron) {
				$task->log("File '$file' not found. Check mounting point.");
			} else {
				echo "<tr class='tab_bg_1'><td><font color='red'>". sprintf(__("File '%s' not found.", 'glpi2mdt'), $file)."</font></td></tr> ";
			}
			return false;
		}
		if (!is_readable($file)) {
			if ($cron) {
				$task->log("File '$file' exists but is not readable. check access rights, and more specifically SELinux settings.");
			} else {
				echo "<tr class='tab_bg_1'><td><font color='red'>".sprintf(__("Looks like '%s' exists but is not readable. ", 'glpi2mdt'), $file);
				echo "<br>Check access rights, and more specifically SELinux settings.</font></td></tr>";
			}
			return false;
		}
		$XML = simplexml_load_file($file);
		if ($XML === false) {
			if ($cron) {
				$task->log("File '$file' contains no valid data. Check MDT configuration");
			} else {
				echo "<tr class='tab_bg_1'><td><font color='red'>".sprintf(__("File '%s' contains no valid data. Check MDT configuration", 'glpi2mdt'), $file);
				echo "</font></td></tr>";
			}
			return false;
		}
		return $XML;
	}
}
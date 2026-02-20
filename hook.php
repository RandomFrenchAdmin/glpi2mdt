<?php
/*
 -------------------------------------------------------------------------
 glpi2mdt plugin for GLPI
 Copyright (C) 2017 by the glpi2mdt Development Team.

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

/**
	* Plugin install process, create databases and crontasks
	*
	* @return boolean
*/
function plugin_glpi2mdt_install() {
	global $DB;

	$dbversion = 1;

	// Function to securely create a table
	$createTable = function($tableName, $tableDefinition) use ($DB) {
		if (!$DB->tableExists($tableName)) {
			$DB->doQuery($tableDefinition) or die("Error creating $tableName: " . $DB->error());
		}
	};

	// Function to register a cron task
	$registerCronTask = function($className, $method, $frequency, $params) {
		CronTask::Register($className, $method, $frequency, $params);
	};

	// Global plugin settings
	$createTable("glpi_plugin_glpi2mdt_parameters", "
		CREATE TABLE `glpi_plugin_glpi2mdt_parameters` (
			`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`parameter` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			`scope` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global',
			`value_num` int(11) DEFAULT NULL,
			`value_char` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			`is_deleted` boolean NOT NULL DEFAULT '0',
			PRIMARY KEY (`id`),
			UNIQUE KEY `Constraint` (`parameter`, `scope`),
			INDEX `is_deleted` (`is_deleted` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// Insertion de la version de la base de données
	$result = $DB->request([
		'FROM' => 'glpi_plugin_glpi2mdt_parameters',
		'COUNT' => 'count',
		'WHERE' => [
			'parameter' => 'DBVersion',
			'scope' => 'global'
		]
	]);
	$row = $result->current();
	$count = $row['count']; 
	if ($count == 0) {
		// Insérer la version de la base de données
		$DB->insert("glpi_plugin_glpi2mdt_parameters", [
			'parameter' => 'DBVersion',
			'scope' => 'global',
			'value_num' => $dbversion,
			'is_deleted' => false
		]);
	}

	// 2. Table des paramètres individuels
	$createTable("glpi_plugin_glpi2mdt_settings", "
		CREATE TABLE `glpi_plugin_glpi2mdt_settings` (
			`id` int(11) UNSIGNED NOT NULL auto_increment,
			`category` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
			`type` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
			`key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
			`value` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
			`is_in_sync` tinyint(1) NOT NULL DEFAULT '1',
			PRIMARY KEY (`id`, `type`, `category`, `key`),
			KEY `is_in_sync` (`is_in_sync`),
			KEY `type` (`type`),
			KEY `category` (`category`)
		) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 3. Table des rôles
	$createTable("glpi_plugin_glpi2mdt_roles", "
		CREATE TABLE `glpi_plugin_glpi2mdt_roles` (
			`id` int(11) UNSIGNED NOT NULL,
			`role` varchar(255) collate utf8mb4_unicode_ci default NULL,
			`is_deleted` boolean NOT NULL default true,
			`is_in_sync` boolean NOT NULL default false,
			PRIMARY KEY (`id`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 4. Table des applications
	$createTable("glpi_plugin_glpi2mdt_applications", "
		CREATE TABLE `glpi_plugin_glpi2mdt_applications` (
			`guid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`shortname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`version` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`hide` boolean NOT NULL DEFAULT false,
			`enable` boolean NOT NULL DEFAULT true,
			`is_deleted` boolean NOT NULL DEFAULT false,
			`is_in_sync` boolean NOT NULL DEFAULT true,
			PRIMARY KEY (`guid`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 5. Table des groupes d'applications
	$createTable("glpi_plugin_glpi2mdt_application_groups", "
		CREATE TABLE `glpi_plugin_glpi2mdt_application_groups` (
			`guid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`hide` boolean NOT NULL DEFAULT false,
			`enable` boolean NOT NULL DEFAULT '1',
			`is_deleted` boolean NOT NULL DEFAULT false,
			`is_in_sync` boolean NOT NULL DEFAULT true,
			PRIMARY KEY (`guid`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 6. Table des liens entre groupes et applications
	$createTable("glpi_plugin_glpi2mdt_application_group_links", "
		CREATE TABLE `glpi_plugin_glpi2mdt_application_group_links` (
			`group_guid` varchar(38) COLLATE utf8mb4_unicode_ci NOT NULL,
			`application_guid` varchar(38) COLLATE utf8mb4_unicode_ci NOT NULL,
			`is_deleted` boolean NOT NULL DEFAULT false,
			`is_in_sync` boolean NOT NULL DEFAULT true,
			PRIMARY KEY (`group_guid`, `application_guid`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 7. Table des systèmes d'exploitation
	$createTable("glpi_plugin_glpi2mdt_operating_systems", "
		CREATE TABLE `glpi_plugin_glpi2mdt_operating_systems` (
			`id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`guid` varchar(38) COLLATE utf8mb4_unicode_ci NOT NULL,
			`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`enable` boolean NOT NULL DEFAULT true,
			`is_deleted` boolean NOT NULL DEFAULT false,
			`is_in_sync` boolean NOT NULL DEFAULT true,
			PRIMARY KEY (`id`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 8. Table des séquences de tâches
	$createTable("glpi_plugin_glpi2mdt_task_sequences", "
		CREATE TABLE `glpi_plugin_glpi2mdt_task_sequences` (
			`id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`guid` varchar(38) COLLATE utf8mb4_unicode_ci NOT NULL,
			`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`hide` boolean NOT NULL DEFAULT false,
			`enable` boolean NOT NULL DEFAULT true,
			`is_deleted` boolean NOT NULL DEFAULT false,
			`is_in_sync` boolean NOT NULL DEFAULT true,
			PRIMARY KEY (`id`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 9. Table des groupes de séquences de tâches
	$createTable("glpi_plugin_glpi2mdt_task_sequence_groups", "
		CREATE TABLE `glpi_plugin_glpi2mdt_task_sequence_groups` (
			`guid` varchar(38) COLLATE utf8mb4_unicode_ci NOT NULL,
			`name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
			`hide` boolean NOT NULL DEFAULT false,
			`enable` boolean NOT NULL DEFAULT true,
			`is_deleted` boolean NOT NULL DEFAULT false,
			`is_in_sync` boolean NOT NULL DEFAULT true,
			PRIMARY KEY (`guid`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 10. Table des liens entre groupes et séquences de tâches
	$createTable("glpi_plugin_glpi2mdt_task_sequence_group_links", "
		CREATE TABLE `glpi_plugin_glpi2mdt_task_sequence_group_links` (
			`group_guid` varchar(38) COLLATE utf8mb4_unicode_ci NOT NULL,
			`sequence_guid` varchar(38) COLLATE utf8mb4_unicode_ci NOT NULL,
			`is_deleted` boolean NOT NULL DEFAULT '0',
			`is_in_sync` boolean NOT NULL DEFAULT '1',
			PRIMARY KEY (`group_guid`, `sequence_guid`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 11. Table des modèles
	$createTable("glpi_plugin_glpi2mdt_models", "
		CREATE TABLE `glpi_plugin_glpi2mdt_models` (
			`id` int(11) UNSIGNED NOT NULL,
			`make` varchar(50) NOT NULL,
			`name` varchar(50) DEFAULT NULL,
			`tech_code` varchar(50) NOT NULL,
			`is_in_sync` tinyint(4) NOT NULL DEFAULT '1',
			`is_deleted` tinyint(4) NOT NULL DEFAULT '0',
			`glpi_plugin_glpi2mdt_modelscol` varchar(45) DEFAULT NULL,
			PRIMARY KEY (`make`, `tech_code`),
			UNIQUE KEY (`id`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// 12. Table des descriptions
	$createTable("glpi_plugin_glpi2mdt_descriptions", "
		CREATE TABLE `glpi_plugin_glpi2mdt_descriptions` (
			`column_name` varchar(255) collate utf8mb4_unicode_ci NOT NULL,
			`category_order` integer NOT NULL default 0,
			`category` varchar(255) default '',
			`description` varchar(255) collate utf8mb4_unicode_ci default '',
			`is_deleted` boolean NOT NULL DEFAULT false,
			`is_in_sync` boolean NOT NULL DEFAULT true,
			PRIMARY KEY (`column_name`),
			INDEX `is_deleted` (`is_deleted` ASC),
			INDEX `is_in_sync`(`is_in_sync` ASC)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
	");

	// Remove cron tasks
	CronTask::Unregister('Glpi2mdtCrontask');

	// Create or update crontask for checking new plugin updates
	CronTask::Register('PluginGlpi2mdtCrontask', 'checkGlpi2mdtUpdate', (3600 * 24), array('mode' => 2, 'allowmode' => 3, 'logs_lifetime' => 30, 'comment' => 'Daily task checking for updates'));

	// Create or update crontask for updating base data from MDT files and database
	CronTask::Register('PluginGlpi2mdtCrontask', 'updateBaseconfigFromMDT', 300, array('mode' => 2, 'allowmode' => 3, 'logs_lifetime' => 30, 'comment' => 'Update base data from MDT XML files and MS-SQL DB'));

	// Create or update crontask for syncrhonizing data between MDT and GLPI (Master-Master & Strict modes)
	CronTask::Register('PluginGlpi2mdtCrontask', 'syncMasterAndStrict', 3600, array('mode' => 2, 'allowmode' => 3, 'logs_lifetime' => 30, 'comment' => 'Synchronize data between MDT and GLPI in Master-Master and Strict modes'));

	// Create or update crontask for disabling "OS Install" flag when expired
	CronTask::Register('PluginGlpi2mdtCrontask', 'expireOSInstallFlag', 300, array('mode' => 2, 'allowmode' => 3, 'logs_lifetime' => 30, 'comment' => 'Disable "OS Install" flag when expired'));

	// Mise à jour de la version de la base de données
	$DB->update(
		'glpi_plugin_glpi2mdt_parameters',
		['parameter' => 'DBVersion'],
		['scope' => 'global', 'parameter' => 'database_version']
	);

	// Vérification de la version de la base de données
	$result = $DB->request([
		'SELECT' => ['SUM' => 'value_num AS version'],
		'FROM' => 'glpi_plugin_glpi2mdt_parameters',
		'WHERE' => [
			'scope' => 'global',
			'parameter' => 'DBVersion'
		]
	]);

	if (count($result) == 0) {
		die(__("Glpi2mdt database is corrupted. Please uninstall and reinstall the plugin", 'glpi2mdt'));
	}

	$currentdbversion = $result->current()['version'];

	// Mise à jour de la base de données si nécessaire
	if ($currentdbversion == 1) {
		// Ajouter ici les mises à jour spécifiques pour les versions futures
	}
	return true;
}

/**
	* Plugin uninstall process
	*
	* @return boolean
*/
function plugin_glpi2mdt_uninstall() {
	global $DB;

	// Plugin tables deletion
	$tables = ["glpi_plugin_glpi2mdt_application_group_links",
				"glpi_plugin_glpi2mdt_application_groups",
				"glpi_plugin_glpi2mdt_applications",
				"glpi_plugin_glpi2mdt_descriptions",
				"glpi_plugin_glpi2mdt_models",
				"glpi_plugin_glpi2mdt_operating_systems",
				"glpi_plugin_glpi2mdt_parameters",
				"glpi_plugin_glpi2mdt_roles",
				"glpi_plugin_glpi2mdt_settings",
				"glpi_plugin_glpi2mdt_task_sequence_group_links",
				"glpi_plugin_glpi2mdt_task_sequence_groups",
				"glpi_plugin_glpi2mdt_task_sequences"];

	foreach ($tables as $table) {
		$DB->dropTable($table, true);
	}

	// Remove cron tasks
	CronTask::Unregister('Glpi2mdtCrontask');

	return true;
}

/**
	* This function is called by GLPI when an update is made to a computer
	* It triggers an update of MDT just in case...
	*
	* @param  $item, object reference to a computer
	* @return nothing
*/
function updateMDT($item) {
	$id = $item->getID();
	$computer = new PluginGlpi2mdtComputer;
	$computer->updateMDT($id);
	unset($computer);
}
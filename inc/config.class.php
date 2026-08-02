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
// Contributors: Enzo Lefrancois
// Purpose of file: Plugin general settings management
// ----------------------------------------------------------------------

if (!defined('GLPI_ROOT')) {
	die(__("Sorry. You can't access directly to this file", 'glpi2mdt'));
}

class PluginGlpi2mdtConfig extends PluginGlpi2mdtMdt {

	/**
		* The right name for this class
		*
		* @var string
	*/
	static $rightname = 'config';


	/**
		* Store configuration parameters
		*
		* @param $key      string: global parameter name to be checked against validkeys array
		*
		* @param $value    string or number: corresponding value for the parameter
		*
		* @return       nothing but dies if failing to write into the database
	**/
	function updateValue($key, $value) {
		global $DB;
		if (!isset($this->validkeys[$key]) || in_array($key, ['_glpi_csrf_token', 'SAVE', 'UPDATE', 'TEST', 'INIT'])) {
			return false;
		}
		$type = $this->validkeys[$key];
		$data = [
			'parameter' => $key,
			'scope' => 'global',
			'is_deleted' => 0, // GLPI utilise 0 pour "non supprimé"
		];
		if ($type == 'txt') {
			$data['value_char'] = $value;
			$data['value_num'] = null;
		} elseif ($type == 'num' && ($value > 0 || $value === '')) {
			$data['value_num'] = ($value === '') ? null : (int)$value;
			$data['value_char'] = null;
		} else {
			Session::addMessageAfterRedirect("Invalid type for parameter $key: $type", true, ERROR);
			return false;
		}
		$table = 'glpi_plugin_glpi2mdt_parameters';
		$where = ['parameter' => $key, 'scope' => 'global'];
		try {
			$exists = $DB->request([
				'COUNT' => 'c',
				'FROM' => $table,
				'WHERE' => $where
			])->current()['c'] > 0;
			if ($exists) {
				$DB->update($table, $data, $where);
			} else {
				$DB->insert($table, $data);
			}
		} catch (Exception $e) {
			Session::addMessageAfterRedirect("Error updating parameter $key: " . $e->getMessage(), true, ERROR);
			return false;
		}
	}

	/**
	 * Shows form to set plugin configuration parameters
	 *
	 * @return void (outputs HTML form)
	 */
	function showPage() {
		$yesno['YES'] = __('YES', 'glpi2mdt');
		$yesno['NO'] = __('NO', 'glpi2mdt');

		echo '<form action="../front/config.form.php" method="post">';
		echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
		echo '<div class="spaced" id="tabsbody">';
		echo '<table class="tab_cadre_fixe">';

		// Database server name
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Database server name', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>' . sprintf(
			'<input type="text" name="DBServer" value="%s" size="50" class="ui-autocomplete-input" autocomplete="off" required pattern="[a-zA-Z0-9\\-\\.]+(\\.[a-zA-Z0-9\\-]+)*" placeholder="myMDTserver.mydomain.local"> &nbsp;&nbsp;&nbsp;',
			htmlspecialchars($this->globalconfig['DBServer'] ?? '', ENT_QUOTES, 'UTF-8')
		) . '</td>';
		echo '</tr>';

		// Database server port
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Database server port', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>' . sprintf(
			'<input type="number" name="DBPort" value="%s" size="5" class="ui-autocomplete-input" autocomplete="off" inputmode="numeric" placeholder="1433" min="1024" max="65535" required> &nbsp;&nbsp;&nbsp;',
			htmlspecialchars($this->globalconfig['DBPort'] ?? '', ENT_QUOTES, 'UTF-8')
		) . '</td>';
		echo '</tr>';

		// SSL self-signed certificate
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Enabling SSL using a self-signed certificate', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>';
		Dropdown::showFromArray("SSLSelfCert", $yesno, ['value' => $this->globalconfig['SSLSelfCert'] ?? 'NO']);
		echo '</td>';
		echo '</tr>';

		// Login
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Login', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>' . sprintf(
			'<input type="text" name="DBLogin" value="%s" size="50" class="ui-autocomplete-input" autocomplete="off" required pattern="[a-zA-Z0-9\\-_@.]+" placeholder="sa"> &nbsp;&nbsp;&nbsp;',
			htmlspecialchars($this->globalconfig['DBLogin'] ?? '', ENT_QUOTES, 'UTF-8')
		) . '</td>';
		echo '</tr>';

		// Password
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Password', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>' . sprintf(
			'<input type="password" name="DBPassword" value="%s" size="50" class="ui-autocomplete-input" autocomplete="off" required> &nbsp;&nbsp;&nbsp;',
			htmlspecialchars($this->globalconfig['DBPassword'] ?? '', ENT_QUOTES, 'UTF-8')
		) . '</td>';
		echo '</tr>';

		// Schema
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Schema', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>' . sprintf(
			'<input type="text" name="DBSchema" value="%s" size="50" class="ui-autocomplete-input" autocomplete="off" required pattern="[a-zA-Z0-9\\_\\-$]+" placeholder="MDT"> &nbsp;&nbsp;&nbsp;',
			htmlspecialchars($this->globalconfig['DBSchema'] ?? '', ENT_QUOTES, 'UTF-8')
		) . '</td>';
		echo '</tr>';

		// FileShare
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Local path to deployment share control directory', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>' . sprintf(
			'<input type="text" name="FileShare" value="%s" size="50" class="ui-autocomplete-input" autocomplete="off" required placeholder="/mnt/smb-share/Deployment-share/Control"> &nbsp;&nbsp;&nbsp;',
			htmlspecialchars($this->globalconfig['FileShare'] ?? '', ENT_QUOTES, 'UTF-8')
		) . '</td>';
		echo '</tr>';

		// Local admin password
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Local admin password', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>' . sprintf(
			'<input type="password" name="LocalAdmin" value="%s" size="50" class="ui-autocomplete-input" autocomplete="off" required> &nbsp;&nbsp;&nbsp;',
			htmlspecialchars($this->globalconfig['LocalAdmin'] ?? '', ENT_QUOTES, 'UTF-8')
		) . '</td>';
		echo '</tr>';

		// Password complexity
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Local admin password complexity', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>';
		Dropdown::showFromArray(
			"Complexity",
			[
				'Basic' => __('Same password on all machines', 'glpi2mdt'),
				'Trivial' => __('Password is hostname', 'glpi2mdt'),
				'Unique' => __('append \'-%hostname%\' to password', 'glpi2mdt')
			],
			['value' => $this->globalconfig['Complexity'] ?? 'Basic']
		);
		echo '</td>';
		echo '</tr>';

		// Link mode
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Link mode', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>';
		Dropdown::showFromArray(
			"Mode",
			[
				'Strict' => __('Strict Master-Slave', 'glpi2mdt'),
				'Loose' => __('Loose Master-Slave', 'glpi2mdt'),
				'Master' => __('Master-Master', 'glpi2mdt')
			],
			['value' => $this->globalconfig['Mode'] ?? 'Loose']
		);
		echo '</td>';
		echo '</tr>';

		// Check for new versions
		echo '<tr class="tab_bg_1">';
		echo '<td>' . __('Automatically check for new versions', 'glpi2mdt') . ' : &nbsp;&nbsp;&nbsp;</td>';
		echo '<td>';
		Dropdown::showFromArray(
			"CheckNewVersion",
			$yesno,
			['value' => $this->globalconfig['CheckNewVersion'] ?? 'NO']
		);
		echo '</td>';
		echo '</tr>';

		// Submit buttons (only if user has rights)
		if (PluginGlpi2mdtConfig::canUpdate()) {
			echo '<tr class="tab_bg_1">';
			echo '<td>' . sprintf(
				'<input type="submit" class="submit" value="%s" name="SAVE"/>',
				htmlspecialchars(__('Save', 'glpi2mdt'), ENT_QUOTES, 'UTF-8')
			) . '</td>';
			echo '<td>' . sprintf(
				'<input type="submit" class="submit" value="%s" name="UPDATE"/>',
				htmlspecialchars(__('Check new version', 'glpi2mdt'), ENT_QUOTES, 'UTF-8')
			) . '</td>';
			echo '</tr>';

			echo '<tr class="tab_bg_1">';
			echo '<td>' . sprintf(
				'<input type="submit" class="submit" value="%s" name="TEST"/>',
				htmlspecialchars(__('Test connection', 'glpi2mdt'), ENT_QUOTES, 'UTF-8')
			) . '</td>';
			echo '<td>' . sprintf(
				'<input type="submit" class="submit" value="%s" name="INIT"/>',
				htmlspecialchars(__('Initialise data', 'glpi2mdt'), ENT_QUOTES, 'UTF-8')
			) . '</td>';
			echo '</tr>';
		}

		echo '</table>';
		echo '</div>';
		echo '</form>';

		// Show alert if a new version is available
		$currentversion = PLUGIN_GLPI2MDT_VERSION;
		$latestversion = $this->globalconfig['LatestVersion'] ?? '';
		if (!empty($latestversion) && version_compare($currentversion, $latestversion, '<')) {
			echo '<div class="alert alert-warning text-center">';
			echo sprintf(
				htmlspecialchars(__('A new version of plugin glpi2mdt is available: v%s', 'glpi2mdt'), ENT_QUOTES, 'UTF-8'),
				htmlspecialchars($latestversion, ENT_QUOTES, 'UTF-8')
			);
			echo '</div>';
		}

		return true;
	}
}
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
	die("Sorry. You can't access directly to this file");
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
		if (!isset($this->validkeys[$key])) {
			return false;
		}
		$type = $this->validkeys[$key];
		$data = [
			'parameter'  => $key,
			'scope'      => 'global',
			'is_deleted' => 0, // GLPI utilise 0 pour "non supprimé"
		];
		if ($type == 'txt') {
			$data['value_char'] = $value;
			$data['value_num'] = null;
		} elseif ($type == 'num' && ($value > 0 || $value === '')) {
			$data['value_num'] = ($value === '') ? null : $value;
			$data['value_char'] = null;
		} else {
			return false;
		}
		$table = 'glpi_plugin_glpi2mdt_parameters';
		$where = ['parameter' => $key, 'scope' => 'global'];
		$exists = $DB->request([
			'COUNT' => 'c',
			'FROM'  => $table,
			'WHERE' => $where
		])->current()['c'] > 0;
		if ($exists) {
			$DB->update(
				$table,
				$data,
				$where
			);
		} else {
			$DB->insert($table, $data);
		}
	}


	/**
		* Shows form to set plugin configuration parameters
		*
		* @param none
		*
		* @return   outputs HTML form
	**/
	function showPage() {

		$yesno['YES'] = __('YES', 'glpi2mdt');
		$yesno['NO'] = __('NO', 'glpi2mdt');
		?>
		<form action="../front/config.form.php" method="post">
			<?php echo Html::hidden('_glpi_csrf_token', array('value' => Session::getNewCSRFToken())); ?>
			<div class="spaced" id="tabsbody">
				<table class="tab_cadre_fixe">
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Database server name', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php echo '<input type="text" name="DBServer" value="'.$this->globalconfig['DBServer'].'" size="50" class="ui-autocomplete-input" 
							autocomplete="off" required pattern="[a-Z0-9.]" placeholder="myMDTserver.mydomain.local"> &nbsp;&nbsp;&nbsp;' ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Database server port', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php echo '<input type="number" name="DBPort" value="'.$this->globalconfig['DBPort'].'" size="5" class="ui-autocomplete-input" 
							autocomplete="off" inputmode="numeric" placeholder="1433" min="1024" max="65535" required> &nbsp;&nbsp;&nbsp;' ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Enabling SSL using a self-signed certificate', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php Dropdown::showFromArray("SSLSelfCert", $yesno,
							array('value' => $this->globalconfig['SSLSelfCert'])
							); ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Login', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php echo '<input type="text" name="DBLogin" value="'.$this->globalconfig['DBLogin'].'" size="50" class="ui-autocomplete-input" 
							autocomplete="off" required pattern="[a-Z0-9]"> &nbsp;&nbsp;&nbsp;' ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Password', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php echo '<input type="password" name="DBPassword" value="'.$this->globalconfig['DBPassword'].'" size="50" class="ui-autocomplete-input" 
							autocomplete="off" required> &nbsp;&nbsp;&nbsp;' ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Schema', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php echo '<input type="text" name="DBSchema" value="'.$this->globalconfig['DBSchema'].'" size="50" class="ui-autocomplete-input" 
							autocomplete="off" required pattern="[a-Z0-9]" placeholder="MDT"> &nbsp;&nbsp;&nbsp;' ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Local path to deployment share control directory', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php echo '<input type="text" name="FileShare" value="'.$this->globalconfig['FileShare'].'" size="50" class="ui-autocomplete-input" 
							autocomplete="off" required placeholder="/mnt/smb-share/Deployment-share/Control"> &nbsp;&nbsp;&nbsp;' ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Local admin password', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php echo '<input type="password" name="LocalAdmin" value="'.$this->globalconfig['LocalAdmin'].'" size="50" class="ui-autocomplete-input" 
							autocomplete="off" required> &nbsp;&nbsp;&nbsp;' ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Local admin password complexity', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php Dropdown::showFromArray("Complexity", array(
							'Basic' => __('Same password on all machines', 'glpi2mdt'),
							'Trivial' => __('Password is hostname', 'glpi2mdt'),
							'Unique' => __('append \'-%hostname%\' to password', 'glpi2mdt')), array(
							'value' => $this->globalconfig['Complexity'])
							); ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Link mode', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php Dropdown::showFromArray("Mode", array(
							'Strict' => __('Strict Master-Slave', 'glpi2mdt'),
							'Loose' => __('Loose Master-Slave', 'glpi2mdt'),
							'Master' => __('Master-Master', 'glpi2mdt')), array(
							'value' => $this->globalconfig['Mode'])); ?>
						</td>
					</tr>
					<tr class="tab_bg_1">
						<td>
							<?php echo __('Automatically check for new versions', 'glpi2mdt'); ?> : &nbsp;&nbsp;&nbsp;
						</td><td>
							<?php Dropdown::showFromArray("CheckNewVersion", $yesno,
							array('value' => $this->globalconfig['CheckNewVersion'])
							); ?>
						</td>
					</tr>
					<?php
					if (PluginGlpi2mdtConfig::canUpdate()) {
						echo '<tr class="tab_bg_1">';
						echo '<td>';
						echo '<input type="submit" class="submit" value="'. __('Save', 'glpi2mdt').'" name="SAVE"/>';
						echo '</td>';
						echo '</td><td>';
						echo '<input type="submit" class="submit" value="'. __('Check new version', 'glpi2mdt').'" name="UPDATE"/>';
						echo '</td>';
						echo '</tr>';
						echo '<tr class="tab_bg_1">';
						echo '<td>';
						echo '<input type="submit" class="submit" value="'. __('Test connection', 'glpi2mdt').'" name="TEST"/>';
						echo '</td><td>';
						echo '<input type="submit" class="submit" value="'. __('Initialise data', 'glpi2mdt').'" name="INIT"/>';
						echo '</td>';
						echo '</tr>';
					} ?>
				</table>
			</div>
		</form>
		<?php
		// Show alert if a new version is available
		$currentversion = PLUGIN_GLPI2MDT_VERSION;
		$latestversion = $this->globalconfig['LatestVersion'];
		if (version_compare($currentversion, $latestversion, '<')) {
			echo "<div class='center'><font color='red'>";
			echo sprintf(__('A new version of plugin glpi2mdt is available: v%s', 'glpi2mdt'), $latestversion);
			echo "</font></div>";
		}
		return true;
	}
}
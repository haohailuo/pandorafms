<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// Load global vars
global $config;

check_login();

if (! check_acl ($config['id_user'], 0, "AW")) {
	db_pandora_audit("ACL Violation",
		"Trying to access downtime scheduler");
	require ("general/noaccess.php");
	return;
}

require_once ('include/functions_users.php');

// Header
ui_print_page_header(
	__("Planned Downtime") . ui_print_help_icon ('planned_downtime', true),
	"images/god1.png",
	false,
	"",
	true,
	"");

$delete_downtime = (int) get_parameter ('delete_downtime');
$id_downtime = (int) get_parameter ('id_downtime', 0);

// DELETE WHOLE DOWNTIME!
if ($delete_downtime) {
	$result = db_process_sql_delete('tplanned_downtime', array('id' => $id_downtime));
	
	$result2 = db_process_sql_delete('tplanned_downtime_agents', array('id' => $id_downtime));
	
	if (($result === false) OR ($result2 === false)) {
		echo '<h3 class="error">'.__('Not deleted. Error deleting data').'</h3>';
	}
	else {
		echo '<h3 class="suc">'.__('Successfully deleted').'</h3>';
	}
}

$groups = users_get_groups ();

// View available downtimes present in database (if any of them)
$table->class = 'databox';
//Start Overview of existing planned downtime
$table->width = '98%';
$table->data = array ();
$table->head = array ();
$table->head[0] = __('Name #Ag.');
$table->head[1] = __('Description');
$table->head[2] = __('Group');
$table->head[3] = __('Type');
$table->head[4] = __('Execution');
$table->head[5] = __('Configuration');
$table->head[6] = __('Delete');
$table->head[7] = __('Update');
$table->head[8] = __('Running');
$table->head[9] = __('Stop downtime');
$table->align[2] = "center";
//$table->align[5] = "center";
$table->align[6] = "center";
$table->align[7] = "center";
$table->align[8] = "center";
$table->align[9] = "center";

if(!empty($groups)) {
	$sql = "SELECT *
		FROM tplanned_downtime
		WHERE id_group IN (" . implode (",", array_keys ($groups)) . ")";
	$downtimes = db_get_all_rows_sql ($sql);
}
else {
	$downtimes = array();
}

if (!$downtimes) {
	echo '<div class="nf">'.__('No planned downtime').'</div>';
}
else {
	foreach ($downtimes as $downtime) {
		$data = array();
		$total  = db_get_sql ("SELECT COUNT(id_agent)
			FROM tplanned_downtime_agents
			WHERE id_downtime = ".$downtime["id"]);
		
		$data[0] = $downtime['name']. " ($total)";
		$data[1] = $downtime['description'];
		$data[2] = ui_print_group_icon ($downtime['id_group'], true);
		
		$type_text = array('quiet' => __('Quiet'),
			'disable_agents' => __('Disabled Agents'),
			'disable_agents_alerts' => __('Disabled only Alerts'));
		
		$data[3] = $type_text[$downtime['type_downtime']];
		
		$execution_text = array('once' => __('once'),
			'periodically' => __('Periodically'));
		
		$data[4] = $execution_text[$downtime['type_execution']];
		
		switch ($downtime['type_execution']) {
			case 'once':
				$data[5] = date ("Y-m-d H:i", $downtime['date_from']) .
					"&nbsp;" . __('to') . "&nbsp;".
					date ("Y-m-d H:i", $downtime['date_to']);
				break;
			case 'periodically':
				switch ($downtime['type_periodicity']) {
					case 'weekly':
						$data[5] = __('Weekly:');
						$data[5] .= "&nbsp;";
						if ($downtime['monday']) {
							$data[5] .= __('Mon');
							$data[5] .= "&nbsp;";
						}
						if ($downtime['tuesday']) {
							$data[5] .= __('Tue');
							$data[5] .= "&nbsp;";
						}
						if ($downtime['wednesday']) {
							$data[5] .= __('Wed');
							$data[5] .= "&nbsp;";
						}
						if ($downtime['thursday']) {
							$data[5] .= __('Thu');
							$data[5] .= "&nbsp;";
						}
						if ($downtime['friday']) {
							$data[5] .= __('Fri');
							$data[5] .= "&nbsp;";
						}
						if ($downtime['saturday']) {
							$data[5] .= __('Sat');
							$data[5] .= "&nbsp;";
						}
						if ($downtime['sunday']) {
							$data[5] .= __('Sun');
							$data[5] .= "&nbsp;";
						}
						$data[5] .= "&nbsp;(" . $downtime['periodically_time_from']; 
						$data[5] .= "-" . $downtime['periodically_time_to'] . ")";
						break;
					case 'monthly':
						$data[5] = __('Monthly:');
						$data[5] .= __('From day') . "&nbsp;" . $downtime['periodically_day_from'];
						$data[5] .= "/" . __('To day') . "&nbsp;";
						$data[5] .= $downtime['periodically_day_to'];
						$data[5] .= "&nbsp;(" . $downtime['periodically_time_from'];
						$data[5] .= "-" . $downtime['periodically_time_to'] . ")";
						break;
				}
				break;
		}
		
		if ($downtime["executed"] == 0) {
			$data[6] = '<a href="index.php?sec=gagente&amp;sec2=godmode/agentes/planned_downtime.list&amp;'.
				'delete_downtime=1&amp;id_downtime='.$downtime['id'].'">' .
			html_print_image("images/cross.png", true, array("border" => '0', "alt" => __('Delete')));
			$data[7] = '<a
				href="index.php?sec=gagente&amp;sec2=godmode/agentes/planned_downtime.editor&amp;edit_downtime=1&amp;id_downtime='.$downtime['id'].'">' .
			html_print_image("images/config.png", true, array("border" => '0', "alt" => __('Update'))) . '</a>';
		}
		else {
			$data[6]= "N/A";
			$data[7]= "N/A";
		
		}
		if ($downtime["executed"] == 0)
			$data[8] = html_print_image ("images/pixel_green.png", true, array ('width' => 20, 'height' => 20, 'alt' => __('Executed')));
		else
			$data[8] = html_print_image ("images/pixel_red.png", true, array ('width' => 20, 'height' => 20, 'alt' => __('Not executed')));
		
		if ($downtime["executed"] != 0) {
			$data[9] = '<a href="index.php?sec=gagente&amp;sec2=godmode/agentes/planned_downtime.list&amp;stop_downtime=1&amp;id_downtime='.$downtime['id'].'">' .
			html_print_image("images/cancel.png", true, array("border" => '0', "alt" => __('Stop downtime')));
		}
		
		array_push ($table->data, $data);
	}
	html_print_table ($table);
}
echo '<div class="action-buttons" style="width: '.$table->width.'">';

echo '<form method="post" action="index.php?sec=gagente&amp;sec2=godmode/agentes/planned_downtime.editor">';
html_print_submit_button (__('Create'), 'create', false, 'class="sub next"');
echo '</form>';
echo '</div>';
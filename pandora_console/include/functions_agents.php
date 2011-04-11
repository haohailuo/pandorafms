<?php

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the  GNU Lesser General Public License
// as published by the Free Software Foundation; version 2

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

/**
 * @package Include
 * @subpackage Agents
 */

/**
 * Creates an agent
 *
 * @param string Agent name.
 * @param string Group to be included.
 * @param int Agent interval
 * @param string Agent IP
 *
 * @return int New agent id if created. False if it could not be created.
 */
function create_agent ($name, $id_group, $interval, $ip_address, $values = false) {
	if (empty ($name))
		return false;
	if (empty ($id_group))
		return false;
	if (empty ($ip_address))
		return false;
	$interval = safe_int ($interval,1, 300);
	if (empty ($interval))
		return false;
	if (! is_array ($values))
		$values = array ();
	$values['nombre'] = $name;
	$values['id_grupo'] = $id_group;
	$values['intervalo'] = $interval;
	$values['direccion'] = $ip_address;
	
	process_sql_begin ();
	
	$id_agent = process_sql_insert ('tagente', $values);
	if ($id_agent === false) {
		process_sql_rollback ();
		return false;
	}
	
	// Create address for this agent in taddress
	agent_add_address ($id_agent, $ip_address);
	
	// Create special module agent_keepalive
	$id_agent_module = process_sql_insert ('tagente_modulo', 
		array ('nombre' => 'agent_keepalive',
			'id_agente' => $id_agent,
			'id_tipo_modulo' => 100,
			'descripcion' => __('Agent keepalive monitor'),
			'id_modulo' => 1,
			'min_warning' => 0,
			'max_warning' => 1));
	
	if ($id_agent_module === false) {
		process_sql_rollback ();
		return false;
	}
	
	$result = process_sql_insert ('tagente_estado', 
			array ('id_agente_modulo' => $id_agent_module,
				'datos' => '',
				'timestamp' => 0,
				'estado' => 0,
				'id_agente' => $id_agent,
				'last_try' => 0,
				'utimestamp' => 0,
				'current_interval' => 0,
				'running_by' => 0,
				'last_execution_try' => 0));
	
	if ($result === false) {
		process_sql_rollback ();
		return false;
	}
	
	process_sql_commit ();
	
	pandora_audit ("Agent management", "New agent '$name' created");

	return $id_agent;
}

/**
 * Get all the simple alerts of an agent.
 *
 * @param int Agent id
 * @param string Filter on "fired", "notfired" or "disabled". Any other value
 * will not do any filter.
 * @param array Extra filter options in an indexed array. See
 * format_array_to_where_clause_sql()
 * @param boolean $allModules
 *
 * @return array All simple alerts defined for an agent. Empty array if no
 * alerts found.
 */
function get_agent_alerts_simple ($id_agent = false, $filter = '', $options = false, $where = '', 
	$allModules = false, $orderby = false, $idGroup = false, $count = false) {
	global $config;

	if (is_array($filter)) {
		$disabled = $filter['disabled'];
		if (isset($filter['standby'])) {
			$filter = ' AND talert_template_modules.standby = "'.$filter['standby'].'"';
		}
		else {
			$filter = '';
		}
	}
	else {
		$filter = '';
		$disabled = $filter;
	}
	
	switch ($disabled) {
		case "notfired":
			$filter .= ' AND times_fired = 0 AND talert_template_modules.disabled = 0';
			break;
		case "fired":
			$filter .= ' AND times_fired > 0 AND talert_template_modules.disabled = 0';
			break;
		case "disabled":
			$filter .= ' AND talert_template_modules.disabled = 1';
			break;
		case "all_enabled":
			$filter .= ' AND talert_template_modules.disabled = 0';
			break;
		default:
			$filter .= '';
	}
	
	if (is_array ($options)) {
		$filter .= format_array_to_where_clause_sql ($options);
	}
	
	if (($id_agent === false) && ($idGroup !== false)) {
		if ($idGroup != 0) { //All group
			$subQuery = 'SELECT id_agente_modulo
				FROM tagente_modulo
				WHERE delete_pending = 0 AND id_agente IN (SELECT id_agente FROM tagente WHERE id_grupo = ' . $idGroup . ')';
		}
		else {
			$subQuery = 'SELECT id_agente_modulo
				FROM tagente_modulo WHERE delete_pending = 0';
		}
	}
	else if ($id_agent === false) {
		if ($allModules) $disabled = '';
		else $disabled = 'WHERE disabled = 0';
		$subQuery = 'SELECT id_agente_modulo
			FROM tagente_modulo ' . $disabled;
	}
	else {
		$id_agent = (array) $id_agent;
		$id_modules = array_keys (get_agent_modules ($id_agent, false, array('delete_pending' => 0)));
		if (empty ($id_modules))
			return array ();
			
		$subQuery = implode (",", $id_modules);
	}
	
	$orderbyText = '';
	if ($orderby !== false) {
		if (is_array($orderby)) {
			$orderbyText = sprintf("ORDER BY %s", $orderby['field'], $orderby['order']);
		}
		else {
			$orderbyText = sprintf("ORDER BY %s", $orderby);
		}
	}
	
	$selectText = 'talert_template_modules.*, t2.nombre AS agent_module_name, t3.nombre AS agent_name, t4.name AS template_name';
	if ($count !== false) {
		$selectText = 'COUNT(talert_template_modules.id) AS count';
	}

	switch ($config["dbtype"]) {
		case "mysql":
		case "postgresql":
			$sql = sprintf ("SELECT %s
			FROM talert_template_modules
				INNER JOIN tagente_modulo AS t2
					ON talert_template_modules.id_agent_module = t2.id_agente_modulo
				INNER JOIN tagente AS t3
					ON t2.id_agente = t3.id_agente
				INNER JOIN talert_templates AS t4
					ON talert_template_modules.id_alert_template = t4.id
			WHERE id_agent_module in (%s) %s %s %s",
			$selectText, $subQuery, $where, $filter, $orderbyText);
		case "oracle":
			$sql = sprintf ("SELECT %s
			FROM talert_template_modules
				INNER JOIN tagente_modulo t2
					ON talert_template_modules.id_agent_module = t2.id_agente_modulo
				INNER JOIN tagente t3
					ON t2.id_agente = t3.id_agente
				INNER JOIN talert_templates t4
					ON talert_template_modules.id_alert_template = t4.id
			WHERE id_agent_module in (%s) %s %s %s",
			$selectText, $subQuery, $where, $filter, $orderbyText);
	}
	$alerts = get_db_all_rows_sql ($sql);
	
	if ($alerts === false)
		return array ();
		
	if ($count !== false) {
		return $alerts[0]['count'];
	}
	else {
		return $alerts;	
	}
}

/**
 * Get all the combined alerts of an agent.
 *
 * @param int $id_agent Agent id
 * @param string Special filter. Can be: "notfired", "fired" or "disabled".
 * @param array Extra filter options in an indexed array. See
 * format_array_to_where_clause_sql()
 *
 * @return array An array with all combined alerts defined for an agent.
 */
function get_agent_alerts_compound ($id_agent = false, $filter = '', $options = false, $idGroup = false, $count = false, $where = '') {
	switch ($filter) {
		case "notfired":
			$filter = ' AND times_fired = 0 AND disabled = 0';
			break;
		case "fired":
			$filter = ' AND times_fired > 0 AND disabled = 0';
			break;
		case "disabled":
			$filter = ' AND disabled = 1';
			break;
		case 'all_enabled':
			$filter = ' AND disabled = 0';
			break;
		default:
			$filter = '';
			break;
	}
	
	if (is_array ($options)) {
		$filter .= format_array_to_where_clause_sql ($options);
	}
	
	if (($id_agent === false) && ($idGroup !== false)) {
		if ($idGroup != 0) { //All group
			$subQuery = 'SELECT id_agente FROM tagente WHERE id_grupo = ' . $idGroup;
		}
		else {
			$subQuery = 'SELECT id_agente FROM tagente';
		}
	}
	else if ($id_agent == false) {
		$subQuery = 'SELECT id_agente
			FROM tagente WHERE disabled = 0';
	}
	else {
		$id_agent = (array) $id_agent;
		
		$subQuery = implode (',', $id_agent);
	}
	
	$selectText = '*';
	if ($count !== false) {
		$selectText = 'COUNT(id) AS count';
	}
	
	$sql = sprintf ("SELECT %s FROM talert_compound
		WHERE id_agent IN (%s) %s %s",
		$selectText, $subQuery, $where, $filter);
	
	$alerts = get_db_all_rows_sql ($sql);
	
	if ($alerts === false)
		return array ();
	
	if ($count !== false) {
		return $alerts[0]['count'];
	}
	else {
		return $alerts;	
	}
}

/**
 * Get a list of agents.
 *
 * By default, it will return all the agents where the user has reading access.
 * 
 * @param array filter options in an indexed array. See
 * format_array_to_where_clause_sql()
 * @param array Fields to get.
 * @param string Access needed in the agents groups.
 * @param array $order The order of agents, by default is upward for field nombre.
 * 
 * @return mixed An array with all alerts defined for an agent or false in case no allowed groups are specified.
 */
function get_agents ($filter = false, $fields = false, $access = 'AR', $order = array('field' => 'nombre', 'order' => 'ASC')) {
    global $config;

	if (! is_array ($filter)) {
		$filter = array ();
	}

	//Get user groups
	$groups = array_keys (get_user_groups ($config["id_user"], $access, false));

	//If no group specified, get all user groups
	if (empty ($filter['id_grupo'])) {
		$filter['id_grupo'] = $groups;
	}
	elseif (! is_array ($filter['id_grupo'])) {
		//If group is specified but not allowed, return false
		if (! in_array ($filter['id_grupo'], $groups)) {
			return false;
		}
		$filter['id_grupo'] = (array) $filter['id_grupo']; //Make an array
	}
	else {
		//Check each group specified to the user groups, remove unwanted groups
		foreach ($filter['id_grupo'] as $key => $id_group) {
			if (! in_array ($id_group, $groups)) {
				unset ($filter['id_grupo'][$key]);
			}
		}
		//If no allowed groups are specified return false
		if (count ($filter['id_grupo']) == 0) {
			return false;
		}
	}
	
	if (in_array (0, $filter['id_grupo'])) {
		unset ($filter['id_grupo']);
	}
	
	if (!is_array ($fields)) {
		$fields = array ();
		$fields[0] = "id_agente";
		$fields[1] = "nombre";
	}
	
	$filter['order'] = $order['field'] . ' ' . $order['order'];
	return get_db_all_rows_filter ('tagente', $filter, $fields);
}

/**
 * Get all the alerts of an agent, simple and combined.
 *
 * @param int $id_agent Agent id
 * @param string Special filter. Can be: "notfired", "fired" or "disabled".
 * @param array Extra filter options in an indexed array. See
 * format_array_to_where_clause_sql()
 *
 * @return array An array with all alerts defined for an agent.
 */
function get_agent_alerts ($id_agent = false, $filter = false, $options = false) {
	$combined_alerts = get_agent_alerts_compound ($id_agent, $filter, $options);
	$simple_alerts = get_agent_alerts_simple ($id_agent, $filter, $options);
	
	return array ('simple' => $simple_alerts, 'compounds' => $combined_alerts);
}

/**
 * Copy the agents config from one agent to the other
 *
 * @param int Agent id
 * @param mixed Agent id or id's (array) to copy to
 * @param bool Whether to copy modules as well (defaults to $_REQUEST['copy_modules'])
 * @param bool Whether to copy alerts as well
 * @param array Which modules to copy.
 * @param array Which alerts to copy. Only will be used if target_modules is empty.
 *
 * @return bool True in case of good, false in case of bad
 */
function process_manage_config ($source_id_agent, $destiny_id_agents, $copy_modules = false, $copy_alerts = false, $target_modules = false, $target_alerts = false) {
	if (empty ($source_id_agent)) {
		echo '<h3 class="error">'.__('No source agent to copy').'</h3>';
		return false;
	}
	
	if (empty ($destiny_id_agents)) {
		echo '<h3 class="error">'.__('No destiny agent(s) to copy').'</h3>';
		return false;
	}
	
	if ($copy_modules == false) {
		$copy_modules = (bool) get_parameter ('copy_modules', $copy_modules);
	}
	
	if ($copy_alerts == false) {
		$copy_alerts = (bool) get_parameter ('copy_alerts', $copy_alerts);
	}
	
	if (! $copy_modules && ! $copy_alerts)
		return;
	
	if (empty ($target_modules)) {
		$target_modules = (array) get_parameter ('target_modules', array ());
	}
	
	if (empty ($target_alerts)) {
		$target_alerts = (array) get_parameter ('target_alerts', array ());
	}
	
	if (empty ($target_modules)) {
		if (! $copy_alerts) {
			echo '<h3 class="error">'.__('No modules have been selected').'</h3>';
			return false;
		}
		$target_modules = array ();
		
		foreach ($target_alerts as $id_alert) {
			$alert = get_alert_agent_module ($id_alert);
			if ($alert === false)
				continue;
			/* Check if some alerts which doesn't belong to the agent was given */
			if (get_agentmodule_agent ($alert['id_agent_module']) != $source_id_agent)
				continue;
			array_push ($target_modules, $alert['id_agent_module']);
		}
	}
	
	process_sql ('SET AUTOCOMMIT = 0');
	process_sql ('START TRANSACTION');
	$error = false;
	
	foreach ($destiny_id_agents as $id_destiny_agent) {
		foreach ($target_modules as $id_agent_module) {
			$result = copy_agent_module_to_agent ($id_agent_module,
				$id_destiny_agent);
		
			if ($result === false) {
				$error = true;
				break;
			}
			
			$id_destiny_module = $result;
			
			if (! $copy_alerts)
				continue;
			
			/* If the alerts were given, copy afterwards. Otherwise, all the
			alerts for the module will be copied */
			if (! empty ($target_alerts)) {
				foreach ($target_alerts as $id_alert) {
					$alert = get_alert_agent_module ($id_alert);
					if ($alert === false)
						continue;
					if ($alert['id_agent_module'] != $id_agent_module)
						continue;
					$result = copy_alert_agent_module_to_agent_module ($alert['id'],
						$id_destiny_module);
					if ($result === false) {
						$error = true;
						break;
					}
				}
				continue;
			}
			
			$alerts = get_alerts_agent_module ($id_agent_module, true);
			
			if ($alerts === false)
				continue;
			
			foreach ($alerts as $alert) {
				$result = copy_alert_agent_module_to_agent_module ($alert['id'],
					$id_destiny_module);
				if ($result === false) {
					$error = true;
					break;
				}
			}
		}
		if ($error)
			break;
	}
	
	if ($error) {
		echo '<h3 class="error">'.__('There was an error copying the agent configuration, the copy has been cancelled').'</h3>';
		process_sql ('ROLLBACK');
	} else {
		echo '<h3 class="suc">'.__('Successfully copied').'</h3>';
		process_sql ('COMMIT');
	}
	process_sql ('SET AUTOCOMMIT = 1');
}

function getNextAgentContact($idAgent, $maxModules = false) {
	
	$agent = get_db_row_sql("SELECT * FROM tagente WHERE id_agente = " . $idAgent);
	
	
	$difference = get_system_time () - strtotime ($agent["ultimo_contacto"]);
	
	
	$max = $agent["intervalo"];
	if ($maxModules) {
		$sql = sprintf ("SELECT MAX(module_interval) FROM tagente_modulo WHERE id_agente = %d", $id_agente);
		$maxModules = (int) get_db_sql ($sql);
		if ($maxModules > 0)
			$max = $maxModules;
	}
	
	if ($max > 0)
		return round ($difference / (($max * 2) / 100));
	else
		return false;
}

/**
 * Get all the modules common in various agents that have associated alerts. If an empty list is passed it will select all
 *
 * @param mixed Agent id to get modules. It can also be an array of agent id's.
 * @param mixed Array, comma delimited list or singular value of rows to
 * select. If nothing is specified, nombre will be selected. A special
 * character "*" will select all the values.
 * @param mixed Aditional filters to the modules. It can be an indexed array
 * (keys would be the field name and value the expected value, and would be
 * joined with an AND operator) or a string, including any SQL clause (without
 * the WHERE keyword).
 * @param bool Wheter to return the modules indexed by the id_agente_modulo or
 * not. Default is indexed.
 * Example:
<code>
Both are similars:
$modules = get_agent_modules ($id_agent, false, array ('disabled' => 0));
$modules = get_agent_modules ($id_agent, false, 'disabled = 0');

Both are similars:
$modules = get_agent_modules ($id_agent, '*', array ('disabled' => 0, 'history_data' => 0));
$modules = get_agent_modules ($id_agent, '*', 'disabled = 0 AND history_data = 0');
</code>
 *
 * @return array An array with all modules in the agent.
 * If multiple rows are selected, they will be in an array
 */
function get_agents_common_modules_with_alerts ($id_agent, $filter = false, $indexed = true, $get_not_init_modules = true) {
	$id_agent = safe_int ($id_agent, 1);

	$where = '';
	if (! empty ($id_agent)) {
		$where = sprintf (' WHERE t2.id_agent_module = t1.id_agente_modulo AND delete_pending = 0
			AND id_agente IN (%s) AND (
				SELECT count(nombre)
				FROM tagente_modulo t3, talert_template_modules t4
				WHERE t4.id_agent_module = t3.id_agente_modulo AND delete_pending = 0
					AND t1.nombre = t3.nombre AND id_agente IN (%s)) = (%s)', implode (",", (array) $id_agent), implode (",", (array) $id_agent), count($id_agent));
	}
		
	if (! empty ($filter)) {
		$where .= ' AND ';
		if (is_array ($filter)) {
			$fields = array ();
			foreach ($filter as $field => $value) {
				array_push ($fields, $field.'="'.$value.'"');
			}
			$where .= implode (' AND ', $fields);
		} else {
			$where .= $filter;
		}
	}
	
	$sql = sprintf ('SELECT DISTINCT(t1.id_agente_modulo)
		FROM tagente_modulo t1, talert_template_modules t2
		%s
		ORDER BY nombre',
		$where);
	$result = get_db_all_rows_sql ($sql);

	if (empty ($result)) {
		return array ();
	}
	
	if (! $indexed)
		return $result;
	
	$modules = array ();
	foreach ($result as $module) {
		if($get_not_init_modules || get_agentmodule_is_init($module['id_agente_modulo'])) {
			$modules[$module['id_agente_modulo']] = $module['id_agente_modulo'];
		}
	}
	return $modules;
}

/**
 * Get all the modules common in various agents. If an empty list is passed it will select all
 *
 * @param mixed Agent id to get modules. It can also be an array of agent id's.
 * @param mixed Array, comma delimited list or singular value of rows to
 * select. If nothing is specified, nombre will be selected. A special
 * character "*" will select all the values.
 * @param mixed Aditional filters to the modules. It can be an indexed array
 * (keys would be the field name and value the expected value, and would be
 * joined with an AND operator) or a string, including any SQL clause (without
 * the WHERE keyword).
 * @param bool Wheter to return the modules indexed by the id_agente_modulo or
 * not. Default is indexed.
 * Example:
<code>
Both are similars:
$modules = get_agent_modules ($id_agent, false, array ('disabled' => 0));
$modules = get_agent_modules ($id_agent, false, 'disabled = 0');

Both are similars:
$modules = get_agent_modules ($id_agent, '*', array ('disabled' => 0, 'history_data' => 0));
$modules = get_agent_modules ($id_agent, '*', 'disabled = 0 AND history_data = 0');
</code>
 *
 * @return array An array with all modules in the agent.
 * If multiple rows are selected, they will be in an array
 */
function get_agents_common_modules ($id_agent, $filter = false, $indexed = true, $get_not_init_modules = true) {
	$id_agent = safe_int ($id_agent, 1);

	$where = '';
	if (! empty ($id_agent)) {
		$where = sprintf (' WHERE delete_pending = 0 AND id_agente IN (%s)
				AND (
					SELECT count(nombre)
					FROM tagente_modulo t2
					WHERE delete_pending = 0 AND t1.nombre = t2.nombre
						AND id_agente IN (%s)) = (%s)', implode (",", (array) $id_agent), implode (",", (array) $id_agent), count($id_agent));
	}
		
	if (! empty ($filter)) {
		$where .= ' AND ';
		if (is_array ($filter)) {
			$fields = array ();
			foreach ($filter as $field => $value) {
				array_push ($fields, $field.'="'.$value.'"');
			}
			$where .= implode (' AND ', $fields);
		}
		else {
			$where .= $filter;
		}
	}
	
	$sql = sprintf ('SELECT DISTINCT(t1.id_agente_modulo) as id_agente_modulo
		FROM tagente_modulo t1, talert_template_modules t2
		%s
		ORDER BY nombre',
		$where);
	$result = get_db_all_rows_sql ($sql);

	if (empty ($result)) {
		return array ();
	}
	
	if (! $indexed)
		return $result;
	
	$modules = array ();
	foreach ($result as $module) {
		if($get_not_init_modules || get_agentmodule_is_init($module['id_agente_modulo'])) {
			$modules[$module['id_agente_modulo']] = $module['id_agente_modulo'];
		}
	}
	return $modules;
}
?>

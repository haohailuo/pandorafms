<?php

// Pandora - the Free monitoring system
// ====================================
// Copyright (c) 2004-2006 Sancho Lerena, slerena@gmail.com
// Copyright (c) 2005-2006 Artica Soluciones Tecnol�gicas S.L, info@artica.es
// Copyright (c) 2004-2006 Raul Mateos Martin, raulofpandora@gmail.com
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
?>

<?php
if (isset($_SESSION["id_usuario"])) {
?>
<div class="bg">
	<div class="imgl"><img src="images/upper-left-corner.gif" width="5" height="5" alt=""></div>
	<div class="tit">:: <?php echo $lang_label["operation_header"] ?> ::</div>
	<div class="imgr"><img src="images/upper-right-corner.gif" width="5" height="5" alt=""></div>
</div>
<div id="menuop">
	<div id="op">

<?php
    if (give_acl($_SESSION["id_usuario"], 0, "AR")==1) {
		if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/agentes/estado_grupo") {
			echo '<div id="op1s">';
		}
		else echo '<div id="op1">';
		echo '<ul class="mn"><li><a href="index.php?sec=estado&amp;sec2=operation/agentes/estado_grupo&amp;refr=60" class="mn">'.$lang_label["view_agents"].'</a></li></ul></div>';
		
		if (isset($_GET["sec"]) && $_GET["sec"] == "estado"){
			if(isset($_GET["sec2"]) && ($_GET["sec2"] == "operation/agentes/estado_agente" || $_GET["sec2"] == "operation/agentes/ver_agente" || $_GET["sec2"] == "operation/agentes/datos_agente")) {
				echo "<div id='arrows1'>";
			}
			else echo "<div id='arrow1'>";
			echo "<ul class='mn'><li><a href='index.php?sec=estado&amp;sec2=operation/agentes/estado_agente&amp;refr=60' class='mn'>".$lang_label["agent_detail"]."</a></li></ul></div>";
			
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/agentes/estado_alertas") {
				echo "<div id='arrows2'>";
			}
			else echo "<div id='arrow2'>";
			echo "<ul class='mn'><li><a href='index.php?sec=estado&amp;sec2=operation/agentes/estado_alertas&amp;refr=60' class='mn'>".$lang_label["alert_detail"]."</a></li></ul></div>";
			
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/agentes/status_monitor") {
				echo "<div id='arrows3'>";
			}
			else echo "<div id='arrow3'>";
			echo "<ul class='mn'><li><a href='index.php?sec=estado&amp;sec2=operation/agentes/status_monitor&amp;refr=60' class='mn'>".$lang_label["detailed_monitoragent_state"]."</a></li></ul></div>";
			
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/agentes/exportdata") {
				echo "<div id='arrows4'>";
			}
			else echo "<div id='arrow4'>";
			echo "<ul class='mn'><li><a href='index.php?sec=estado&amp;sec2=operation/agentes/exportdata' class='mn'>".$lang_label["export_data"]."</a></li></ul></div>";
			
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/agentes/estadisticas") {
				echo "<div id='arrows5'>";
			}
			else echo "<div id='arrow5'>";
			echo "<ul class='mn'><li><a href='index.php?sec=estado&amp;sec2=operation/agentes/estadisticas' class='mn'>".$lang_label["statistics"]."</a></li></ul></div>";
		}
	}
	if (give_acl($_SESSION["id_usuario"], 0, "AR")==1) {
		if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/servers/view_server") {
			echo '<div id="op2s">';
		}
		else echo '<div id="op2">';
		echo '<ul class="mn"><li><a href="index.php?sec=estado_server&amp;sec2=operation/servers/view_server&amp;refr=60" class="mn">'.$lang_label["view_servers"].'</a></li></ul></div>';
	}
	if (give_acl($_SESSION["id_usuario"], 0, "IR")==1) {
		if(isset($_GET["sec2"]) && ($_GET["sec2"] == "operation/incidents/incident" || $_GET["sec2"] == "operation/incidents/incident_detail"|| $_GET["sec2"] == "operation/incidents/incident_note")) {
			echo '<div id="op3s">';
		}
		else echo '<div id="op3">';
		echo '<ul class="mn"><li><a href="index.php?sec=incidencias&amp;sec2=operation/incidents/incident" class="mn">'.$lang_label["manage_incidents"].'</a></li></ul></div>';
		
		if (isset($_GET["sec"]) && $_GET["sec"] == "incidencias"){
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/incidents/incident_search") {
				echo "<div id='arrows1'>";
			}
			else echo "<div id='arrow1'>";
			echo "<ul class='mn'><li><a href='index.php?sec=incidencias&amp;sec2=operation/incidents/incident_search' class='mn'>".$lang_label["search_incident"]."</a></li></ul></div>";
			
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/incidents/incident_statistics") {
				echo "<div id='arrows2'>";
			}
			else echo "<div id='arrow2'>";
			echo "<ul class='mn'><li><a href='index.php?sec=incidencias&amp;sec2=operation/incidents/incident_statistics' class='mn'>".$lang_label["statistics"]."</a></li></ul></div>";
		}
	}
	if (give_acl($_SESSION["id_usuario"], 0, "AR")==1) {
		if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/events/events") {
			echo '<div id="op4s">';
		}
		else echo '<div id="op4">';
		echo '<ul class="mn"><li><a href="index.php?sec=eventos&amp;sec2=operation/events/events" class="mn">'.$lang_label["view_events"].'</a></li></ul></div>';
		
		if (isset($_GET["sec"]) && $_GET["sec"] == "eventos"){
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/events/event_statistics") {
				echo "<div id='arrows1'>";
			}
			else echo "<div id='arrow1'>";
			echo "<ul class='mn'><li><a href='index.php?sec=eventos&amp;sec2=operation/events/event_statistics' class='mn'>".$lang_label["statistics"]."</a></li></ul></div>";
		}
	}
		if(isset($_GET["sec2"]) && ($_GET["sec2"] == "operation/users/user" || $_GET["sec2"] == "operation/users/user_edit" )) {
			echo '<div id="op5s">';
		}
		else echo '<div id="op5">';
		echo '<ul class="mn"><li><a href="index.php?sec=usuarios&amp;sec2=operation/users/user" class="mn">'.$lang_label["view_users"].'</a></li></ul></div>';
		
		if (isset($_GET["sec"]) && $_GET["sec"] == "usuarios"){
			if(isset($_GET["ver"]) && $_GET["ver"] == $_SESSION["id_usuario"]){
				echo "<div id='arrows1'>";
			}
			else echo "<div id='arrow1'>";
			echo "<ul class='mn'><li><a href='index.php?sec=usuarios&amp;sec2=operation/users/user_edit&amp;ver=".$_SESSION["id_usuario"]."' class='mn'>".$lang_label["index_myuser"]."</a></li></ul></div>";
			
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/users/user_statistics") {
				echo "<div id='arrows1'>";
			}
			else echo "<div id='arrow1'>";
			echo "<ul class='mn'><li><a href='index.php?sec=usuarios&amp;sec2=operation/users/user_statistics' class='mn'>".$lang_label["statistics"]."</a></li></ul></div>";
		
		}
		if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/snmpconsole/snmp_view") {
			echo '<div id="op6s">';
		}
		else echo '<div id="op6">';
		echo '<ul class="mn"><li><a href="index.php?sec=snmpconsole&amp;sec2=operation/snmpconsole/snmp_view&amp;refr=30" class="mn">'.$lang_label["SNMP_console"].'</a></li></ul></div>';

		if (isset($_GET["sec"]) && $_GET["sec"] == "snmpconsole"){
			if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/snmpconsole/snmp_alert") {
				echo "<div id='arrows1'>";
			}
			else echo "<div id='arrow1'>";
			echo "<ul class='mn'><li><a href='index.php?sec=snmpconsole&amp;sec2=operation/snmpconsole/snmp_alert' class='mn'>".$lang_label["snmp_console_alert"]."</a></li></ul></div>";
		}
		if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/messages/message" && !isset($_GET["nuevo_g"])) {
			echo '<div id="op7s">';
		}
		else echo '<div id="op7">';
		echo '<ul class="mn"><li><a href="index.php?sec=messages&amp;sec2=operation/messages/message" class="mn">'. $lang_label["messages"].'</a></li></ul></div>';
		
		if (isset($_GET["sec"]) && $_GET["sec"] == "messages"){
			if(isset($_GET["sec2"]) && isset($_GET["nuevo_g"])) {
				echo "<div id='arrows1'>";
			}
			else echo "<div id='arrow1'>";
			echo "<ul class='mn'><li><a href='index.php?sec=messages&amp;sec2=operation/messages/message&amp;nuevo_g' class='mn'>".$lang_label["messages_g"]."</a></li></ul></div>";
		}

?>
<?php
	// testing
		if(isset($_GET["sec2"]) && $_GET["sec2"] == "operation/reporting/report_create") {
			echo '<div id="op8s">';
		}
		else echo '<div id="op8">';
		echo '<ul class="mn"><li><a href="index.php?sec=reporting&amp;sec2=operation/reporting/report_create" class="mn">' . $lang_label["rep_menu"] . '</a></li></ul></div>';
		
		
?>

		
	</div>
</div>	
<?php
}
?>

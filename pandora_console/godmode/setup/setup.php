<?php 

// Pandora - the Free monitoring system
// ====================================
// Copyright (c) 2004-2006 Sancho Lerena, slerena@gmail.com
// Copyright (c) 2005-2006 Artica Soluciones Tecnologicas S.L, info@artica.es
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

// Load global vars
require("include/config.php");

if (comprueba_login() == 0) 
 	if ((give_acl($id_user, 0, "PM")==1) or (dame_admin($id_user)==1)) {	
	if (isset($_GET["update"])){
		$block_size=$_POST["block_size"];
		$language_code=$_POST["language_code"];
		$days_compact=$_POST["days_compact"];
		$days_purge=$_POST["days_purge"];
		$config_graph_res=$_POST["graph_res"];
		$config_step_compact=$_POST["step_compact"];
		$config_graph_order=$_POST["graph_order"];
		$config_truetype=$_POST["truetype"];
		$config_bgimage=$_POST["bgimage"];
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$block_size."' WHERE TOKEN='block_size'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$language_code."' WHERE TOKEN='language_code'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$days_purge."' WHERE TOKEN='days_purge'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$days_compact." ' WHERE TOKEN='days_compact'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$config_graph_res."' WHERE TOKEN='graph_res'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$config_step_compact."' WHERE TOKEN='step_compact'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$config_truetype."' WHERE TOKEN='truetype'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$config_graph_order."' WHERE TOKEN='graph_order'");
		$result2=mysql_query("UPDATE tconfig SET VALUE='".$config_bgimage."' WHERE token='bgimage'");
	}	
	echo "<h2>".$lang_label["setup_screen"]."</h2>";
	echo "<h3>".$lang_label["general_config"]."<a href='help/".$help_code."/chap9.php#9' target='_help' class='help'>&nbsp;<span>".$lang_label["help"]."</span></a></h3>";
	echo "<form name='setup' method='POST' action='index.php?sec=gsetup&amp;sec2=godmode/setup/setup&update=1'>";
	echo '<table width="500" cellpadding="3" cellspacing="3">';
	echo '<tr><td class="lb" rowspan="9" width="5"></td><td class="datos">'.$lang_label["language_code"];
	echo '<td class="datos"><select name="language_code" onChange="javascript:this.form.submit();" class="w180">';
	
	$sql="SELECT * FROM tlanguage";
	$result=mysql_query($sql);

	// This combo is dedicated to Raul... beautiful interface for dirty minds :-D
	$result2=mysql_query("SELECT * FROM tlanguage WHERE id_language = '$language_code'");
	if ($row2=mysql_fetch_array($result2)){
		echo '<option value="'.$row2["id_language"].'">'.$row2["name"];
	}
		
	while ($row=mysql_fetch_array($result)){
		echo "<option value=".$row["id_language"].">".$row["name"];
	}
	echo '</select>';
			
	echo '<tr><td class="datos2">'.$lang_label["block_size"];
	echo '<td class="datos2"><input type="text" name="block_size" size=5 value="'.$block_size.'">';
	
	echo '<tr><td class="datos">'.$lang_label["days_compact"];
	echo '<td class="datos"><input type="text" name="days_compact" size=5 value="'.$days_compact.'">';
	
	echo '<tr><td class="datos2">'.$lang_label["days_purge"];
	echo '<td class="datos2"><input type="text" name="days_purge" size=5 value="'.$days_purge.'">';
	
	echo '<tr><td class="datos">'.$lang_label["graph_res"];
	echo '<td class="datos"><input type="text" name="graph_res" size=5 value="'.$config_graph_res.'">';
	
	echo '<tr><td class="datos2">'.$lang_label["step_compact"];
	echo '<td class="datos2"><input type="text" name="step_compact" size=5 value="'.$config_step_compact.'">';

	echo '<tr><td class="datos">'.$lang_label["graph_order"];
	echo '<td class="datos"><select name="graph_order" class="w120">';
	if ($config_graph_order==0) {
		echo '<option value="0">'.$lang_label["left_right"].'</option>';
		echo '<option value="1">'.$lang_label["right_left"].'</option>';
	}
	else {
		echo '<option value="1">'.$lang_label["right_left"].'</option>';
		echo '<option value="0">'.$lang_label["left_right"].'</option>';
	}
	
	echo '<tr><td class="datos2">'.$lang_label["truetype"];
	echo '<td class="datos2"><select name="truetype" class="w120">';
	if ($config_truetype==1) {
		echo '<option value="1">'.$lang_label["active"].'</option>';
		echo '<option value="0">'.$lang_label["disabled"].'</option>';
	}
	else {
		echo '<option value="0">'.$lang_label["disabled"].'</option>';
		echo '<option value="1">'.$lang_label["active"].'</option>';
	}

	echo '<tr><td class="datos">'.$lang_label["background_image"];
	echo '<td class="datos">';
	echo '<select name="bgimage" class="w155">';
	if ($config_bgimage!=""){
		echo '<option>'.$config_bgimage;
	}
	
	$ficheros = list_files('images/backgrounds/', "background",1, 0);
	$a=0;
	while (isset($ficheros[$a])){
		echo "<option>".$ficheros[$a];
		$a++;
	}
	echo '</select>';

	echo "<tr><td colspan='3'><div class='raya'></div></td></tr>";
	echo "<tr><td colspan='3' align='right'>";
	echo '<input type="submit" class="sub" value="'.$lang_label["update"].'">';
	echo "</table>";
}
else {
		audit_db($id_user,$REMOTE_ADDR, "ACL Violation","Trying to access Database Management");
		require ("general/noaccess.php");
	}
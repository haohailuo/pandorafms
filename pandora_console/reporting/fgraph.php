<?php

// Pandora - The Free Monitoring System
// This code is protected by GPL license.
// Este codigo esta protegido por la licencia GPL.
// Sancho Lerena <slerena@gmail.com>, 2003-2006
// Raúl Mateos <raulofpandora@gmail.com>, 2006

// Load global vars
include ("../include/config.php");
include ("../include/functions.php");
include ("../include/functions_db.php");
require ("../include/languages/language_".$language_code.".php");

function dame_fecha($mh){ 
	// Return a MySQL timestamp date, formatted with actual date MINUS X minutes, given as parameter
	$m_year = date("Y", time()-$mh*60); 
	$m_month = date("m", time()-$mh*60);
	$m_day = date ("d", time()-$mh*60);
	$m_hour = date ("H", time()-$mh*60);
	$m_min = date ("i", time()-$mh*60);
	$m = $m_year."-".$m_month."-".$m_day." ".$m_hour.":".$m_min.":00";
	return $m;	
}

function dame_fecha_grafico($mh){ // Devuelve fecha formateada en funcion de un numero de minustos antes de la fecha actual

	// Date 24x7x30 hours ago (one month)
	$m_year = date("Y", time()-$mh*60);
	$m_month = date("m", time()-$mh*60);
	$m_day = date ("d", time()-$mh*60);
	$m_hour = date ("H", time()-$mh*60);
	$m_min = date ("i", time()-$mh*60);
	$m = $m_month."/".$m_day." ".$m_hour.":".$m_min;
	return $m;
}

function grafico_modulo_sparse($id_agente_modulo, $periodo, $intervalo, $etiqueta, $color){
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_line.php");
	require ("../include/languages/language_".$language_code.".php");

	// WHere periodo is lapse of time in seconds that we want to show in a graph, this could be a week, 1 hour, a day, etc
	$fechatope = dame_fecha($periodo);	// Max old-date limit
	$horasint = $periodo / $intervalo;	// Each intervalo is $horasint seconds length
	$nombre_agente = dame_nombre_agente_agentemodulo($id_agente_modulo);
	$nombre_modulo = dame_nombre_modulo_agentemodulo($id_agente_modulo);

	// Para crear las graficas vamos a crear un array de Ax4 elementos, donde
	// A es el numero de posiciones diferentes en la grafica (30 para un mes, 7 para una semana, etc)
	// y los 4 valores en el ejeY serian los detallados a continuacion:
	// Rellenamos la tabla con un solo select, y los calculos se hacen todos sobre memoria
	// esto acelera el tiempo de calculo al maximo, aunque complica el algoritmo :-)
	
	// Where
	// intervalo - This is the number of "rows" we are divided the time to fill data.
	//             more interval, more resolution, and slower.
	
	// periodo - Gap of time, in seconds. This is now to (now-periodo) secs

	// Init tables
	for ($x = 0; $x <= $intervalo; $x++) {
		$valores[$x][0] = 0; // [0] Value (counter)
		$valores[$x][2] = dame_fecha($horasint * $x); // [2] Rango superior de fecha para ese rango
		$valores[$x][3] = dame_fecha($horasint*($x+1)); // [3] Rango inferior de fecha para ese rango
		$etiq_base[] = dame_fecha_grafico($horasint * $x);
		$valores_min[$x]= 0; $valores_max[$x]=0;
	}

	// Get the last value, the last known value (more recent)
	$sql1 = "SELECT * FROM tagente_datos WHERE id_agente_modulo = ".$id_agente_modulo."  ORDER BY timestamp DESC limit 1";
	if ($result=mysql_query($sql1)){
		$row=mysql_fetch_array($result);
		$old_value=$row["datos"];
		$old_date = $row["timestamp"];
	} else {
		$old_value=0;
	}

	// Get the last known date (most near to now) for lastcontact in this module
	$sql1 = "SELECT * FROM tagente_estado WHERE id_agente_modulo = ".$id_agente_modulo;
	if ($result=mysql_query($sql1)){
		$row=mysql_fetch_array($result);
		$old_date = $row["timestamp"];
	}

	// Get the last first date (most far to now) for lastcontact in this module
	// there is no data far away, so value must be 0 before this date
	$sql1 = "SELECT * FROM tagente_datos WHERE id_agente_modulo = ".$id_agente_modulo." order by timestamp asc limit 1";
	if ($result=mysql_query($sql1)){
		$row=mysql_fetch_array($result);
		$first_date = $row["timestamp"];
	}

	// Get the oldest known date just out of lower bound for global interval
	$sql1 = "SELECT * FROM tagente_datos WHERE id_agente_modulo = ".$id_agente_modulo." and timestamp < '".$fechatope."' order by timestamp desc limit 1";
	if ($result=mysql_query($sql1)){
		$row=mysql_fetch_array($result);
		$old_date_interval = $row["timestamp"];
		$old_data_interval = $row["datos"];
		$old_data_used = 0;
	}

	for ($i = $intervalo; $i >= 0; $i--){
		$sql1 = "SELECT AVG(datos),MAX(datos),MIN(datos) FROM tagente_datos WHERE id_agente_modulo = ".$id_agente_modulo." and timestamp >= '".$valores[$i][3]."' and timestamp < '".$valores[$i][2]."'";
		$sql2 = "SELECT datos FROM tagente_datos WHERE id_agente_modulo = ".$id_agente_modulo." and timestamp >= '".$valores[$i][3]."' and timestamp < '".$valores[$i][2]."' order by timestamp desc limit 1";
		$result2=mysql_query($sql2);
		$result=mysql_query($sql1);
		$row=mysql_fetch_array($result);
		$row2=mysql_fetch_array($result2);
#echo "AVG  MAX  MIN (old_value)  ".$valores[$i][3]."<br>";
#echo $row[0]."  ".$row[1]."  ".$row[2]."  old-".$old_value." ".$old_date;
#echo "<br><br>";
		if ($row[0] != ""){		
			$data_item=$row[0];
			$valores_max[$i] = $row[1];
			$valores_min[$i] = $row[2];
			if ($data_item == ""){
				$data_item = $old_value;
				$valores_min[$i] = $old_value;
				$valores_max[$i] = $old_value;
			} else {
				$old_value = $row2[0]; // Last data
				#$old_date = $valores[$i][3];
			}
			$old_data_used =1; 	// Dont use "previous value for this interval" anymore 				// if a real value its in database.
		} else {
			// Interval more recent that last module -contact-
			if ((strtotime($old_date) < strtotime($valores[$i][2]))){
                                $data_item = 0;
                                $valores_min[$i] = 0;
                                $valores_max[$i] = 0;
			// Get data from lower limit of this interval
                        } elseif ((isset($old_date_interval)) AND (strtotime($old_date_interval) < strtotime($valores[$i][2])) AND ($old_data_used == 0)) {
                                $data_item = $old_data_interval;
			        $valores_min[$i] = $data_item;
                                $valores_max[$i] = $data_item;
			} elseif ( strtotime($valores[$i][2]) < strtotime($first_date)){
				$data_item = 0;
                                $valores_min[$i] = 0;
                                $valores_max[$i] = 0;
			}  else {
                                $data_item = $old_value;
                                $valores_min[$i] = $old_value;
                                $valores_max[$i] = $old_value;
                        }
		}

		$valores[$i][0]=$data_item;
	}

	$sql1 = "SELECT MAX(datos) FROM tagente_datos WHERE id_agente_modulo = ".$id_agente_modulo." AND timestamp > '".$valores[0][2]."' AND timestamp > '".$valores[$intervalo][3]."'";
	if ($result=mysql_query($sql1)){
		$row=mysql_fetch_array($result);
		$valor_maximo=$row[0];
		if ($valor_maximo == ""){
				$valor_maximo = 0;
			}
	} else {
		$valor_maximo=0;
	}

	// Invert data order in graph
	if ($config_graph_order == 1){
		$valor_maximo=0;$valor_minimo=0;
		for ($x = 0; $x <=$intervalo; $x++) {
			$grafica[$x]=$valores[$x][0];
			if ($valores_max[$x] > $valor_maximo){
				$valor_maximo = $valores_max[$x];
			}
			if ($valores_min[$x] < $valor_minimo){
				$valor_minimo = $valores_min[$x];
			}
		}
	} else {
		// Invert data
		$valor_maximo=0;$valor_minimo=0;
		for ($x = $intervalo; $x>=0; $x--) {
			$grafica[$x]=$valores[$intervalo-$x][0];
			$valores_max2[$x] = $valores_max[$intervalo-$x];
			$valores_min2[$x] = $valores_min[$intervalo-$x];
			if ($valores_max[$x] > $valor_maximo){
				$valor_maximo = $valores_max[$x];
			}
			if ($valores_min[$x] < $valor_minimo){
				$valor_minimo = $valores_min[$x] - 50;
			}
			$etiq_base2[$intervalo-$x]=$etiq_base[$x];
		}
		$valores_max = $valores_max2;
		$valores_min = $valores_min2;
		$etiq_base = $etiq_base2;
	}

	// Create graph
	$graph = new Graph(550,220);     
	$graph->SetMargin(50,120,30,60); 
	$valor_maximo =  ceil($valor_maximo / 4) + $valor_maximo;
	$graph->SetScale("textlin",0,$valor_maximo,0,0);
        $graph->SetAlphaBlending();	
	// Which background color
	$graph->SetMarginColor('white');
	
	// Without frame
	$graph->SetFrame(false);
	
	// Colour Y-axe line
	$graph->ygrid->SetFill(true,'#EFEFEF@0.6','#BBCCFF@0.6');
	//$graph->xgrid->Show();
	
	// Title
	$graph->tabtitle->Set("$etiqueta - $nombre_agente / $nombre_modulo");

	// To use true type fonts (who permits a label text in angle of 45)
	// cp /usr/share/fonts/truetype/msttcorefonts/arial.ttf /usr/X11R6/lib/X11/fonts/truetype/
	if ($config_truetype == 1){
		$graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,7); 
		$graph->xaxis->SetLabelAngle(35);
	} else {
		// Fixed font
		$graph->xaxis->SetFont(FF_FONT0);
		$graph->xaxis->SetLabelAngle(90);
	}
	
	$graph->xaxis->SetTickLabels($etiq_base);
	//$graph->xaxis->SetTextLabelInterval(ceil($intervalo / 10),0);
	
	$graph->xaxis->SetTextTickInterval(ceil($intervalo/10),0); 
	$graph->yaxis->SetFont(FF_FONT0);
	// Crete data line

	$line0=new LinePlot($valores_max);
	$line0->SetColor("blue");
	$line0->SetWeight(1);
	$line0->SetFillColor("blue@0.2");
 	$line0->SetLegend($lang_label["max"]); 
	
	$line1=new LinePlot($grafica);
	$line1->SetColor($color);
	$line1->SetWeight(1);
	$line1->SetFillColor($color."@0.2");
	$line1->SetLegend($lang_label["med"]); 
	
	$line2=new LinePlot($valores_min);
	$line2->SetColor("yellow");
	$line2->SetWeight(1);
	$line2->SetFillColor("yellow@0.2");
	$line2->SetLegend($lang_label["min"]); 
	
	// Add line to graph
	$graph->Add($line0);
	$graph->Add($line1);
	$graph->Add($line2);
	
	$graph->legend->Pos(0.01,0.2,"right","center");
	
	// Y-axe up graph
	$graph->SetGridDepth(DEPTH_BACK);
	// Antialias
	// $graph->img->SetAntiAliasing();
	// Mostramos la imagen 
	$graph->Stroke();
}

function graphic_agentmodules($id_agent) {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$sql1="SELECT * FROM ttipo_modulo";
	$result=mysql_query($sql1);
	$ax = 0;
	while ($row=mysql_fetch_array($result)){
		$data_label[$ax]=$row["nombre"]; 
		$data[$ax]=0;
		$data_id[$ax] = $row["id_tipo"];
		$ax++;
	}
	$cx=0;
	$sql1="SELECT * FROM tagente_modulo WHERE id_agente = ".$id_agent;
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		$cx++;
		for ($bx=0;$bx<=$ax;$bx++){
			if (isset($data_id[$bx])){
				if ($data_id[$bx] == $row["id_tipo_modulo"]){
					$data[$bx]++;
				}
			}
		}		
	}

	$mayor = 0;
	$mayor_data =0;
	for ($a=0;$a < sizeof($data); $a++)
		if ($data[$a] > $mayor_data){
			$mayor = $a;
			$mayor_data = $data[$a];
		}
	$bx=0;
	for ($a=0;$a < sizeof($data_label); $a++)
		if ($data[$a] > 0){
			$data_label2[$bx] = $data_label[$a];
			$data2[$bx] = $data[$a];
			$bx++;
		}


	$graph = new PieGraph(280,120,"auto");
	// $graph->SetMarginColor('white@0.2');
	$graph->SetMargin(15,4,2,2); 
	$graph->SetMarginColor('#f5f5f5');
	$graph->img->SetCanvasColor('#f5f5f5');
	$graph->SetFrame(True,'#f5f5f5',0);
	$graph->SetAlphaBlending();	
	if ($cx > 1){
		$p1 = new PiePlot3D($data2);
		$p1->SetLegends($data_label2);
	} else {
		$data_void[]="1";
		$legend_void[]="N/A";
		$p1 = new PiePlot3D($data_void);
		$p1->SetLegends($legend_void);
	}
	$p1->ExplodeSlice($mayor);
	$p1->SetSize(0.5);
	$p1->SetCenter(0.3);
	$p1->value->SetColor("#f5f5f5"); // Invisible 
	$graph->legend->SetAbsPos(5,5,'right','top');
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();	
}


function graphic_agentaccess($id_agent, $periodo){
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_line.php");
	require ("../include/languages/language_".$language_code.".php");
	$color ="#437722"; // Green pandora 1.1 octopus

	$agent_interval = give_agentinterval($id_agent);
	$intervalo = 30 * $config_graph_res; // Desired interval / range between dates
	$intervalo_real = (86400 / $agent_interval); // 60x60x24 secs
	if ($intervalo_real < $intervalo ) {
		$intervalo = $intervalo_real;
		
	}
	$fechatope = dame_fecha($periodo);
	$horasint = $periodo / $intervalo;

	// $intervalo now stores "ideal" interval			}
	// interval is the number of rows that will store data. more rows, more resolution

	// Para crear las graficas vamos a crear un array de Ax4 elementos, donde
	// A es el numero de posiciones diferentes en la grafica (30 para un mes, 7 para una semana, etc)
	// y los 4 valores en el ejeY serian los detallados a continuacion:
	// Rellenamos la tabla con un solo select, y los calculos se hacen todos sobre memoria
	// esto acelera el tiempo de calculo al maximo, aunque complica el algoritmo :-)
	
	// Creamos la tabla (array) con los valores para el grafico. Inicializacion
	for ($x = 0; $x <$intervalo; $x++) {
		$valores[$x][0] = 0; // [0] Valor (contador)
		$valores[$x][1] = 0; // [0] Valor (contador)
		$valores[$x][2] = dame_fecha($horasint * $x); // [2] Rango superior de fecha para ese rango
		$valores[$x][3] = dame_fecha($horasint*($x+1)); // [3] Rango inferior de fecha para ese rango
	}
	$sql1="SELECT * FROM tagent_access WHERE id_agent = ".$id_agent." and timestamp > '".$fechatope."'";

	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		for ($i = 0; $i < $intervalo; $i++){
			if (($row["timestamp"] < $valores[$i][2]) and ($row["timestamp"] >= $valores[$i][3]) ){ 
				// entra en esta fila
				$valores[$i][0]++;
			}
		} 
		
	}
	$valor_maximo = 0;
	for ($i = 0; $i < $intervalo; $i++) { // 30 entries in graph, one by day
		$grafica[]=$valores[$i][0];

	}

	// Create graph 
	$graph = new Graph(280,70);     
	$graph-> img-> SetImgFormat("png"); // Replaced instead of GIF format. PNG is free :-)
					    // and GIF was causing problems in Redhat EL 4 
	$graph->SetMargin(25,5,3,3); 
	$graph->SetScale("textlin",0,0,0,0);
	$graph->SetAlphaBlending(true);

        $graph->yaxis->HideTicks(false);
	$graph->xaxis->HideTicks(true);
	$graph->xaxis->HideLabels(true);
	$graph->yaxis->HideLabels(false);
	
	$graph->SetMarginColor('#f5f5f5');
	$graph->img->SetCanvasColor('#f5f5f5');
	$graph->SetFrame(True,'#f5f5f5',0);
		
	
	// Linea del eje Y de color
	// $graph->ygrid->SetFill(true,'#EFEFEF@0.6','#BBCCFF@0.6');
	// $graph->xgrid->Show();
	
	// Titulo guay
	//$graph->tabtitle->Set("Access Access");
	//$graph->xaxis->SetTickLabels("Que ostias");
	$graph->xaxis->SetFont(FF_FONT0);
	$graph->xaxis->SetLabelAngle(90);
	//$graph->xaxis->SetTextLabelInterval(ceil($intervalo / 10));
	$graph->yaxis->SetFont(FF_FONT0);
	// Creacion de la linea de datos

	
	$line1=new LinePlot($grafica);
	$line1->SetColor($color);
	$line1->SetWeight(1);
	$line1->SetFillColor($color."@0.2");
	//$line1->SetLegend($lang_label["med"]); 
	
	// A�dimos la linea a la imagen
	$line1->SetFillColor($color."@0.2");
	$graph->Add($line1);
	
	//$graph->legend->Pos(0.01,0.2,"right","center");
	
	// Lineas eje Y por encima del grafico
	//$graph->SetGridDepth(DEPTH_BACK);
	// Antialias
	//$graph->img->SetAntiAliasing();
	// Mostramos la imagen 
	$graph->Stroke();
}


function grafico_incidente_estados() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array(0,0,0,0);
	// 0 - Abierta / Sin notas
	// 2 - Descartada
	// 3 - Caducada 
	// 13 - Cerrada
	$sql1="SELECT * FROM tincidencia";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		if ($row["estado"] == 0)
			$data[0]=$data[0]+1;
		if ($row["estado"] == 2)
			$data[1]=$data[1]+1;
		if ($row["estado"] == 3)
			$data[2]=$data[2]+1;
		if ($row["estado"] == 13)
			$data[3]=$data[3]+1;
	}
	$mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++)
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }
	$graph = new PieGraph(370,180,"auto");
	$graph->SetMarginColor('white@0.2');
	$graph->title->Set($lang_label["incident_status"]);
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->setShadow();
	$graph->SetAlphaBlending();	
	$graph->SetFrame(true);
	$p1 = new PiePlot3D($data);
 	$p1->ExplodeSlice($mayor);
	$p1->SetSize(0.4);
	$p1->SetCenter(0.3);
	$legend = array ("Open Incident", "Closed Incident", "Outdated", "Invalid");
	$p1->SetLegends($legend);
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();
}

function grafico_incidente_prioridad() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array(0,0,0,0,0,0);
	// 0 - Abierta / Sin notas
	// 2 - Descartada
	// 3 - Caducada 
	// 13 - Cerrada
	$sql1="SELECT * FROM tincidencia";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		if ($row["prioridad"] == 0)
			$data[0]=$data[0]+1;
		if ($row["prioridad"] == 1)
			$data[1]=$data[1]+1;
		if ($row["prioridad"] == 2)
			$data[2]=$data[2]+1;
		if ($row["prioridad"] == 3)
			$data[3]=$data[3]+1;
		if ($row["prioridad"] == 4)
			$data[4]=$data[4]+1;
		if ($row["prioridad"] == 10)
			$data[5]=$data[5]+1;
	}
		
	$mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++)
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }


	$graph = new PieGraph(370,180,"auto");
	$graph->SetMarginColor('white@0.2');
	$graph->title->Set($lang_label["incident_priority"]);
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->SetShadow();
	$graph->SetAlphaBlending();	
	$graph->SetFrame(true);
	$p1 = new PiePlot3D($data);
  	$p1->ExplodeSlice($mayor);
	$p1->SetSize(0.4);
	$p1->SetCenter(0.3);
	$legend = array ("Informative","Low","Medium","Serious", "Very serious", "Maintance");
	$p1->SetLegends($legend);
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();
}

function graphic_incident_group() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

        $data = array();
        $legend = array();
        $sql1="SELECT distinct id_grupo FROM tincidencia ";
        $result=mysql_query($sql1);
        while ($row=mysql_fetch_array($result)){
                $sql1="SELECT COUNT(id_incidencia) FROM tincidencia WHERE id_grupo = ".$row[0];
                $result2=mysql_query($sql1);
                $row2=mysql_fetch_array($result2);
                $data[] = $row2[0];
                $legend[] = dame_nombre_grupo($row[0])."(".$row2[0].")";
        }
	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
        $mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++){
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }
	}

        $ajuste_altura = sizeof($data) * 20;
        $graph = new PieGraph(370,80+$ajuste_altura,'auto');        
        $graph->SetMarginColor('white@0.2');
        $graph->title->Set($lang_label["incident_group"]);
        $graph->title->SetFont(FF_FONT1,FS_BOLD);
        $graph->SetShadow();
        $graph->SetAlphaBlending();
        $graph->SetFrame(true);
        $p1 = new PiePlot3D($data);
        $p1->ExplodeSlice($mayor);
        $p1->SetSize(0.25);
        $p1->SetCenter(0.3);
        $p1->SetLegends($legend);
        $graph->Add($p1);
        $graph->img->SetAntiAliasing();
        $graph->Stroke();
}

function graphic_incident_user() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array();
	$legend = array();
	$sql1="SELECT distinct id_usuario FROM tincidencia ";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
			$sql1="SELECT COUNT(id_incidencia) FROM tincidencia WHERE id_usuario = '".$row[0]."'";
			$result2=mysql_query($sql1);
			$row2=mysql_fetch_array($result2);
			$data[] = $row2[0];
			$legend[] = $row[0]."(".$row2[0].")";
	}
	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
        $mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++){
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }
        }

        $ajuste_altura = sizeof($data) * 20;
        $graph = new PieGraph(370,80+$ajuste_altura,'auto');
        $graph->SetMarginColor('white@0.2');
        $graph->title->Set($lang_label["incident_user"]);
        $graph->title->SetFont(FF_FONT1,FS_BOLD);
        $graph->SetShadow();
        $graph->SetAlphaBlending();
        $graph->SetFrame(true);
        $p1 = new PiePlot3D($data);
        $p1->ExplodeSlice($mayor);
        $p1->SetSize(0.25);
        $p1->SetCenter(0.3);
        $p1->SetLegends($legend);
        $graph->Add($p1);
        $graph->img->SetAntiAliasing();
        $graph->Stroke();
}

function graphic_user_activity() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array();
	$legend = array();
	$sql1="SELECT DISTINCT ID_usuario FROM tsesion ";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
	$entrada= entrada_limpia($row[0]);
			$sql1='SELECT COUNT(ID_usuario) FROM tsesion WHERE ID_usuario = "'.$entrada.'"';
			$result2=mysql_query($sql1);
			$row2=mysql_fetch_array($result2);
			$data[] = $row2[0];
			$legend[] = substr($row[0],0,16)."(".$row2[0].")";
	}

	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
	// or much better, pay me to do a special version for you, highly optimized :-))))
	for ($a=0;$a < sizeof($data);$a++){
		for ($b=$a; $b <sizeof($data); $b++)
		if ($data[$b] > $data[$a]){
			$temp = $data[$a];
			$temp_label = $legend[$a];
			$data[$a] = $data[$b];
			$legend[$a] = $legend[$b];
			$data[$b] = $temp;
			$legend[$b] = $temp_label;
		}
	}

        $mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++){
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }
        }

        $ajuste_altura = sizeof($data) * 20;
        $graph = new PieGraph(500,80+$ajuste_altura,'auto');
        $graph->SetMarginColor('white@0.2');
        $graph->title->Set($lang_label["users_statistics"]);
        $graph->title->SetFont(FF_FONT1,FS_BOLD);
        $graph->SetShadow();
        $graph->SetAlphaBlending();
        $graph->SetFrame(true);
        $p1 = new PiePlot3D($data);
        $p1->ExplodeSlice($mayor);
        $p1->SetSize(0.25);
        $p1->SetCenter(0.3);
        $p1->SetLegends($legend);
	$graph->legend->Pos(0.05,0.49,"right","center");
        $graph->Add($p1);
        $graph->img->SetAntiAliasing();
        $graph->Stroke();
}

function graphic_incident_source() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

        $data = array();
        $legend = array();
        $sql1="SELECT DISTINCT origen FROM tincidencia";
        $result=mysql_query($sql1);
        while ($row=mysql_fetch_array($result)){
                $sql1="SELECT COUNT(id_incidencia) FROM tincidencia WHERE origen = '".$row[0]."'";
                $result2=mysql_query($sql1);
                $row2=mysql_fetch_array($result2);
                $data[] = $row2[0];
                $legend[] = $row[0]."(".$row2[0].")";
        }
	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
        $mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++){
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }
        }

        $ajuste_altura = sizeof($data) * 20;
        $graph = new PieGraph(370,80+$ajuste_altura,'auto');
        $graph->SetMarginColor('white@0.2');
        $graph->title->Set($lang_label["incident_source"]);
        $graph->title->SetFont(FF_FONT1,FS_BOLD);
        $graph->SetShadow();
        $graph->SetAlphaBlending();
        $graph->SetFrame(true);
        $p1 = new PiePlot3D($data);
        $p1->ExplodeSlice($mayor);
        $p1->SetSize(0.25);
        $p1->SetCenter(0.3);
        $p1->SetLegends($legend);
        $graph->Add($p1);
        $graph->img->SetAntiAliasing();
        $graph->Stroke();
}
function grafico_db_agentes_modulos() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_bar.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array();
	$legend = array();
	$sql1="SELECT * FROM tagente";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		$sql1="SELECT COUNT(id_agente_modulo) FROM tagente_modulo WHERE id_agente = ".$row["id_agente"];;
		$result2=mysql_query($sql1);
		$row2=mysql_fetch_array($result2);
		$data[] = $row2[0];
		$legend[] = $row["nombre"];
	}
	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
	$mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++)
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }

	$ajuste_altura = sizeof($data) * 20;	
	//$graph = new PieGraph(400,140+$ajuste_altura,"auto");
	$graph = new Graph(400,140+$ajuste_altura,'auto');
	$graph->SetScale("textlin");
	$graph->SetMarginColor('white@0.2');
	$graph->title->Set($lang_label["modules_per_agent"]);
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->yaxis->scale->SetGrace(0);
	$graph->yaxis->SetLabelAlign('center','bottom');
	$graph->SetAlphaBlending();	
	$graph->SetFrame(true);
	$graph->xaxis->SetLabelMargin(5);
	$graph->Set90AndMargin(100,20,50,30);
	$p1 = new BarPlot($data);
	$p1->value->SetFormat('%.0f ');
	$p1->value->Show();
	$p1->value->SetAlign('left','center');
	$p1->SetFillColor("#00bf00");
	$p1->SetWidth(0.6);
	$p1->SetShadow();
	$graph->yaxis->SetLabelFormat('%d');
	$graph->xaxis->SetTickLabels($legend);
	$graph->legend->Pos(0.05,0.49,"right","center");
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();

}

function grafico_eventos_usuario() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array();
	$legend = array();
	$sql1="SELECT * FROM tusuario";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		$sql1="SELECT COUNT(id_evento) FROM tevento WHERE id_usuario = '".$row["id_usuario"]."'";
		$result2=mysql_query($sql1);
		$row2=mysql_fetch_array($result2);
		if ($row2[0] > 0){
			$data[] = $row2[0];
			$legend[] = $row["id_usuario"]." ( $row2[0] )";
		}
	}
	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
	$mayor = 0;
	$mayor_data =0;
	for ($a=0;$a < sizeof($data); $a++)
	if ($data[$a] > $mayor_data){
   		$mayor = $a;
   		$mayor_data = $data[$a];
	}

	$ajuste_altura = sizeof($data) * 17;
	$graph = new PieGraph(430,170+$ajuste_altura,"auto");
	$graph->SetMarginColor('white@0.2');
	$graph->title->Set($lang_label["events_per_user"]);
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->SetShadow();
	$graph->SetAlphaBlending();	
	$graph->SetFrame(true);
	$p1 = new PiePlot3D($data);
	$p1->ExplodeSlice($mayor);
	$p1->SetSize(0.2);
	$p1->SetCenter(0.3);
	$p1->SetLegends($legend);
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();
}

function grafico_eventos_total() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array();
	$legend = array();
	$total = 0;
	
	$sql1="SELECT COUNT(id_evento) FROM tevento WHERE estado = 1 ";
	$result=mysql_query($sql1);
	$row=mysql_fetch_array($result);
	$data[] = $row[0];
	$legend[] = "Revised ( $row[0] )";
	$total = $row[0];
	
	$sql1="SELECT COUNT(id_evento) FROM tevento WHERE estado = 0 ";
	$result=mysql_query($sql1);
	$row=mysql_fetch_array($result);
	$data[] = $row[0];
	$total = $total + $row[0];
	$legend[] = "Not Revised ( $row[0] )";

	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
	$mayor=0; $mayor_data=0;
        for ($a=0;$a < sizeof($data); $a++)
        if ($data[$a] > $mayor_data){
                $mayor = $a;
                $mayor_data = $data[$a];
        }
	
	$graph = new PieGraph(430,200,"auto");
	$graph->SetMarginColor('white@0.2');
	$graph->title->Set($lang_label["event_total"]." ( $total )");
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->SetShadow();
	$graph->SetAlphaBlending();	
	$graph->SetFrame(true);
	$p1 = new PiePlot3D($data);
 	$p1->ExplodeSlice($mayor);
	$p1->SetSize(0.4);
	$p1->SetCenter(0.28);
	$p1->SetLegends($legend);
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();
}

function grafico_eventos_grupo() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array();
	$legend = array();
	$sql1="SELECT * FROM tgrupo";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		$sql1="SELECT COUNT(id_evento) fROM tevento WHERE id_grupo = ".$row["id_grupo"];
		$result2=mysql_query($sql1);
		$row2=mysql_fetch_array($result2);
		if ($row2[0] > 0){
			$data[] = $row2[0];
			$legend[] = $row["nombre"]." ( $row2[0] )";
		}
	}
	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        } 
        $mayor=0; $mayor_data=0;
        for ($a=0;$a < sizeof($data); $a++)
        if ($data[$a] > $mayor_data){
                $mayor = $a;
                $mayor_data = $data[$a];
        }
	$total_grupos = sizeof($data);
	$ajuste_altura = $total_grupos * 10;
	
	$graph = new PieGraph(430,150+$ajuste_altura,"auto");
	$graph->SetMarginColor('white@0.2');
	$graph->title->Set($lang_label["events_per_group"]);
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->SetShadow();
	$graph->SetAlphaBlending();	
	$graph->SetFrame(true);
	$p1 = new PiePlot3D($data);
	$p1->ExplodeSlice($mayor);
	$p1->SetSize(0.35);
	$p1->SetCenter(0.28);
	$p1->SetLegends($legend);
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();
}

function grafico_db_agentes_paquetes() {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_bar.php");
	require ("../include/languages/language_".$language_code.".php");

	$data = array();
	$legend = array();
	$sql1="SELECT distinct (id_agente) FROM tagente_datos";
	$result=mysql_query($sql1);
	while ($row=mysql_fetch_array($result)){
		if (! is_null($row["id_agente"])){
			$sql1="SELECT COUNT(id_agente) FROM tagente_datos WHERE id_agente = ".$row["id_agente"];
			$result3=mysql_query($sql1);
			if ($row3=mysql_fetch_array($result3)){
				$data[]= $row3[0];
				$legend[] = dame_nombre_agente($row[0]);
			}
		}
	}
	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
	$mayor = 0;
        $mayor_data =0;
        for ($a=0;$a < sizeof($data); $a++)
                if ($data[$a] > $mayor_data){
                        $mayor = $a;
                        $mayor_data = $data[$a];
                }	

        $ajuste_altura = sizeof($data) * 20;
        $graph = new Graph(400,140+$ajuste_altura,'auto');
        $graph->SetScale("textlin");
        $graph->SetMarginColor('white@0.2');
        $graph->title->Set($lang_label["packets_by_agent"]);
        $graph->title->SetFont(FF_FONT1,FS_BOLD);
        $graph->yaxis->scale->SetGrace(0);
        $graph->yaxis->SetLabelAlign('center','bottom');
        $graph->SetAlphaBlending();
        $graph->SetFrame(true);
        $graph->xaxis->SetLabelMargin(5);
        $graph->Set90AndMargin(100,20,50,30);
        $p1 = new BarPlot($data);
	$p1->value->SetFormat('%.0f ');
        $p1->value->Show();
        $p1->value->SetAlign('left','center');
        $p1->SetFillColor("#0000fd");
        $p1->SetWidth(0.6);
        $p1->SetShadow();
        $graph->yaxis->SetLabelFormat('%d');
        $graph->xaxis->SetTickLabels($legend);
        $graph->legend->Pos(0.05,0.49,"right","center");
        $graph->Add($p1);
        $graph->img->SetAntiAliasing();
        $graph->Stroke();
}

function grafico_db_agentes_purge($id_agente) {
	include ("../include/config.php");
	include ("jpgraph/jpgraph.php");
	include ("jpgraph/jpgraph_pie.php");
	include ("jpgraph/jpgraph_pie3d.php");
	require ("../include/languages/language_".$language_code.".php");

	// All data (now)
	$purge_all=date("Y-m-d H:i:s",time());
		
	// 1 day
	$d1_year = date("Y", time()-28800);
	$d1_month = date("m", time()-28800);
	$d1_day = date ("d", time()-28800);
	$d1_hour = date ("H", time()-28800);
	$minuto = date("i",time());
	$segundo = date("s",time());
	$d1 = $d1_year."-".$d1_month."-".$d1_day." ".$d1_hour.":".$minuto.":".$segundo."";
	
	// 3 days
	$d3_year = date("Y", time()-86400);
	$d3_month = date("m", time()-86400);
	$d3_day = date ("d", time()-86400);
	$d3_hour = date ("H", time()-86400);
	$d3 = $d3_year."-".$d3_month."-".$d3_day." ".$d3_hour.":".$minuto.":".$segundo."";
	
	// Fecha 24x7 Horas (una semana)
	$week_year = date("Y", time()-604800);
	$week_month = date("m", time()-604800);
	$week_day = date ("d", time()-604800);
	$week_hour = date ("H", time()-604800);
	$d7 = $week_year."-".$week_month."-".$week_day." ".$week_hour.":".$minuto.":".$segundo."";
	
	// Fecha 24x7x2 Horas (dos semanas)
	$week2_year = date("Y", time()-1209600);
	$week2_month = date("m", time()-1209600);
	$week2_day = date ("d", time()-1209600);
	$week2_hour = date ("H", time()-1209600);
	$d14 = $week2_year."-".$week2_month."-".$week2_day." ".$week2_hour.":".$minuto.":".$segundo."";
		
	// Fecha de hace 24x7x30 Horas (un mes)
	$month_year = date("Y", time()-2592000);
	$month_month = date("m", time()-2592000);
	$month_day = date ("d", time()-2592000);
	$month_hour = date ("H", time()-2592000);
	$d30 = $month_year."-".$month_month."-".$month_day." ".$month_hour.":".$minuto.":".$segundo."";
	
	// Three months
	$month3_year = date("Y", time()-7257600);
	$month3_month = date("m", time()-7257600);
	$month3_day = date ("d", time()-7257600);
	$month3_hour = date ("H", time()-7257600);
	$d90 = $month3_year."-".$month3_month."-".$month3_day." ".$month3_hour.":".$minuto.":".$segundo."";
	
	$data = array();
	$legend = array();

	$fechas= array($d90, $d30, $d7, $d1);
	$fechas_label = array("> 30 days","7-30 days","2-7 days","24Hr");

	// Calc. total packets
        $sql1="SELECT COUNT(id_agente_datos) FROM tagente_datos";;
        $result2=mysql_query($sql1);
        $row2=mysql_fetch_array($result2);
        $total = $row2[0];

	for ($a=0;$a<sizeof($fechas);$a++){	// 4 x intervals will be enought, increase if your database is very very quickly :)
		if ($a==3)
			$sql1="SELECT COUNT(id_agente_datos) FROM tagente_datos WHERE timestamp >= '".$fechas[$a]."' ";
		else
			$sql1="SELECT COUNT(id_agente_datos) FROM tagente_datos WHERE timestamp >= '".$fechas[$a]."' AND timestamp < '".$fechas[$a+1]."' ";
		$result=mysql_query($sql1);
		$row=mysql_fetch_array($result);
		$data[] = $row[0];
		$legend[]=$fechas_label[$a]." ( ".$row[0]." )";
	}

	// Sort array by bubble method (yes, I study more methods in university, but if you want more speed, please, submit a patch :)
        // or much better, pay me to do a special version for you, highly optimized :-))))
        for ($a=0;$a < sizeof($data);$a++){
                for ($b=$a; $b <sizeof($data); $b++)
                if ($data[$b] > $data[$a]){
                        $temp = $data[$a];
                        $temp_label = $legend[$a];
                        $data[$a] = $data[$b];
                        $legend[$a] = $legend[$b];
                        $data[$b] = $temp;
                        $legend[$b] = $temp_label;
                }
        }
	$mayor = 0;
	$mayor_data =0;
	for ($a=0;$a < sizeof($data); $a++)
		if ($data[$a] > $mayor_data){
			$mayor = $a;
			$mayor_data = $data[$a];
		}
	
	$graph = new PieGraph(500,200,"auto");
	$graph->SetMarginColor('white@0.2');
	$graph->title->Set($lang_label["packets_by_date"]." ( Tot - $total ) ");
	$graph->title->SetFont(FF_FONT1,FS_BOLD);
	$graph->SetShadow();
	$graph->SetAlphaBlending();	
	$graph->SetFrame(true);
	$p1 = new PiePlot3D($data);
	$p1->ExplodeSlice($mayor);
	$p1->SetSize(0.35);
	$p1->SetCenter(0.3);
	$p1->SetLegends($legend);
	$graph->Add($p1);
	$graph->img->SetAntiAliasing();
	$graph->Stroke();
}

function drawWarning($width,$height) {
	include ("../include/config.php");
	require ("../include/languages/language_".$language_code.".php");
	if ($width == 0) {
		$width = 50;
	}
	if ($height == 0) {
		$height = 30;
	}
	
	
	$image = imagecreate($width,$height);
	//colors
	$back = ImageColorAllocate($image,255,255,255);
	$border = ImageColorAllocate($image,0,0,0);
	$red = ImageColorAllocate($image,255,60,75);
	$fill = ImageColorAllocate($image,44,81,150);

	ImageFilledRectangle($image,0,0,$width-1,$height-1,$back);
	ImageRectangle($image,0,0,$width-1,$height-1,$border);
	ImageTTFText($image, 8, 0, ($width/2)-($width/10), ($height/2)+($height/5), $border, $config_fontpath, $lang_label["no_data"]);
	imagePNG($image);
	imagedestroy($image);
}

function progress_bar($progress,$width,$height) {
   // Copied from the PHP manual:
   // http://us3.php.net/manual/en/function.imagefilledrectangle.php
   // With some adds from sdonie at lgc dot com
   // Get from official documentation PHP.net website. Thanks guys :-)
   // Code ripped from Babel Project :-)
   function drawRating($rating,$width,$height) {
	include ("../include/config.php");
	require ("../include/languages/language_".$language_code.".php");
	if ($width == 0) {
		$width = 150;
	}
	if ($height == 0) {
		$height = 20;
	}
	
	//$rating = $_GET['rating'];
	$ratingbar = (($rating/100)*$width)-2;
	
	$image = imagecreate($width,$height);
	//colors
	$back = ImageColorAllocate($image,255,255,255);
	$border = ImageColorAllocate($image,0,0,0);
	$red = ImageColorAllocate($image,255,60,75);
	$fill = ImageColorAllocate($image,44,81,150);

	ImageFilledRectangle($image,0,0,$width-1,$height-1,$back);
	if ($rating > 100)
		ImageFilledRectangle($image,1,1,$ratingbar,$height-1,$red);
	else
		ImageFilledRectangle($image,1,1,$ratingbar,$height-1,$fill);
	ImageRectangle($image,0,0,$width-1,$height-1,$border);
	if ($rating > 50) 
		if ($rating > 100)
			ImageTTFText($image, 8, 0, ($width/3)-($width/10), ($height/2)+($height/5), $back, $config_fontpath,$lang_label["out_of_limits"]);
		else
			ImageTTFText($image, 8, 0, ($width/2)-($width/10), ($height/2)+($height/5), $back, $config_fontpath, $rating."%");
	else 
		ImageTTFText($image, 8, 0, ($width/2)-($width/10), ($height/2)+($height/5), $border, $config_fontpath, $rating."%");
	imagePNG($image);
	imagedestroy($image);
   }
   Header("Content-type: image/png");
   drawRating($progress,$width,$height);
}



// *****************************************************************************************************************
//   MAIN Code
//   parse get parameters
// *****************************************************************************************************************

if (isset($_GET["tipo"])){
	if ($_GET["tipo"]=="sparse"){
		if (isset($_GET["id"]) and   (isset($_GET["label"])) and ( isset($_GET["periodo"])) and (isset ($_GET["intervalo"])) AND (isset ($_GET["color"])) ){
			$id = $_GET["id"];
			$color = $_GET["color"];
			$tipo = $_GET["tipo"];
			$periodo = $_GET["periodo"];
			$intervalo = $_GET["intervalo"];
			$label = $_GET["label"];
			$color = "#".$color;
			grafico_modulo_sparse($id, $periodo, $intervalo, $label, $color);
		}
	}
	elseif ($_GET["tipo"] =="estado_incidente") 
		grafico_incidente_estados();	
	elseif ($_GET["tipo"] =="prioridad_incidente") 
		grafico_incidente_prioridad();	
	elseif ($_GET["tipo"]=="db_agente_modulo")
		grafico_db_agentes_modulos();
	elseif ($_GET["tipo"]=="db_agente_paquetes")
		grafico_db_agentes_paquetes();
	elseif ($_GET["tipo"] =="db_agente_purge")
		grafico_db_agentes_purge(-1);
	elseif ($_GET["tipo"] =="group_events")
		grafico_eventos_grupo();
	elseif ($_GET["tipo"] =="user_events")
		grafico_eventos_usuario();
	elseif ($_GET["tipo"] =="total_events")
		grafico_eventos_total();
	elseif ($_GET["tipo"] =="group_incident")
		graphic_incident_group();
	elseif ($_GET["tipo"] =="user_incident")
                graphic_incident_user();
	elseif ($_GET["tipo"] =="source_incident")
                graphic_incident_source();
	elseif ($_GET["tipo"] =="user_activity")
                graphic_user_activity();
	elseif ($_GET["tipo"] == "agentaccess")
		graphic_agentaccess($_GET["id"], $_GET["periodo"]);
	elseif ($_GET["tipo"] == "agentmodules")
		graphic_agentmodules($_GET["id"]);
	elseif ( $_GET["tipo"] =="progress"){
		$percent= $_GET["percent"];
		$width= $_GET["width"];
		$height= $_GET["height"];
		progress_bar($percent,$width,$height);
	}
}
?>

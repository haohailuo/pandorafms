package pandora_db;
##########################################################################
# Pandora Database Package
##########################################################################
# Copyright (c) 2004-2006 Sancho Lerena, slerena@gmail.com
# Copyright (c) 2005-2006 Artica Soluciones Tecnologicas S.L
#
#This program is free software; you can redistribute it and/or
#modify it under the terms of the GNU General Public License
#as published by the Free Software Foundation; either version 2
#of the License, or (at your option) any later version.
#This program is distributed in the hope that it will be useful,
#but WITHOUT ANY WARRANTY; without even the implied warranty of
#MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#GNU General Public License for more details.
#You should have received a copy of the GNU General Public License
#along with this program; if not, write to the Free Software
#Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
##########################################################################

use warnings;
use Time::Local;
use DBI;
use Date::Manip;	# Needed to manipulate DateTime formats of input, output and compare
use XML::Simple;

use POSIX qw(strtod);

use pandora_tools;

require Exporter;

our @ISA = ("Exporter");
our %EXPORT_TAGS = ( 'all' => [ qw( ) ] );
our @EXPORT_OK = ( @{ $EXPORT_TAGS{'all'} } );
our @EXPORT = qw( 	crea_agente_modulo			
			dame_server_id				
			dame_agente_id
			dame_agente_modulo_id
			dame_agente_nombre
			dame_comando_alerta
			dame_desactivado
			dame_grupo_agente
			dame_id_tipo_modulo
			dame_intervalo
			dame_learnagente
			dame_modulo_id
			dame_nombreagente_agentemodulo
			dame_nombretipomodulo_idagentemodulo
			dame_ultimo_contacto
			give_networkserver_status
			pandora_updateserver
			pandora_serverkeepaliver
			pandora_audit
			pandora_event
			pandora_lastagentcontact
			pandora_writedata
			pandora_writestate
			pandora_calcula_alerta
			module_generic_proc
			module_generic_data
			module_generic_data_inc
			module_generic_data_string
			execute_alert
		);

# Spanish translation note:
# 'Crea' in spanish means 'create'
# 'Dame' in spanish means 'give'

##########################################################################
## SUB pandora_calcula_alerta 
## (paconfig, timestamp,nombre_agente,tipo_modulo,nombre_modulo,datos,dbh)
## Given a datamodule, generate alert if needed
##########################################################################

sub pandora_calcula_alerta (%$$$$$$) {
	my $pa_config = $_[0];
    my $timestamp = $_[1];
	my $nombre_agente = $_[2];
	my $tipo_modulo = $_[3];
	my $nombre_modulo = $_[4];
	my $datos = $_[5];
	my $dbh = $_[6];

	my $id_modulo;
	my $id_agente;
	my $id_agente_modulo;
	my $max;
	my $min; # for calculate max & min to generate ALERTS

	# Get IDs from data packet
	$id_agente = dame_agente_id($pa_config, $nombre_agente, $dbh);
	$id_modulo = dame_modulo_id($pa_config, $tipo_modulo,$dbh);
	$id_agente_modulo = dame_agente_modulo_id($pa_config, $id_agente,$id_modulo,$nombre_modulo,$dbh);
	logger($pa_config, "DEBUG: calcula_alerta() Calculado id_agente_modulo a $id_agente_modulo",3);

	# If any alert from this combinatio of agent/module
	my $query_idag = "select * from talerta_agente_modulo where id_agente_modulo = '$id_agente_modulo'";
	my $s_idag = $dbh->prepare($query_idag);
	$s_idag ->execute;
	my @data;
	# If exists a defined alert for this module then continue
	if ($s_idag->rows != 0) {
		while (@data = $s_idag->fetchrow_array()) {
			my $id_aam = $data[0];
			my $id_alerta = $data[2];
			$id_agente_modulo = $data[1];
			$id_agente = dame_agente_id($pa_config,dame_nombreagente_agentemodulo($pa_config, $id_agente_modulo,$dbh),$dbh);
			my $id_grupo = dame_grupo_agente($pa_config, $id_agente,$dbh);
			my $campo1 = $data[3];
			my $campo2 = $data[4];
			my $campo3 = $data[5];
			my $descripcion = $data[6];
			my $dis_max = $data[7];
			my $dis_min = $data[8];
			my $threshold = $data[9];
			my $last_fired = $data[10];
			my $max_alerts = $data[11];
			my $times_fired = $data[12];
			my $module_type = $data[13];
			my $min_alerts = $data[14];
			my $internal_counter = $data[15];
			my $perl_expr = $data[16];
			my $comando ="";
			logger($pa_config, "Found an alert defined for $nombre_modulo, its ID $id_alerta",5);
			# Here we process alert if conditions are ok
			# Get data for defined alert given as $id_alerta
			my $query_idag2 = "select * from talerta where id_alerta = '$id_alerta'";
			my $s2_idag = $dbh->prepare($query_idag2);
			$s2_idag ->execute;
			my @data2;
			if ($s2_idag->rows != 0) {
				while (@data2 = $s2_idag->fetchrow_array()) {
					$comando = $data2[2];
				}
			}
			$s2_idag->finish();
              		# Get MAX and MIN value for this Alert. Only generate alerts if value is ABOVE MIN and BELOW MAX.
			my @data_max; 
                	my $query_idag_max = "select * from tagente_modulo where id_agente_modulo = ".$id_agente_modulo;
                	my $s_idag_max = $dbh->prepare($query_idag_max);
               	 	$s_idag_max ->execute;
                	if ($s_idag_max->rows == 0) {
                        	logger($pa_config, "ERROR Cannot find agenteModulo $id_agente_modulo",2);
                        	logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
                	} else  {    @data = $s_idag_max->fetchrow_array(); }
                	$max = $data_max[5];
                	$min = $data_max[6];
                	$s_idag_max->finish();
			# Init values for alerts
			my $alert_prefired = 0;
			my $alert_fired = 0;
			my $update_counter =0;

			# alert needs to be fired ?
			
			my $flag_firealert = 0;  # turned to 1 if alert needs to be fired
			
			logger ($pa_config, "XXX: moduletype $module_type", 3);
			if ($module_type == 3) {   # if module is generic_data_string
				# $perl_expr contains the regular expression to be match
				# TODO:  are security checks needed ???
				logger ($pa_config, "XXX alerta de log. dato $datos, re $perl_expr", 3);
				if ($datos =~ $perl_expr) { $flag_firealert = 1; }
			} else {
				logger($pa_config,"XXX alerta numerica. dato $datos, max $dis_max, min $dis_min",3);
				if (($datos > $dis_max) || ($datos < $dis_min)) { $flag_firealert = 1; }
			}
			
			if ( $flag_firealert ){
				logger ($pa_config, "XXX alerta tiene que ser disparada!",3);  
				# Check timegap
				my $fecha_ultima_alerta = ParseDate($last_fired);
				my $fecha_actual = ParseDate( $timestamp );
				my $ahora_mysql = &UnixDate("today","%Y-%m-%d %H:%M:%S");  # If we need to update MYSQL ast_fired will use $ahora_mysql
				my $time_threshold = $threshold;
				my $err; my $flag;
				my $fecha_limite = DateCalc($fecha_ultima_alerta,"+ $time_threshold seconds",\$err);
				$flag = Date_Cmp($fecha_actual,$fecha_limite);
				# DEBUG print "actual $fecha_actual limite $fecha_limite flag $flag times_fired $times_fired internal_counter $internal_counter \n";
				# Check timer threshold for this alert
				if ( $flag >= 0 ) { # Out limits !, reset $times_fired, but do not write to
						    # database until a real alarm was fired
					if ($times_fired > 1){ 
						$times_fired = 0;
						$internal_counter=0;
					}
					#my $query_idag = "update talerta_agente_modulo set times_fired = $times_fired, last_fired = $fecha_limite, internal_counter = $internal_counter where id_aam = $id_aam ";
					#	$dbh->do($query_idag);
					#my $query_idag = "update talerta_agente_modulo set times_fired = $times_fired , internal_counter = $internal_counter where id_aam = $id_aam ";
					#$dbh->do($query_idag);	
					logger ($pa_config,"Alarm out of timethreshold limits, resetting counters",10);
				}
				# We are between limits marked by time_threshold or running a new time-alarm-interval 
				# Caution: MIN Limit is related to triggered (in time-threshold limit) alerts
				# but MAX limit is related to executed alerts, not only triggered. Because an alarm to be
				# executed could be triggered X (min value) times to be executed.
				logger ($pa_config, "XXX : int_count $internal_counter, minal $min_alerts", 3);
				logger ($pa_config, "XXX : times_fired $times_fired, maxalerts $max_alerts", 3);
				if (($internal_counter >= $min_alerts) && ($times_fired  <= $max_alerts)){
					# The new alert is between last valid time + threshold and between max/min limit to alerts in this gap of time.
					$times_fired++;
					$internal_counter++;
					# FIRE ALERT !
					my $evt_descripcion = "Alert fired ($nombre_modulo)";
					pandora_event($pa_config, $evt_descripcion,$id_grupo,$id_agente, $dbh);	
					my $query_idag = "update talerta_agente_modulo set times_fired = $times_fired, last_fired = '$ahora_mysql', internal_counter = $internal_counter where id_aam = $id_aam ";
					$dbh->do($query_idag);
					my $nombre_agente = dame_nombreagente_agentemodulo($pa_config, $id_agente_modulo,$dbh);
					logger( $pa_config, "Alert TRIGGERED for $nombre_agente ! ",1);
					# If exists a defined alert for this module then continue
					if ($id_alerta != 0){ # id_alerta 0 is reserved for internal audit system
						$comando =~ s/_field1_/"$campo1"/gi;
						$comando =~ s/_field2_/"$campo2"/gi;
						$comando =~ s/_field3_/"$campo3"/gi;
						$comando =~ s/_agent_/$nombre_agente/gi;
						$comando =~ s/_timestamp_/$timestamp/gi;
						$comando =~ s/_data_/$datos/ig;
						$comando =~ s/\^M/\r\n/g; # Replace Carriage rerturn and line feed
						# Clean up some "tricky" characters
						$comando =~ s/&gt;/>/g;
						# EXECUTING COMMAND !!!
						eval {
							my $exit_value = system ($comando);
							$exit_value  = $? >> 8; # Shift 8 bits to get a "classic" errorlevel
							if ($exit_value != 0) {
								logger($pa_config, "Executed command for triggered alert had errors (errorlevel =! 0) ",0);
								logger($pa_config, "Executed command was $comando ",0);
							}
						};
						if ($@){
							logger($pa_config, "ERROR: Error executing alert command  ( $comando )",0);
							logger($pa_config, "ERROR Code: $@",1);
						}
					} else { # id_alerta = 0, is a internal system audit
						logger( $pa_config, "Internal audit lauch for agent name $nombre_agente",2);
						$campo1 =~ s/_timestamp_/$timestamp/g;
						$campo1 =~ s/_data_/$datos/g;
						pandora_audit ($pa_config, $campo1, $nombre_agente, "User Alert", $dbh);
					}
				} else { # Alert is in valid timegap but has too many alerts or too many little
					$internal_counter++;
					if ($internal_counter < $min_alerts){
						# Now update the new value for times_fired & last_fired if we are below min limit for triggering this alert
						my $query_idag = "update talerta_agente_modulo set times_fired = $times_fired, internal_counter = $internal_counter where id_aam = $id_aam ";
						$dbh->do($query_idag);
						logger ($pa_config, "Alarm not fired because is below min limit",8);
					} else { # Too many alerts fired (upper limit)
						my $query_idag = "update talerta_agente_modulo set times_fired = $times_fired,  internal_counter = $internal_counter where id_aam = $id_aam ";
						$dbh->do($query_idag);						
						logger ($pa_config, "Alarm not fired because is above max limit",8);
					}
				} #main block 
			} # data between alert values
			else {
				# Check timegap
				my $fecha_ultima_alerta = ParseDate($last_fired);
				my $fecha_actual = ParseDate( $timestamp );
				my $ahora_mysql = &UnixDate("today","%Y-%m-%d %H:%M:%S");       # If we need to update MYSQL ast_fired will use $ahora_mysql
				my $time_threshold = $threshold;
				my $err; my $flag;
				my $fecha_limite = DateCalc($fecha_ultima_alerta,"+ $time_threshold seconds",\$err);
				$flag = Date_Cmp($fecha_actual,$fecha_limite);
				# Check timer threshold for this alert
				if ( $flag >= 0 ) { # Out limits !, reset $times_fired, but do not write to
						    # database until a real alarm was fired
					my $query_idag = "update talerta_agente_modulo set times_fired = 0, internal_counter = 0 where id_aam = $id_aam ";
					$dbh->do($query_idag);
					# DEBUG print "SQL $query_idag \n";	
				}	
			}
		} # While principal
	} # if there are valid records
	$s_idag->finish();
}



##########################################################################
## SUB execute_alert (id_alert, field1, field2, field3, agent, timestamp, data)
## Do a execution of given alert with this parameters
##########################################################################

sub execute_alert (%$$$$$$$$) {
	my $pa_config = $_[0];
	my $id_alert = $_[1];
	my $field1 = $_[2];
	my $field2 = $_[3];
	my $field3 = $_[4];
	my $agent = $_[5];
	my $timestamp = $_[6];
	my $data = $_[7];
	my $dbh = $_[8];
	my $comand = "";
	my $alert_name="";

	# Get values for commandline, reading from talerta.
	my $query_idag = "select * from talerta where id_alerta = '$id_alert'";
	my $idag = $dbh->prepare($query_idag);
	$idag ->execute;
	my @datarow;
	if ($idag->rows != 0) {
		while (@datarow = $idag->fetchrow_array()) {
			$comand = $datarow[2];
			$alert_name = $datarow[1];		
		}
	}
	$idag->finish();

	logger($pa_config, "Alert ($alert_name) TRIGGERED for $agent",1);
	if ($id_alert != 0){ # id_alerta 0 is reserved for internal audit system
		$comand =~ s/_field1_/"$field1"/g;
		$comand =~ s/_field2_/"$field2"/g;
		$comand =~ s/_field3_/"$field3"/g;
		$comand=~ s/_agent_/$agent/g;
		$comand =~ s/_timestamp_/$timestamp/g;
		$comand =~ s/_data_/$data/g;
		# Clean up some "tricky" characters
		$comand =~ s/&gt;/>/g;
		# EXECUTING COMMAND !!!
		eval {
			my $exit_value = system ($comand);
			$exit_value  = $? >> 8; # Shift 8 bits to get a "classic" errorlevel
			if ($exit_value != 0) {
				logger($pa_config, "Executed command for triggered alert '$alert_name' had errors (errorlevel =! 0) ",0);
				logger($pa_config, "Executed command was $comand ",0);
			}
		};
		if ($@){
			logger($pa_config, "ERROR: Error executing alert command ( $comand )",0);
			logger($pa_config, "ERROR Code: $@",1);
		}
	} else { # id_alerta = 0, is a internal system audit
		logger($pa_config, "Internal audit lauch for agent name $agent",2);
		$field1 =~ s/_timestamp_/$timestamp/g;
		$field1 =~ s/_data_/$data/g;
		pandora_audit ($pa_config,$field1, $agent, "User Alert ($alert_name)",$dbh);
	}
	my $evt_descripcion = "Alert fired ($agent $alert_name) $data";
	my $id_agente = dame_agente_id($pa_config,$agent,$dbh);
	pandora_event($pa_config,$evt_descripcion,$id_agente,0,$dbh);
}


##########################################################################
## SUB pandora_writestate (pa_config, nombre_agente,tipo_modulo,nombre_modulo,valor_datos, estado)
## Alter data, chaning status of modules in state table
##########################################################################

sub pandora_writestate (%$$$$$$) {
	# my $timestamp = $_[0];
	# slerena, 05/10/04 : Fixed bug because differences between agent / server time source.
	# now we use only local timestamp to stamp state of modules
	my $pa_config = $_[0];
	my $nombre_agente = $_[1];
	my $tipo_modulo = $_[2];
	my $nombre_modulo = $_[3];
	my $datos = $_[4]; # OJO, no pasa una estructura sino un valor discreto
	my $estado = $_[5];
	my $dbh = $_[6];
	my $timestamp = &UnixDate("today","%Y-%m-%d %H:%M:%S");
	my @data;
	my $cambio = 0; my $id_grupo; 
	# Get id
	# BE CAREFUL: We don't verify the strings chains
	# TO DO: Verify errors
	my $id_agente = dame_agente_id($pa_config,$nombre_agente,$dbh);
	my $id_modulo = dame_modulo_id($pa_config,$tipo_modulo,$dbh);
	my $id_agente_modulo = dame_agente_modulo_id($pa_config,$id_agente, $id_modulo, $nombre_modulo,$dbh);
	if (($id_agente eq "-1") || ($id_agente_modulo eq "-1")) {
		goto fin_pandora_writestate;
	}
	# Check alert subroutine
	eval {
		# Alerts checks for Agents, only for master servers
                if ($pa_config->{"pandora_master"} == 1){
			pandora_calcula_alerta($pa_config, $timestamp, $nombre_agente, $tipo_modulo, $nombre_modulo, $datos, $dbh);
		}
	};
	if ($@) {
			logger($pa_config, "ERROR: Error in SUB calcula_alerta(). ModuleName: $nombre_modulo ModuleType: $tipo_modulo AgentName: $nombre_agente",1);
			logger($pa_config, "ERROR Code: $@",1)
	}
	# $id_agente is agent ID to update ".dame_nombreagente_agentemodulo ($id_agente_modulo)."
	# Let's see if there is any entry at tagente_estado table
	my $idages = "select * from tagente_estado where id_agente_modulo = $id_agente_modulo";
	my $s_idages = $dbh->prepare($idages);
	$s_idages ->execute;
	$datos = $dbh->quote($datos); # Parse data entry for adecuate SQL representation.
	my $query_act; # OJO que dentro de una llave solo tiene existencia en esa llave !!
	if ($s_idages->rows == 0) { # Doesnt exist entry in table, lets make the first entry
		logger($pa_config, "Generando entrada (INSERT) en tagente_estado para $nombre_modulo",2);
    		$query_act = "insert into tagente_estado (id_agente_modulo,datos,timestamp,estado,cambio,id_agente,last_try) values ($id_agente_modulo,$datos,'$timestamp','$estado','1',$id_agente,'$timestamp')"; # Cuando se hace un insert, siempre hay un cambio de estado

    	} else { # There are an entry in table already
	        @data = $s_idages->fetchrow_array();
	        # Se supone que $data[5] nos daria el estado ANTERIOR, podriamos leerlo
	        # ($c1,$c2,$c3...) $i_dages->fetchrow_array(); y luego hacer referencia a $c6 p.e
		# For xxxx_PROC type (boolean / monitor), create an event if state has changed
	        if (( $data[5] != $estado) && ($tipo_modulo =~ /proc/) ) {
	                # Cambio de estado detectado !
	                $cambio = 1;
	                # Este seria el momento oportuno de probar a saltar la alerta si estuviera definida
			# Makes an event entry, only if previous state changes, if new state, doesnt give any alert
			$id_grupo = dame_grupo_agente($pa_config, $id_agente,$dbh);
			my $descripcion;
 			if ( $estado == 0) { $descripcion = "Monitor ($nombre_modulo) goes up "; }
			if ( $estado == 1) { $descripcion = "Monitor ($nombre_modulo) goes down"; }
			pandora_event($pa_config, $descripcion,$id_grupo,$id_agente,$dbh);
	        }
    		$query_act = "update tagente_estado set datos = $datos, cambio = '$cambio', timestamp = '$timestamp', estado = '$estado', id_agente = $id_agente, last_try = '$timestamp' where id_agente_modulo = $id_agente_modulo ";
    	}
	my $a_idages = $dbh->prepare($query_act);
	$a_idages->execute;
	$a_idages->finish();
    	$s_idages->finish();
fin_pandora_writestate:
}

##########################################################################
####   MODULOS implementados en Pandora
##########################################################################

# ----------------------------------------+
# Modulos genericos de Pandora            |
# ----------------------------------------+

# Los modulos genericos de pandora son de 4 tipos
#
# generic_data . Almacena numeros enteros largos, util para monitorizar proceos que
#                                general valores o sensores que devuelven valores.

# generic_proc . Almacena informacion booleana (cierto/false), util para monitorizar
#                 procesos logicos.

# generic_data_inc . Almacena datos igual que generic_data pero tiene una logica
#                                que sirve para las fuentes de datos que alimentan el agente con datos
#                                que se incrementan continuamente, por ejemplo, los contadores de valores
#                                en las MIB de los adaptadores de red, las entradas de cierto tipo en
#                                un log o el nº de segundos que ha pasado desde X momento. Cuando el valor
#                                es mejor que el anterior o es 0, se gestiona adecuadamente el cambio.

# generic_data_string. Store a string, max 255 chars.

##########################################################################
## SUB pandora_accessupdate (pa_config, id_agent, dbh)
## Update agent access table
##########################################################################

sub pandora_accessupdate (%$$) {
	my $pa_config = $_[0];
	my $id_agent = $_[1];
	my $dbh = $_[2];
	
	my $intervalo = dame_intervalo($pa_config, $id_agent, $dbh);
	my $timestamp = &UnixDate("today","%Y-%m-%d %H:%M:%S");
	my $temp = $intervalo / 2;
	my $fecha_limite = DateCalc($timestamp,"- $temp seconds",\$err);
	$fecha_limite = &UnixDate($fecha_limite,"%Y-%m-%d %H:%M:%S");
	# Fecha limite has limit date, if there are records below this date
	# we cannot insert any data in Database. We use a limit based on agent_interval / 2
	# So if an agent has interval 300, could have a max of 24 records per hour in access_table
	# This is to do not saturate database with access records (because if you hace a network module with interval 30, you have
	# a new record each 30 seconds !
	# Compare with tagente.ultimo_contacto (tagent_lastcontact in english), so this will have
	# the latest update for this agent
	
	my $query = "select count(*) from tagent_access where id_agent = $id_agent and timestamp > '$fecha_limite'";
	my $query_exec = $dbh->prepare($query);
	my @data_row;
	$query_exec ->execute;
	@data_row = $query_exec->fetchrow_array();
	$temp = $data_row[0];
	$query_exec->finish();
	if ( $temp == 0) { # We need update access time
		my $query2 = "insert into tagent_access (id_agent, timestamp) VALUES ($id_agent,'$timestamp')";
		$dbh->do($query2);	
		logger($pa_config,"Updating tagent_access for agent id $id_agent",9);
	}
}

##########################################################################
## SUB module_generic_proc (param_1, param_2, param_3)
## Procesa datos genericos sobre un proceso
##########################################################################
## param_1 : Nombre de la estructura contenedora de datos (XML)
## paran_2 : Timestamp del paquete de datos
## param_3 : Agent name
## param_4 : Module Type

sub module_generic_proc (%$$$$$) {
	my $pa_config = $_[0];
	my $datos = $_[1];
	my $a_timestamp = $_[2];
	my $agent_name = $_[3];
	my $module_type = $_[4];
	my $dbh = $_[5];

	my $estado;
	# Leemos datos de la estructura
	my $a_datos = $datos->{data}->[0];

	if ((ref($a_datos) ne "HASH")){
		$a_datos = sprintf("%.2f", $a_datos);		# Two decimal float. We cannot store more
								# to change this, you need to change mysql structure
		$a_datos =~ s/\,/\./g; 				# replace "," by "." avoiding locale problems
		my $a_name = $datos->{name}->[0];
		my $a_desc = $datos->{description}->[0];
		my $a_max = $datos->{max}->[0];
		my $a_min = $datos->{min}->[0];

		if (ref($a_max) eq "HASH") {
			$a_max = "";
		}
		if (ref($a_min) eq "HASH") {
			$a_min = "";
		}
		
		pandora_writedata($pa_config, $a_timestamp,$agent_name,$module_type,$a_name,$a_datos,$a_max,$a_min,$a_desc,$dbh);
	
		# Check for status: <1 state 1 (Bad), >= 1 state 0 (Good)
		# Calculamos su estado
		if ( $datos->{'data'}->[0] < 1 ) { 
			$estado = 1;
		} else { 
			$estado=0;
		}
		pandora_writestate ($pa_config, $agent_name,$module_type,$a_name,$a_datos,$estado,$dbh);
	} else {
		logger ($pa_config, "Invalid data received from agent $agent_name",2);
	}
}

##########################################################################
## SUB module_generic_data (param_1, param_2,param_3, param_4)
## Process generated data form numeric data module acquire
##########################################################################
## param_1 : XML name
## paran_2 : Timestamp
## param_3 : Agent name
## param_4 : Module type

sub module_generic_data (%$$$$$) {
	my $pa_config = $_[0];
	my $datos = $_[1];
	my $m_timestamp = $_[2];
	my $agent_name = $_[3];
	my $module_type = $_[4];
	my $dbh = $_[5];

	# Leemos datos de la estructura
	my $m_name = $datos->{name}->[0];
	my $a_desc = $datos->{description}->[0];
	my $m_data = $datos->{data}->[0];
	
	if (ref($m_data) ne "HASH"){
		if ($m_data =~ /[0-9]*/){
			$m_data =~ s/\,/\./g; # replace "," by "."
			$m_data = sprintf("%.2f", $m_data);	# Two decimal float. We cannot store more
		} else {
			$m_data =0;
		}
		# to change this, you need to change mysql structure
		$m_data =~ s/\,/\./g; # replace "," by "."
		my $a_max = $datos->{max}->[0];
		my $a_min = $datos->{min}->[0];
	
		if (ref($a_max) eq "HASH") {
			$a_max = "";
		}
		if (ref($a_min) eq "HASH") {
			$a_min = "";
		}
		pandora_writedata($pa_config, $m_timestamp,$agent_name,$module_type,$m_name,$m_data,$a_max,$a_min,$a_desc,$dbh);
		# Numeric data has status N/A (100) always
		pandora_writestate ($pa_config, $agent_name,$module_type,$m_name,$m_data,100, $dbh);
	} else {
		logger($pa_config, "Invalid data value received from $agent_name, module $m_name",2);
	}
}

##########################################################################
## SUB module_generic_data_inc (param_1, param_2,param_3, param_4)
## Process generated data form incremental numeric data module acquire
##########################################################################
## param_1 : XML name
## paran_2 : Timestamp
## param_3 : Agent name
## param_4 : Module type
sub module_generic_data_inc (%$$$$$) {
	my $pa_config = $_[0];
	my $datos = $_[1];
	my $m_timestamp = $_[2];
	my $agent_name = $_[3];
	my $module_type = $_[4];
	my $dbh = $_[5];

	# Read structure data
	my $m_name = $datos->{name}->[0];
	my $a_desc = $datos->{description}->[0];
	my $m_data = $datos->{data}->[0];
	my $a_max = $datos->{max}->[0];
    	my $a_min = $datos->{min}->[0];

	if (ref($m_data) ne "HASH"){
		$m_data =~ s/\,/\./g; # replace "," by "."
		$m_data = sprintf("%.2f", $m_data);	# Two decimal float. We cannot store more
							# to change this, you need to change mysql structure
		$m_data =~ s/\,/\./g; # replace "," by "."
	
		if (ref($a_max) eq "HASH") {
			$a_max = "";
		}
		if (ref($a_min) eq "HASH") {
			$a_min = "";
		}	
		my $no_existe=0;
		my $timestamp = &UnixDate("today","%Y-%m-%d %H:%M:%S");
		# Algoritmo:
		# 1) Buscamos el valor anterior en la base de datos
		# 2) Si el dato nuevo es mayor o igual, guardamos en la tabla de datos general la diferencia y en la tabla de estado de datos incrementales, modificamos el valor por el actual.
		# 3) Si el dato nuevo es menor, guardamos el valor completo en la tabla de datos general y en la tabla de estado de datos incrementales, modificamos el valor por el actual.
		
		# Luego:
		# a) Obtener valor anterior, si no existe, el valor anterior sera 0
		# b) Comparar ambos valores (anterior y actual)
		# c) Actualizar tabla de estados de valores incrementales
		# d) Insertar valor en tabla de valores de datos generales
		
		# Obtemos los ID's a traves del paquete de datos
		my $id_agente = dame_agente_id($pa_config, $agent_name, $dbh);
		my $id_modulo = dame_modulo_id($pa_config, $module_type, $dbh); # Fixed type here, its OK, dont change !
		my $id_agente_modulo = dame_agente_modulo_id($pa_config,$id_agente,$id_modulo,$m_name,$dbh);
		my $query_idag = "select * from tagente_datos_inc where id_agente_modulo = $id_agente_modulo";
		# Take last real data from tagente_datos_inc
		# in this table, store the last real data, not the difference who its stored in tagente_datos table and 
		# tagente_estado table
		my $s_idag = $dbh->prepare($query_idag);
		my $diferencia; my @data_row; my $data_anterior;
		$s_idag ->execute;
		if ($s_idag->rows == 0) {
			$diferencia = 0;
			$no_existe = 1;
		} else {
			@data_row = $s_idag->fetchrow_array();
			$data_anterior = $data_row[2];
			$diferencia = $m_data - $data_anterior;
			if ($diferencia < 0){ # New value is lower than old value, resetting inc system
				my $query2 = "update tagente_datos_inc set datos = '$m_data' where id_agente_modulo  = $id_agente_modulo";
				my $queryexec = $dbh->prepare($query2);
				$queryexec->execute;
				$queryexec->finish();
				$diferencia=0;
			}
		}
		$s_idag->finish();
		# c) Actualizar tabla de estados de valores incrementales (se pone siempre el ultimo valor)
		
		# tagente_datos_inc stores real data, not incremental data
		if ($no_existe == 1){
			my $query = "insert into tagente_datos_inc (id_agente_modulo,datos,timestamp) VALUES ($id_agente_modulo,'$m_data','$timestamp')";
			$dbh->do($query);
		} else { # If exists, modfy
			if ($diferencia > 0) {
				my $query_idag = "update tagente_datos_inc set datos = '$m_data' where id_agente_modulo  = $id_agente_modulo";
				$s_idag = $dbh->prepare($query_idag);
				$s_idag ->execute;
				$s_idag->finish();
			}
		}
		my $nuevo_data = 0;
		if ($diferencia >= 0) {
			if ($no_existe==0) {
				$nuevo_data = $diferencia;
			}
		} else { # Si diferencia = 0 o menor (problemilla?)
			if ($no_existe !=0){
				# Houston, we have a Problem !
				logger($pa_config, "ERROR: Error inside data_inc algorithm, for Agent $agent_name and Type Generic_data_inc ",6);
			}
		}
		pandora_writedata($pa_config, $m_timestamp,$agent_name,$module_type,$m_name,$nuevo_data,$a_max,$a_min,$a_desc,$dbh);
	
		# Calculamos su estado (su estado siempre es bueno, jeje)
		# Inc status is always 100 (N/A)
		pandora_writestate ($pa_config, $agent_name,$module_type,$m_name,$nuevo_data,100,$dbh);
	} else {
		logger ($pa_config, "Invalid data received from $agent_name",2);
	}
}


##########################################################################
## SUB module_generic_data (param_1, param_2,param_3, param_4)
## Process generated data form alfanumeric data module acquire
##########################################################################
## param_1 : XML name
## paran_2 : Timestamp
## param_3 : Agent name
## param_4 : Module type

sub module_generic_data_string (%$$$$$) {
	my $pa_config = $_[0];
	my $datos = $_[1];
	my $m_timestamp = $_[2];
	my $agent_name = $_[3];
	my $module_type = $_[4];	
	my $dbh = $_[5];	

	# Read Structure
	my $m_name = $datos->{name}->[0];
	my $m_data = $datos->{data}->[0];
	my $a_desc = $datos->{description}->[0];
	my $a_max = $datos->{max}->[0];
        my $a_min = $datos->{min}->[0];
	if (ref($m_data) eq "HASH") {
    		$m_data = XMLout($m_data, RootName=>undef);
 	}
	if (ref($a_max) eq "HASH") {
                $a_max = "";
        }
        if (ref($a_min) eq "HASH") {
                $a_min = "";
        }
	pandora_writedata($pa_config, $m_timestamp,$agent_name,$module_type,$m_name,$m_data,$a_max,$a_min,$a_desc,$dbh);
    	# String type has no state (100 = N/A)
	pandora_writestate ($pa_config, $agent_name,$module_type,$m_name,$m_data,100,$dbh);
}


##########################################################################
## SUB pandora_writedata (pa_config, timestamp,nombre_agente,tipo_modulo,nombre_modulo,datos)
## Insert data in main table: tagente_datos
       
##########################################################################

sub pandora_writedata (%$$$$$$$$$) {
	my $pa_config = $_[0];
    	my $timestamp = $_[1];
        my $nombre_agente = $_[2];
        my $tipo_modulo = $_[3];
        my $nombre_modulo = $_[4];
        my $datos = $_[5];
        my $max = $_[6];
	my $min = $_[7];
	my $descripcion = $_[8];
	my $dbh = $_[9];
	my @data;


        # Obtenemos los identificadores
        my $id_agente = dame_agente_id($pa_config, $nombre_agente,$dbh);
        # Check if exists module and agent_module reference in DB, if not, and learn mode activated, insert module in DB
	if ($id_agente eq "-1"){
		goto fin_DB_insert_datos;
	}
        my $id_modulo = dame_modulo_id($pa_config, $tipo_modulo,$dbh);
        my $id_agente_modulo = dame_agente_modulo_id($pa_config, $id_agente, $id_modulo, $nombre_modulo,$dbh);

	my $needscreate = 0;

	# take max and min values for this id_agente_module
        if ($id_agente_modulo != -1){ # ID AgenteModulo does exists
		my $query_idag = "select * from tagente_modulo where id_agente_modulo = ".$id_agente_modulo;;
		my $s_idag = $dbh->prepare($query_idag);
		$s_idag ->execute;
		if ($s_idag->rows == 0) {
			logger( $pa_config, "ERROR Cannot find agenteModulo $id_agente_modulo",6);
			logger( $pa_config, "ERROR: SQL Query is $query_idag ",8);
		} else  {    @data = $s_idag->fetchrow_array(); }
		$max = $data[5];
		$min = $data[6];
		$s_idag->finish();
	} else { # Id AgenteModulo DOESNT exist, it could need to be created...
		if (dame_learnagente($pa_config, $id_agente,$dbh) eq "1"){
			# Try to write a module and agent_module definition for that datablock
			logger( $pa_config, "Pandora_insertdata will create module (learnmode) for agent $nombre_agente",4);
			crea_agente_modulo($pa_config, $nombre_agente, $tipo_modulo, $nombre_modulo, $max, $min, $descripcion,$dbh);
			$id_agente_modulo = dame_agente_modulo_id($pa_config, $id_agente, $id_modulo, $nombre_modulo,$dbh);
			$needscreate = 1; # Really needs to be created
		} else {
			logger( $pa_config, "VERBOSE: pandora_insertdata cannot find module definition ($nombre_modulo / $tipo_modulo )for agent $nombre_agente - Use LEARN MODE for autocreate.",2);
			goto fin_DB_insert_datos;
		}
	} # Module exists or has been created
	
	# Check old value for this data in tagente_data
	# if old value nonequal to new value, needs update
        my $query;
	my $needsupdate =0;
	
	$query = "select * from tagente_estado where id_agente_modulo = $id_agente_modulo";
       	my $sql_oldvalue = $dbh->prepare($query);
        $sql_oldvalue->execute;
        @data = $sql_oldvalue->fetchrow_array();
       	$sql_oldvalue = $dbh->prepare($query);
        $sql_oldvalue->execute;
    	if ($sql_oldvalue->rows != 0) {
        	@data = $sql_oldvalue->fetchrow_array();
		#$data[2] contains data
		if ($tipo_modulo =~ /string/){
			$datos = $datos; # No change
		} else { # Numeric change to real 
			$datos = sprintf("%.2f", $datos);
			$data[2] = sprintf("%.2f", $data[2]);
			# Two decimal float. We cannot store more
			# to change this, you need to change mysql structure
			$datos =~ s/\,/\./g; # replace "," by "."
			$data[2] =~ s/\,/\./g; # replace "," by "."
		}
		if ($data[2] ne $datos){
			$needsupdate=1;
			logger( $pa_config, "Updating data for $nombre_modulo after compare with tagente_data: new($datos) ne old($data[2])",10);
		} else {
			# Data in DB is the same, but could be older (more than 1 day )
			my $fecha_datos = $data[3];
			my $fecha_mysql = &UnixDate("today","%Y-%m-%d %H:%M:%S");      
			my $fecha_actual = ParseDate( $fecha_mysql );
			my $fecha_flag; my $err;
			my $fecha_limite = DateCalc($fecha_actual,"- 1 days",\$err);
			$fecha_flag = Date_Cmp($fecha_limite,$fecha_datos);
			if ($fecha_flag >= 0) { # write data, 
			logger( $pa_config, "Updating data for $nombre_modulo, data too ld in tagente_data",10);
				$needsupdate = 1;
			}
		}
    	} else {
		$needsupdate=1; # There aren't data
		logger( $pa_config, "Updating data for $nombre_modulo, because there are not data in DB ",10);

	}
	$sql_oldvalue->finish();

	if (($needscreate == 1) || ($needsupdate == 1)){
		my $outlimit = 0;
		if ($tipo_modulo =~ /string/) { # String module types
			$datos = $dbh->quote($datos); # Parse data entry for adecuate SQL representation.
			$query = "insert into tagente_datos_string (id_agente_modulo,datos,timestamp,id_agente) VALUES ($id_agente_modulo,$datos,'$timestamp',$id_agente)";	
		} else {
			if ($max != $min) {
				if ($datos > $max) { 
					$datos = $max; 
					$outlimit=1;
					logger($pa_config,"DEBUG: MAX Value reached ($max) for agent $nombre_agente / $nombre_modulo",2);
				}		
				if ($datos < $min) { 
					$datos = $min;
					$outlimit = 1;
					logger($pa_config, "DEBUG: MIN Value reached ($min) for agent $nombre_agente / $nombre_modulo",2);
				}
			}
			$query = "insert into tagente_datos (id_agente_modulo,datos,timestamp,id_agente) VALUES ($id_agente_modulo,$datos,'$timestamp',$id_agente)";
		} # If data is out of limits, do not insert into database (Thanks for David Villanueva for his words)
		if ($outlimit == 0){
			logger($pa_config, "DEBUG: pandora_insertdata Calculado id_agente_modulo a $id_agente_modulo",3);
			logger($pa_config, "DEBUG: pandora_insertdata SQL : $query",4);
			$dbh->do($query);
		}
	}
fin_DB_insert_datos:
}

##########################################################################
## SUB pandora_serverkeepalive (pa_config, status, dbh)
## Update server status
##########################################################################
sub pandora_serverkeepaliver (%$) {
        my $pa_config= $_[0];
	my $opmode = $_[1]; # 0 dataserver, 1 network server, 2 snmp console
	my $dbh = $_[2];
	my $pandorasuffix;
	my @data;

	if ($pa_config->{"keepalive"} <= 0){
		my $timestamp = &UnixDate("today","%Y-%m-%d %H:%M:%S");
		my $temp = $pa_config->{"keepalive_orig"} * 2; # Down if keepalive x 2 seconds unknown
		my $fecha_limite = DateCalc($timestamp,"- $temp seconds",\$err);
		$fecha_limite = &UnixDate($fecha_limite,"%Y-%m-%d %H:%M:%S");		
		# Look updated servers and take down non updated servers
		my $query_idag = "select * from tserver where keepalive < '$fecha_limite'";
		my $s_idag = $dbh->prepare($query_idag);
		$s_idag ->execute;
		if ($s_idag->rows != 0) {
			while (@data = $s_idag->fetchrow_array()){
				if ($data[3] != 0){ # only if it's currently not down
					# Update server data
					my $sql_update = "update tserver set status = 0 where id_server = $data[0]";
					$dbh->do($sql_update);
					pandora_event($pa_config, "Server ".$data[1]." going Down", 0, 0, $dbh);
					logger( $pa_config, "Server ".$data[1]." going Down ",1);
				}
			}
		}
		$s_idag->finish();
		# Update my server
		pandora_updateserver ($pa_config,$pa_config->{'servername'},1,$opmode, $dbh);	
		$pa_config->{"keepalive"}=$pa_config->{"keepalive_orig"};
	}
	$pa_config->{"keepalive"}=$pa_config->{"keepalive"}-$pa_config->{"server_threshold"};
}


##########################################################################
## SUB pandora_updateserver (pa_config, status, dbh)
## Update server status
##########################################################################
sub pandora_updateserver (%$$$) {
    my $pa_config= $_[0];
	my $servername = $_[1];
	my $status = $_[2];
	my $opmode = $_[3]; # 0 dataserver, 1 network server, 2 snmp console
	my $dbh = $_[4];
	my $sql_update;
	my $pandorasuffix;
	if ($opmode == 0){
		$pandorasuffix = "_Data";
	} elsif ($opmode == 1){
		$pandorasuffix = "_Net";
	} else {
		$pandorasuffix = "_SNMP";
	}
	my $id_server = dame_server_id($pa_config, $servername.$pandorasuffix, $dbh);
	if ($id_server == -1){ 
		# Must create a server entry
		my $sql_server = "insert into tserver (name,description) values ('$servername".$pandorasuffix."','Autocreated at startup')";
		$dbh->do($sql_server);
		$id_server = dame_server_id($pa_config, $pa_config->{'servername'}.$pandorasuffix, $dbh);
	}
	my @data;
	my $query_idag = "select * from tserver where id_server = $id_server";
	my $s_idag = $dbh->prepare($query_idag);
	$s_idag ->execute;
	if ($s_idag->rows != 0) {
		if (@data = $s_idag->fetchrow_array()){
			if ($data[3] == 0){ # If down, update to get up the server
				pandora_event($pa_config, "Server ".$data[1]." going UP", 0, 0, $dbh);
				logger( $pa_config, "Server ".$data[1]." going UP ",1);
			}
			# Update server data
			my $timestamp = &UnixDate("today","%Y-%m-%d %H:%M:%S");
			if ($opmode == 0){
				$sql_update = "update tserver set status = 1, laststart = '$timestamp', keepalive = '$timestamp', snmp_server = 0, network_server = 0, data_server = 1, master = $pa_config->{'pandora_master'}, checksum = $pa_config->{'pandora_check'} where id_server = $id_server";
			} elsif ($opmode == 1){
				$sql_update = "update tserver set status = 1, laststart = '$timestamp', keepalive = '$timestamp', snmp_server = 0, network_server = 1, data_server = 0, master = $pa_config->{'pandora_master'}, checksum = 0 where id_server = $id_server";
			} else {
				$sql_update = "update tserver set status = 1, laststart = '$timestamp', keepalive = '$timestamp', snmp_server = 1, network_server = 0, data_server = 0, master = $pa_config->{'pandora_master'}, checksum = 0 where id_server = $id_server";
			}
			$dbh->do($sql_update);
		}
		$s_idag->finish();
	}
}

##########################################################################
## SUB pandora_lastagentcontact (pa_config, timestamp,nombre_agente,os_data, agent_version,interval,dbh)
## Update last contact field in Agent Table
##########################################################################

sub pandora_lastagentcontact (%$$$$$$) {
        my $pa_config= $_[0];
        my $timestamp = $_[1];
        my $time_now = &UnixDate("today","%Y-%m-%d %H:%M:%S");
        my $nombre_agente = $_[2];
        my $os_data = $_[3];
        my $agent_version = $_[4];
        my $interval = $_[5];
	my $dbh = $_[6];

        my $id_agente = dame_agente_id($pa_config, $nombre_agente,$dbh);
	pandora_accessupdate ($pa_config, $id_agente, $dbh);
        my $query = ""; 
        if ($interval == -1){ # no update for interval field (some old agents doest support it) 
		$query = "update tagente set agent_version = '$agent_version', ultimo_contacto_remoto = '$timestamp', ultimo_contacto = '$time_now', os_version = '$os_data' where id_agente = $id_agente";                	
        } else {
		$query = "update tagente set intervalo = $interval, agent_version = '$agent_version', ultimo_contacto_remoto = '$timestamp', ultimo_contacto = '$time_now', os_version = '$os_data' where id_agente = $id_agente";
        }
        logger( $pa_config, "pandora_lastagentcontact: Updating Agent last contact data for $nombre_agente",6);
	logger( $pa_config, "pandora_lastagentcontact: SQL Query: ".$query,10);
        my $sag = $dbh->prepare($query);
        $sag ->execute;
    	$sag ->finish();
}

##########################################################################
## SUB pandora_event (pa_config, evento, id_grupo, id_agente)
## Write in internal audit system an entry.
##########################################################################

sub pandora_event (%$$$$) {
	my $pa_config = $_[0];
        my $evento = $_[1];
        my $id_grupo = $_[2];
        my $id_agente = $_[3];
	my $dbh = $_[4];
	
        my $timestamp = &UnixDate("today","%Y-%m-%d %H:%M:%S");
        $evento = $dbh->quote($evento);
       	$timestamp = $dbh->quote($timestamp);
	my $query = "insert into tevento (id_agente, id_grupo, evento, timestamp, estado) VALUES ($id_agente,$id_grupo,$evento,$timestamp,0)";
	logger ($pa_config,"EVENT Insertion: $query",5);
        $dbh->do($query);	
}

##########################################################################
## SUB pandora_audit (pa_config, escription, name, action, pandora_dbcfg_hash)
## Write in internal audit system an entry.
##########################################################################
sub pandora_audit (%$$$$) {
	my $pa_config = $_[0];
        my $desc = $_[1];
        my $name = $_[2];
        my $action = $_[3];
	my $dbh = $_[4];
	my $local_dbh =0;

	# In startup audit, DBH not passed
	if (! defined($dbh)){
		$local_dbh = 1;
		$dbh = DBI->connect("DBI:mysql:pandora:$pa_config->{'dbhost'}:3306", $pa_config->{'dbuser'}, $pa_config->{'dbpass'}, { RaiseError => 1, AutoCommit => 1 });
	}
        my $timestamp = &UnixDate("today","%Y-%m-%d %H:%M:%S");

        my $query = "insert into tsesion (ID_usuario, IP_origen, accion, fecha, descripcion) values ('SYSTEM','".$name."','".$action."','".$timestamp."','".$desc."')";
	eval { # Check for problems in Database, if cannot audit, break execution
        	$dbh->do($query);
	};
	if ($@){
		logger ($pa_config,"FATAL: pandora_audit() cannot connect with database",0);
		logger ($pa_config,"FATAL: Error code $@",2);
	}
	if ($local_dbh == 1){
		$dbh->disconnect();
	}
}

##########################################################################
## SUB dame_agente_id (nombre_agente)
## Return agent ID, use "nombre_agente" as name of agent.
##########################################################################
sub dame_agente_id (%$$) {
	my $pa_config = $_[0];
        my $nombre_agente = $_[1];
	my $dbh = $_[2];

        my $id_agente;my @data;
	if (defined($nombre_agente)){
		# Calculate agent ID using select by its name
		my $query_idag = "select * from tagente where nombre = '$nombre_agente'";
		my $s_idag = $dbh->prepare($query_idag);
		$s_idag ->execute;
		if ($s_idag->rows == 0) {
			logger ($pa_config, "ERROR dame_agente_id(): Cannot find agent called $nombre_agente. Returning -1",1);
			logger ($pa_config, "ERROR: SQL Query is $query_idag ",2);
			$data[0]=-1;
		} else  {           @data = $s_idag->fetchrow_array();   }
		$id_agente = $data[0];
		$s_idag->finish();
		return $id_agente;
	} else {
		return -1; 
 	}
}

##########################################################################
## SUB dame_server_id (pa_config, servername, dbh)
## Return serverID, using "nane" as name of server
##########################################################################
sub dame_server_id (%$$) {
	my $pa_config = $_[0];
        my $name = $_[1];
	my $dbh = $_[2];

        my $id_server;my @data;
        # Get serverid
        my $query_idag = "select * from tserver where name = '$name'";
       	my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger ($pa_config, "ERROR dame_server_id(): Cannot find server called $name. Returning -1",10);
        	logger ($pa_config, "ERROR: SQL Query is $query_idag ",10);
		$data[0]=-1;
    	} else  {           @data = $s_idag->fetchrow_array();   }
    	$id_server = $data[0];
    	$s_idag->finish();
        return $id_server;
}

##########################################################################
## SUB give_networkserver_status (id_server) 
## Return NETWORK server status given its id
##########################################################################

sub give_networkserver_status (%$$) {
	my $pa_config = $_[0];
	my $id_server = $_[1];
	my $dbh = $_[2];

	my $status;
	my @data;
        my $query_idag = "select * from tserver where id_server = $id_server and network_server = 1";
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	$status = -1;
    	} else  {
		@data = $s_idag->fetchrow_array();   
		$status = $data[3];
	}
    	$s_idag->finish();
       	return $status;
}

##########################################################################
## SUB dame_grupo_agente (id_agente) 
## Return id_group of an agent given its id
##########################################################################

sub dame_grupo_agente (%$$) {
	my $pa_config = $_[0];
	my $id_agente = $_[1];
	my $dbh = $_[2];

	my $id_grupo;
	my @data;
	# Calculate agent using select by its id
        my $query_idag = "select * from tagente where id_agente = $id_agente";
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger ($pa_config, "ERROR dame_grupo_agente(): Cannot find agent with id $id_agente",1);
        	logger ($pa_config, "ERROR: SQL Query is $query_idag ",2);
    	} else  {           @data = $s_idag->fetchrow_array();   }
 	$id_grupo = $data[4];
    	$s_idag->finish();
       	return $id_grupo;
}

##########################################################################
## SUB dame_comando_alerta (id_alerta)
## Return agent ID, use "nombre_agente" as name of agent.
##########################################################################
sub dame_comando_alerta (%$$) {
	my $pa_config = $_[0];
        my $id_alerta = $_[1];
	my $dbh = $_[2];

	my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from talerta where id_alerta = $id_alerta";
        my $s_idag = $dbh->prepare($query_idag);
	my $comando = "";
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger ($pa_config, "ERROR dame_comando_alerta(): Cannot find alert $id_alerta",1);
        	logger ($pa_config, "ERROR: SQL Query is $query_idag ",2);
    	} else  {           
		@data = $s_idag->fetchrow_array();   
    		$comando = $data[2];
	}
    	$s_idag->finish();
    	return $comando;
}


##########################################################################
## SUB dame_agente_nombre (id_agente)
## Return agent name, given "id_agente"
##########################################################################
sub dame_agente_nombre (%$$) {
	my $pa_config = $_[0];
        my $id_agente = $_[1];
	my $dbh = $_[2];
        
	my $nombre_agente;
	my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from tagente where id_agente = '$id_agente'";
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_agente_nombre(): Cannot find agent with id $id_agente",1);
        	logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
    	} else  {           @data = $s_idag->fetchrow_array();   }
    	$nombre_agente = $data[1];
    	$s_idag->finish();
        return $nombre_agente;
}


##########################################################################
## SUB dame_modulo_id (nombre_modulo)
## Return module ID, given "nombre_modulo" as module name
##########################################################################
sub dame_modulo_id (%$$) {
	my $pa_config = $_[0];
        my $nombre_modulo = $_[1];
	my $dbh = $_[2];

        my $id_modulo; my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from ttipo_modulo where nombre = '$nombre_modulo'";
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_modulo_id(): Cannot find module called $nombre_modulo ",1);
        	logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
        	$id_modulo = -1;
    	} else  {    
    		@data = $s_idag->fetchrow_array();
    		$id_modulo = $data[0];
    	}
    	$s_idag->finish();
    	return $id_modulo;
}


##########################################################################
## SUB dame_agente_modulo_id (id_agente, id_tipomodulo, nombre)
## Return agente_modulo ID, from tabla tagente_modulo, given id_agente, id_tipomodulo and name
##########################################################################
sub dame_agente_modulo_id (%$$$$) {
	my $pa_config = $_[0];
        my $id_agente = $_[1];
        my $id_tipomodulo = $_[2];
        my $nombre = $_[3];
	my $dbh = $_[4];
        my $id_agentemodulo;
	my @data;
	
        # Calculate agent ID using select by its name
        my $query_idag = "select * from tagente_modulo where id_agente = '$id_agente' and id_tipo_modulo = '$id_tipomodulo' and nombre = '$nombre'";
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_agente_modulo_id(): Cannot find agente_modulo called $nombre",2);
        	logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
        	$id_agentemodulo = -1;
    	} else  {    
    		@data = $s_idag->fetchrow_array(); 
    		$id_agentemodulo = $data[0];
    	}
    	$s_idag->finish();
    	return $id_agentemodulo;
}

##########################################################################
## SUB dame_nombreagente_agentemodulo (id_agente_modulo)
## Return agent name diven id_agente_modulo
##########################################################################
sub dame_nombreagente_agentemodulo (%$$) {
	my $pa_config = $_[0];
        my $id_agentemodulo = $_[1];
	my $dbh = $_[2];

        my $id_agente; my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from tagente_modulo where id_agente_modulo = ".$id_agentemodulo;
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_nombreagente_agentemodulo(): Cannot find id_agente_modulo $id_agentemodulo",1);
        	logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
		$id_agente = -1;
    	} else  {   
		@data = $s_idag->fetchrow_array(); 
		$id_agente= $data[1];
	}
    	
    	$s_idag->finish();
    	my $nombre_agente = dame_agente_nombre ($pa_config, $id_agente,$dbh);
    	return $nombre_agente;
}

##########################################################################
## SUB dame_nombretipomodulo_idtipomodulo (id_tipo_modulo)
## Return name of moduletype given id_tipo_modulo
##########################################################################
sub dame_nombretipomodulo_idagentemodulo (%$$) {
	my $pa_config = $_[0];
	my $id_tipomodulo = $_[1]; 
	my $dbh = $_[2];
	
	my @data;
	# Calculate agent ID using select by its name
	my $query_idag = "select * from ttipo_modulo where id_tipo = ".$id_tipomodulo;
	my $s_idag = $dbh->prepare($query_idag);
	$s_idag ->execute;
	if ($s_idag->rows == 0) {
		logger( $pa_config, "ERROR dame_nombreagente_agentemodulo(): Cannot find module type with ID $id_tipomodulo",1);
		logger( $pa_config, "ERROR: SQL Query is $query_idag ",2);
	} else  {    @data = $s_idag->fetchrow_array(); }
	my $tipo= $data[1];
	$s_idag->finish();
	return $tipo;
}

##########################################################################
## SUB dame_learnagente (id_agente)
## Return 1 if agent is in learn mode, 0 if not
##########################################################################
sub dame_learnagente (%$$) {
	my $pa_config = $_[0];
        my $id_agente = $_[1];
	my $dbh = $_[2];
	my @data;
        
        # Calculate agent ID using select by its name
        my $query = "select * from tagente where id_agente = ".$id_agente;
        my $s_idag = $dbh->prepare($query);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger( $pa_config, "ERROR dame_learnagente(): Cannot find agente $id_agente",2);
      		logger( $pa_config, "ERROR: SQL Query is $query ",2);
    	} else  {    @data = $s_idag->fetchrow_array(); }
    	my $learn= $data[6];
    	$s_idag->finish();
        return $learn;
}


##########################################################################
## SUB dame_id_tipo_modulo (id_agente_modulo)
## Return id_tipo of module with id_agente_modulo
##########################################################################
sub dame_id_tipo_modulo (%$$) {
	my $pa_config = $_[0];
        my $id_agente_modulo = $_[1];
	my $dbh = $_[2];

        my $tipo; my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from tagente_modulo where id_agente_modulo = ".$id_agente_modulo;
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_id_tipo_modulo(): Cannot find id_agente_modulo $id_agente_modulo",1);
      		logger($pa_config,  "ERROR: SQL Query is $query_idag ",2);
		$tipo ="-1";
    	} else  {    
		@data = $s_idag->fetchrow_array(); 
		$tipo= $data[2];
	}
    	$s_idag->finish();
        return $tipo;
}

##########################################################################
## SUB dame_intervalo (id_agente)
## Return interval for id_agente
##########################################################################
sub dame_intervalo (%$$) {
	my $pa_config = $_[0];
        my $id_agente = $_[1];
	my $dbh = $_[2];

        my $tipo; my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from tagente where id_agente = ".$id_agente;
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_intervalo(): Cannot find agente $id_agente",1);
      		logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
		$tipo = 0;
    	} else  {    @data = $s_idag->fetchrow_array(); }
    	$tipo= $data[7];
    	$s_idag->finish();
        return $tipo;
}

##########################################################################
## SUB dame_desactivado (id_agente)
## Return disabled = 1 if disabled, 0 if not disabled
##########################################################################
sub dame_desactivado (%$$) {
	my $pa_config = $_[0];
        my $id_agente = $_[1];
	my $dbh = $_[2];
	my $desactivado;

        my $tipo; my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from tagente where id_agente = ".$id_agente;
	my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_desactivado(): Cannot find agente $id_agente",1);
      	 	logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
		$desactivado = -1;
    	} else  {    
		@data = $s_idag->fetchrow_array(); 
		$desactivado= $data[12];
		}

    	$s_idag->finish();
        return $desactivado;
}

##########################################################################
## SUB dame_ultimo_contacto (id_agente)
## Return last_contact for id_agente
##########################################################################
sub dame_ultimo_contacto (%$$) {
	my $pa_config = $_[0];
        my $id_agente = $_[1];
	my $dbh = $_[2];

        my $tipo; my @data;
        # Calculate agent ID using select by its name
        my $query_idag = "select * from tagente where id_agente = ".$id_agente;
        my $s_idag = $dbh->prepare($query_idag);
        $s_idag ->execute;
    	if ($s_idag->rows == 0) {
        	logger($pa_config, "ERROR dame_ultimo_contacto(): Cannot find agente $id_agente",1);
      	 	logger($pa_config, "ERROR: SQL Query is $query_idag ",2);
    	} else  {    @data = $s_idag->fetchrow_array(); }
    	$tipo= $data[5];
    	$s_idag->finish();
        return $tipo;
}

##########################################################################
## SUB crea_agente_modulo(nombre_agente, nombre_tipo_modulo, nombre_modulo)
## create an entry in tagente_modulo
##########################################################################
sub crea_agente_modulo (%$$$$$$$) {
	my $pa_config = $_[0];
	my $nombre_agente = $_[1];
	my $tipo_modulo = $_[2];
	my $nombre_modulo = $_[3];
	my $max = $_[4];
	my $min = $_[5];
	my $descripcion = $_[6];
	my $dbh = $_[7];

	my $modulo_id = dame_modulo_id($pa_config, $tipo_modulo,$dbh);
	my $agente_id = dame_agente_id($pa_config, $nombre_agente,$dbh);
	
	if ((!defined($max)) || ($max eq "")){
		$max =0;
	}
	
	if ((!defined($min)) || ($min eq "")){
		$min =0;
	}

	if ((!defined($descripcion)) || ($descripcion eq "")){
		$descripcion="N/A";
	}

	my $query = "insert into tagente_modulo (id_agente,id_tipo_modulo,nombre,max,min,descripcion) values ($agente_id,$modulo_id,'$nombre_modulo',$max,$min,'$descripcion (*)')";
	if (($max eq "") and ($min eq "")) {
		$query = "insert into tagente_modulo (id_agente,id_tipo_modulo,nombre,descripcion) values ($agente_id,$modulo_id,'$nombre_modulo','$descripcion (*)')";
	} elsif ($min eq "") {
		$query = "insert into tagente_modulo (id_agente,id_tipo_modulo,nombre,max,descripcion) values ($agente_id,$modulo_id,'$nombre_modulo',$max,'$descripcion (*)')";
	} elsif ($min eq "") {
		$query = "insert into tagente_modulo (id_agente,id_tipo_modulo,nombre,min,descripcion) values 	($agente_id,$modulo_id,'$nombre_modulo',$min,'$descripcion (*)')";
	}
	logger( $pa_config, "DEBUG: Query for autocreate : $query ",3);	
    	$dbh->do($query);
}


# End of function declaration
# End of defined Code

1;
__END__

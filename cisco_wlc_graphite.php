<?php
/**
 * Get SNMP stats from Cisco WLC and insert into Graphite/Carbon.
 *
 * @author: Arjan Koopen <arjan@koopen.net>
 */

include("config.php");
include("common.php");

if ($graphite_send) $fsock = fsockopen($graphite_ip, $graphite_port);

foreach ($controllers as $c_name => $ip) {
	/**
 	 * Clients per radio
 	 */

	$aps = getApTable();
	$radios = getRadioTable($aps);
	$clientsPerRadio = getNoOfClientsPerRadio($radios);
	$clientsPerRadio["bogus.bogus"] = 0;

	$total = 0;
	$assoc_band = array();

	$prev_ap = "";
	$prev_no = 0;
	foreach ($clientsPerRadio as $radio => $no) {
		$tmp = explode(".",$radio);

		if ($prev_ap != "" && $prev_ap != $tmp[0]) {
			sendGraphite("assoc.ap." . $prev_ap . ".total", $prev_no);
			$prev_no = 0;
		}

		if ($tmp[0] != "bogus") {
			$total += $no;
			$assoc_band[$tmp[1]] += $no;
			$prev_no += $no;

			sendGraphite("assoc.ap." . $radio , $no);
			$prev_ap = $tmp[0];
		}
	}
	
	sendGraphite("assoc.total", $total);

	foreach ($assoc_band as $band => $no) {
		sendGraphite("assoc.band." . $band, $no);
	}

	/**
 	 * Clients per ESS
 	 */
	$ess = getEssTable();

	$clientsPerEss = getClientsPerEss($ess);

	foreach ($clientsPerEss as $ess => $no) {
		sendGraphite("assoc.ess." . $ess, $no);
	}

	/**
 	 * Radio info
 	 */

	// channel info
	$chan = getChannelPerRadio($radios);
	foreach ($chan as $radio => $ch) {
		sendGraphite("radio." . $radio . ".channel", $ch);
	}

	// util
	$util = getUtilPerRadio($radios);
	foreach ($util as $radio => $ut) {
		sendGraphite("radio." . $radio . ".util", $ut);
	}

	// noise
	$noise = getNoisePerRadio($radios, $chan);
	foreach ($noise as $radio => $ns) {
		sendGraphite("radio." . $radio . ".noise", $ns);
	}
	
	// counters
	$counters = getRadioCounters($radios);
	foreach ($counters as $radio => $cnt) {
		foreach ($cnt as $field => $value) {
			sendGraphite("radio." . $radio . "." . $field, $value);
		}
	}
}

if ($graphite_send) fclose($fsock);
?>
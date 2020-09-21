<?php
require_once 'vendor/autoload.php';
require_once 'utils.php';

$logFile = __DIR__ . '/vendor/skymeyer/vatsimphp/app/logs/pilots.log';
$Db = new PDO('sqlite:resources/airports.db');
$sqlLookup = "SELECT name,elevation,lat,lon from airports where icao=:icao";
$vatsim = new \Vatsimphp\VatsimData();
//$vatsim->setConfig('cacheOnly', true);
//$vatsim->setConfig('logFile', $logFile);

if ($vatsim->loadData()) {
    $pilots = $vatsim->getPilots()->toArray();
    $result = array();

    foreach ($pilots as $pilot) {
        //Destination airport data
        $airport = getAirportData($pilot["planned_destairport"]);
        if ($airport) {
            $pilot["dest_elevation"] = number_format($airport["elevation"], 0, ",", ".");
            $pilot["dest_name"] = $airport["name"];
            $pilot["dest_lat"] = $airport["lat"];
            $pilot["dest_lon"] = $airport["lon"];
            $pilot["dest_distance"] = number_format((int) distance(
                            $pilot["latitude"],
                            $pilot["longitude"],
                            $airport["lat"],
                            $airport["lon"],
                            "N"
                    ), 0, ",", ".");
            $date_utc = new \DateTime("now", new \DateTimeZone("UTC"));
            if ((int) $pilot["groundspeed"] <> 0) {
                $minutesToDest = (intval(str_replace('.', '', $pilot["dest_distance"])) / (int) $pilot["groundspeed"]) * 60;
                $pilot["ETA"] = $date_utc->modify("+" . (int) $minutesToDest . " minutes")->format("H:i");
            } else {
                $minutesToDest = 0;
                $pilot["ETA"] = "00:00";
            }
            $pilot["minutesToDest"] = $minutesToDest;
        } else {
            $pilot["dest_elevation"] = 0;
            $pilot["dest_name"] = "N/A";
            $pilot["dest_lat"] = 0;
            $pilot["dest_lon"] = 0;
            $pilot["dest_distance"] = 0;
            $pilot["minutesToDest"] = 0;
        }
        //Departure airport data
        $airport = getAirportData($pilot["planned_depairport"]);
        if ($airport) {
            $pilot["dep_elevation"] = number_format($airport["elevation"], 0, ",", ".");
            $pilot["dep_name"] = $airport["name"];
            $pilot["dep_lat"] = $airport["lat"];
            $pilot["dep_lon"] = $airport["lon"];
            $pilot["dep_distance"] = number_format((int) distance(
                            $pilot["latitude"],
                            $pilot["longitude"],
                            $airport["lat"],
                            $airport["lon"],
                            "N"
                    ), 0, ",", ".");
        } else {
            $pilot["dep_elevation"] = 0;
            $pilot["dep_name"] = "N/A";
            $pilot["dep_lat"] = 0;
            $pilot["dep_lon"] = 0;
            $pilot["dep_distance"] = 0;
        }
        //Replace ETA with Status
        if ($pilot["groundspeed"] < 40 and intval(str_replace('.', '', $pilot["dep_distance"])) < 10) {
            $pilot["ETA"] = "DEPARTING";
        }
        if ($pilot["groundspeed"] < 40 and intval(str_replace('.', '', $pilot["dest_distance"])) < 10) {
            $pilot["ETA"] = "ARRIVED";
        }
        $pilot["altitude"] = number_format($pilot["altitude"], 0, ",", ".");
        array_push($result, $pilot);
    }
    $departures = array_values(array_filter($result, "filterDEP"));
    $arrivals = array_values(array_filter($result, "filterARR"));
    usort($arrivals, "sortByDistanceDest");
    usort($departures, "sortByDistanceDep");
    $result = array_merge($arrivals, $departures);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(array_values(array_filter($result, "filterVAT")));
} else {
    echo json_encode("Data could not be loaded");
}

function sortByDistanceDest($a, $b) {
    return intval(str_replace('.', '', $a["dest_distance"])) - intval(str_replace('.', '', $b["dest_distance"]));
}

function sortByDistanceDep($a, $b) {
    return intval(str_replace('.', '', $a["dep_distance"])) - intval(str_replace('.', '', $b["dep_distance"]));
}

function getAirportData($param) {
    global $Db, $sqlLookup;
    $sqlStatement = $Db->prepare($sqlLookup);
    $sqlStatement->bindValue(':icao', $param);
    $sqlStatement->execute();
    return $airport = $sqlStatement->fetch(\PDO::FETCH_ASSOC);
}

function filterDEP($array) {
    if (
            ($array["planned_depairport"] === strtoupper(filter_input(INPUT_GET, "icao")) &&
            $array["clienttype"] === "PILOT")
    ) {
        return TRUE;
    } else {
        return FALSE;
    }
}

function filterARR($array) {
    if (
            ($array["planned_destairport"] === strtoupper(filter_input(INPUT_GET, "icao")) &&
            $array["clienttype"] === "PILOT")
    ) {
        return TRUE;
    } else {
        return FALSE;
    }
}

function filterVat($array) {
    if (
            ($array["planned_depairport"] === strtoupper(filter_input(INPUT_GET, "icao")) ||
            $array["planned_destairport"] === strtoupper(filter_input(INPUT_GET, "icao"))) &&
            $array["clienttype"] === "PILOT"
    ) {
        return TRUE;
    } else {
        return FALSE;
    }
}

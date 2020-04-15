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
            $pilot["dest_elevation"] = $airport["elevation"];
            $pilot["dest_name"] = $airport["name"];
            $pilot["dest_lat"] = $airport["lat"];
            $pilot["dest_lon"] = $airport["lon"];
            $pilot["dest_distance"] = (int)distance(
                    $pilot["latitude"],
                    $pilot["longitude"],
                    $airport["lat"],
                    $airport["lon"],
                    "N"
            );
            $date_utc = new \DateTime("now", new \DateTimeZone("UTC"));
            if ((int) $pilot["groundspeed"] <> 0) {
                $minutesToDest = (int)((int) $pilot["dest_distance"] / (int) $pilot["groundspeed"]) * 60;
                $pilot["ETA"] = $date_utc->modify("+" . (int)$minutesToDest . " minutes")->format("H:i");
            } else {
                $minutesToDest = 0;
                $pilot["ETA"] = "00:00";
            }
            //Replace ETA with Status
            if($pilot["groundspeed"] < 30 and $pilot["dest_distance"] > 10 ){
                $pilot["ETA"] = "DEPARTING";
            }
            if($pilot["groundspeed"] < 30 and $pilot["dest_distance"] < 10 ){
                $pilot["ETA"] = "ARRIVED";
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
            $pilot["dep_elevation"] = $airport["elevation"];
            $pilot["dep_name"] = $airport["name"];
            $pilot["dep_lat"] = $airport["lat"];
            $pilot["dep_lon"] = $airport["lon"];
            $pilot["dep_distance"] = (int)distance(
                    $pilot["latitude"],
                    $pilot["longitude"],
                    $airport["lat"],
                    $airport["lon"],
                    "N"
            );
        } else {
            $pilot["dep_elevation"] = 0;
            $pilot["dep_name"] = "N/A";
            $pilot["dep_lat"] = 0;
            $pilot["dep_lon"] = 0;
            $pilot["dep_distance"] = 0;
        }
        array_push($result, $pilot);
    }
    header('Content-Type: application/json');
    echo json_encode(array_values(array_filter($result, "filterVAT")));
} else {
    echo json_encode("Data could not be loaded");
}

function getAirportData($param) {
    global $Db, $sqlLookup;
    $sqlStatement = $Db->prepare($sqlLookup);
    $sqlStatement->bindValue(':icao', $param);
    $sqlStatement->execute();
    return $airport = $sqlStatement->fetch(\PDO::FETCH_ASSOC);
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

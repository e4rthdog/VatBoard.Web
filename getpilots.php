<?php

require_once 'vendor/autoload.php';

$logFile = __DIR__ . '/vendor/skymeyer/vatsimphp/app/logs/pilots.log';

$vatsim = new \Vatsimphp\VatsimData();
//$vatsim->setConfig('cacheOnly', true);
//$vatsim->setConfig('logFile', $logFile);

if ($vatsim->loadData()) {
    $pilots = $vatsim->getPilots();
    header('Content-Type: application/json');
    echo json_encode(array_values(array_filter($pilots->toArray(), "filterVAT")));
} else {
    echo json_encode("Data could not be loaded");
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

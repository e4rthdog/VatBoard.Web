<?php

$Db = new SQLite3('sqlite:resources/airports.db');
$data = file_get_contents('resources/airport-codes_json.json');
$airports = json_decode($data);
$result = $Db->query("DELETE from airports");
$sqlInsert = "INSERT INTO airports values(:icao,:name,:elevation,:coordinates,:lat,:lon)";


foreach ($airports as $airport) {
//    if (substr($airport->ident, 0, 2) === "LG") {
        $sqlStatement = $Db->prepare($sqlInsert);
        if (empty($airport->elevation_ft)){
            $airport->elevation_ft = 0;
        }
        $coordinates = explode(",",$airport->coordinates);
        $input_parameters = [
            ':icao' => $airport->ident,
            ':name' => $airport->name,
            ':elevation' => $airport->elevation_ft,
            ':coordinates' => $airport->coordinates,
            ':lat' => $coordinates[1],
            ':lon' => $coordinates[0]
        ];
        $sqlStatement->execute($input_parameters);
        echo $Db->lastInsertId();
//    }
}

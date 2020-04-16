<?php

use GitWrapper\GitWrapper;
require_once 'vendor/autoload.php';
$gitWrapper = new GitWrapper();
header('Content-Type: application/json');
echo json_encode($gitWrapper->git("describe --long"));


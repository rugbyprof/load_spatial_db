<?php

require_once('geoJsonParser.php');

$testfile = './tl_2013_01_prisecroads.json';

$listener = new GeoJsonParser();
$stream = fopen($testfile, 'r');
try {
    $parser = new Parser($stream, $listener);
    $parser->parse();
} catch (Exception $e) {
    fclose($stream);
    throw $e;
}

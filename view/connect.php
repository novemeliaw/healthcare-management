<?php
require '../vendor/autoload.php';

use Laudis\Neo4j\ClientBuilder;

// MongoDB
$client = new MongoDB\Client("mongodb://localhost:27017");
$database = $client->proyek;
$dokterCollection = $database->dokter;
$igdCollection = $database->igd;
$pasienCollection = $database->pasien;
$riCollection = $database->ri;
$rjCollection = $database->rj;


$clientNeo = ClientBuilder::create()
    ->withDriver('default', 'bolt://neo4j:password@localhost:7687')
    ->build();

$servername = "localhost";
$username = "root";
$password = "root";
$dbname = "proyek";

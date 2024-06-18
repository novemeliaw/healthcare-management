<?php
require 'vendor/autoload.php';
use MongoDB\Client as MongoClient;
use GraphAware\Neo4j\Client\ClientBuilder;

$mongo = new MongoClient("mongodb://localhost:27017");
$neo4j = ClientBuilder::create()
    ->addConnection('default', 'http://neo4j:1234567890@localhost:7687') // Example connection URI
    ->build();
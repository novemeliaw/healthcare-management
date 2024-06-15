<?php
require 'vendor/autoload.php';
use MongoDB\Client as MongoClient;
use GraphAware\Neo4j\Client\ClientBuilder;

// MongoDB connection
$mongoClient = new MongoClient("mongodb://localhost:27017");
$mongoCollection = $mongoClient->proyek->ri;

// Fetching appointment data from MongoDB
$appointments = $mongoCollection->find([], [
    'projection' => [
        'document_id' => 1,
        'tanggal_jam' => 1
    ]
]);

$appointmentDates = [];
foreach ($appointments as $appointment) {
    $appointmentDates[$appointment['document_id']] = $appointment['tanggal_jam']->toDateTime()->format('Y-m-d H:i:s');
}

// Neo4j connection
$neo4jClient = ClientBuilder::create()
    ->addConnection('default', 'http://neo4j:1234567890@localhost:7474') // Example connection URI
    ->build();

// Building Cypher query with fetched dates
$query = '
MATCH (p:Pasien)-[:MELAKUKAN]->(i:IGD)-[:BERLANJUT_KE]->(ri:RawatInap)
WITH ri, p, CASE ri.name ';

foreach ($appointmentDates as $appointment_id => $tanggal_jam) {
    $query .= "WHEN '{$appointment_id}' THEN date(datetime('$tanggal_jam')) ";
}

$query .= 'ELSE null END AS admission_date
RETURN admission_date, count(DISTINCT p) AS patient_count
ORDER BY admission_date';

$result = $neo4jClient->run($query);

// Output results
foreach ($result->getRecords() as $record) {
    echo "Date: " . $record->get('admission_date') . " - Patient Count: " . $record->get('patient_count') . "\n";
}
?>
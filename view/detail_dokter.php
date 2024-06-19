<?php
require '../vendor/autoload.php';

// Neo4j connection
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;

$neo4j = ClientBuilder::create()
    ->withDriver('bolt', 'bolt://neo4j:1234567890@localhost')
    ->withDefaultDriver('bolt')
    ->build();

// MongoDB connection
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");
$doctorCollection = $mongoClient->proyek->dokter;

// Get doctor ID from query parameters
$doctorId = isset($_GET['doctor_id']) ? $_GET['doctor_id'] : '';

if (empty($doctorId)) {
    echo json_encode(['error' => 'Doctor ID is required']);
    exit;
}

// Find doctor details
$doctorDetails = $doctorCollection->findOne(['document_id' => $doctorId]);

if (!$doctorDetails) {
    echo json_encode(['error' => 'Doctor not found']);
    exit;
}

// Fetch all case IDs for the doctor from MongoDB
$caseIds = [];

$caseCollections = [$mongoClient->proyek->igd, $mongoClient->proyek->ri, $mongoClient->proyek->rj];
foreach ($caseCollections as $collection) {
    $cases = $collection->find(['doctor_in_charge' => $doctorDetails['nama']], ['projection' => ['document_id' => 1]])->toArray();
    foreach ($cases as $case) {
        $caseIds[] = $case['document_id'];
    }
}

// var_dump ($caseIds);

// Fetch case details from MongoDB
$caseIGD = $mongoClient->proyek->igd->find([
    'document_id' => ['$in' => $caseIds]
])->toArray();
$caseRI = $mongoClient->proyek->ri->find([
    'document_id' => ['$in' => $caseIds]
])->toArray();
$caseRJ = $mongoClient->proyek->rj->find([
    'document_id' => ['$in' => $caseIds]
])->toArray();

$caseCollection = array_merge($caseIGD, $caseRI, $caseRJ);

// var_dump($caseIds);

$queryResults = []; // Initialize an array to store query results

foreach ($caseIds as $case) {
    if (substr($case, 0, 2) == 'RI' || substr($case, 0, 2) == 'RJ') {
        $neo4jQuery = "
            MATCH (i2)-[r2*0..]->(doc)-[r*0..]->(i) 
            WHERE i.document_id = '$case' AND (doc.type = 'Rawat Inap' OR doc.type = 'IGD' OR doc.type = 'Rawat Jalan')
            RETURN DISTINCT doc.document_id AS doc_id
        ";
    } elseif (substr($case, 0, 3) == 'IGD') {
        $neo4jQuery = "
            MATCH (i2)-[r2*0..]->(doc)-[r*0..]->(i) 
            WHERE i2.document_id = '$case' AND (doc.type = 'Rawat Inap' OR doc.type = 'IGD' OR doc.type = 'Rawat Jalan')
            RETURN DISTINCT doc.document_id AS doc_id
        ";
    }

    // Run the query and store the results in $queryResults
    $neo4jResult = $neo4j->run($neo4jQuery, ['caseIds' => [$case]]);
    foreach ($neo4jResult as $record) {
        $queryResults[$case] = $record->get('doc_id');
    }
}

// Now $queryResults contains the combined results of all queries
var_dump($queryResults);

$allCases = [];
$allCaseIdsArray = array_values($queryResults);

$allCaseCollections = [$mongoClient->proyek->igd, $mongoClient->proyek->ri, $mongoClient->proyek->rj];
foreach ($allCaseCollections as $collection) {
    // Ensure $allCaseIds is passed as an array
    if (!empty($queryResults) && is_array($queryResults)) {
        $cases = $collection->find(['document_id' => ['$in' => $allCaseIdsArray]])->toArray();
        $allCases = array_merge($allCases, $cases);
    } else {
        echo "Error: allCaseIds is not a valid array.";
        exit;
    }
}

// Sort cases by 'tanggal_jam'
usort($allCases, function ($a, $b) {
    return strtotime($b['tanggal_jam']) - strtotime($a['tanggal_jam']);
});

var_dump($allCases);

// Determine if each case is a follow-up case
foreach ($allCases as &$case) {
    if (in_array($case['document_id'], $currentCaseIds)) {
        $case['is_follow_up'] = 'Bukan'; // Current case is not follow-up
    } elseif (in_array($case['document_id'], $futureCaseIds)) {
        $case['is_follow_up'] = 'Ada Lanjutan'; // Related case has follow-up
    } elseif (in_array($case['document_id'], $pastCaseIds)) {
        $case['is_follow_up'] = 'Merupakan Lanjutan'; // Related case is follow-up
    }
}

// Prepare response
$response = [
    'no_lisensi_praktek' => $doctorDetails['no_lisensi_praktek'],
    'nama' => $doctorDetails['nama'],
    'spesialis' => $doctorDetails['spesialis'],
    'gender' => $doctorDetails['gender'],
    'cases' => $allCases
];

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>

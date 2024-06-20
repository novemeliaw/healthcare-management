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
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
error_log("Year: " . $year);

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
    $cases = $collection->find(['doctor_in_charge' => $doctorDetails['nama']], ['projection' => ['document_id' => 1, 'tanggal_jam' => 1]])->toArray();
    foreach ($cases as $case) {
        if (strpos($case['tanggal_jam'], $year) === 0) {
            $caseIds[] = $case['document_id'];
        }
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


$allCases = [];
$allCaseIdsArray = array_values($queryResults);
// var_dump($allCaseIdsArray);

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

// var_dump($caseIds);

$formattedCases = [];
foreach ($allCases as $case) {
    $followUpCase = [];
    // Check if the case ID exists in $queryResults
    if (isset($queryResults[$case['document_id']])) {
        $relatedCaseId = $queryResults[$case['document_id']];

        // Check if the key (case ID) differs from its associated value (related case ID)
        if ($case['document_id'] !== $relatedCaseId) {
            // Check if the related case ID starts with 'RJ'
            if (substr($relatedCaseId, 0, 2) === 'RJ' || substr($relatedCaseId, 0, 2) === 'RI') {
                $isFollowUp = 'Has a Follow Up';
            } else {
                $isFollowUp = 'Is Follow Up';
            }
            $followUpCase[] = $relatedCaseId;
        } else {
            // No follow-up if the key is the same as the value
            $isFollowUp = 'No Follow Up';
        }
    } else {
        // Case is not related to any follow-up in $queryResults
        $isFollowUp = 'Not Applicable';
    }
    // Format the case data as required
    $formattedCase = [
        'document_id' => $case['document_id'],
        'type' => $case['type'],
        'tanggal_jam' => $case['tanggal_jam'],
        'nama_pasien' => $case['nama_pasien'],
        'doctor_in_charge' => $case['doctor_in_charge'],
        'diagnosa' => $case['diagnosa'],
        'resep_obat' => $case['resep_obat'],
        'is_follow_up' => $isFollowUp,
        'follow_up_cases' => $followUpCase
    ];

    // Add the formatted case to the results array
    $formattedCases[] = $formattedCase;
}

// Prepare the final response
$response = [
    'no_lisensi_praktek' => $doctorDetails['no_lisensi_praktek'],
    'nama' => $doctorDetails['nama'],
    'spesialis' => $doctorDetails['spesialis'],
    'gender' => $doctorDetails['gender'],
    'cases' => $formattedCases
];

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
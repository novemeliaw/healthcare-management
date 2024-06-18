<?php
require '../vendor/autoload.php';

// Neo4j connection
use Laudis\Neo4j\ClientBuilder;

$neo4j = ClientBuilder::create()
    ->withDriver('bolt', 'bolt://neo4j:1234567890@localhost') 
    ->withDefaultDriver('bolt')
    ->build();

// MongoDB connection
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");
$igdCollection = $mongoClient->proyek->igd;
$riCollection = $mongoClient->proyek->ri;
$rjCollection = $mongoClient->proyek->rj;

// Get year from query string
$selectedYear = isset($_GET['year']) ? $_GET['year'] : null;

// Validate year
if (!$selectedYear || !preg_match('/^\d{4}$/', $selectedYear)) {
    echo json_encode(['error' => 'Invalid year provided']);
    exit;
}

if ($selectedYear == null){
    $resultData = [];
    header('Content-Type: application/json');
echo json_encode($resultData);
exit;
}

// Define start and end dates as strings for the selected year
$startDate = "$selectedYear-01-01 00:00:00";
$endDate = "$selectedYear-12-31 23:59:59";

// Neo4j query to fetch doctors and their IDs
$neo4jQuery = "
    MATCH (d:Dokter)
    RETURN d.name AS doctor_name, d.doc_id AS doctor_id
";

$neo4jResult = $neo4j->run($neo4jQuery);
$doctorIds = [];

foreach ($neo4jResult as $record) {
    $doctorIds[$record->get('doctor_name')] = $record->get('doctor_id');
}

// MongoDB aggregation pipelines for IGD, Rawat Inap, Rawat Jalan
$igdPipeline = [
    ['$match' => ['tanggal_jam' => ['$gte' => $startDate, '$lte' => $endDate]]],
    ['$group' => ['_id' => '$doctor_in_charge', 'total_cases' => ['$sum' => 1]]],
];

$riPipeline = [
    ['$match' => ['tanggal_jam' => ['$gte' => $startDate, '$lte' => $endDate]]],
    ['$group' => ['_id' => '$doctor_in_charge', 'total_cases' => ['$sum' => 1]]],
];

$rjPipeline = [
    ['$match' => ['tanggal_jam' => ['$gte' => $startDate, '$lte' => $endDate]]],
    ['$group' => ['_id' => '$doctor_in_charge', 'total_cases' => ['$sum' => 1]]],
];

// Aggregate data from MongoDB collections
$igdResult = $igdCollection->aggregate($igdPipeline);
$riResult = $riCollection->aggregate($riPipeline);
$rjResult = $rjCollection->aggregate($rjPipeline);

// Combine results into an associative array based on doctor and case type
$resultData = [];

function combineResults($result, $caseType) {
    global $resultData, $doctorIds;
    foreach ($result as $doc) {
        $doctorName = $doc['_id'];
        $doctorId = isset($doctorIds[$doctorName]) ? $doctorIds[$doctorName] : '';
        $totalCases = $doc['total_cases'];

        if (!isset($resultData[$doctorName])) {
            $resultData[$doctorName] = [
                'doctor_id' => $doctorId,
                'doctor_name' => $doctorName,
                'rawat_inap_count' => 0,
                'igd_count' => 0,
                'rawat_jalan_count' => 0,
                'total_count' => 0, // Total count for all case types
            ];
        }

        switch ($caseType) {
            case 'IGD':
                $resultData[$doctorName]['igd_count'] += $totalCases;
                break;
            case 'Rawat Inap':
                $resultData[$doctorName]['rawat_inap_count'] += $totalCases;
                break;
            case 'Rawat Jalan':
                $resultData[$doctorName]['rawat_jalan_count'] += $totalCases;
                break;
        }

        // Increment total count for all case types
        $resultData[$doctorName]['total_count'] += $totalCases;
    }
}

combineResults($igdResult, 'IGD');
combineResults($riResult, 'Rawat Inap');
combineResults($rjResult, 'Rawat Jalan');

// Sort the results by total count in descending order
usort($resultData, function($a, $b) {
    return $b['total_count'] <=> $a['total_count'];
});

// Limit the results to top 5 doctors
// $topDoctors = array_slice($resultData, 0, 5);

// Send JSON response
header('Content-Type: application/json');
echo json_encode($resultData);
?>

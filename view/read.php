<?php
require 'connect.php';

// Function to convert BSONDocument to array
function bsonToArray($bson) {
    return json_decode(json_encode($bson), true);
}

// Function to check if the same person comes back within one week and return all instances
function getReturnWithinOneWeekInstances($igdCollection, $nama_pasien, $startOfYear, $endOfYear)
{
    $cursor = $igdCollection->find([
        'nama_pasien' => $nama_pasien,
        'tanggal_jam' => [
            '$gte' => $startOfYear,
            '$lt' => $endOfYear
        ]
    ]);

    $dates = [];
    foreach ($cursor as $document) {
        $dates[] = [
            'date' => new DateTime($document['tanggal_jam']),
            'doctor' => $document['doctor_in_charge'],
            'type' => $document['type'] ?? '',
            'diagnosa' => bsonToArray($document['diagnosa'] ?? []),
            'resep_obat' => bsonToArray($document['resep_obat'] ?? [])
        ];
    }

    if (count($dates) < 2) {
        return [];
    }

    sort($dates);
    $instances = [];

    for ($i = 0; $i < count($dates) - 1; $i++) {
        $interval = $dates[$i]['date']->diff($dates[$i + 1]['date']);
        if ($interval->days < 7) {
            $instances[] = [
                'nama_pasien' => $nama_pasien,
                'doctor_in_charge' => $dates[$i]['doctor'],
                'tanggal_jam' => $dates[$i]['date']->format('Y-m-d H:i:s'),
                'type' => $dates[$i]['type'],
                'diagnosa' => $dates[$i]['diagnosa'],
                'resep_obat' => $dates[$i]['resep_obat']
            ];
            $instances[] = [
                'nama_pasien' => $nama_pasien,
                'doctor_in_charge' => $dates[$i + 1]['doctor'],
                'tanggal_jam' => $dates[$i + 1]['date']->format('Y-m-d H:i:s'),
                'type' => $dates[$i + 1]['type'],
                'diagnosa' => $dates[$i + 1]['diagnosa'],
                'resep_obat' => $dates[$i + 1]['resep_obat']
            ];
        }
    }

    return $instances;
}

// Get the selected year from the form submission
$selectedYear = $_POST['year'] ?? date("Y");
$isAllYears = $selectedYear === 'All';

if ($isAllYears) {
    $startOfYear = "1900-01-01 00:00:00"; // Assuming no data before 1900
    $endOfYear = "2100-12-31 23:59:59"; // Assuming no data after 2100
} else {
    $startOfYear = $selectedYear . "-01-01 00:00:00";
    $endOfYear = $selectedYear . "-12-31 23:59:59";
}

// Initialize $results as an empty array
$results = [
    'readmissionCount' => 0,  // Initialize the readmission count
    'instances' => []         // Initialize an array to hold patient instances
];

// Get all unique patient names and count distinct readmissions
$distinctPatients = $igdCollection->distinct('nama_pasien');
$readmissionCount = 0;

foreach ($distinctPatients as $nama_pasien) {
    $instances = getReturnWithinOneWeekInstances($igdCollection, $nama_pasien, $startOfYear, $endOfYear);
    if (!empty($instances)) {
        $results['readmissionCount']++;
    }
    // Merge $instances into $results
    $results['instances'] = array_merge($results['instances'], $instances);
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($results);
?>

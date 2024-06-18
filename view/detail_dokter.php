<?php
require '../vendor/autoload.php';

// MongoDB connection
$mongoClient = new MongoDB\Client("mongodb://localhost:27017");
$doctorCollection = $mongoClient->proyek->dokter;

$doctorId = $_GET['doctor_id'];
$doctor = $doctorCollection->findOne(['document_id' => $doctorId]);

if ($doctor) {
    // Fetch cases from MongoDB where the doctor_in_charge matches the doctor's name
    $caseIGD = $mongoClient->proyek->igd;
    $caseRI = $mongoClient->proyek->ri;
    $caseRJ = $mongoClient->proyek->rj;
    
    $aggregatedCases = [];

    // Aggregate IGD cases
    $casesIGD = $caseIGD->find(['doctor_in_charge' => $doctor['nama']]);
    foreach ($casesIGD as $case) {
        $aggregatedCases[] = [
            'document_id' => $case['document_id'],
            'type' => $case['type'],
            'tanggal_jam' => $case['tanggal_jam'],
            'nama_pasien' => $case['nama_pasien'],
            'doctor_in_charge' => $case['doctor_in_charge'],
            'diagnosa' => $case['diagnosa'],
            'resep_obat' => $case['resep_obat'],
        ];
    }
    
    // Aggregate RI cases
    $casesRI = $caseRI->find(['doctor_in_charge' => $doctor['nama']]);
    foreach ($casesRI as $case) {
        $aggregatedCases[] = [
            'document_id' => $case['document_id'],
            'type' => $case['type'],
            'tanggal_jam' => $case['tanggal_jam'],
            'nama_pasien' => $case['nama_pasien'],
            'doctor_in_charge' => $case['doctor_in_charge'],
            'diagnosa' => $case['diagnosa'],
            'resep_obat' => $case['resep_obat'],
        ];
    }
    
    // Aggregate RJ cases
    $casesRJ = $caseRJ->find(['doctor_in_charge' => $doctor['nama']]);
    foreach ($casesRJ as $case) {
        $aggregatedCases[] = [
            'document_id' => $case['document_id'],
            'type' => $case['type'],
            'tanggal_jam' => $case['tanggal_jam'],
            'nama_pasien' => $case['nama_pasien'],
            'doctor_in_charge' => $case['doctor_in_charge'],
            'diagnosa' => $case['diagnosa'],
            'resep_obat' => $case['resep_obat'],
        ];
    }
    $response = [
        'no_lisensi_praktek' => $doctor['no_lisensi_praktek'],
        'nama' => $doctor['nama'],
        'spesialis' => $doctor['spesialis'],
        'gender' => $doctor['gender'],
        'cases' => $aggregatedCases
    ];
} else {
    // Handle the case where no doctor was found
    $response = [
        'no_lisensi_praktek' => null,
        'nama' => null,
        'spesialis' => null,
        'gender' => null,
        'cases' => []
    ];
}

// Send response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>

<?php
// Assuming you have already established a connection to your MongoDB collection

// Value to search for
$doctor_in_charge = "Anita Utami";

// MongoDB query to find documents with specified doctor
$doctorQuery = ['doctor_in_charge' => $doctor_in_charge];
$doctorCursor = $mongoCollection->find($doctorQuery);

// Iterate through the cursor to access matching documents
foreach ($doctorCursor as $doctorDocument) {
    // Get the nama_pasien from the current doctor's document
    $nama_pasien = $doctorDocument['nama_pasien'];

    // MongoDB query to find documents with the same nama_pasien as the current doctor's document
    $query = [
        'doctor_in_charge' => $doctor_in_charge,
        'nama_pasien' => $nama_pasien
    ];
    $cursor = $mongoCollection->find($query);

    // Iterate through the cursor to access matching documents for each doctor's case
    foreach ($cursor as $document) {
        // Process the document as needed, for example, outputting its contents
        echo json_encode($document) . "\n";
    }
}
?>

<?php
require 'connect.php';

$documentId = isset($_GET['document_id']) ? $_GET['document_id'] : '';

if ($documentId) {
    try {
        // Fetch follow-up cases based on the document ID
        $caseIGD = $client->proyek->igd->find(['document_id' => $documentId])->toArray();
        $caseRI = $client->proyek->ri->find(['document_id' => $documentId])->toArray();
        $caseRJ = $client->proyek->rj->find(['document_id' => $documentId])->toArray();

        $caseCollection = array_merge($caseIGD, $caseRI, $caseRJ);

        echo json_encode(['status' => 'success', 'data' => $caseCollection]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error fetching follow-up cases: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid document ID.']);
}
?>

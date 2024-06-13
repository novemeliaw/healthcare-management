<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Data Analysis</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php
    include 'connect.php';

    // Get the selected year from dropdown or default to current year
    $selectedYear = $_GET['year'] ?? date("Y");
    $startOfYear = $selectedYear . "-01-01 00:00:00";
    $endOfYear = $selectedYear . "-12-31 23:59:59";

    // Fetch all medications with their counts
    $medicationsCursor = $igdCollection->aggregate([
        ['$match' => [
            'tanggal_jam' => [
                '$gte' => $startOfYear,
                '$lte' => $endOfYear
            ]
        ]],
        ['$unwind' => '$resep_obat'],
        ['$group' => [
            '_id' => '$resep_obat.nama_obat',
            'count' => ['$sum' => 1]
        ]],
        ['$sort' => ['count' => -1]],
        ['$limit' => 5]
    ]);

    $topMedications = iterator_to_array($medicationsCursor);

    // Calculate total medications count
    $totalMedications = array_sum(array_column($topMedications, 'count'));

    // Fetch all diagnoses with their counts
    $diagnosesCursor = $igdCollection->aggregate([
        ['$match' => [
            'tanggal_jam' => [
                '$gte' => $startOfYear,
                '$lte' => $endOfYear
            ]
        ]],
        ['$project' => [
            'diagnosa' => ['$objectToArray' => '$diagnosa']
        ]],
        ['$unwind' => '$diagnosa'],
        ['$group' => [
            '_id' => '$diagnosa.v',
            'count' => ['$sum' => 1]
        ]],
        ['$sort' => ['count' => -1]],
        ['$limit' => 5]
    ]);

    $topDiagnoses = iterator_to_array($diagnosesCursor);

    // Calculate total diagnoses count
    $totalDiagnoses = array_sum(array_column($topDiagnoses, 'count'));

    // Extract top 5 diagnosis names
    $topDiagnosisNames = array_column($topDiagnoses, '_id');

    // Fetch all records for the selected year
    $recordsCursor = $igdCollection->find([
        'tanggal_jam' => [
            '$gte' => $startOfYear,
            '$lte' => $endOfYear
        ]
    ]);

    $records = iterator_to_array($recordsCursor);

    // Create an associative array to hold diagnosis to medication mapping
    $diagnosisMedications = [];
    $diagnosisCounts = [];
    $medicationFrequencies = [];

    foreach ($records as $record) {
        if (isset($record['diagnosa']) && isset($record['resep_obat'])) {
            $diagnoses = $record['diagnosa'];
            $medications = $record['resep_obat'];
            $count = -1;
            foreach ($diagnoses as $index => $diagnosis) {
                $count++;
                $diagnosisName = $diagnosis;
                if (in_array($diagnosisName, $topDiagnosisNames)) {  // Only process top 5 diagnoses
                    if (!isset($diagnosisCounts[$diagnosisName])) {
                        $diagnosisCounts[$diagnosisName] = 0;
                    }
                    $diagnosisCounts[$diagnosisName]++;
                    if (isset($medications[$count])) {
                        $medicationName = $medications[$count]['nama_obat'];
                        if (!isset($diagnosisMedications[$diagnosisName])) {
                            $diagnosisMedications[$diagnosisName] = [];
                        }

                        if (!in_array($medicationName, $diagnosisMedications[$diagnosisName])) {
                            $diagnosisMedications[$diagnosisName][] = $medicationName;
                        }

                        if (!isset($medicationFrequencies[$diagnosisName][$medicationName])) {
                            $medicationFrequencies[$diagnosisName][$medicationName] = 0;
                        }
                        $medicationFrequencies[$diagnosisName][$medicationName]++;
                    } else {
                        echo "<pre>Index $index not found in medications.</pre>";
                    }
                }
            }
        }
    }

    // Sort diagnoses by count descending
    arsort($diagnosisCounts);
    ?>

    <div class="container mt-5">
        <h1 class="mb-3">Medical Data Analysis for Year: <?php echo htmlspecialchars($selectedYear); ?></h1>

        <form action="" method="get">
            <div class="mb-3">
                <label for="year" class="form-label">Select Year:</label>
                <select class="form-select" name="year" id="year" onchange="this.form.submit()">
                    <!-- Generate year options dynamically or hard-code -->
                    <?php for ($year = 2019; $year <= date("Y"); $year++) : ?>
                        <option value="<?php echo $year; ?>" <?php if ($year == $selectedYear) echo 'selected'; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>

        <h2 class="mb-3">Most Frequently Prescribed Medications</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Medication Name</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topMedications as $medication) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($medication['_id']); ?></td>
                        <td><?php echo htmlspecialchars($medication['count']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h3>Total Medications Prescribed: <?php echo $totalMedications; ?></h3>

        <h2 class="mb-3">Most Common Diagnoses</h2>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Diagnosis</th>
                    <th>Count</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topDiagnoses as $diagnosis) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($diagnosis['_id']); ?></td>
                        <td><?php echo htmlspecialchars($diagnosis['count']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h3>Total Diagnoses: <?php echo $totalDiagnoses; ?></h3>

        <?php foreach ($diagnosisCounts as $diagnosisName => $count) : ?>
            <?php if (isset($diagnosisMedications[$diagnosisName])) : ?>
                <h2 class="mb-3">Medications Used for <?php echo htmlspecialchars($diagnosisName); ?></h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Medication Name</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($diagnosisMedications[$diagnosisName] as $medication) : ?>
                            <tr>
                                <td><?php echo htmlspecialchars($medication); ?></td>
                                <td><?php echo htmlspecialchars($medicationFrequencies[$diagnosisName][$medication]); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endforeach; ?>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
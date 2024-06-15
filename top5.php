<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Data Analysis</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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










    $diagnosesCursor = $igdCollection->aggregate([
        ['$match' => [
            'tanggal_jam' => [
                '$gte' => $startOfYear,
                '$lte' => $endOfYear
            ]
        ]],
        ['$addFields' => [
            'tanggal_jam' => ['$dateFromString' => ['dateString' => '$tanggal_jam']]
        ]],
        ['$project' => [
            'diagnosaArray' => [
                '$objectToArray' => '$diagnosa'
            ]
        ]],
        ['$unwind' => '$diagnosaArray'],
        ['$group' => [
            '_id' => [
                'key' => '$diagnosaArray.k',
                'value' => '$diagnosaArray.v'
            ],
            'count' => ['$sum' => 1]
        ]],
        ['$sort' => ['count' => -1]],
        ['$limit' => 5]
    ]);

    $topDiagnoses = iterator_to_array($diagnosesCursor);
    $totalDiagnoses = array_sum(array_column($topDiagnoses, 'count'));
    $topDiagnosisNames = array_map(function ($diagnosis) {
        return $diagnosis['_id'];
    }, $topDiagnoses);


    // Fetch monthly data for top diagnoses
    $monthlyData = [];
    $top5DiagnosisNames = [];
    foreach ($topDiagnosisNames as $diagnosis) {
        $top5DiagnosisNames[] = $diagnosis['value']; // Store the diagnosis value in the new array
        $monthlyDataCursor = $igdCollection->aggregate([
            ['$match' => [
                'tanggal_jam' => [
                    '$gte' => $startOfYear,
                    '$lte' => $endOfYear
                ],
                'diagnosa.' . $diagnosis['key'] => $diagnosis['value']
            ]],
            ['$addFields' => [
                'tanggal_jam' => ['$dateFromString' => ['dateString' => '$tanggal_jam']]
            ]],
            ['$group' => [
                '_id' => [
                    'month' => ['$month' => '$tanggal_jam']
                ],
                'count' => ['$sum' => 1]
            ]],
            ['$sort' => ['_id.month' => 1]]
        ]);

        // Store the contents of the monthly data cursor in an array
        $monthlyDataArray = iterator_to_array($monthlyDataCursor);

        $monthlyData[$diagnosis['value']] = $monthlyDataArray;
    }










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
                if (in_array($diagnosisName, $top5DiagnosisNames)) {  // Only process top 5 diagnoses
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

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Most Frequently Prescribed Medications</div>
                    <div class="card-body">
                        <canvas id="medicationChart"></canvas>
                        <script>
                            var ctx = document.getElementById('medicationChart').getContext('2d');
                            var medicationChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo json_encode(array_column($topMedications, '_id')); ?>,
                                    datasets: [{
                                        label: 'Count',
                                        data: <?php echo json_encode(array_column($topMedications, 'count')); ?>,
                                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        </script>
                    </div>
                    <div class="card-footer">Total Medications Prescribed: <?php echo $totalMedications; ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">Most Common Diagnoses</div>
                    <div class="card-body">
                        <canvas id="diagnosisChart"></canvas>
                        <script>
                            var monthlyData = <?php echo json_encode($monthlyData); ?>;
                            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                            var datasets = [];
                            // Array of predefined colors for borders
                            var borderColors = [
                                'rgba(75, 192, 192, 1)',
                                'rgba(192, 75, 192, 1)',
                                'rgba(192, 192, 75, 1)',
                                'rgba(75, 75, 192, 1)',
                                'rgba(192, 75, 75, 1)',
                                'rgba(75, 192, 75, 1)'
                                // Add more colors as needed
                            ];
                            var colorIndex = 0;

                            for (var diagnosis in monthlyData) {
                                var data = new Array(12).fill(0);
                                monthlyData[diagnosis].forEach(function(item) {
                                    data[item._id.month - 1] = item.count;
                                });

                                // Get the next color in the array
                                var borderColor = borderColors[colorIndex % borderColors.length];
                                colorIndex++;

                                datasets.push({
                                    label: diagnosis,
                                    data: data,
                                    fill: false,
                                    borderColor: borderColor,
                                    tension: 0.1
                                });
                            }

                            var ctx = document.getElementById('diagnosisChart').getContext('2d');
                            var diagnosisChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: months,
                                    datasets: datasets
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true
                                        }
                                    }
                                }
                            });
                        </script>
                    </div>
                    <div class="card-footer">Total Diagnoses: <?php echo $totalDiagnoses; ?></div>
                </div>
            </div>

        </div>
        <div class="row">
            <?php
            // Define an array of border colors
            $borderColors = [
                'rgba(75, 192, 192, 1)',
                'rgba(192, 75, 192, 1)',
                'rgba(192, 192, 75, 1)',
                'rgba(75, 75, 192, 1)',
                'rgba(192, 75, 75, 1)',
                'rgba(75, 192, 75, 1)'
                // Add more colors as needed
            ];
            $backgroundColors = [
                'rgba(75, 192, 192, 0.2)',
                'rgba(192, 75, 192, 0.2)',
                'rgba(192, 192, 75, 0.2)',
                'rgba(75, 75, 192, 0.2)',
                'rgba(192, 75, 75, 0.2)',
                'rgba(75, 192, 75, 0.2)'
                // Add more colors as needed
            ];

            $colorIndex = 0; // Initialize color index
            ?>

            <?php foreach ($diagnosisCounts as $diagnosisName => $count) : ?>
                <?php if (isset($diagnosisMedications[$diagnosisName])) : ?>
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">Medications Used for <?php echo htmlspecialchars($diagnosisName); ?></div>
                            <div class="card-body">
                                <canvas id="medicationsChart_<?php echo htmlspecialchars($diagnosisName); ?>"></canvas>
                                <script>
                                    var ctx = document.getElementById('medicationsChart_<?php echo htmlspecialchars($diagnosisName); ?>').getContext('2d');
                                    var medicationsChart = new Chart(ctx, {
                                        type: 'bar',
                                        data: {
                                            labels: <?php echo json_encode($diagnosisMedications[$diagnosisName]); ?>,
                                            datasets: [{
                                                label: 'Count',
                                                data: <?php echo json_encode(array_values($medicationFrequencies[$diagnosisName])); ?>,
                                                backgroundColor: '<?php echo $backgroundColors[$colorIndex % count($backgroundColors)]; ?>',
                                                borderColor: '<?php echo $borderColors[$colorIndex % count($borderColors)]; ?>', // Set border color dynamically
                                                borderWidth: 1

                                            }]
                                        },
                                        options: {
                                            scales: {
                                                y: {
                                                    beginAtZero: true
                                                }
                                            }
                                        }
                                    });
                                </script>
                            </div>
                            <div class="card-footer">Total Diagnoses: <?php echo json_encode(array_sum(array_values($medicationFrequencies[$diagnosisName]))); ?></div>
                        </div>
                    </div>
                    <?php
                    $colorIndex++; // Increment color index for next iteration
                    ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
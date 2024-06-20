<?php
require 'connect.php';
require 'navbar.php';

function getCountsAndMonthlySumsByYear($collection, $year)
{
    $results = $collection->find([]);
    $counts = [
        'Mandiri' => array_fill(1, 12, 0),
        'Asuransi' => array_fill(1, 12, 0),
        'Mandiri + Asuransi' => array_fill(1, 12, 0),
    ];
    $monthlySums = [
        'obat' => array_fill(1, 12, 0),
        'jasa_visit_dokter' => array_fill(1, 12, 0),
        'tes_tambahan' => array_fill(1, 12, 0),
    ];
    $paymentMethodSums = [
        'Mandiri' => array_fill(1, 12, 0),
        'Asuransi' => array_fill(1, 12, 0),
        'Mandiri + Asuransi' => array_fill(1, 12, 0),
    ];

    $highestValueDocument = null;
    $highestValueAmount = 0;
    $totalPatientsPerMonth = array_fill(1, 12, 0);

    foreach ($results as $document) {
        $date = new DateTime($document['tanggal_jam']);
        if ($year === 'All' || $date->format('Y') == $year) {
            $month = (int)$date->format('m');
            $cara_pembayaran = $document['cara_pembayaran'];
            if (array_key_exists($cara_pembayaran, $counts)) {
                $counts[$cara_pembayaran][$month]++;
                $totalPembayaran = intval(str_replace(['Rp.', ' '], '', $document['total_pembayaran']['obat']))
                    + intval(str_replace(['Rp.', ' '], '', $document['total_pembayaran']['jasa visit dokter']))
                    + intval(str_replace(['Rp.', ' '], '', $document['total_pembayaran']['tes tambahan (opsional)']));
                $paymentMethodSums[$cara_pembayaran][$month] += $totalPembayaran;

                if ($totalPembayaran > $highestValueAmount) {
                    $highestValueAmount = $totalPembayaran;
                    $highestValueDocument = $document;
                }
            }
            $total_pembayaran = $document['total_pembayaran'];
            $monthlySums['obat'][$month] += intval(str_replace(['Rp.', ' '], '', $total_pembayaran['obat']));
            $monthlySums['jasa_visit_dokter'][$month] += intval(str_replace(['Rp.', ' '], '', $total_pembayaran['jasa visit dokter']));
            $monthlySums['tes_tambahan'][$month] += intval(str_replace(['Rp.', ' '], '', $total_pembayaran['tes tambahan (opsional)']));

            $totalPatientsPerMonth[$month]++;
        }
    }

    // Calculate total each month
    $totalSumsPerMonth = [];
    for ($i = 1; $i <= 12; $i++) {
        $totalSumsPerMonth[$i] = $monthlySums['obat'][$i] + $monthlySums['jasa_visit_dokter'][$i] + $monthlySums['tes_tambahan'][$i];
    }

    // Find the month with the highest total payment
    $highestTotalMonth = array_keys($totalSumsPerMonth, max($totalSumsPerMonth))[0];
    $highestTotalPaymentMonth = max($totalSumsPerMonth);

    return [
        'counts' => $counts,
        'monthlySums' => $monthlySums,
        'paymentMethodSums' => $paymentMethodSums,
        'highestValueDocument' => $highestValueDocument,
        'highestValueAmount' => $highestValueAmount,
        'highestTotalMonth' => $highestTotalMonth,
        'highestTotalPaymentMonth' => $highestTotalPaymentMonth,
        'totalPatientsPerMonth' => $totalPatientsPerMonth
    ];
}

$selectedYear = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Prepare data for display
$data = getCountsAndMonthlySumsByYear($igdCollection, $selectedYear);
$counts = $data['counts'];
$monthlySums = $data['monthlySums'];
$paymentMethodSums = $data['paymentMethodSums'];
$highestValueDocument = $data['highestValueDocument'];
$highestValueAmount = $data['highestValueAmount'];
$highestTotalMonth = $data['highestTotalMonth'];
$highestTotalPaymentMonth = $data['highestTotalPaymentMonth'];
$totalPatientsPerMonth = $data['totalPatientsPerMonth'];
$totalPaymentMethodSums = array_sum($paymentMethodSums['Mandiri']) + array_sum($paymentMethodSums['Asuransi']) + array_sum($paymentMethodSums['Mandiri + Asuransi']);
$totalMonthlySums = array_sum($monthlySums['obat']) + array_sum($monthlySums['jasa_visit_dokter']) + array_sum($monthlySums['tes_tambahan']);
//Payment method with the highest value
$highestPaymentMethodValue = array_keys($paymentMethodSums, max($paymentMethodSums))[0];
$highestPaymentMethodValueSum = max(array_sum($paymentMethodSums['Mandiri']), array_sum($paymentMethodSums['Asuransi']), array_sum($paymentMethodSums['Mandiri + Asuransi']));
//Payment method with the most frequency
$highestPaymentMethodFrequency = array_keys($counts, max($counts))[0];
$highestPaymentMethodFrequencyCount = max(array_sum($counts['Mandiri']), array_sum($counts['Asuransi']), array_sum($counts['Mandiri + Asuransi']));
//Highest total payment
$highestTotalPayment = max(array_sum($monthlySums['obat']), array_sum($monthlySums['jasa_visit_dokter']), array_sum($monthlySums['tes_tambahan']));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Data Analysis for Year: <?php echo htmlspecialchars($selectedYear); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        function submitForm() {
            document.getElementById('yearForm').submit();
        }

        window.onload = function() {
            // Chart for Payment Method Counts
            var ctxCounts = document.getElementById('monthlyCountsChart').getContext('2d');
            var countsChart = new Chart(ctxCounts, {
                type: 'bar',
                data: {
                    labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    datasets: [{
                            label: 'Mandiri',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($counts['Mandiri'])); ?>
                        },
                        {
                            label: 'Asuransi',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($counts['Asuransi'])); ?>
                        },
                        {
                            label: 'Mandiri + Asuransi',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($counts['Mandiri + Asuransi'])); ?>
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Chart for Total Pembayaran Sums
            var ctxSums = document.getElementById('monthlySumsChart').getContext('2d');
            var sumsChart = new Chart(ctxSums, {
                type: 'bar',
                data: {
                    labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    datasets: [{
                            label: 'Obat',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            borderColor: 'rgba(255, 99, 132, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($monthlySums['obat'])); ?>
                        },
                        {
                            label: 'Jasa Visit Dokter',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($monthlySums['jasa_visit_dokter'])); ?>
                        },
                        {
                            label: 'Tes Tambahan (Opsional)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($monthlySums['tes_tambahan'])); ?>
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Chart for Total Pembayaran Sums per Payment Method
            var ctxPaymentMethodSums = document.getElementById('monthlyPaymentMethodSumsChart').getContext('2d');
            var paymentMethodSumsChart = new Chart(ctxPaymentMethodSums, {
                type: 'bar',
                data: {
                    labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    datasets: [{
                            label: 'Mandiri',
                            backgroundColor: 'rgba(255, 206, 86, 0.2)',
                            borderColor: 'rgba(255, 206, 86, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($paymentMethodSums['Mandiri'])); ?>
                        },
                        {
                            label: 'Asuransi',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            borderColor: 'rgba(75, 192, 192, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($paymentMethodSums['Asuransi'])); ?>
                        },
                        {
                            label: 'Mandiri + Asuransi',
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1,
                            data: <?php echo json_encode(array_values($paymentMethodSums['Mandiri + Asuransi'])); ?>
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true
                        },
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    </script>
</head>

<body class = "bg-purple-100">
    <div class="container">
        <h1 class="my-4 text-3xl font-medium">Payment Method Counts and Sums for Year: <?php echo htmlspecialchars($selectedYear); ?></h1>
        <form method="get" id="yearForm" class="mb-4">
            <div class="mb-3">
                <label for="yearSelect" class="form-label">Select Year:</label>
                <select name="year" id="yearSelect" class="form-select" onchange="submitForm()">
                    <option value="All" <?php echo $selectedYear === 'All' ? 'selected' : ''; ?>>All</option>
                    <?php for ($year = 2019; $year <= 2024; $year++) : ?>
                        <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
        </form>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Payment Method Counts per Month</h5>
                        <canvas id="monthlyCountsChart" width="400" height="200"></canvas>
                    </div>
                    <div class="card-footer">
                        <strong>Total Mandiri:</strong> <?php echo array_sum($counts['Mandiri']); ?><br>
                        <strong>Total Asuransi:</strong> <?php echo array_sum($counts['Asuransi']); ?><br>
                        <strong>Total Mandiri + Asuransi:</strong> <?php echo array_sum($counts['Mandiri + Asuransi']); ?><br>
                        <strong>Sum of All:</strong> <?php echo array_sum($counts['Mandiri']) + array_sum($counts['Asuransi']) + array_sum($counts['Mandiri + Asuransi']); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Pembayaran Sums per Payment Method per Month</h5>
                        <canvas id="monthlyPaymentMethodSumsChart" width="400" height="200"></canvas>
                    </div>
                    <div class="card-footer">
                        <strong>Total Pembayaran Mandiri:</strong> Rp. <?php echo number_format(array_sum($paymentMethodSums['Mandiri']), 0, ',', '.'); ?><br>
                        <strong>Total Pembayaran Asuransi:</strong> Rp. <?php echo number_format(array_sum($paymentMethodSums['Asuransi']), 0, ',', '.'); ?><br>
                        <strong>Total Pembayaran Mandiri + Asuransi:</strong> Rp. <?php echo number_format(array_sum($paymentMethodSums['Mandiri + Asuransi']), 0, ',', '.'); ?><br>
                        <strong>Sum of All:</strong> Rp. <?php echo number_format($totalPaymentMethodSums, 0, ',', '.'); ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Pembayaran Sums per Month</h5>
                        <canvas id="monthlySumsChart" width="400" height="200"></canvas>
                    </div>
                    <div class="card-footer">
                        <strong>Total Obat:</strong> Rp. <?php echo number_format(array_sum($monthlySums['obat']), 0, ',', '.'); ?><br>
                        <strong>Total Jasa Visit Dokter:</strong> Rp. <?php echo number_format(array_sum($monthlySums['jasa_visit_dokter']), 0, ',', '.'); ?><br>
                        <strong>Total Tes Tambahan (Opsional):</strong> Rp. <?php echo number_format(array_sum($monthlySums['tes_tambahan']), 0, ',', '.'); ?><br>
                        <strong>Sum of All:</strong> Rp. <?php echo number_format($totalMonthlySums, 0, ',', '.'); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card text-white bg-primary mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Method with Highest Value</h5>
                                <p class="card-text">
                                    <?php echo ucfirst($highestPaymentMethodValue); ?>: Rp. <?php echo number_format($highestPaymentMethodValueSum, 0, ',', '.'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-white bg-success mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Method with Most Frequency</h5>
                                <p class="card-text">
                                    <?php echo ucfirst($highestPaymentMethodFrequency); ?>: <?php echo $highestPaymentMethodFrequencyCount; ?> times
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-white bg-warning mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Month with Highest Total Payment</h5>
                                <p class="card-text">
                                    Month: <?php echo DateTime::createFromFormat('!m', $highestTotalMonth)->format('F'); ?><br>
                                    Amount: Rp. <?php echo number_format($highestTotalPaymentMonth, 0, ',', '.'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-white bg-danger mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Document with Highest Payment</h5>
                                <p class="card-text">
                                    Document ID: <?php echo $highestValueDocument['document_id']; ?><br>
                                    Rp. <?php echo number_format($highestValueAmount, 0, ',', '.'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-white bg-info mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Total Payments Per Month (Per Patients)</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="card-text">
                                            <?php foreach ($totalPatientsPerMonth as $month => $count) : ?>
                                                <?php if ($month <= 6) : ?>
                                                    <?php echo DateTime::createFromFormat('!m', $month)->format('F'); ?>: <?php echo $count; ?><br>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="card-text">
                                            <?php foreach ($totalPatientsPerMonth as $month => $count) : ?>
                                                <?php if ($month > 6) : ?>
                                                    <?php echo DateTime::createFromFormat('!m', $month)->format('F'); ?>: <?php echo $count; ?><br>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card text-white bg-dark mb-3">
                            <div class="card-body">
                                <h5 class="card-title">Average Payment Per Method</h5>
                                <p class="card-text">
                                    <?php
                                    $averageMandiri = array_sum($paymentMethodSums['Mandiri']) / (array_sum($counts['Mandiri']) ?: 1);
                                    $averageAsuransi = array_sum($paymentMethodSums['Asuransi']) / (array_sum($counts['Asuransi']) ?: 1);
                                    $averageMandiriAsuransi = array_sum($paymentMethodSums['Mandiri + Asuransi']) / (array_sum($counts['Mandiri + Asuransi']) ?: 1);
                                    ?>
                                    Mandiri: Rp. <?php echo number_format($averageMandiri, 0, ',', '.'); ?><br>
                                    Asuransi: Rp. <?php echo number_format($averageAsuransi, 0, ',', '.'); ?><br>
                                    Mandiri + Asuransi: Rp. <?php echo number_format($averageMandiriAsuransi, 0, ',', '.'); ?><br>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
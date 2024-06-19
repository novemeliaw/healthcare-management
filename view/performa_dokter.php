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

// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 8; // Number of records per page
$offset = ($page - 1) * $limit;

// Query for the table (paginated)
$tableQuery = "
    MATCH (d:Dokter)-[:MENANGANI]->(i)
    WHERE i:IGD OR i:RawatInap OR i:RawatJalan
    RETURN d.name AS doctor_name, 
           d.doc_id AS doctor_id,
           count(CASE WHEN i:IGD THEN 1 ELSE NULL END) as case_igd,
           count(CASE WHEN i:RawatInap THEN 1 ELSE NULL END) as case_ri,
           count(CASE WHEN i:RawatJalan THEN 1 ELSE NULL END) as case_rj,
           count(i) as total_case
    ORDER BY doctor_name
    SKIP $offset LIMIT $limit
";

$tableResult = $neo4j->run($tableQuery);
$doctors = [];

foreach ($tableResult as $record) {
    $doctors[] = [
        'doctor_name' => $record->get('doctor_name'),
        'doctor_id' => $record->get('doctor_id'),
        'igd_count' => $record->get('case_igd'),
        'rawat_inap_count' => $record->get('case_ri'),
        'rawat_jalan_count' => $record->get('case_rj'),
        'total_count' => $record->get('total_case')
    ];
}

// Query for the chart (top 5 doctors by total count)
$chartQuery = "
    MATCH (d:Dokter)-[:MENANGANI]->(i)
    WHERE i:IGD OR i:RawatInap OR i:RawatJalan
    RETURN d.name AS doctor_name, 
           d.doc_id AS doctor_id,
           count(CASE WHEN i:IGD THEN 1 ELSE NULL END) as case_igd,
           count(CASE WHEN i:RawatInap THEN 1 ELSE NULL END) as case_ri,
           count(CASE WHEN i:RawatJalan THEN 1 ELSE NULL END) as case_rj,
           count(i) as total_case
    ORDER BY total_case DESC
    LIMIT 5
";

$chartResult = $neo4j->run($chartQuery);
$topDoctors = [];

foreach ($chartResult as $record) {
    $topDoctors[] = [
        'doctor_name' => $record->get('doctor_name'),
        'doctor_id' => $record->get('doctor_id'),
        'igd_count' => $record->get('case_igd'),
        'rawat_inap_count' => $record->get('case_ri'),
        'rawat_jalan_count' => $record->get('case_rj'),
        'total_count' => $record->get('total_case')
    ];
}

// Get total number of doctors for pagination
$totalDoctorsQuery = "
    MATCH (d:Dokter)-[:MENANGANI]->(i)
    WHERE i:IGD OR i:RawatInap OR i:RawatJalan
    RETURN count(DISTINCT d) as total
";
$totalDoctorsResult = $neo4j->run($totalDoctorsQuery);
$totalDoctors = $totalDoctorsResult->first()->get('total');
$totalPages = ceil($totalDoctors / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctors</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
</head>
<body class="bg-gray-100 p-4">
    <div class="container mx-10">
        <h2 class="text-2xl font-bold mb-6">Doctors</h2>
        <div class="my-3">
            <label for="yearSelect" class="block text-sm font-medium text-gray-700">Select Year:</label>
            <select id="yearSelect" name="yearSelect" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                <option value="">Select Year</option>
                <?php
                $currentYear = date('Y');
                for ($year = 2019; $year <= $currentYear; $year++) {
                    echo "<option value='$year'>$year</option>";
                }
                ?>
            </select>
        </div>
        <div class="flex">
            <!-- Chart Section -->
            <div class="border rounded-lg shadow-md w-full md:w-1/2 px-4 mb-6 md:mb-0">
                <canvas id="doctorChart" width="400" height="200"></canvas>
            </div>
            <!-- Table Section -->
            <div class="border rounded-lg shadow-md w-full md:w-1/2 px-2 py-2 ml-3">
                <h3 class="text-xl font-semibold mb-4">All Doctors</h3>
                <div class="bg-white overflow-y-auto shadow-md rounded-lg">
                    <table class="min-w-full bg-white text-center" id="doctorTable">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b-2 border-gray-300 text-left">Doctor Name</th>
                                <th class="py-2 px-4 border-b-2 border-gray-300">IGD Count</th>
                                <th class="py-2 px-4 border-b-2 border-gray-300">Rawat Inap Count</th>
                                <th class="py-2 px-4 border-b-2 border-gray-300">Rawat Jalan Count</th>
                            </tr>
                        </thead>
                    </table>
                    <div class="max-h-96 overflow-y-auto">
                        <table class="w-full table-fixed min-w-max">
                            <tbody id="doctorTableBody" name="doctorTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal -->
        <div class="fixed z-10 inset-0 overflow-y-auto hidden" id="doctorModal" aria-labelledby="doctorModalLabel" aria-hidden="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0 ">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">​</span>
                <div class="inline-block  align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:max-w-6xl sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="doctorModalLabel">Doctor Details</h3>
                            <div class="mt-2" id="doctorDetails">
                                <!-- Doctor details will be loaded here -->
                            </div>
                            <div class="mt-4">
                                <h4 class="text-md font-semibold">Cases</h4>
                                <div id="doctorCases">
                                    <!-- Doctor cases will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm" id="closeModal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

    <script>
        document.getElementById('yearSelect').addEventListener('change', function () {
            var selectedYear = this.value;
            if (selectedYear) {
                fetch('yearly_dokter.php?year=' + selectedYear)
                    .then(response => response.json())
                    .then(data => {
                        var topDoctors = data.slice(0, 5);
                        updateChart(topDoctors);
                        updateTable(data);
                    });
            } else {
                updateChart([]);
                updateTable([]);
            }
        });

        function updateTable(doctors) {
            var tableBody = document.getElementById('doctorTableBody');
            tableBody.innerHTML = '';

            if (doctors.length > 0) {
                doctors.forEach(function (doctor) {
                    var row = document.createElement('tr');

                    var nameCell = document.createElement('td');
                    var nameLink = document.createElement('a');
                    nameLink.href = '#';
                    nameLink.className = 'doctor-link text-blue-500';
                    nameLink.setAttribute('data-id', doctor.doctor_id);
                    nameLink.textContent = doctor.doctor_name;
                    nameCell.className = 'py-2 px-4 border-b border-gray-300 text-left';
                    nameCell.appendChild(nameLink);
                    row.appendChild(nameCell);

                    var igdCell = document.createElement('td');
                    igdCell.className = 'py-2 px-4 border-b border-gray-300 text-center';
                    igdCell.textContent = doctor.igd_count;
                    row.appendChild(igdCell);

                    var rawatInapCell = document.createElement('td');
                    rawatInapCell.className = 'py-2 px-4 border-b border-gray-300 text-center';
                    rawatInapCell.textContent = doctor.rawat_inap_count;
                    row.appendChild(rawatInapCell);

                    var rawatJalanCell = document.createElement('td');
                    rawatJalanCell.className = 'py-2 px-4 border-b border-gray-300 text-center';
                    rawatJalanCell.textContent = doctor.rawat_jalan_count;
                    row.appendChild(rawatJalanCell);

                    tableBody.appendChild(row);
                });
            } else {
                var noDataRow = document.createElement('tr');
                var noDataCell = document.createElement('td');
                noDataCell.colSpan = 4;
                noDataCell.textContent = 'No Data Found';
                noDataCell.className = 'py-2 px-4 border-b border-gray-300';
                noDataRow.appendChild(noDataCell);
                tableBody.appendChild(noDataRow);
            }

            // Modal view
            document.querySelectorAll('.doctor-link').forEach(function (element) {
                element.addEventListener('click', function (event) {
                    event.preventDefault();
                    var doctorId = this.getAttribute('data-id');
                    fetch('detail_dokter.php?doctor_id=' + doctorId)
                        .then(response => response.json())
                        .then(data => {
                            var details = `<p><strong>No Lisensi Praktek:</strong> ${data.no_lisensi_praktek}</p> 
                                           <p><strong>Name:</strong> ${data.nama}</p> 
                                           <p><strong>Spesialis:</strong> ${data.spesialis}</p> 
                                           <p><strong>Jenis Kelamin:</strong> ${data.gender}</p>`;
                            var casesDetails = '<h4 class="text-lg font-medium mt-4">3 Kasus Terakhir yang Ditangani:</h4>';
                            if (data.cases.length > 0) {
                                data.cases.sort(function(a, b) {
                                    return new Date(b.tanggal_jam) - new Date(a.tanggal_jam);
                                });

                                // Get the 3 most recent cases
                                var recentCases = data.cases.slice(0, 3);

                                // Create casesDetails for the 3 most recent cases
                                recentCases.forEach(function(caseItem) {
                                    casesDetails += `<div class="border rounded-lg p-2 my-2">
                                                        <p><strong>ID:</strong> ${caseItem.document_id}</p>
                                                        <p><strong>Type:</strong> ${caseItem.type}</p>
                                                        <p><strong>Merupakan Kasus Lanjutan?</strong> ${caseItem.is_follow_up}</p>
                                                        <p><strong>Date and Time:</strong> ${caseItem.tanggal_jam}</p>
                                                        <p><strong>Patient Name:</strong> ${caseItem.nama_pasien}</p>
                                                        <p><strong>Diagnosis:</strong> ${JSON.stringify(caseItem.diagnosa)}</p>
                                                        <p><strong>Prescription:</strong> ${JSON.stringify(caseItem.resep_obat)}</p>
                                                    </div>`;

                                    if (caseItem.preceding_cases && caseItem.preceding_cases.length > 0) {
                                        casesDetails += `<div class="ml-4 border-l-2 border-gray-300 pl-4">`;
                                        casesItem.preceding_cases.forEach(function(precedingCase) {
                                            casesDetails += `<div class="border rounded-lg p-2 my-2">
                                                                <p><strong>Preceding Case ID:</strong> ${precedingCase.document_id}</p>
                                                                <p><strong>Type:</strong> ${precedingCase.type}</p>
                                                                <p><strong>Date and Time:</strong> ${precedingCase.tanggal_jam}</p>
                                                                <p><strong>Patient Name:</strong> ${precedingCase.nama_pasien}</p>
                                                                <p><strong>Diagnosis:</strong> ${JSON.stringify(precedingCase.diagnosa)}</p>
                                                                <p><strong>Prescription:</strong> ${JSON.stringify(precedingCase.resep_obat)}</p>
                                                            </div>`;
                                        });
                                        casesDetails += `</div>`;
                                    }
                                });
                            } else {
                                casesDetails += '<p>No cases found.</p>';
                            }

                            document.getElementById('doctorDetails').innerHTML = details + casesDetails;
                            document.getElementById('doctorModal').classList.remove('hidden');
                        });
                });
            });

            // Close modal
            document.getElementById('closeModal').addEventListener('click', function () {
                document.getElementById('doctorModal').classList.add('hidden');
            });
        }

        function updateChart(data) {
            if (data.length === 0) {
                doctorChart.data.labels = ['No Data'];
                doctorChart.data.datasets[0].data = [0];
            } else {
                doctorChart.data.labels = data.map(doc => doc.doctor_name);
                doctorChart.data.datasets[0].data = data.map(doc => doc.total_count);
            }
            doctorChart.update();
        }

        var ctx = document.getElementById('doctorChart').getContext('2d');
        var topDoctors = <?= json_encode($topDoctors) ?>;
        var doctorChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: topDoctors.map(doc => doc.doctor_name),
                datasets: [{
                    label: 'Total Count',
                    data: topDoctors.map(doc => doc.total_count),
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                plugins: {
                    title: {
                        display: true,
                        text: 'Top 5 Doctors by Total Cases',
                        font: {
                            size: 18,
                            weight: 'bold'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(tooltipItem) {
                                var doc = topDoctors[tooltipItem.dataIndex];
                                return [
                                    'Total Count: ' + doc.total_count,
                                    'IGD Count: ' + doc.igd_count,
                                    'Rawat Inap Count: ' + doc.rawat_inap_count,
                                    'Rawat Jalan Count: ' + doc.rawat_jalan_count
                                ];
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Initial table load
        updateTable(<?= json_encode($doctors) ?>);
    </script>
</body>
</html>

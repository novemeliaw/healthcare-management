<?php
require '../vendor/autoload.php';
require 'navbar.php';
require 'connect.php';

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
$igdCollection = $mongoClient->proyek->igd;

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

$selectedYear = $_POST['year'] ?? date("Y");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctors</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body class="bg-purple-100">
<div id="loader" class="fixed top-0 left-0 w-full h-full flex items-center justify-center bg-gray-800 bg-opacity-75 z-50 hidden">
        <div class="animate-spin rounded-full h-32 w-32 border-t-2 border-b-2 border-gray-900"></div>
    </div>
    <div class="container py-4 w-full justify-items-center">
        <h2 class="text-3xl font-semibold mb-6">Doctors Performance Analysis Year <span id="displayYear"></span></h2>
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
        <div class="flex justify-center">
            <!-- Chart Section -->
            <div class="border rounded-lg shadow-md w-full md:w-1/2 px-4 mb-6 md:mb-0 bg-white justify-self-center">
        
                <canvas id="doctorChart" width="400" height="200" class="inline-block align-middle"></canvas>
                
            </div>
            <!-- Table Section -->
            <div class="border rounded-lg shadow-md w-full md:w-1/2 px-2 py-2 ml-3 bg-white">
                <h3 class="text-xl font-semibold p-3">All Doctors</h3>
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
                    <div class="w-auto">
                        <div class="mt-3 text-left sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2" id="doctorModalLabel">Doctor Details</h3>
                            <div class="mt-2" id="doctorDetails">
                                <!-- Doctor details will be loaded here -->
                            </div>
                            <div class="mt-4 w-full max-h-screen overflow-y-scroll">
                                <h4 class="text-md font-semibold"></h4>
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

        <!-- Follow-up Case Modal -->
        <div class="fixed z-10 inset-0 overflow-y-auto hidden" id="followUpModal" aria-labelledby="followUpModalLabel" aria-hidden="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
                    <div class="w-auto">
                        <div class="mt-3 text-left sm:mt-0 sm:ml-4 sm:text-left">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-2" id="followUpModalLabel">Follow-Up Case Details</h3>
                            <div class="mt-2" id="followUpDetails">
                                <!-- Follow-up case details will be loaded here -->
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                        <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm" id="closeFollowUpModal">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Readmission Section -->

        <div class="container mt-3 p-3 border rounded-lg shadow-md w-full bg-white">
        <h2 class="mb-4">Patients Returning Within One Week</h2>
            <p id="patientReadmissionInfo"></p>
            <table class="table table-bordered mb-5">
                <thead>
                    <tr>
                        <th>Nama Pasien</th>
                        <th>Doctor in Charge</th>
                        <th>Tanggal Jam</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody id="readmissionTableBody"></tbody>
            </table>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="infoModal" tabindex="-1" role="dialog" aria-labelledby="infoModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="infoModalLabel">Patient Information</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p><strong>Type:</strong> <span id="modalType"></span></p>
                <p><strong>Diagnosa:</strong> <br> <span id="modalDiagnosa"></span></p>
                <p><strong>Resep Obat:</strong> <br> <span id="modalResepObat"></span></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
    
</div>
    <script>
       document.getElementById('yearSelect').addEventListener('change', function () {
        var selectedYear = this.value;
        var displayYear = document.getElementById('displayYear');
        document.getElementById('loader').classList.remove('hidden');

        if (selectedYear) {
            // Fetch both datasets simultaneously
            displayYear.textContent = selectedYear;
            Promise.all([
                fetch(`yearly_dokter.php?year=${selectedYear}`).then(response => response.json()),
                fetch(`read.php?year=${selectedYear}`).then(response => response.json())
            ]).then(([yearlyData, readmissionData]) => {
                document.getElementById('loader').classList.add('hidden');
                // Handle yearly doctor data
                var topDoctors = yearlyData.slice(0, 5);
                console.log(topDoctors)
                console.log(readmissionData)
                updateChart(topDoctors);
                updateTable(yearlyData);

                // Handle readmission data
                updateReadmissionTable(readmissionData);
                updateReadmissionInfo(readmissionData);
            }).catch(error => {
                console.error('Error fetching data:', error);
                document.getElementById('readmissionInfo').innerHTML = '<p>Error loading data.</p>';
            });
        } else {
            updateChart([]);
            updateTable([]);
            document.getElementById('readmissionInfo').innerHTML = '<p>Please select a year.</p>';
            document.getElementById('readmissionTableBody').innerHTML = '';
        }
    });

        function updateReadmissionInfo(data) {
        const container = document.getElementById('patientReadmissionInfo');
        if (!data.instances || data.instances.length === 0) {
            container.innerHTML = '<p>No patients returned within one week for this year.</p>';
        } else {
            // Display the number of patients readmitted
            const readmissionCount = data.readmissionCount || 0; // Fallback to 0 if undefined
            container.innerHTML = `<p><strong>Number of Patients Readmitted within One Week: </strong>${readmissionCount}</p>`;
        }
}

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
                                    casesDetails += `<div class="w-full max-w-screen mx-auto border rounded-lg p-4 my-4 bg-white">
                                        <p class="font-bold">ID: ${caseItem.document_id}</p>
                                            <p class="font-bold">Type: ${caseItem.type}</p>
                                            <p class="font-bold">Date and Time: ${caseItem.tanggal_jam}</p>
                                            <p class="font-bold">Patient Name:</p>
                                            <p>${caseItem.nama_pasien}</p>
                                            <p class="font-bold">Diagnosis:</p>
                                            <ul class="list-disc pl-4">
                                                ${Object.entries(caseItem.diagnosa).map(([code, diagnosis]) => `<li>${code} - ${diagnosis}</li>`).join('')}
                                            </ul>
                                            <p class="font-bold">Prescription:</p>
                                            <ul class="list-disc pl-4">
                                                ${caseItem.resep_obat.map(prescription => `<li>${prescription.nama_obat} - ${prescription.dosis} (${prescription.signatura})</li>`).join('')}
                                            </ul>
                                            <p class="font-bold">Follow-up Status:</p>
                                            <p>${caseItem.is_follow_up}</p>
                                            <p class="font-bold">Follow-up Cases:</p>
                                             ${Array.isArray(caseItem.follow_up_cases) ?
                                            (caseItem.follow_up_cases.length > 0 ?
                                                caseItem.follow_up_cases.map(followUpCase => `<a class="follow-up-case-link cursor-pointer" data-document-id="${followUpCase}">${followUpCase} &#128269;</a>`).join('') :
                                                'No Follow Up Cases Available') :
                                            (caseItem.follow_up_cases || 'No Follow Up Cases Available')}
                                           
                                    </div>`;
                            })

                            } else {
                                casesDetails += '<p>No cases found.</p>';
                            }

                            

                            document.getElementById('doctorDetails').innerHTML = details + casesDetails;
                            document.getElementById('doctorModal').classList.remove('hidden');
                
                                document.querySelectorAll('.follow-up-case-link').forEach(link => {
                                    link.addEventListener('click', function(event) {
                                        console.log('Follow-up case link clicked:', this.getAttribute('data-document-id'));
                                        event.preventDefault();
                                        var documentId = this.getAttribute('data-document-id');
                                        fetchFollowUpDetails(documentId);
                                    });
                                });
                         

                            
                        });
                });
            });

            // Close modal
            document.getElementById('closeModal').addEventListener('click', function () {
                document.getElementById('doctorModal').classList.add('hidden');
            });
        }

        // Function to fetch follow-up case details
            function fetchFollowUpDetails(documentId) {
                fetch('fetch_follow_up.php?document_id=' + documentId)
                    .then(response => response.json())
                    .then(data => {
                        var followUpDetails = '<p><strong></strong></p>';
                        if (data.status === 'success') {
                            data.data.forEach(caseItem => {
                                followUpDetails += `<div class="w-full max-w-screen-md mx-auto border rounded-lg p-4 my-4 bg-white">
                                     <p class="font-bold">ID: ${caseItem.document_id}</p>
                                        <p class="font-bold">Type: ${caseItem.type}</p>
                                        <p class="font-bold">Date and Time: ${caseItem.tanggal_jam}</p>
                                        <p class="font-bold">Patient Name:</p>
                                        <p>${caseItem.nama_pasien}</p>
                                        <p class="font-bold">Diagnosis:</p>
                                        <ul class="list-disc pl-4">
                                            ${Object.entries(caseItem.diagnosa).map(([code, diagnosis]) => `<li>${code} - ${diagnosis}</li>`).join('')}
                                        </ul>
                                        <p class="font-bold">Prescription:</p>
                                        <ul class="list-disc pl-4">
                                            ${caseItem.resep_obat.map(prescription => `<li>${prescription.nama_obat} - ${prescription.dosis} (${prescription.signatura})</li>`).join('')}
                                        </ul>
                                   
                                </div>`;
                            });
                        } else {
                            followUpDetails += '<p>No follow-up case details found.</p>';
                        }
                        document.getElementById('followUpDetails').innerHTML = followUpDetails;
                        document.getElementById('followUpModal').classList.remove('hidden');
                    })
                    .catch(error => {
                        console.error('Error fetching follow-up case details:', error);
                        // Handle error if needed
                    });
            }

        // Event listener for closing follow-up case modal
        document.getElementById('closeFollowUpModal').addEventListener('click', function () {
            document.getElementById('followUpModal').classList.add('hidden');
        });

        function updateChart(data) {
            if (data.length === 0) {
                doctorChart.data.labels = ['No Data'];
                doctorChart.data.datasets[0].data = [0];
            } else {
                doctorChart.data.labels = data.map(doc => doc.doctor_name);
                doctorChart.data.datasets[0].data = data.map(doc => doc.total_count);
            }

             // Update chart tooltip labels
             doctorChart.options.plugins.tooltip.callbacks.label = function(tooltipItem) {
                var doc = data[tooltipItem.dataIndex];
                return [
                    'Total Count: ' + doc.total_count,
                    'IGD Count: ' + doc.igd_count,
                    'Rawat Inap Count: ' + doc.rawat_inap_count,
                    'Rawat Jalan Count: ' + doc.rawat_jalan_count
                ];
            };
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

        function updateReadmissionTable(readmissions) {
            var tableBody = document.getElementById('readmissionTableBody');
            tableBody.innerHTML = ''; // Clear existing table entries

            // Assuming 'data' is the object containing 'instances'
            readmissions.instances.forEach(patient => {
                var tr = document.createElement('tr'); // Create a new row
                tr.innerHTML = `<td>${patient.nama_pasien}</td>
                                <td>${patient.doctor_in_charge}</td>
                                <td>${patient.tanggal_jam}</td>
                                <td><button class="btn btn-info" data-toggle="modal" data-target="#infoModal"
                                            data-type='${JSON.stringify(patient.type)}' 
                                            data-diagnosa='${JSON.stringify(patient.diagnosa)}' 
                                            data-resep_obat='${JSON.stringify(patient.resep_obat)}'>Details</button></td>`;
                tableBody.appendChild(tr); // Append the row to the table body
});
}
    </script>
    <script>
         $(document).ready(function() {
            $('#readmissionTableBody').on('click', '.btn-info', function(event){
                var button = $(event.currentTarget);
                console.log(button)
                
                // Retrieve data attributes from the button
                var type = button.data('type');
                var diagnosa = button.data('diagnosa');
                var resep_obat = button.data('resep_obat');

                // Prepare HTML content for modal
                var diagnosaStr = "";
                for (var key in diagnosa) {
                    if (diagnosa.hasOwnProperty(key)) {
                        diagnosaStr += key + ": " + diagnosa[key] + "<br>";
                    }
                }

                var resepObatStr = "";
                resep_obat.forEach(function(obat) {
                    resepObatStr += "Kode Obat: " + obat.kode_obat + "<br>";
                    resepObatStr += "Nama Obat: " + obat.nama_obat + "<br>";
                    resepObatStr += "Dosis: " + obat.dosis + "<br>";
                    resepObatStr += "Signatura: " + obat.signatura + "<br><br>";
                });

                // Update modal content
                var modal =$('#infoModal'); ;
                modal.find('#modalType').html(type);
                modal.find('#modalDiagnosa').html(diagnosaStr);
                modal.find('#modalResepObat').html(resepObatStr);
            });
        });
        </script>
</body>
</html>

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

// Get all unique patient names
$distinctPatients = $igdCollection->distinct('nama_pasien');

$results = [];
foreach ($distinctPatients as $nama_pasien) {
    $instances = getReturnWithinOneWeekInstances($igdCollection, $nama_pasien, $startOfYear, $endOfYear);
    $results = array_merge($results, $instances);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Return Check</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h2 class="mb-4">Patients Returning Within One Week in Year <?php echo htmlspecialchars($selectedYear, ENT_QUOTES, 'UTF-8'); ?></h2>
        <form method="post" class="mb-4">
            <div class="form-group">
                <label for="year">Select Year:</label>
                <select name="year" id="year" class="form-control">
                    <option value="All" <?php if ($selectedYear == 'All') echo 'selected'; ?>>All Years</option>
                    <?php for ($year = 2019; $year <= 2024; $year++) : ?>
                        <option value="<?php echo $year; ?>" <?php if ($selectedYear == $year) echo 'selected'; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>
        <?php if (empty($results)) : ?>
            <p>No patients returned within one week for this year.</p>
        <?php else : ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Nama Pasien</th>
                        <th>Doctor in Charge</th>
                        <th>Tanggal Jam</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['nama_pasien'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($result['doctor_in_charge'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($result['tanggal_jam'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                            <button class="btn btn-info" data-toggle="modal" data-target="#infoModal" 
                                data-type='<?php echo json_encode($result['type']); ?>' 
                                data-diagnosa='<?php echo json_encode($result['diagnosa']); ?>' 
                                data-resep_obat='<?php echo json_encode($result['resep_obat']); ?>'>
                                Details
                            </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#infoModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                
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
                var modal = $(this);
                modal.find('#modalType').html(type);
                modal.find('#modalDiagnosa').html(diagnosaStr);
                modal.find('#modalResepObat').html(resepObatStr);
            });
        });
    </script>
</body>

</html>
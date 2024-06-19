<?php
require 'connect.php';

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
            'doctor' => $document['doctor_in_charge']
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
                'tanggal_jam' => $dates[$i]['date']->format('Y-m-d H:i:s')
            ];
            $instances[] = [
                'nama_pasien' => $nama_pasien,
                'doctor_in_charge' => $dates[$i + 1]['doctor'],
                'tanggal_jam' => $dates[$i + 1]['date']->format('Y-m-d H:i:s')
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
        <h2 class="mb-4">Patients Returning Within One Week in Year <?php echo $selectedYear; ?></h2>
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
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $result) : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['nama_pasien'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($result['doctor_in_charge'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($result['tanggal_jam'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>
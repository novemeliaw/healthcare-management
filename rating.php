<?php
require 'connect.php';

// Define document types and fetch all documents from collections
$documentTypes = ['IGD', 'Rawat Inap', 'Rawat Jalan', 'All'];

// Fetch all documents from all collections
$allDocuments = array_merge(
    iterator_to_array($igdCollection->find()),
    iterator_to_array($riCollection->find()),
    iterator_to_array($rjCollection->find())
);

// Determine available ratings categories
$ratings = [];
foreach ($allDocuments as $document) {
    if (isset($document['rating'])) {
        foreach ($document['rating'] as $key => $value) {
            if (!in_array($key, $ratings)) {
                $ratings[] = $key;
            }
        }
    }
}

// Handle form submissions
$selectedRatingCategory = $_POST['ratingCategory'] ?? [];
$selectedDocumentType = $_POST['documentType'] ?? 'All';
$selectedYear = $_POST['year'] ?? 'All';
$filteredDocuments = [];

// Initialize arrays for average ratings and chart data
$averageRatings = [];
$chartData = [];

// Prepare query conditions based on form inputs
$query = [];

// Filter by rating categories
if (!empty($selectedRatingCategory)) {
    $orQuery = [];
    foreach ($selectedRatingCategory as $category) {
        $orQuery[] = ['rating.' . $category => ['$exists' => true]];
    }
    $query['$or'] = $orQuery;
}

// Filter by document type if selected and not "All"
if ($selectedDocumentType !== 'All') {
    $query['type'] = $selectedDocumentType;
}

// Filter by year if selected and not "All"
if ($selectedYear !== 'All') {
    $regexYear = "^$selectedYear";
    $query['tanggal_jam'] = ['$regex' => $regexYear];
}

// Perform the query on appropriate collection(s)
try {
    $filteredDocuments = array_merge(
        iterator_to_array($igdCollection->find($query)),
        iterator_to_array($riCollection->find($query)),
        iterator_to_array($rjCollection->find($query))
    );

    if (!empty($filteredDocuments)) {
        // Calculate average ratings per category
        foreach ($selectedRatingCategory as $category) {
            $totalRating = 0;
            $count = 0;
            $monthlyRatings = array_fill(1, 12, ['sum' => 0, 'count' => 0]);
            $yearlyRatings = array_fill_keys(range(2019, 2024), ['sum' => 0, 'count' => 0]);

            foreach ($filteredDocuments as $document) {
                if (isset($document['rating'][$category])) {
                    $rating = $document['rating'][$category];
                    $totalRating += $rating;
                    $count++;

                    // Parse tanggal_jam for charting purposes
                    $date = new DateTime($document['tanggal_jam']);

                    if ($selectedDocumentType === 'All' && $selectedYear === 'All') {
                        // Multiline chart by year and doc type
                        $year = (int)$date->format('Y');
                        $yearlyRatings[$year]['sum'] += $rating;
                        $yearlyRatings[$year]['count']++;
                    } elseif ($selectedDocumentType === 'All' && $selectedYear !== 'All') {
                        // Multiline chart by month and doc type
                        $month = (int)$date->format('m');
                        $monthlyRatings[$month]['sum'] += $rating;
                        $monthlyRatings[$month]['count']++;
                    } elseif ($selectedDocumentType !== 'All' && $selectedYear === 'All') {
                        // Single line chart by year
                        $year = (int)$date->format('Y');
                        $yearlyRatings[$year]['sum'] += $rating;
                        $yearlyRatings[$year]['count']++;
                    } else {
                        // Default: Single line chart by month
                        $month = (int)$date->format('m');
                        $monthlyRatings[$month]['sum'] += $rating;
                        $monthlyRatings[$month]['count']++;
                    }
                }
            }

            // Calculate average rating for current category
            if ($count > 0) {
                $averageRatings[$category] = round($totalRating / $count, 2);
            } else {
                $averageRatings[$category] = 0;
            }

            // Prepare chart data for current category
            if ($selectedDocumentType === 'All' && $selectedYear === 'All') {
                // Multiline chart by year and doc type
                $chartData[] = [
                    'category' => $category,
                    'labels' => range(2019, 2024),
                    'data' => array_map(function($year) use ($yearlyRatings, $category) {
                        return isset($yearlyRatings[$year]) && $yearlyRatings[$year]['count'] > 0
                            ? round($yearlyRatings[$year]['sum'] / $yearlyRatings[$year]['count'], 2)
                            : 0;
                    }, range(2019, 2024))
                ];
            } elseif ($selectedDocumentType === 'All' && $selectedYear !== 'All') {
                // Multiline chart by month and doc type
                $chartData[] = [
                    'category' => $category,
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    'data' => array_map(function($month) use ($monthlyRatings, $category) {
                        return isset($monthlyRatings[$month]) && $monthlyRatings[$month]['count'] > 0
                            ? round($monthlyRatings[$month]['sum'] / $monthlyRatings[$month]['count'], 2)
                            : 0;
                    }, range(1, 12))
                ];
            } elseif ($selectedDocumentType !== 'All' && $selectedYear === 'All') {
                // Single line chart by year
                $chartData[] = [
                    'category' => $category,
                    'labels' => range(2019, 2024),
                    'data' => array_map(function($year) use ($yearlyRatings, $category) {
                        return isset($yearlyRatings[$year]) && $yearlyRatings[$year]['count'] > 0
                            ? round($yearlyRatings[$year]['sum'] / $yearlyRatings[$year]['count'], 2)
                            : 0;
                    }, range(2019, 2024))
                ];
            } else {
                // Default: Single line chart by month
                $chartData[] = [
                    'category' => $category,
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    'data' => array_map(function($month) use ($monthlyRatings, $category) {
                        return isset($monthlyRatings[$month]) && $monthlyRatings[$month]['count'] > 0
                            ? round($monthlyRatings[$month]['sum'] / $monthlyRatings[$month]['count'], 2)
                            : 0;
                    }, range(1, 12))
                ];
            }
        }
    }
} catch (Exception $e) {
    // Handle any exceptions that may occur during MongoDB queries
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Average Rating</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        .card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2); /* Adjusted box shadow on hover */
            transform: scale(1.02); /* Slightly scale up the card on hover */
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="my-4">Average Rating</h1>
        <form method="POST">
            <div class="form-group">
                <label for="ratingCategory">Rating Category</label>
                <div class="p-3" style="border: 1px solid #ccc; border-radius: 4px; padding-right: 10px;">
                    <div class="row">
                        <?php foreach ($ratings as $rating): ?>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" id="ratingCategory_<?php echo htmlspecialchars($rating, ENT_QUOTES, 'UTF-8'); ?>" name="ratingCategory[]" value="<?php echo htmlspecialchars($rating, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($rating, $selectedRatingCategory) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="ratingCategory_<?php echo htmlspecialchars($rating, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($rating, ENT_QUOTES, 'UTF-8');                ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="documentType">Document Type</label>
                <select class="form-control" id="documentType" name="documentType">
                    <?php foreach ($documentTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDocumentType == $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="year">Year</label>
                <select class="form-control" id="year" name="year">
                    <option value="All">All</option>
                    <?php for ($year = 2019; $year <= 2024; $year++): ?>
                        <option value="<?php echo $year; ?>" <?php echo $selectedYear == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <div class="row">
            <?php if (empty($filteredDocuments)): ?>
                <div class="col-md-12">
                    <div class="alert alert-warning my-4">No data available for the selected filters.</div>
                </div>
            <?php else: ?>
                <?php foreach ($chartData as $chart): ?>
                    <div class="col-md-6">
                        <div class="card my-4" style="transition: box-shadow 0.3s ease; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);">
                            <div class="card-body" style="color: #333; transition: transform 0.3s ease;">
                                <h5 class="card-title">Average Rating - <?php echo htmlspecialchars($chart['category'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="card-text" style="font-size:35px;"><?php echo $averageRatings[$chart['category']]; ?></p>
                                <p class="card-text" style="color: #0D6EFD">Based on <?php echo count($filteredDocuments); ?> documents</p>
                                <hr style="border-top: 1px solid #000;">
                                <canvas id="ratingDistributionChart_<?php echo htmlspecialchars($chart['category'], ENT_QUOTES, 'UTF-8'); ?>"></canvas>
                            </div>
                        </div>
                        <script>
                            var ctx_<?php echo htmlspecialchars($chart['category'], ENT_QUOTES, 'UTF-8'); ?> = document.getElementById('ratingDistributionChart_<?php echo htmlspecialchars($chart['category'], ENT_QUOTES, 'UTF-8'); ?>').getContext('2d');
                            new Chart(ctx_<?php echo htmlspecialchars($chart['category'], ENT_QUOTES, 'UTF-8'); ?>, {
                                type: <?php
                                    if ($selectedDocumentType === 'All' && $selectedYear === 'All') {
                                        echo "'line'";
                                    } else {
                                        echo "'bar'";
                                    }
                                ?>,
                                data: {
                                    labels: <?php echo json_encode($chart['labels']); ?>,
                                    datasets: [{
                                        label: '',
                                        backgroundColor: 'rgba(<?php echo rand(0, 255); ?>, <?php echo rand(0, 255); ?>, <?php echo rand(0, 255); ?>, 0.2)',
                                        borderColor: 'rgba(<?php echo rand(0, 255); ?>, <?php echo rand(0, 255); ?>, <?php echo rand(0, 255); ?>, 1)',
                                        borderWidth: 1,
                                        data: <?php echo json_encode($chart['data']); ?>
                                    }]
                                },
                                options: {
                                    scales: {
                                        yAxes: [{
                                            ticks: {
                                                beginAtZero: true,
                                                max: 5
                                            }
                                        }]
                                    },
                                    legend: {
                                        display: false
                                    }
                                }
                            });
                        </script>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
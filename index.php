<?php
include 'config.php';
$stmt = $pdo->query("SELECT time, data FROM gas_measurements ORDER BY time ASC");
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$times = [];
$variables = [];

foreach ($records as $record) {
    $times[] = $record['time'];
    $data = json_decode($record['data'], true);

    foreach ($data as $key => $value) {
        if (is_numeric($value)) {
            $variables[$key][] = $value;
        }
    }
}

$availableVariables = array_keys($variables);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GAZ | Visualisation des Données</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@2.47.0/dist/full.css" rel="stylesheet">
    <style>
        body {
            background-color: white; 
            font-family: 'Inter', sans-serif;
        }

        .container {
            margin-top: 50px;
        }

        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            padding: 24px;
            margin-bottom: 30px;
        }

        h4 {
            color: #1F2937; 
        }

        .select {
            background-color: #F9FAFB; 
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 1rem;
        }

        .file-input {
            border: 1px solid #D1D5DB;
            border-radius: 8px;
            padding: 8px 12px;
        }

        .btn {
            font-size: 0.875rem; 
            padding: 8px 16px; 
            border-radius: 8px;
        }

        .btn-primary {
            background-color:#0F4C81;
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background-color: #60A9FA; 
        }

        .btn-outline {
            border: none;
            background-color:#0F4C81; 
            color: white;
        }

        .btn-outline:hover {
            background-color: #93C5FD; 
            color: white;
        }

        .btn-group button {
            transition: all 0.3s ease;
        }

        .chart-container {
            position: relative;
            height: 500px;
        }


        @media (max-width: 768px) {
            .btn-group {
                flex-direction: column;
            }

            .btn-group button {
                width: 100% !important;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body class="bg-white">

<div class="container mx-auto px-4 md:px-8">

    <h2 class="text-4xl font-bold text-center mb-10 text-gray-700">📊 Visualisation des Données de Production de Gaz</h2>

    <!-- File Upload Section -->
    <div class="card">
        <h4 class="text-xl font-semibold mb-4 text-gray-800">📂 Importer un fichier</h4>
        <form action="upload.php" method="post" enctype="multipart/form-data">
            <input type="file" name="file" class="file-input w-full mb-6" required>
            <button type="submit" class="btn btn-primary w-full">Importer</button>
        </form>
    </div>

    <!-- Variable Selection Section -->
    <div class="card">
        <h4 class="text-xl font-semibold mb-6 text-gray-800">📌 Sélectionner une variable</h4>
        <select id="variableSelect" class="select w-full mb-6">
            <?php foreach ($availableVariables as $var) : ?>
                <option value="<?= htmlspecialchars($var) ?>"><?= htmlspecialchars($var) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Chart Type Buttons Section -->
    <div class="card">
        <h4 class="text-xl font-semibold mb-6 text-gray-800">Choisir le type de graphique</h4>
        <div class="btn-group flex md:flex-row flex-col gap-4 md:gap-0">
            <button class="btn btn-outline w-1/3 md:w-auto" onclick="changeChartType('line')">📈 Ligne</button>
            <button class="btn btn-outline w-1/3 md:w-auto" onclick="changeChartType('bar')">📊 Barres</button>
            <button class="btn btn-outline w-1/3 md:w-auto" onclick="changeChartType('radar')">🌐 Radar</button>
        </div>
    </div>

    <!-- Chart Section -->
    <div class="card">
        <h4 class="text-xl font-semibold mb-6 text-gray-800">Graphique des Données</h4>
        <div class="chart-container">
            <canvas id="gasChart"></canvas>
        </div>
    </div>

    <!-- Data Table Section -->
    <div class="card">
        <h4 class="text-xl font-semibold mb-4 text-gray-800">📋 Tableau des Données Importées</h4>
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full border-collapse">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Temps</th>
                        <?php foreach ($availableVariables as $var): ?>
                            <th class="px-4 py-2"><?= htmlspecialchars($var) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($times as $index => $time): ?>
                        <tr class="hover:bg-gray-100 transition duration-200">
                            <td class="px-4 py-2"><?= htmlspecialchars($time) ?></td>
                            <?php foreach ($availableVariables as $var): ?>
                                <td class="px-4 py-2"><?= isset($variables[$var][$index]) ? htmlspecialchars($variables[$var][$index]) : 'N/A' ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
    const ctx = document.getElementById('gasChart').getContext('2d');
    const times = <?= json_encode($times) ?>;
    const variables = <?= json_encode($variables) ?>;
    let selectedVariable = document.getElementById('variableSelect').value;
    let chartType = 'line';

    function getRandomColor() {
        return `rgba(${Math.floor(Math.random() * 200)}, ${Math.floor(Math.random() * 200)}, ${Math.floor(Math.random() * 200)}, 1)`;
    }

    let chart = new Chart(ctx, {
        type: chartType,
        data: {
            labels: times,
            datasets: [{
                label: selectedVariable,
                data: variables[selectedVariable],
                borderColor: getRandomColor(),
                backgroundColor: getRandomColor(),
                borderWidth: 2,
                fill: chartType === 'radar'
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: { title: { display: true, text: 'Temps' } },
                y: { title: { display: true, text: 'Valeur' } }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        title: function(tooltipItem) {
                            return 'Date: ' + tooltipItem[0].label;
                        }
                    }
                }
            }
        }
    });


    document.getElementById('variableSelect').addEventListener('change', function() {
        selectedVariable = this.value;
        chart.data.datasets[0].label = selectedVariable;
        chart.data.datasets[0].data = variables[selectedVariable];
        chart.data.datasets[0].borderColor = getRandomColor();
        chart.data.datasets[0].backgroundColor = getRandomColor();
        chart.update();
    });

    function changeChartType(type) {
        chartType = type;
        chart.destroy();
        chart = new Chart(ctx, {
            type: chartType,
            data: {
                labels: times,
                datasets: [{
                    label: selectedVariable,
                    data: variables[selectedVariable],
                    borderColor: getRandomColor(),
                    backgroundColor: getRandomColor(),
                    borderWidth: 2,
                    fill: chartType === 'radar'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: { title: { display: true, text: 'Temps' } },
                    y: { title: { display: true, text: 'Valeur' } }
                }
            }
        });
    }
</script>

</body>
</html>

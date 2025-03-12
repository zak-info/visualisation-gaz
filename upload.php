<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["file"])) {
    $file = $_FILES["file"]["tmp_name"];

    if (!file_exists($file)) {
        die("Erreur : Le fichier n'existe pas");
    }

    $lines = array_filter(array_map('trim', file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)));

    if (count($lines) < 3) {
        die("Erreur : Format de fichier incorrect");
    }

    foreach ($lines as &$line) {
        $line = preg_replace('/\s+/', ';', trim($line));  
    }
    unset($line);

    $headers = array_map('trim', explode(";", $lines[0]));

    if (count($headers) < 2) {
        die("Erreur : Le fichier ne contient pas assez de colonnes après correction");
    }

    // Start a transaction to handle the import
    $pdo->beginTransaction();
    try {
        // Iterate over each line starting from the second one (skipping headers)
        for ($i = 1; $i < count($lines); $i++) {
            $values = array_map('trim', explode(";", $lines[$i]));

            // Check if the number of columns matches the headers count
            if (count($values) < count($headers)) {
                continue; // Skip this line if the format doesn't match
            }

            // Prepare data to insert
            $time = $values[0]; // The first column is time
            $data = [];

            // Handle time (ensure it's in a valid datetime format)
            $formattedTime = validateAndFormatTime($time);
            if ($formattedTime === false) {
                continue; // Skip the record if time is invalid
            }

            // Loop over the columns and prepare data
            for ($j = 1; $j < count($headers); $j++) {
                $val = trim($values[$j]);

                // If the value contains 'E', treat it as a float (scientific notation)
                if (stripos($val, "E") !== false) {
                    $val = (float) sprintf('%f', $val);
                }

                // Ensure '0.' is represented as '0.0'
                if ($val === "0.") {
                    $val = "0.0";
                }

                // If it's a valid number, store it in the data array
                if (is_numeric($val)) {
                    $data[$headers[$j]] = (float) str_replace(",", ".", $val);
                }
            }

            // If we have valid data, insert it into the database
            if (!empty($data)) {
                $stmt = $pdo->prepare("INSERT INTO gas_measurements (time, data) VALUES (?, ?)");
                $stmt->execute([$formattedTime, json_encode($data)]);
            }
        }
        // Commit the transaction after all lines are inserted
        $pdo->commit();

        // Redirect to index.php after successful importation
        header('Location: index.php?imported=true');
        exit();
    } catch (Exception $e) {
        // Rollback if any error occurs
        $pdo->rollBack();
        die("Erreur MySQL : " . $e->getMessage());
    }
}

// Function to validate and format time
function validateAndFormatTime($time) {
    // Check if the time is a valid Unix timestamp or a valid datetime string
    if (is_numeric($time) && $time > 0) {
        // If time is a valid Unix timestamp, format it
        return date('Y-m-d H:i:s', $time);
    } elseif (strtotime($time) !== false) {
        // If time is a valid datetime string (e.g., '2021-12-31 23:59:59')
        return date('Y-m-d H:i:s', strtotime($time));
    } else {
        // Invalid time format (e.g., '0000-00-00 00:00:00')
        return false;  // Return false to indicate invalid time
    }
}
?>

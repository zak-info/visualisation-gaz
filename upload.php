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

    $pdo->beginTransaction();
    try {
        for ($i = 1; $i < count($lines); $i++) {
            $values = array_map('trim', explode(";", $lines[$i]));

            if (count($values) < count($headers)) {
                continue;
            }

            $time = $values[0];
            $data = [];

            $formattedTime = validateAndFormatTime($time);
            if ($formattedTime === false) {
                continue;
            }

            for ($j = 1; $j < count($headers); $j++) {
                $val = trim($values[$j]);

                if (stripos($val, "E") !== false) {
                    $val = (float) sprintf('%f', $val);
                }

                if ($val === "0.") {
                    $val = "0.0";
                }

                if (is_numeric($val)) {
                    $data[$headers[$j]] = (float) str_replace(",", ".", $val);
                }
            }

            if (!empty($data)) {
                $stmt = $pdo->prepare("INSERT INTO gas_measurements (time, data) VALUES (?, ?)");
                $stmt->execute([$formattedTime, json_encode($data)]);
            }
        }
        $pdo->commit();

        header('Location: index.php?imported=true');
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Erreur MySQL : " . $e->getMessage());
    }
}

function validateAndFormatTime($time) {
    if (is_numeric($time) && $time > 0) {
        return date('Y-m-d H:i:s', $time);
    } elseif (strtotime($time) !== false) {
        return date('Y-m-d H:i:s', strtotime($time));
    } else {
        return false;
    }
}
?>

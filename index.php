<?php
require_once 'php_csv_consolidator.php';

$premium = isset($_GET['premium']) && $_GET['premium'] == '1';
$maxFiles = $premium ? 10 : 3;
$maxRows = $premium ? 10000 : 1000;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['csv_files'])) {
        $message = 'No files uploaded';
    } else {
        $count = count($_FILES['csv_files']['name']);
        if ($count > $maxFiles) {
            $message = 'File limit exceeded for your plan.';
        } else {
            $uploadDir = __DIR__ . '/uploads/' . uniqid('batch_');
            mkdir($uploadDir, 0755, true);
            $totalRows = 0;
            for ($i = 0; $i < $count; $i++) {
                if ($_FILES['csv_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES['csv_files']['tmp_name'][$i];
                    $dest = $uploadDir . '/' . basename($_FILES['csv_files']['name'][$i]);
                    move_uploaded_file($tmp, $dest);
                    $rows = max(0, count(file($dest)) - 1);
                    $totalRows += $rows;
                }
            }
            if ($totalRows > $maxRows) {
                $message = 'Row limit exceeded for your plan.';
            } else {
                $_ENV['INPUT_DIRECTORY'] = $uploadDir;
                $consolidator = new AICSVConsolidator();
                $consolidator->consolidate();
                $message = 'Consolidation complete. Output: ' . $consolidator->config['output_file'];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>CSV Consolidator</title>
    <style>
        body { font-family: Arial, sans-serif; margin:40px; }
        .container { max-width: 600px; margin:auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>CSV Consolidator <?php echo $premium ? '(Premium)' : '(Free)'; ?></h1>
    <?php if ($message) { echo '<p>' . htmlspecialchars($message) . '</p>'; } ?>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="csv_files[]" multiple accept=".csv" required />
        <button type="submit">Upload & Process</button>
    </form>
    <?php if (!$premium): ?>
    <p>You can upload up to 3 files and 1000 rows for free.</p>
    <a href="upgrade.php">Upgrade to Premium</a>
    <?php endif; ?>
</div>
</body>
</html>

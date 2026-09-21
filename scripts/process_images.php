<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');
set_time_limit(120);

$incomingDir  = __DIR__ . '/../uploads/incomming/';
$processedDir = __DIR__ . '/../uploads/processed/';
$errorDir     = __DIR__ . '/../uploads/error/';

foreach ([$incomingDir, $processedDir, $errorDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

if (!extension_loaded('imagick')) {
    die('Fehler: Imagick ist nicht installiert oder nicht aktiv.');
}

$files = array_merge(
    glob($incomingDir . '*.jpg'),
    glob($incomingDir . '*.JPG'),
    glob($incomingDir . '*.jpeg'),
    glob($incomingDir . '*.JPEG')
);

echo '<pre>';

if (empty($files)) {
    echo "Keine neuen Bilder gefunden.\n";
    echo '</pre>';
    exit;
}

// Nur 1 Bild pro Durchlauf verarbeiten
$filePath = $files[0];
$fileName = basename($filePath);
$processedFile = $processedDir . $fileName;
$errorTarget = $errorDir . $fileName;

try {
    clearstatcache(true, $filePath);
    $lastModified = filemtime($filePath);
    $age = $lastModified ? (time() - $lastModified) : -1;

    if ($lastModified === false || $age < 10) {
        echo "Übersprungen (noch zu neu): {$fileName}\n";
        echo '</pre>';
        exit;
    }

    echo "Verarbeite: {$fileName}\n";

    $image = new Imagick();
    $image->readImage($filePath);

    // EXIF-Orientierung korrigieren
    $orientation = $image->getImageOrientation();
    switch ($orientation) {
        case Imagick::ORIENTATION_BOTTOMRIGHT:
            $image->rotateImage('#000', 180);
            break;
        case Imagick::ORIENTATION_RIGHTTOP:
            $image->rotateImage('#000', 90);
            break;
        case Imagick::ORIENTATION_LEFTBOTTOM:
            $image->rotateImage('#000', -90);
            break;
    }
    $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

    $image->setImageFormat('jpeg');

    // Erst verkleinern, dann leicht entrauschen
    $image->resizeImage(2200, 0, Imagick::FILTER_LANCZOS, 1);

    // Sehr leichte Rauschreduktion
    $image->reduceNoiseImage(1);

    // Leicht nachschärfen
    $image->unsharpMaskImage(0.4, 0.4, 0.6, 0.02);

    $image->setImageCompression(Imagick::COMPRESSION_JPEG);
    $image->setImageCompressionQuality(88);

    if (!$image->writeImage($processedFile)) {
        throw new Exception('Processed konnte nicht gespeichert werden.');
    }

    $image->clear();
    $image->destroy();

    if (file_exists($processedFile)) {
        unlink($filePath);
        echo "Processed gespeichert: {$processedFile}\n";
        echo "Original gelöscht: {$fileName}\n";
    } else {
        throw new Exception('Datei wurde nicht korrekt in processed gespeichert.');
    }

} catch (Throwable $e) {
    @rename($filePath, $errorTarget);
    echo "FEHLER: " . $e->getMessage() . "\n";
    echo "Datei nach error verschoben: {$errorTarget}\n";
}

echo '</pre>';
?>
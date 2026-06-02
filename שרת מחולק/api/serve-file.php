<?php
declare(strict_types=1);

// שולף את שם הקובץ מה-GET
$filename = $_GET['file'] ?? '';

// בונה את הנתיב המלא לתיקיית ההעלאות
$path = __DIR__ . '/../uploads/' . basename($filename);

// בודק אם הקובץ קיים
if (!file_exists($path)) {
    http_response_code(404); // אם לא קיים מחזיר 404
    echo "File not found";
    exit;
}

// קובע את סוג הקובץ (MIME type) להחזרת הכותרות הנכונות
$mime = mime_content_type($path) ?: 'application/octet-stream';

// שולח כותרת Content-Type לפי סוג הקובץ
header('Content-Type: ' . $mime);

// שולח כותרת Content-Disposition כך שהקובץ יוצג בדפדפן (inline) ושם הקובץ נשמר
header('Content-Disposition: inline; filename="' . basename($path) . '"');

// שולח כותרת Content-Length עם גודל הקובץ בבייטים
header('Content-Length: ' . filesize($path));

// קורא ושולח את תוכן הקובץ ישירות לדפדפן
readfile($path);


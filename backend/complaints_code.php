<?php
require_once __DIR__ . '/../connection.php';

$statuses = ['Pending', 'Under Review', 'Resolved'];

if (isset($_POST['create_complaint'])) {
    $residentId = filter_var($_POST['resident_id'] ?? null, FILTER_VALIDATE_INT);
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $dateReported = $_POST['date_reported'] ?? '';
    $date = DateTime::createFromFormat('!Y-m-d', $dateReported);
    $photo = $_FILES['photo_evidence'] ?? null;

    if ($residentId === false || $residentId < 1 || $location === '' || strlen($location) > 255
        || $description === '' || strlen($description) > 255
        || !$date || $date->format('Y-m-d') !== $dateReported
        || !$photo || $photo['error'] !== UPLOAD_ERR_OK || $photo['size'] > 5 * 1024 * 1024) {
        header('Location: ../complaints.php?notice=create_error');
        exit;
    }

    $imageInfo = @getimagesize($photo['tmp_name']);
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mimeType = $imageInfo['mime'] ?? '';
    if (!$imageInfo || !isset($allowedTypes[$mimeType])) {
        header('Location: ../complaints.php?notice=create_error');
        exit;
    }

    $residentCheck = $pdo->prepare('SELECT 1 FROM residents WHERE ResidentID = :resident_id');
    $residentCheck->execute([':resident_id' => $residentId]);
    if (!$residentCheck->fetchColumn()) {
        header('Location: ../complaints.php?notice=create_error');
        exit;
    }

    $uploadDirectory = __DIR__ . '/../uploads';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
        header('Location: ../complaints.php?notice=create_error');
        exit;
    }
    $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($photo['tmp_name'], $uploadDirectory . '/' . $fileName)) {
        header('Location: ../complaints.php?notice=create_error');
        exit;
    }

    try {
        $insert = $pdo->prepare(
            'INSERT INTO complaints (ResidentID, Location, Description, DateReported, PhotoEvidence, Status)
             VALUES (:resident_id, :location, :description, :date_reported, :photo_evidence, :status)'
        );
        $insert->execute([
            ':resident_id' => $residentId,
            ':location' => $location,
            ':description' => $description,
            ':date_reported' => $dateReported,
            ':photo_evidence' => $fileName,
            ':status' => 'Pending',
        ]);
    } catch (PDOException $exception) {
        unlink($uploadDirectory . '/' . $fileName);
        header('Location: ../complaints.php?notice=create_error');
        exit;
    }

    header('Location: ../complaints.php?notice=created');
    exit;
}

if (isset($_POST['update_complaint_status'])) {
    $complaintId = filter_var($_POST['complaint_id'] ?? null, FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? '';
    if ($complaintId === false || $complaintId < 1 || !in_array($status, $statuses, true)) {
        header('Location: ../complaints.php?notice=update_error');
        exit;
    }

    $complaintCheck = $pdo->prepare('SELECT 1 FROM complaints WHERE ComplaintID = :complaint_id');
    $complaintCheck->execute([':complaint_id' => $complaintId]);
    if (!$complaintCheck->fetchColumn()) {
        header('Location: ../complaints.php?notice=update_error');
        exit;
    }

    try {
        $update = $pdo->prepare('UPDATE complaints SET Status = :status WHERE ComplaintID = :complaint_id');
        $update->execute([':status' => $status, ':complaint_id' => $complaintId]);
    } catch (PDOException $exception) {
        header('Location: ../complaints.php?notice=update_error');
        exit;
    }
    header('Location: ../complaints.php?notice=updated');
    exit;
}

header('Location: ../complaints.php');
exit;
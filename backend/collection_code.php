<?php

require_once __DIR__ . "/../connection.php";
require_once __DIR__ . "/../classes/CollectionSchedule.php";

$schedule = new CollectionSchedule($pdo);

if (isset($_POST['delete_user'])) {
    $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $deleted = $id !== false && $id > 0 && $schedule->deleteUser($id);
    header('Location: ../index.php?' . ($deleted ? 'success=user_deleted' : 'error=user_delete'));
    exit;
}

if (isset($_POST['update_user'])) {
    $id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($id === false || $id < 1 || $fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $role === '' || ($password !== '' && strlen($password) < 6) || ($contact !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $contact))) {
        header('Location: ../index.php?error=user_update');
        exit;
    }
    $updated = $schedule->updateUser($id, $fullName, $email, $role, $contact === '' ? null : $contact, $password);
    header('Location: ../index.php?' . ($updated ? 'success=user_updated' : 'error=user_update'));
    exit;
}

if (isset($_POST['add_entity_record'])) {
    $entity = $_POST['entity'] ?? '';
    if ($entity === 'complaints') {
        $photo = $_FILES['photo_evidence'] ?? null;
        if (!$photo || $photo['error'] !== UPLOAD_ERR_OK || $photo['size'] > 5 * 1024 * 1024) {
            header('Location: ../index.php?error=record');
            exit;
        }
        $imageInfo = @getimagesize($photo['tmp_name']);
        $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mimeType = $imageInfo['mime'] ?? '';
        if (!$imageInfo || !isset($allowedTypes[$mimeType])) {
            header('Location: ../index.php?error=record');
            exit;
        }
        $uploadDirectory = __DIR__ . '/../uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true)) {
            header('Location: ../index.php?error=record');
            exit;
        }
        $fileName = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
        if (!move_uploaded_file($photo['tmp_name'], $uploadDirectory . '/' . $fileName)) {
            header('Location: ../index.php?error=record');
            exit;
        }
        $_POST['photo_evidence'] = $fileName;
    }
    $requiredFields = [
        'violators' => ['full_name', 'address', 'violation_count'],
        'collectors' => ['zone_id', 'user_id', 'vehicle_plate'],
        'residents' => ['user_id', 'zone_id', 'household_no', 'home_address'],
        'complaints' => ['resident_id', 'location', 'description', 'date_reported', 'photo_evidence', 'status'],
        'violations' => ['complaint_id', 'violator_id', 'fine_amount', 'date_issued', 'status'],
        'notifications' => ['user_id', 'message', 'date_sent', 'status'],
        'activity_logs' => ['user_id', 'action_taken', 'timestamp'],
    ];
    $missing = !isset($requiredFields[$entity]);
    foreach ($requiredFields[$entity] ?? [] as $field) {
        if (trim((string) ($_POST[$field] ?? '')) === '') {
            $missing = true;
        }
    }
    if ($entity === 'violations') {
        $issuedDate = DateTime::createFromFormat('Y-m-d', $_POST['date_issued'] ?? '');
        if (!$issuedDate || $issuedDate->format('Y-m-d') !== $_POST['date_issued']) {
            header('Location: ../index.php?error=record');
            exit;
        }
        $_POST['date_issued'] = $issuedDate->format('Ymd');
    }
    if ($missing) {
        header('Location: ../index.php?error=record');
        exit;
    }
    $result = $schedule->addEntityRecord($entity, $_POST);
    $redirect = $result && $entity === 'violators' ? '../page.php?view=violators&success=record_added' : '../index.php?' . ($result ? 'success=record_added' : 'error=record');
    header('Location: ' . $redirect);
    exit;
}

if (isset($_POST['add_user'])) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = trim($_POST['role'] ?? '');
    $contactNumber = trim($_POST['contact_number'] ?? '');

    if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6 || $role === '' || ($contactNumber !== '' && !preg_match('/^[0-9+() -]{7,20}$/', $contactNumber))) {
        header("Location: ../index.php?error=user");
        exit;
    }

    $contactNumber = $contactNumber === '' ? null : $contactNumber;
    $added = $schedule->addUser($fullName, $email, $password, $role, $contactNumber);
    header("Location: ../index.php?" . ($added ? 'success=user_added' : 'error=user'));
    exit;
}

if (isset($_POST['add_collection_schedule'])) {
    if (empty($_POST['collection_date']) || empty($_POST['time_slot']) || empty($_POST['user_id']) || empty($_POST['zone_id'])) {
        header("Location: ../page.php?view=schedules&error=add");
        exit;
    }

    $schedule->collectionDate = $_POST['collection_date'];
    $schedule->timeSlot = $_POST['time_slot'];
    $schedule->userId = (int) $_POST['user_id'];
    $schedule->zoneId = (int) $_POST['zone_id'];
    $schedule->status = $_POST['status'] ?? 'Scheduled';

    $result = $schedule->addCollectionSchedule();
    header("Location: ../page.php?view=schedules&" . ($result ? 'success=added&zone_id=' . $schedule->zoneId : 'error=add'));
    exit;
}

if (isset($_POST['delete_collection_schedule'])) {
    $id = filter_var($_POST['delete_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        header("Location: ../page.php?view=schedules&error=delete");
        exit;
    }

    header("Location: ../page.php?view=schedules&" . ($schedule->deleteCollectionSchedule($id) ? 'success=deleted' : 'error=delete'));
    exit;
}

if (isset($_POST['update_collection_schedule'])) {
    $id = filter_var($_POST['schedule_id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1 || empty($_POST['collection_date']) || empty($_POST['time_slot']) || empty($_POST['user_id']) || empty($_POST['zone_id'])) {
        header("Location: ../page.php?view=schedules&error=update");
        exit;
    }

    $schedule->collectionDate = $_POST['collection_date'];
    $schedule->timeSlot = $_POST['time_slot'];
    $schedule->userId = (int) $_POST['user_id'];
    $schedule->zoneId = (int) $_POST['zone_id'];
    $schedule->status = $_POST['status'] ?? 'Scheduled';

    $result = $schedule->updateCollectionSchedule($id);
    header("Location: ../page.php?view=schedules&" . ($result ? 'success=updated&zone_id=' . $schedule->zoneId : 'error=update'));
    exit;
}

header("Location: ../index.php?error=invalid");
exit;
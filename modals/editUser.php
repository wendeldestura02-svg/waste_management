<?php
require_once __DIR__ . '/../connection.php';
require_once __DIR__ . '/../classes/CollectionSchedule.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$schedule = new CollectionSchedule($pdo);
$user = $id && $id > 0 ? $schedule->getUser($id) : false;
if (!$user) {
    header('Location: ../index.php?error=user_update');
    exit;
}
?>
<!doctype html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Edit User</title><link rel="stylesheet" href="../style.css?v=11"></head>
<body><main class="container entity-form-page"><p class="eyebrow">USERS TABLE RECORD</p><h1>Edit User</h1><p class="form-intro">Update this user record. Leave the password blank to keep the current password.</p>
<form class="form-container entity-form" action="../backend/collection_code.php" method="post"><input type="hidden" name="user_id" value="<?= htmlspecialchars($user['user_id']) ?>"><label>Full Name</label><input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required><label>New Password</label><input type="password" name="password" minlength="6"><label>Role</label><select name="role" required><option value="Admin" <?= $user['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option><option value="Collector" <?= $user['role'] === 'Collector' ? 'selected' : '' ?>>Collector</option><option value="Resident" <?= $user['role'] === 'Resident' ? 'selected' : '' ?>>Resident</option></select><label>Contact Number</label><input type="tel" name="contact_number" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>"><div class="form-actions"><button type="submit" name="update_user">Save Changes</button><a href="../index.php#user-list">Cancel</a></div></form></main></body></html>

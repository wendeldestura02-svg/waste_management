<?php
require_once __DIR__ . '/connection.php';

$statuses = ['Pending', 'Under Review', 'Resolved'];
$selectedStatus = $_GET['status'] ?? '';
if (!in_array($selectedStatus, $statuses, true)) {
    $selectedStatus = '';
}
$keyword = trim($_GET['search'] ?? '');

$counts = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(Status = 'Pending') AS pending,
            SUM(Status = 'Under Review') AS under_review,
            SUM(Status = 'Resolved') AS resolved
     FROM complaints"
)->fetch();

$where = [];
$parameters = [];
if ($selectedStatus !== '') {
    $where[] = 'co.Status = :status';
    $parameters[':status'] = $selectedStatus;
}
if ($keyword !== '') {
    $where[] = '(CAST(co.ComplaintID AS CHAR) LIKE :keyword
        OR co.Location LIKE :keyword
        OR co.Description LIKE :keyword
        OR u.Fullname LIKE :keyword
        OR r.HomeAddress LIKE :keyword)';
    $parameters[':keyword'] = '%' . $keyword . '%';
}
$sql = 'SELECT co.ComplaintID AS complaint_id, co.Location AS location,
               co.Description AS description, co.DateReported AS date_reported,
               co.PhotoEvidence AS photo_evidence, co.Status AS status,
               r.ResidentID AS resident_id, r.HomeAddress AS home_address,
               u.Fullname AS resident_name
        FROM complaints co
        INNER JOIN residents r ON r.ResidentID = co.ResidentID
        INNER JOIN users u ON u.User_id = r.User_id'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY co.ComplaintID DESC';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$complaints = $statement->fetchAll();

$residents = $pdo->query(
    'SELECT r.ResidentID AS resident_id, r.HomeAddress AS home_address,
            r.HouseholdNo AS household_no, u.Fullname AS resident_name
     FROM residents r
     INNER JOIN users u ON u.User_id = r.User_id
     ORDER BY u.Fullname, r.ResidentID'
)->fetchAll();

$messages = [
    'created' => 'Complaint submitted successfully.',
    'updated' => 'Complaint status updated.',
    'create_error' => 'Unable to submit the complaint. Check the fields and evidence image.',
    'update_error' => 'Unable to update that complaint status.',
];
$noticeKey = $_GET['notice'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints | CleanRoute</title>
    <link rel="stylesheet" href="style.css?v=12">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><span class="brand-mark">♻</span><span><strong>CleanRoute</strong><small>Waste Management</small></span></div>
        <nav>
            <a class="nav-link" href="index.php"><span>⌂</span> Dashboard</a>
            <a class="nav-link" href="index.php#schedules"><span>▣</span> Schedules</a>
            <a class="nav-link" href="index.php#user-list"><span>♙</span> Users</a>
            <a class="nav-link" href="index.php#entity-zones"><span>⚑</span> Zones</a>
            <a class="nav-link active" href="complaints.php"><span>?</span> Complaints</a>
            <a class="nav-link" href="transaction.php"><span>⇄</span> Transactions</a>
        </nav>
        <div class="sidebar-note"><span>▣</span><p>Keep every route<br>clean and on time.</p></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div><p class="eyebrow">SERVICE REQUESTS</p><h1>Complaint Management</h1><p class="subheading">Review resident reports and track resolution.</p></div>
            <button class="primary-button" type="button" onclick="document.getElementById('complaintModal').style.display='flex'">＋ New Complaint</button>
        </header>
        <?php if (isset($messages[$noticeKey])): ?>
            <div class="notice <?= str_ends_with($noticeKey, 'error') ? 'error' : 'success' ?>"><?= htmlspecialchars($messages[$noticeKey]) ?></div>
        <?php endif; ?>
        <section class="stats-grid complaint-stats" aria-label="Complaint summary">
            <article class="stat-card blue"><span class="stat-icon">▤</span><div><strong><?= (int) $counts['total'] ?></strong><span>Total complaints</span></div></article>
            <article class="stat-card orange"><span class="stat-icon">!</span><div><strong><?= (int) $counts['pending'] ?></strong><span>Pending</span></div></article>
            <article class="stat-card purple"><span class="stat-icon">◷</span><div><strong><?= (int) $counts['under_review'] ?></strong><span>Under review</span></div></article>
            <article class="stat-card green"><span class="stat-icon">✓</span><div><strong><?= (int) $counts['resolved'] ?></strong><span>Resolved</span></div></article>
        </section>
        <section class="panel">
            <div class="panel-heading complaint-toolbar">
                <div><h2>Resident Complaints</h2><p><?= count($complaints) ?> matching record<?= count($complaints) === 1 ? '' : 's' ?></p></div>
                <form class="search-form complaint-filters" method="get">
                    <input type="search" name="search" value="<?= htmlspecialchars($keyword) ?>" placeholder="Search complaints..." aria-label="Search complaints">
                    <select name="status" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        <?php foreach ($statuses as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= $selectedStatus === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit">Filter</button>
                </form>
            </div>
            <div class="table-wrap">
                <table class="complaint-table">
                    <thead><tr><th>ID</th><th>Resident</th><th>Reported</th><th>Location</th><th>Description</th><th>Evidence</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if ($complaints): foreach ($complaints as $complaint): ?>
                        <tr>
                            <td>#<?= (int) $complaint['complaint_id'] ?></td>
                            <td><?= htmlspecialchars($complaint['resident_name']) ?><small class="cell-detail"><?= htmlspecialchars($complaint['home_address']) ?></small></td>
                            <td><?= htmlspecialchars($complaint['date_reported']) ?></td>
                            <td><?= htmlspecialchars($complaint['location']) ?></td>
                            <td class="complaint-description"><?= htmlspecialchars($complaint['description']) ?></td>
                            <td><?php $evidence = basename($complaint['photo_evidence']); $evidencePath = __DIR__ . '/uploads/' . $evidence; ?><?php if (is_file($evidencePath)): ?><a href="uploads/<?= rawurlencode($evidence) ?>" target="_blank" rel="noopener">View image</a><?php else: ?><span class="muted-text">Unavailable</span><?php endif; ?></td>
                            <td><form class="status-form" action="backend/complaints_code.php" method="post"><input type="hidden" name="complaint_id" value="<?= (int) $complaint['complaint_id'] ?>"><select name="status" aria-label="Status for complaint <?= (int) $complaint['complaint_id'] ?>"><?php foreach ($statuses as $status): ?><option value="<?= htmlspecialchars($status) ?>" <?= $complaint['status'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option><?php endforeach; ?></select><button type="submit" name="update_complaint_status">Save</button></form></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7" class="no-data">No complaints match these filters.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <footer>© 2026 CleanRoute Waste Management. All rights reserved.</footer>
    </main>
</div>
<div id="complaintModal" class="modal"><div class="modal-content"><button class="close" type="button" aria-label="Close" onclick="document.getElementById('complaintModal').style.display='none'">×</button><h2>Submit a Complaint</h2><p>Record a resident report and attach a JPG, PNG, or WebP image.</p>
    <?php if ($residents): ?>
    <form class="modal-form" action="backend/complaints_code.php" method="post" enctype="multipart/form-data">
        <label>Resident<select name="resident_id" required><option value="">Select resident</option><?php foreach ($residents as $resident): ?><option value="<?= (int) $resident['resident_id'] ?>"><?= htmlspecialchars($resident['resident_name'] . ' · Household ' . $resident['household_no'] . ' · ' . $resident['home_address']) ?></option><?php endforeach; ?></select></label>
        <label>Location<input name="location" maxlength="255" required></label>
        <label>Description<input name="description" maxlength="255" required></label>
        <label>Date reported<input type="date" name="date_reported" value="<?= date('Y-m-d') ?>" required></label>
        <label>Photo evidence<input type="file" name="photo_evidence" accept="image/jpeg,image/png,image/webp" required></label>
        <button class="primary-button" type="submit" name="create_complaint">Submit Complaint</button>
    </form>
    <?php else: ?><p class="empty-residents">Add a resident record before submitting a complaint.</p><?php endif; ?>
</div></div>
</body>
</html>
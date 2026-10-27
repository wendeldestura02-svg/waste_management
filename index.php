<?php

require_once __DIR__ . "/connection.php";
require_once __DIR__ . "/classes/CollectionSchedule.php";

$schedule = new CollectionSchedule($pdo);
$keyword = $_GET['search'] ?? '';
$schedules = $keyword !== ''
    ? $schedule->searchCollectionSchedules($keyword)
    : $schedule->getCollectionSchedules();
$totalSchedules = (int) $pdo->query("SELECT COUNT(*) FROM collection_schedule")->fetchColumn();
$totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalZones = (int) $pdo->query("SELECT COUNT(*) FROM zones")->fetchColumn();
$usersList = $schedule->getAllUsers();
$selectedZoneId = filter_var($_GET['zone_id'] ?? null, FILTER_VALIDATE_INT);
$selectedZoneId = $selectedZoneId !== false && $selectedZoneId > 0 ? $selectedZoneId : null;
if ($selectedZoneId !== null) {
    $zoneExists = $pdo->prepare('SELECT 1 FROM zones WHERE Zone_ID = :zone_id');
    $zoneExists->execute([':zone_id' => $selectedZoneId]);
    if (!$zoneExists->fetchColumn()) {
        $selectedZoneId = null;
    }
}
$entityPreviews = $schedule->getEntityPreviews($selectedZoneId);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleanRoute Waste Management</title>
    <link rel="stylesheet" href="style.css?v=12">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><span class="brand-mark">♻</span><span><strong>CleanRoute</strong><small>Waste Management</small></span></div>
        <nav>
            <a class="nav-link active" href="index.php"><span>⌂</span> Dashboard</a>
            <a class="nav-link" href="#schedules"><span>▣</span> Schedules</a>
            <a class="nav-link" href="#user-list"><span>♙</span> Users</a>
            <a class="nav-link" href="#entity-zones"><span>⚑</span> Zones</a>
            <a class="nav-link" href="#database"><span>▥</span> ERD Tables</a>
            <a class="nav-link" href="#entity-collectors"><span>▣</span> Collectors</a>
            <a class="nav-link" href="#entity-residents"><span>♙</span> Residents</a>
            <a class="nav-link" href="#entity-violators"><span>!</span> Violators</a>
            <a class="nav-link" href="complaints.php"><span>?</span> Complaints</a>
            <a class="nav-link" href="#entity-violations"><span>!</span> Violations</a>
            <a class="nav-link" href="#entity-notifications"><span>●</span> Notifications</a>
            <a class="nav-link" href="#entity-activity-logs"><span>▤</span> Activity Logs</a>
            <a class="nav-link" href="transaction.php"><span>⇄</span> Transactions</a>
        </nav>
        <div class="sidebar-note"><span>▣</span><p>Keep every route<br>clean and on time.</p></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div><p class="eyebrow">OPERATIONS OVERVIEW</p><h1>Waste Collection Dashboard</h1><p class="subheading">Manage routes, residents, and collection schedules.</p></div>
            <div class="profile" aria-label="Current user"><span class="profile-icon">●</span> Admin User</div>
        </header>
        <?php if (isset($_GET['success'])): ?><div class="notice success"><?= $_GET['success'] === 'user_added' ? 'User added successfully.' : ($_GET['success'] === 'user_updated' ? 'User updated successfully.' : ($_GET['success'] === 'user_deleted' ? 'User deleted successfully.' : ($_GET['success'] === 'record_added' ? 'Record added successfully.' : 'Schedule ' . htmlspecialchars($_GET['success']) . ' successfully.'))) ?></div><?php elseif (isset($_GET['error'])): ?><div class="notice error"><?= $_GET['error'] === 'user' ? 'Unable to add user. Check all fields and try again.' : ($_GET['error'] === 'user_update' ? 'Unable to update user. Check the fields and database relationships.' : ($_GET['error'] === 'user_delete' ? 'Unable to delete user. This user may still be linked to other ERD records.' : ($_GET['error'] === 'record' ? 'Unable to add record. Check the required fields.' : 'Unable to ' . htmlspecialchars($_GET['error']) . ' schedule. Please try again.'))) ?></div><?php endif; ?>
        <section class="stats-grid" aria-label="Summary">
            <article class="stat-card purple"><span class="stat-icon">▣</span><div><strong><?= $totalSchedules ?></strong><span>Total Schedules</span><a href="#schedules">View all schedules</a></div></article>
            <article class="stat-card blue"><span class="stat-icon">♙</span><div><strong><?= $totalUsers ?></strong><span>Registered Users</span><a href="#users">View all users</a></div></article>
            <article class="stat-card green"><span class="stat-icon">⚑</span><div><strong><?= $totalZones ?></strong><span>Active Zones</span><a href="#zones">View all zones</a></div></article>
            <article class="stat-card orange"><span class="stat-icon">●</span><div><strong><?= $totalSchedules ?></strong><span>Scheduled Routes</span><a href="#schedules">View schedule</a></div></article>
        </section>
        <section class="panel" id="schedules">
            <div class="panel-heading"><div><h2>Collection Schedules</h2><p>List of all waste collection schedules</p></div><form class="search-form" method="GET"><input type="search" name="search" value="<?= htmlspecialchars($keyword) ?>" placeholder="Search schedule..."><button type="submit">Search</button></form><button class="primary-button" type="button" onclick="document.getElementById('addModal').style.display='flex'">＋ Add Schedule</button></div>
            <div class="table-wrap"><table><thead><tr><th>ID</th><th>Collection Date</th><th>Time Slot</th><th>User</th><th>Zone</th><th>Vehicle</th><th>Status</th><th>Action</th></tr></thead><tbody>
            <?php if ($schedules): while ($row = $schedules->fetch()): ?><tr><td><?= htmlspecialchars($row['schedule_id']) ?></td><td><?= htmlspecialchars($row['collection_date']) ?></td><td><?= htmlspecialchars($row['time_slot']) ?></td><td class="person-cell"><span class="avatar"><?= htmlspecialchars(strtoupper(substr($row['user_name'] ?? 'U', 0, 1))) ?></span><?= htmlspecialchars($row['user_name'] ?? 'Unassigned') ?></td><td><?= htmlspecialchars($row['zone_name'] ?? 'Unassigned') ?></td><td><?= htmlspecialchars($row['vehicle_plate'] ?? '—') ?></td><td><span class="status-pill"><?= htmlspecialchars($row['status'] ?? 'Scheduled') ?></span></td><td class="actions"><a class="edit" href="modals/updateCollection.php?id=<?= urlencode($row['schedule_id']) ?>">Edit</a><form action="backend/collection_code.php" method="POST" onsubmit="return confirm('Delete this schedule?');"><input type="hidden" name="delete_id" value="<?= htmlspecialchars($row['schedule_id']) ?>"><button class="delete" type="submit" name="delete_collection_schedule">Delete</button></form></td></tr><?php endwhile; else: ?><tr><td colspan="8" class="no-data">No collection schedules found.</td></tr><?php endif; ?>
            </tbody></table></div><div class="panel-footer"><span>Showing <?= $totalSchedules ?> schedule<?= $totalSchedules === 1 ? '' : 's' ?></span><span class="page-number">1</span></div>
        </section>
        <section class="reference-grid" id="users">
            <article class="reference-panel"><div class="reference-heading"><h2>Users</h2><button class="small-button" type="button" onclick="document.getElementById('userModal').style.display='flex'">＋ Add User</button></div><p>People assigned to collection work.</p><strong><?= $totalUsers ?> registered users</strong></article>
            <article class="reference-panel" id="zones"><div class="reference-heading"><h2>Zones</h2><form class="zone-filter" method="GET"><select name="zone_id" onchange="this.form.submit()"><option value="">View a zone</option><?php $zoneChoices = $schedule->getZones(); while ($choice = $zoneChoices->fetch()): ?><option value="<?= htmlspecialchars($choice['zone_id']) ?>" <?= $selectedZoneId == $choice['zone_id'] ? 'selected' : '' ?>><?= htmlspecialchars($choice['zone_name']) ?></option><?php endwhile; ?></select></form></div><p><?= $selectedZoneId ? 'Showing only the selected collection zone.' : 'Choose a zone to view its connected records.' ?></p><strong><?= $selectedZoneId ? 'Zone ' . $selectedZoneId : $totalZones . ' zones available' ?></strong></article>
            <article class="reference-panel"><h2>ERD Tables</h2><p>Users, Zones, Collectors, Residents, Schedules, Complaints, Violations, Notifications, and Activity Logs.</p><strong>10 connected tables</strong></article>
        </section>
        <section class="panel user-list-panel" id="user-list">
            <div class="panel-heading"><div><h2>Registered Users</h2><p>Users currently stored in the Users table.</p></div></div>
            <div class="table-wrap"><table><thead><tr><th>User ID</th><th>Full Name</th><th>Email</th><th>Role</th><th>Contact Number</th><th>Actions</th></tr></thead><tbody>
            <?php while ($user = $usersList->fetch()): ?><tr><td><?= htmlspecialchars($user['user_id']) ?></td><td><?= htmlspecialchars($user['full_name']) ?></td><td><?= htmlspecialchars($user['email']) ?></td><td><span class="status-pill role-pill"><?= htmlspecialchars($user['role']) ?></span></td><td><?= htmlspecialchars($user['contact_number'] ?? 'Not provided') ?></td><td class="actions"><a class="edit" href="modals/editUser.php?id=<?= urlencode($user['user_id']) ?>">Edit</a><form action="backend/collection_code.php" method="post" onsubmit="return confirm('Delete this user?');"><input type="hidden" name="user_id" value="<?= htmlspecialchars($user['user_id']) ?>"><button class="delete" type="submit" name="delete_user">Delete</button></form></td></tr><?php endwhile; ?>
            </tbody></table></div>
        </section>
        <section class="data-section" id="database">
            <div class="section-title"><p class="eyebrow">DATABASE OVERVIEW</p><h2>All ERD Records</h2><p>Live previews from every connected table.</p></div>
            <div class="entity-grid">
            <?php foreach ($entityPreviews as $entityName => $rows): ?>
                <article class="entity-panel" id="entity-<?= htmlspecialchars(strtolower(str_replace(' ', '-', $entityName))) ?>"><div class="entity-heading"><h3><?= htmlspecialchars($entityName) ?></h3><span><?= count($rows) ?> shown</span><?php if ($entityName !== 'Users' && $entityName !== 'Zones' && $entityName !== 'Collection Schedule'): ?><a class="small-button" href="modals/addEntity.php?entity=<?= urlencode(strtolower(str_replace(' ', '_', $entityName))) ?>">＋ Add</a><?php endif; ?></div><div class="table-wrap"><table><thead><tr><?php foreach (array_keys($rows[0] ?? ['No records' => '']) as $column): ?><th><?= htmlspecialchars(str_replace('_', ' ', $column)) ?></th><?php endforeach; ?></tr></thead><tbody><?php if ($rows): foreach ($rows as $entityRow): ?><tr><?php foreach ($entityRow as $value): ?><td><?= htmlspecialchars((string) ($value ?? '-')) ?></td><?php endforeach; ?></tr><?php endforeach; else: ?><tr><td>No records yet.</td></tr><?php endif; ?></tbody></table></div></article>
            <?php endforeach; ?>
            </div>
        </section>
        <footer>© 2026 CleanRoute Waste Management. All rights reserved.</footer>
    </main>
</div>
<div id="addModal" class="modal"><div class="modal-content"><button class="close" type="button" onclick="document.getElementById('addModal').style.display='none'">×</button><h2>Add Collection Schedule</h2><p>Create one route using the fields from Collection_Schedule.</p><form class="modal-form" action="backend/collection_code.php" method="POST"><label>Collection Date<input type="date" name="collection_date" required></label><label>Time Slot<input type="time" name="time_slot" required></label><label>User<select name="user_id" required><option value="">Select User</option><?php $users = $schedule->getUsers(); while ($user = $users->fetch()): ?><option value="<?= htmlspecialchars($user['user_id']) ?>"><?= htmlspecialchars($user['user_name']) ?></option><?php endwhile; ?></select></label><label>Zone<select name="zone_id" required><option value="">Select Zone</option><?php $zones = $schedule->getZones(); while ($zone = $zones->fetch()): ?><option value="<?= htmlspecialchars($zone['zone_id']) ?>"><?= htmlspecialchars($zone['zone_name']) ?></option><?php endwhile; ?></select></label><label>Status<select name="status"><option value="Scheduled">Scheduled</option><option value="Completed">Completed</option><option value="Cancelled">Cancelled</option></select></label><button class="primary-button" type="submit" name="add_collection_schedule">Add Schedule</button></form></div></div>
<div id="userModal" class="modal"><div class="modal-content"><button class="close" type="button" onclick="document.getElementById('userModal').style.display='none'">×</button><h2>Add User</h2><p>Create a record in the Users table.</p><form class="modal-form" action="backend/collection_code.php" method="POST"><label>Full Name<input type="text" name="full_name" required></label><label>Email<input type="email" name="email" required></label><label>Password<input type="password" name="password" minlength="6" required></label><label>Role<select name="role" required><option value="">Select Role</option><option value="Admin">Admin</option><option value="Collector">Collector</option><option value="Resident">Resident</option></select></label><label>Contact Number<input type="tel" name="contact_number" inputmode="numeric"></label><button class="primary-button" type="submit" name="add_user">Add User</button></form></div></div>
</body>
</html>

<?php
session_start();

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/classes/Transaction.php';

$transaction = new Transaction($pdo);

// ------------------------------------------------------------------
// Handle a submitted transaction, then redirect (so refreshing the
// page does not run the same transaction again).
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invalid = ['status' => 'error', 'message' => 'Please check the fields and try again.', 'steps' => []];

    if (isset($_POST['issue_violation'])) {
        $title = 'Transaction 1: Issue Violation';
        $complaintId = filter_var($_POST['complaint_id'] ?? null, FILTER_VALIDATE_INT);
        $violatorId = filter_var($_POST['violator_id'] ?? null, FILTER_VALIDATE_INT);
        $fine = filter_var($_POST['fine_amount'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
        $result = ($complaintId === false || $complaintId < 1 || $violatorId === false || $violatorId < 1 || $fine === false)
            ? $invalid
            : $transaction->issueViolation($complaintId, $violatorId, $fine);
    } elseif (isset($_POST['complete_collection'])) {
        $title = 'Transaction 2: Complete Collection';
        $scheduleId = filter_var($_POST['schedule_id'] ?? null, FILTER_VALIDATE_INT);
        $result = ($scheduleId === false || $scheduleId < 1)
            ? $invalid
            : $transaction->completeCollection($scheduleId);
    } elseif (isset($_POST['register_resident'])) {
        $title = 'Transaction 3: Register Resident';
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $contact = trim($_POST['contact_number'] ?? '');
        $zoneId = filter_var($_POST['zone_id'] ?? null, FILTER_VALIDATE_INT);
        $householdNo = filter_var($_POST['household_no'] ?? null, FILTER_VALIDATE_INT);
        $homeAddress = trim($_POST['home_address'] ?? '');
        $valid = $fullName !== '' && strlen($fullName) <= 100
            && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100
            && strlen($password) >= 6
            && ($contact === '' || preg_match('/^[0-9+\-\s]{1,20}$/', $contact))
            && $zoneId !== false && $zoneId > 0
            && $householdNo !== false && $householdNo > 0
            && $homeAddress !== '' && strlen($homeAddress) <= 255;
        $result = !$valid
            ? $invalid
            : $transaction->registerResident($fullName, $email, $password, $contact === '' ? null : $contact, $zoneId, $householdNo, $homeAddress);
    }

    if (isset($result)) {
        $result['title'] = $title;
        $_SESSION['transaction_result'] = $result;
    }
    header('Location: transaction.php');
    exit;
}

$result = $_SESSION['transaction_result'] ?? null;
unset($_SESSION['transaction_result']);

// ------------------------------------------------------------------
// Data for the forms and the live tables
// ------------------------------------------------------------------
$complaints = $transaction->getComplaints();
$violators = $transaction->getViolators();
$schedules = $transaction->getSchedules();
$zones = $transaction->getZones();

$complaintRows = array_map(static fn ($c) => [
    'ID' => $c['id'], 'Resident' => $c['resident_name'], 'Status' => $c['status'], 'Violations' => $c['violations'],
], $complaints);

function renderRows(array $rows): void
{
    $columns = array_keys($rows[0] ?? ['No records' => '']);
    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . htmlspecialchars(str_replace('_', ' ', (string) $column)) . '</th>';
    }
    echo '</tr></thead><tbody>';
    if ($rows) {
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars((string) ($value ?? '-')) . '</td>';
            }
            echo '</tr>';
        }
    } else {
        echo '<tr><td>No records yet.</td></tr>';
    }
    echo '</tbody></table></div>';
}

$livePanels = [
    'Complaints' => $complaintRows,
    'Violators' => $violators,
    'Violations' => $transaction->getRecentViolations(),
    'Collection Schedule' => $schedules,
    'Residents' => $transaction->getRecentResidents(),
    'Notifications' => $transaction->getRecentNotifications(),
    'Activity Logs' => $transaction->getRecentLogs(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Demo | CleanRoute</title>
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
            <a class="nav-link" href="complaints.php"><span>?</span> Complaints</a>
            <a class="nav-link active" href="transaction.php"><span>⇄</span> Transactions</a>
        </nav>
        <div class="sidebar-note"><span>▣</span><p>Keep every route<br>clean and on time.</p></div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div><p class="eyebrow">TRANSACTION CONTROL LANGUAGE</p><h1>Transaction Demo</h1><p class="subheading">Each transaction changes several tables at once: either everything is saved (COMMIT) or nothing is (ROLLBACK).</p></div>
            <div class="profile" aria-label="Current user"><span class="profile-icon">●</span> Admin User</div>
        </header>

        <?php if ($result): ?>
        <section class="txn-result" aria-live="polite">
            <div class="notice <?= $result['status'] === 'success' ? 'success' : 'error' ?>"><?= htmlspecialchars($result['title']) ?> — <?= htmlspecialchars($result['message']) ?></div>
            <?php if ($result['steps']): ?>
            <ol class="txn-steps">
                <?php foreach ($result['steps'] as $step): ?>
                <li class="<?= $step === 'COMMIT' ? 'step-commit' : ($step === 'ROLLBACK' ? 'step-rollback' : '') ?>"><?= htmlspecialchars($step) ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="txn-grid">
            <article class="txn-card">
                <span class="txn-badge">TRANSACTION 1</span>
                <h2>Issue Violation</h2>
                <p>Complaints · Violations · Violators · Notifications · Activity_Logs</p>
                <p class="txn-hint">Rollback test: issue a violation to a complaint that already has one.</p>
                <?php if ($complaints && $violators): ?>
                <form class="modal-form" method="post">
                    <label>Complaint<select name="complaint_id" required><option value="">Select complaint</option><?php foreach ($complaints as $c): ?><option value="<?= (int) $c['id'] ?>">#<?= (int) $c['id'] ?> · <?= htmlspecialchars($c['resident_name']) ?> · <?= htmlspecialchars($c['status']) ?> · <?= (int) $c['violations'] ?> violation<?= (int) $c['violations'] === 1 ? '' : 's' ?></option><?php endforeach; ?></select></label>
                    <label>Violator<select name="violator_id" required><option value="">Select violator</option><?php foreach ($violators as $v): ?><option value="<?= (int) $v['id'] ?>">#<?= (int) $v['id'] ?> · <?= htmlspecialchars($v['name']) ?> · <?= (int) $v['violation_count'] ?> prior</option><?php endforeach; ?></select></label>
                    <label>Fine Amount (₱)<input type="number" name="fine_amount" min="1" max="1000000" value="500" required></label>
                    <button class="primary-button" type="submit" name="issue_violation">Run Transaction 1</button>
                </form>
                <?php else: ?>
                <p class="empty-residents">Needs at least one complaint and one violator. Import <code>transaction_demo.sql</code> to add sample violators.</p>
                <?php endif; ?>
            </article>

            <article class="txn-card">
                <span class="txn-badge">TRANSACTION 2</span>
                <h2>Complete Collection</h2>
                <p>Collection_Schedule · Notifications · Activity_Logs</p>
                <p class="txn-hint">Rollback test: pick a schedule that is already Completed, or whose zone has 0 residents.</p>
                <?php if ($schedules): ?>
                <form class="modal-form" method="post">
                    <label>Collection Schedule<select name="schedule_id" required><option value="">Select schedule</option><?php foreach ($schedules as $s): ?><option value="<?= (int) $s['id'] ?>">#<?= (int) $s['id'] ?> · <?= htmlspecialchars($s['collection_date']) ?> · <?= htmlspecialchars($s['zone_name']) ?> · <?= htmlspecialchars($s['status']) ?> · <?= (int) $s['residents'] ?> resident<?= (int) $s['residents'] === 1 ? '' : 's' ?></option><?php endforeach; ?></select></label>
                    <button class="primary-button" type="submit" name="complete_collection">Run Transaction 2</button>
                </form>
                <?php else: ?>
                <p class="empty-residents">Add a collection schedule on the dashboard first.</p>
                <?php endif; ?>
            </article>

            <article class="txn-card">
                <span class="txn-badge">TRANSACTION 3</span>
                <h2>Register Resident</h2>
                <p>Users · Residents · Notifications · Activity_Logs</p>
                <p class="txn-hint">Rollback test: reuse an existing email, or a household number already used in that zone.</p>
                <?php if ($zones): ?>
                <form class="modal-form" method="post">
                    <label>Full Name<input type="text" name="full_name" maxlength="100" required></label>
                    <label>Email<input type="email" name="email" maxlength="100" required></label>
                    <label>Password<input type="password" name="password" minlength="6" required></label>
                    <label>Contact Number<input type="tel" name="contact_number" inputmode="numeric" maxlength="20"></label>
                    <label>Zone<select name="zone_id" required><option value="">Select zone</option><?php foreach ($zones as $z): ?><option value="<?= (int) $z['id'] ?>"><?= htmlspecialchars($z['name']) ?></option><?php endforeach; ?></select></label>
                    <label>Household No<input type="number" name="household_no" min="1" required></label>
                    <label>Home Address<input type="text" name="home_address" maxlength="255" required></label>
                    <button class="primary-button" type="submit" name="register_resident">Run Transaction 3</button>
                </form>
                <?php else: ?>
                <p class="empty-residents">Add a zone first.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="data-section" id="database">
            <div class="section-title"><p class="eyebrow">LIVE DATA</p><h2>What the Transactions Changed</h2><p>Compare these tables before and after each run. After a ROLLBACK nothing here changes (auto-increment IDs are still used up, so a gap in IDs is normal).</p></div>
            <div class="entity-grid">
            <?php foreach ($livePanels as $name => $rows): ?>
                <article class="entity-panel"><div class="entity-heading"><h3><?= htmlspecialchars($name) ?></h3><span><?= count($rows) ?> shown</span></div><?php renderRows($rows); ?></article>
            <?php endforeach; ?>
            </div>
        </section>
        <footer>© 2026 CleanRoute Waste Management. All rights reserved.</footer>
    </main>
</div>
</body>
</html>

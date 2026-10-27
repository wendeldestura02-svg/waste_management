<?php
require_once __DIR__ . "/../connection.php";
require_once __DIR__ . "/../classes/CollectionSchedule.php";
$schedule = new CollectionSchedule($pdo);
?>
<div class="modal">
    <div class="modal-content">
        <h2>Add Collection Schedule</h2>
        <form action="../backend/collection_code.php" method="POST">
            <label>Collection Date</label>
            <input type="date" name="collection_date" required>
            <label>User</label>
            <select name="user_id" required>
                <option value="">Select User</option>
                <?php $users = $schedule->getUsers(); while ($user = $users->fetch()): ?>
                    <option value="<?= htmlspecialchars($user['user_id']) ?>"><?= htmlspecialchars($user['user_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <label>Zone</label>
            <select name="zone_id" required>
                <option value="">Select Zone</option>
                <?php $zones = $schedule->getZones(); while ($zone = $zones->fetch()): ?>
                    <option value="<?= htmlspecialchars($zone['zone_id']) ?>"><?= htmlspecialchars($zone['zone_name']) ?></option>
                <?php endwhile; ?>
            </select>
            <label>Status</label>
            <select name="status">
                <option value="Scheduled">Scheduled</option>
                <option value="Collected">Collected</option>
                <option value="Cancelled">Cancelled</option>
            </select>
            <button type="submit" name="add_collection_schedule">Add Schedule</button>
        </form>
    </div>
</div>

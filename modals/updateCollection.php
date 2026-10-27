<?php

require_once __DIR__ . "/../connection.php";
require_once __DIR__ . "/../classes/CollectionSchedule.php";

$schedule = new CollectionSchedule($pdo);


if (!isset($_GET['id'])) {

    header("Location: ../index.php");
    exit;

}

$id = $_GET['id'];

$data = $schedule->getCollectionSchedule((int) $id);

if (!$data) {

    echo "Registration not found.";
    exit;

}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Update Collection Schedule</title>

    <link
        rel="stylesheet"
        href="../style.css?v=11"
    >

</head>

<body>

<div class="container">

    <p class="eyebrow">COLLECTION SCHEDULE</p>
    <h1>Edit Collection Schedule</h1>
    <p class="form-intro">Update the route date, time, assigned user, zone, or status.</p>


    <form
        action="../backend/collection_code.php"
        method="POST"
        class="form-container"
    >

        <input
            type="hidden"
            name="schedule_id"
            value="<?php
                echo $data['schedule_id'];
            ?>"
        >


        <label>Collection Date</label>

        <input
            type="date"
            name="collection_date"
            value="<?php
                echo $data['collection_date'];
            ?>"
            required
        >


        <label>Time Slot</label>

        <input
            type="time"
            name="time_slot"
            value="<?php echo htmlspecialchars($data['time_slot']); ?>"
            required
        >


        <label>User</label>

        <select
            name="user_id"
            required
        >

            <?php

            $participants = $schedule->getUsers();

            while ($participant =
                $participants->fetch()):

            ?>

                <option
                    value="<?php
                        echo $participant['user_id'];
                    ?>"
                    <?php

                    if (
                        $participant['user_id']
                        == $data['user_id']
                    ) {
                        echo "selected";
                    }

                    ?>
                >

                    <?php
                    echo htmlspecialchars(
                        $participant['user_name']
                    );
                    ?>

                </option>

            <?php endwhile; ?>

        </select>


        <label>Zone</label>

        <select
            name="zone_id"
            required
        >

            <?php

            $events = $schedule->getZones();

            while ($event =
                $events->fetch()):

            ?>

                <option
                    value="<?php
                        echo $event['zone_id'];
                    ?>"
                    <?php

                    if (
                        $event['zone_id']
                        == $data['zone_id']
                    ) {
                        echo "selected";
                    }

                    ?>
                >

                    <?php
                    echo htmlspecialchars(
                        $event['zone_name']
                    );
                    ?>

                </option>

            <?php endwhile; ?>

        </select>


        <label>Status</label>

        <select name="status">
            <option value="Scheduled" <?= $data['status'] === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
            <option value="Completed" <?= $data['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= $data['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>


        <div class="form-actions">
            <button type="submit" name="update_collection_schedule">Save Changes</button>
            <a href="../index.php">Cancel</a>
        </div>

    </form>

</div>

</body>

</html>
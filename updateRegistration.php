<?php

require_once "modals/connection.php";
require_once "classes/Registration.php";

$registration = new Registration($pdo);


if (!isset($_GET['id'])) {

    header("Location: index.php");
    exit;

}

$id = $_GET['id'];

$data =
    $registration->getRegistration($id);

if (!$data) {

    echo "Registration not found.";
    exit;

}

?>

<!DOCTYPE html>

<html>

<head>

    <title>Update Registration</title>

    <link
        rel="stylesheet"
        href="style.css"
    >

</head>

<body>

<div class="container">

    <h1>Update Registration</h1>


    <form
        action="backend/registrations_code.php"
        method="POST"
        class="form-container"
    >

        <input
            type="hidden"
            name="registration_id"
            value="<?php
                echo $data['registration_id'];
            ?>"
        >


        <label>Registration Date</label>

        <input
            type="date"
            name="registration_date"
            value="<?php
                echo $data['registration_date'];
            ?>"
            required
        >


        <label>Participant</label>

        <select
            name="participant_id"
            required
        >

            <?php

            $participants =
                $registration->getParticipants();

            while ($participant =
                $participants->fetch()):

            ?>

                <option
                    value="<?php
                        echo $participant['participant_id'];
                    ?>"
                    <?php

                    if (
                        $participant['participant_id']
                        == $data['participant_id']
                    ) {
                        echo "selected";
                    }

                    ?>
                >

                    <?php
                    echo htmlspecialchars(
                        $participant['first_name']
                        . " "
                        . $participant['last_name']
                    );
                    ?>

                </option>

            <?php endwhile; ?>

        </select>


        <label>Event</label>

        <select
            name="event_id"
            required
        >

            <?php

            $events =
                $registration->getEvents();

            while ($event =
                $events->fetch()):

            ?>

                <option
                    value="<?php
                        echo $event['event_id'];
                    ?>"
                    <?php

                    if (
                        $event['event_id']
                        == $data['event_id']
                    ) {
                        echo "selected";
                    }

                    ?>
                >

                    <?php
                    echo htmlspecialchars(
                        $event['event_name']
                    );
                    ?>

                </option>

            <?php endwhile; ?>

        </select>


        <button
            type="submit"
            name="update_registration"
        >
            Update Registration
        </button>


        <a href="index.php">
            Cancel
        </a>

    </form>

</div>

</body>

</html>
<?php

require_once "../modals/connection.php";
require_once "../classes/Registration.php";

$registration = new Registration($pdo);



if (isset($_POST['add_registration'])) {

    $registration->registration_date =
        $_POST['registration_date'];

    $registration->participant_id =
        $_POST['participant_id'];

    $registration->event_id =
        $_POST['event_id'];

    if ($registration->addRegistration()) {

        header("Location: ../index.php?success=added");
        exit;

    } else {

        header("Location: ../index.php?error=add");
        exit;
    }
}

if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    if ($registration->deleteRegistration($id)) {

        header("Location: ../index.php?success=deleted");
        exit;

    } else {

        header("Location: ../index.php?error=delete");
        exit;
    }
}


if (isset($_POST['update_registration'])) {

    $id = $_POST['registration_id'];

    $registration->registration_date =
        $_POST['registration_date'];

    $registration->participant_id =
        $_POST['participant_id'];

    $registration->event_id =
        $_POST['event_id'];

    if ($registration->updateRegistration($id)) {

        header("Location: ../index.php?success=updated");
        exit;

    } else {

        header("Location: ../index.php?error=update");
        exit;
    }
}

?>
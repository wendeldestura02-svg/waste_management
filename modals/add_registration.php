<div class="modal">
    
    <div class="modal-content">

        <h2>Add Registration</h2>

        <form
            action="backend/registrations_code.php"
            method="POST"
        >

            <label>Registration Date</label>

            <input
                type="date"
                name="registration_date"
                required
            >


            <label>Participant</label>

            <select
                name="participant_id"
                required
            >

                <option value="">
                    Select Participant
                </option>

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

                <option value="">
                    Select Event
                </option>

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
                name="add_registration"
            >
                Add Registration
            </button>

        </form>

    </div>

</div>
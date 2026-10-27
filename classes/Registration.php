<?php

class Registration
{
    private $conn;

    public $registration_id;
    public $registration_date;
    public $participant_id;
    public $event_id;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    // ==========================================
    // ADD REGISTRATION
    // ==========================================

    public function addRegistration()
    {
        try {

            $sql = "INSERT INTO registrations
                    (registration_date, participant_id, event_id)
                    VALUES
                    (:registration_date, :participant_id, :event_id)";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(
                ":registration_date",
                $this->registration_date
            );

            $stmt->bindParam(
                ":participant_id",
                $this->participant_id,
                PDO::PARAM_INT
            );

            $stmt->bindParam(
                ":event_id",
                $this->event_id,
                PDO::PARAM_INT
            );

            return $stmt->execute();

        } catch (PDOException $e) {

            echo "Error adding registration: "
                . $e->getMessage();

            return false;
        }
    }


    public function getRegistrations()
    {
        try {

            $sql = "SELECT
                        r.registration_id,
                        r.registration_date,
                        r.participant_id,
                        r.event_id,

                        CONCAT(
                            p.first_name,
                            ' ',
                            p.last_name
                        ) AS participant_name,

                        e.event_name,
                        e.venue,
                        e.registration_fee

                    FROM registrations r

                    INNER JOIN participants p
                        ON r.participant_id = p.participant_id

                    INNER JOIN events e
                        ON r.event_id = e.event_id

                    ORDER BY r.registration_id DESC";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute();

            return $stmt;

        } catch (PDOException $e) {

            echo "Error fetching registrations: "
                . $e->getMessage();

            return false;
        }
    }

    public function getRegistration($id)
    {
        try {

            $sql = "SELECT *
                    FROM registrations
                    WHERE registration_id = :id";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(
                ":id",
                $id,
                PDO::PARAM_INT
            );

            $stmt->execute();

            return $stmt->fetch();

        } catch (PDOException $e) {

            echo "Error fetching registration: "
                . $e->getMessage();

            return false;
        }
    }

    public function updateRegistration($id)
    {
        try {

            $sql = "UPDATE registrations
                    SET
                        registration_date = :registration_date,
                        participant_id = :participant_id,
                        event_id = :event_id
                    WHERE registration_id = :id";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(
                ":registration_date",
                $this->registration_date
            );

            $stmt->bindParam(
                ":participant_id",
                $this->participant_id,
                PDO::PARAM_INT
            );

            $stmt->bindParam(
                ":event_id",
                $this->event_id,
                PDO::PARAM_INT
            );

            $stmt->bindParam(
                ":id",
                $id,
                PDO::PARAM_INT
            );

            return $stmt->execute();

        } catch (PDOException $e) {

            echo "Error updating registration: "
                . $e->getMessage();

            return false;
        }
    }

    public function deleteRegistration($id)
    {
        try {

            $sql = "DELETE FROM registrations
                    WHERE registration_id = :id";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(
                ":id",
                $id,
                PDO::PARAM_INT
            );

            return $stmt->execute();

        } catch (PDOException $e) {

            echo "Error deleting registration: "
                . $e->getMessage();

            return false;
        }
    }

    public function searchRegistrations($keyword)
    {
        try {

            $sql = "SELECT
                        r.registration_id,
                        r.registration_date,

                        CONCAT(
                            p.first_name,
                            ' ',
                            p.last_name
                        ) AS participant_name,

                        e.event_name,
                        e.venue,
                        e.registration_fee

                    FROM registrations r

                    INNER JOIN participants p
                        ON r.participant_id = p.participant_id

                    INNER JOIN events e
                        ON r.event_id = e.event_id

                    WHERE
                        r.registration_id LIKE :keyword
                        OR r.registration_date LIKE :keyword
                        OR p.first_name LIKE :keyword
                        OR p.last_name LIKE :keyword
                        OR e.event_name LIKE :keyword
                        OR e.venue LIKE :keyword

                    ORDER BY r.registration_id DESC";

            $stmt = $this->conn->prepare($sql);

            $searchKeyword = "%" . $keyword . "%";

            $stmt->bindParam(
                ":keyword",
                $searchKeyword
            );

            $stmt->execute();

            return $stmt;

        } catch (PDOException $e) {

            echo "Error searching registrations: "
                . $e->getMessage();

            return false;
        }
    }

    public function getParticipants()
    {
        try {

            $sql = "SELECT
                        participant_id,
                        first_name,
                        last_name
                    FROM participants
                    ORDER BY first_name ASC";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute();

            return $stmt;

        } catch (PDOException $e) {

            echo "Error fetching participants: "
                . $e->getMessage();

            return false;
        }
    }


    // ==========================================
    // GET EVENTS
    // ==========================================

    public function getEvents()
    {
        try {

            $sql = "SELECT
                        event_id,
                        event_name
                    FROM events
                    ORDER BY event_name ASC";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute();

            return $stmt;

        } catch (PDOException $e) {

            echo "Error fetching events: "
                . $e->getMessage();

            return false;
        }
    }
}
?>
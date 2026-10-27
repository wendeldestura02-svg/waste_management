<?php

/**
 * Transaction demo (TCL) for the CleanRoute Waste Management system.
 *
 * Every public transaction method follows the same pattern from the demo:
 *   beginTransaction()  ->  do several writes  ->  check  ->  commit()
 * and if anything fails: rollBack() so NO table is left half-updated.
 *
 *   1. issueViolation()    Complaints + Violations + Violators + Notifications + Activity_Logs
 *   2. completeCollection() Collection_Schedule + Notifications + Activity_Logs
 *   3. registerResident()  Users + Residents + Notifications + Activity_Logs
 *
 * Each method returns ['status' => 'success'|'error', 'message' => ..., 'steps' => [...]]
 * so the page can show exactly what ran before the COMMIT or the ROLLBACK.
 */
class Transaction
{
    private PDO $conn;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    // ==========================================================
    // TRANSACTION 1: ISSUE A VIOLATION FOR A COMPLAINT
    // ==========================================================
    // One complaint may only receive ONE violation. The check comes AFTER
    // the writes (like the "check sender balance" step in the money demo),
    // so a duplicate attempt is undone by the ROLLBACK.

    public function issueViolation(int $complaintId, int $violatorId, int $fineAmount): array
    {
        $steps = [];

        try {
            $this->conn->beginTransaction();
            $steps[] = 'START TRANSACTION';

            // Lock the complaint row and find the resident who filed it.
            $stmt = $this->conn->prepare(
                'SELECT r.User_id
                 FROM Complaints c
                 INNER JOIN Residents r ON r.ResidentID = c.ResidentID
                 WHERE c.ComplaintID = ?
                 FOR UPDATE'
            );
            $stmt->execute([$complaintId]);
            $complainantId = $stmt->fetchColumn();
            if ($complainantId === false) {
                throw new Exception("Complaint #$complaintId does not exist.");
            }
            $steps[] = "SELECT Complaints #$complaintId FOR UPDATE";

            // The violator must exist.
            $stmt = $this->conn->prepare('SELECT FullName FROM Violators WHERE ViolatorID = ?');
            $stmt->execute([$violatorId]);
            $violatorName = $stmt->fetchColumn();
            if ($violatorName === false) {
                throw new Exception("Violator #$violatorId does not exist.");
            }

            // 1) Record the violation.
            $stmt = $this->conn->prepare(
                'INSERT INTO Violations (ComplaintID, ViolatorID, FineAmount, DateIssued, Status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$complaintId, $violatorId, $fineAmount, (int) date('Ymd'), 'Unpaid']);
            $steps[] = "INSERT Violations (fine ₱$fineAmount, Unpaid)";

            // 2) Add one to the violator's count.
            $stmt = $this->conn->prepare(
                'UPDATE Violators
                 SET Violation_Count = COALESCE(Violation_Count, 0) + 1
                 WHERE ViolatorID = ?'
            );
            $stmt->execute([$violatorId]);
            $steps[] = "UPDATE Violators #$violatorId (Violation_Count + 1)";

            // 3) Mark the complaint as resolved.
            $stmt = $this->conn->prepare("UPDATE Complaints SET Status = 'Resolved' WHERE ComplaintID = ?");
            $stmt->execute([$complaintId]);
            $steps[] = "UPDATE Complaints #$complaintId (Status = Resolved)";

            // 4) Check: a complaint can only have one violation.
            $stmt = $this->conn->prepare('SELECT COUNT(*) FROM Violations WHERE ComplaintID = ?');
            $stmt->execute([$complaintId]);
            if ((int) $stmt->fetchColumn() > 1) {
                throw new Exception("Complaint #$complaintId already has a violation issued.");
            }
            $steps[] = 'CHECK one violation per complaint: OK';

            // 5) Tell the resident who filed the complaint.
            $this->addNotification(
                (int) $complainantId,
                "Your complaint #$complaintId has been resolved and a violation was issued."
            );
            $steps[] = 'INSERT Notifications (to the complainant)';

            // 6) Audit trail.
            $this->addActivityLog($this->getAdminId(), "Issued violation for complaint #$complaintId");
            $steps[] = 'INSERT Activity_Logs';

            $this->conn->commit();
            $steps[] = 'COMMIT';

            return [
                'status' => 'success',
                'message' => "Violation issued to $violatorName for complaint #$complaintId (₱$fineAmount). Transaction committed.",
                'steps' => $steps,
            ];
        } catch (Exception $e) {
            return $this->fail($e, $steps);
        }
    }

    // ==========================================================
    // TRANSACTION 2: COMPLETE A COLLECTION SCHEDULE
    // ==========================================================
    // Marks the route Completed, then notifies every resident of that zone.
    // If the zone has nobody to notify, the status update is rolled back.

    public function completeCollection(int $scheduleId): array
    {
        $steps = [];

        try {
            $this->conn->beginTransaction();
            $steps[] = 'START TRANSACTION';

            // Lock the schedule row and read its zone and collector.
            $stmt = $this->conn->prepare(
                'SELECT s.Zone_ID, s.User_id, s.CollectionDate, s.Status, z.Zone_Name, z.Barangay
                 FROM Collection_Schedule s
                 INNER JOIN Zones z ON z.Zone_ID = s.Zone_ID
                 WHERE s.ScheduleID = ?
                 FOR UPDATE'
            );
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch();
            if (!$schedule) {
                throw new Exception("Schedule #$scheduleId does not exist.");
            }
            $steps[] = "SELECT Collection_Schedule #$scheduleId FOR UPDATE";

            if ($schedule['Status'] !== 'Scheduled') {
                throw new Exception("Schedule #$scheduleId is already {$schedule['Status']}.");
            }

            // 1) Mark the route as completed.
            $stmt = $this->conn->prepare("UPDATE Collection_Schedule SET Status = 'Completed' WHERE ScheduleID = ?");
            $stmt->execute([$scheduleId]);
            $steps[] = "UPDATE Collection_Schedule #$scheduleId (Status = Completed)";

            // 2) Notify every resident registered in that zone (one row per user).
            $zoneLabel = $schedule['Zone_Name'] . ' - ' . $schedule['Barangay'];
            $stmt = $this->conn->prepare(
                'INSERT INTO Notifications (User_id, Message, DateSent, Status)
                 SELECT DISTINCT r.User_id, ?, ?, ?
                 FROM Residents r
                 WHERE r.Zone_ID = ?'
            );
            $stmt->execute([
                "Waste collection for $zoneLabel on {$schedule['CollectionDate']} has been completed.",
                date('Y-m-d'),
                'Unread',
                $schedule['Zone_ID'],
            ]);
            $notified = $stmt->rowCount();
            $steps[] = "INSERT Notifications ($notified resident" . ($notified === 1 ? '' : 's') . ')';

            // 3) Check: somebody must have been notified.
            if ($notified === 0) {
                throw new Exception("No residents are registered in $zoneLabel to notify.");
            }
            $steps[] = 'CHECK at least one resident notified: OK';

            // 4) The collector logs the completed route.
            $this->addActivityLog((int) $schedule['User_id'], "Completed collection schedule #$scheduleId");
            $steps[] = 'INSERT Activity_Logs';

            $this->conn->commit();
            $steps[] = 'COMMIT';

            return [
                'status' => 'success',
                'message' => "Schedule #$scheduleId completed and $notified resident" . ($notified === 1 ? '' : 's') . ' notified. Transaction committed.',
                'steps' => $steps,
            ];
        } catch (Exception $e) {
            return $this->fail($e, $steps);
        }
    }

    // ==========================================================
    // TRANSACTION 3: REGISTER A NEW RESIDENT
    // ==========================================================
    // Creates the Users row AND the Residents row together. If the email or
    // household number is already taken, the new Users row is rolled back
    // too, so no orphan user is left behind.

    public function registerResident(
        string $fullName,
        string $email,
        string $password,
        ?string $contactNumber,
        int $zoneId,
        int $householdNo,
        string $homeAddress
    ): array {
        $steps = [];

        try {
            $this->conn->beginTransaction();
            $steps[] = 'START TRANSACTION';

            // 1) Create the user account.
            $stmt = $this->conn->prepare(
                'INSERT INTO Users (Fullname, Email, Password, Role, Contact_Number)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), 'Resident', $contactNumber]);
            $userId = (int) $this->conn->lastInsertId();
            $steps[] = "INSERT Users (User_id $userId, Role = Resident)";

            // 2) Check: the email must be unique.
            $stmt = $this->conn->prepare('SELECT COUNT(*) FROM Users WHERE Email = ?');
            $stmt->execute([$email]);
            if ((int) $stmt->fetchColumn() > 1) {
                throw new Exception("The email $email is already registered.");
            }
            $steps[] = 'CHECK unique email: OK';

            // The zone must exist.
            $stmt = $this->conn->prepare('SELECT Zone_Name, Barangay FROM Zones WHERE Zone_ID = ?');
            $stmt->execute([$zoneId]);
            $zone = $stmt->fetch();
            if (!$zone) {
                throw new Exception("Zone #$zoneId does not exist.");
            }

            // 3) Create the resident record linked to the new user.
            $stmt = $this->conn->prepare(
                'INSERT INTO Residents (User_id, Zone_ID, HouseholdNo, HomeAddress)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $zoneId, $householdNo, $homeAddress]);
            $steps[] = "INSERT Residents (Zone $zoneId, Household $householdNo)";

            // 4) Check: one household number per zone.
            $stmt = $this->conn->prepare('SELECT COUNT(*) FROM Residents WHERE Zone_ID = ? AND HouseholdNo = ?');
            $stmt->execute([$zoneId, $householdNo]);
            if ((int) $stmt->fetchColumn() > 1) {
                throw new Exception("Household No. $householdNo is already registered in {$zone['Zone_Name']}.");
            }
            $steps[] = 'CHECK unique household in zone: OK';

            // 5) Welcome notification for the new resident.
            $this->addNotification(
                $userId,
                "Welcome to CleanRoute! You are registered in {$zone['Zone_Name']} - {$zone['Barangay']}."
            );
            $steps[] = 'INSERT Notifications (welcome)';

            // 6) Audit trail.
            $this->addActivityLog($this->getAdminId(), "Registered resident $fullName");
            $steps[] = 'INSERT Activity_Logs';

            $this->conn->commit();
            $steps[] = 'COMMIT';

            return [
                'status' => 'success',
                'message' => "$fullName registered as a resident of {$zone['Zone_Name']}. Transaction committed.",
                'steps' => $steps,
            ];
        } catch (Exception $e) {
            return $this->fail($e, $steps);
        }
    }

    // ==========================================================
    // HELPERS (used inside the transactions above)
    // ==========================================================

    private function fail(Exception $e, array $steps): array
    {
        if ($this->conn->inTransaction()) {
            $this->conn->rollBack();
        }
        $steps[] = 'ROLLBACK';

        return [
            'status' => 'error',
            'message' => $e instanceof PDOException
                ? 'A database error occurred. Transaction rolled back, no changes were saved.'
                : $e->getMessage() . ' Transaction rolled back, no changes were saved.',
            'steps' => $steps,
        ];
    }

    private function getAdminId(): int
    {
        $id = $this->conn->query("SELECT User_id FROM Users WHERE Role = 'Admin' ORDER BY User_id LIMIT 1")->fetchColumn();
        if (!$id) {
            $id = $this->conn->query('SELECT User_id FROM Users ORDER BY User_id LIMIT 1')->fetchColumn();
        }
        if (!$id) {
            throw new Exception('No users exist yet.');
        }
        return (int) $id;
    }

    private function addNotification(int $userId, string $message): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO Notifications (User_id, Message, DateSent, Status) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $message, date('Y-m-d'), 'Unread']);
    }

    private function addActivityLog(int $userId, string $action): void
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO Activity_Logs (User_id, ActionTaken, `Timestamp`) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $action, date('H:i:s')]);
    }

    // ==========================================================
    // READ METHODS (dropdowns and live tables on the demo page)
    // ==========================================================

    public function getComplaints(): array
    {
        return $this->conn->query(
            'SELECT c.ComplaintID AS id, c.Description AS description, c.Status AS status,
                    u.Fullname AS resident_name,
                    (SELECT COUNT(*) FROM Violations v WHERE v.ComplaintID = c.ComplaintID) AS violations
             FROM Complaints c
             INNER JOIN Residents r ON r.ResidentID = c.ResidentID
             INNER JOIN Users u ON u.User_id = r.User_id
             ORDER BY c.ComplaintID'
        )->fetchAll();
    }

    public function getViolators(): array
    {
        return $this->conn->query(
            'SELECT ViolatorID AS id, FullName AS name, COALESCE(Violation_Count, 0) AS violation_count
             FROM Violators ORDER BY ViolatorID'
        )->fetchAll();
    }

    public function getSchedules(): array
    {
        return $this->conn->query(
            'SELECT s.ScheduleID AS id, s.CollectionDate AS collection_date, s.Status AS status,
                    CONCAT(z.Zone_Name, " - ", z.Barangay) AS zone_name,
                    u.Fullname AS collector_name,
                    (SELECT COUNT(DISTINCT r.User_id) FROM Residents r WHERE r.Zone_ID = s.Zone_ID) AS residents
             FROM Collection_Schedule s
             INNER JOIN Zones z ON z.Zone_ID = s.Zone_ID
             INNER JOIN Users u ON u.User_id = s.User_id
             ORDER BY s.ScheduleID'
        )->fetchAll();
    }

    public function getZones(): array
    {
        return $this->conn->query(
            'SELECT Zone_ID AS id, CONCAT(Zone_Name, " - ", Barangay) AS name FROM Zones ORDER BY Zone_Name'
        )->fetchAll();
    }

    public function getRecentViolations(): array
    {
        return $this->conn->query(
            'SELECT v.ViolationID, v.ComplaintID, vr.FullName AS Violator, v.FineAmount, v.Status
             FROM Violations v
             INNER JOIN Violators vr ON vr.ViolatorID = v.ViolatorID
             ORDER BY v.ViolationID DESC LIMIT 5'
        )->fetchAll();
    }

    public function getRecentResidents(): array
    {
        return $this->conn->query(
            'SELECT r.ResidentID, u.Fullname, z.Zone_Name AS Zone, r.HouseholdNo
             FROM Residents r
             INNER JOIN Users u ON u.User_id = r.User_id
             INNER JOIN Zones z ON z.Zone_ID = r.Zone_ID
             ORDER BY r.ResidentID DESC LIMIT 5'
        )->fetchAll();
    }

    public function getRecentNotifications(): array
    {
        return $this->conn->query(
            'SELECT NotificationID, User_id, Message, Status
             FROM Notifications ORDER BY NotificationID DESC LIMIT 5'
        )->fetchAll();
    }

    public function getRecentLogs(): array
    {
        return $this->conn->query(
            'SELECT LogID, User_id, ActionTaken, `Timestamp`
             FROM Activity_Logs ORDER BY LogID DESC LIMIT 5'
        )->fetchAll();
    }
}

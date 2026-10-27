<?php

class CollectionSchedule
{
    private PDO $conn;

    public string $collectionDate = '';
    public string $timeSlot = '';
    public int $userId = 0;
    public int $zoneId = 0;
    public string $status = 'Scheduled';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        try {
            $this->restoreZonesIfEmpty();
        } catch (PDOException $exception) {
        }
    }

    private function restoreZonesIfEmpty(): void
    {
        if ((int) $this->conn->query('SELECT COUNT(*) FROM zones')->fetchColumn() > 0) {
            return;
        }
        $ownerId = $this->conn->query('SELECT User_id FROM users ORDER BY User_id LIMIT 1')->fetchColumn();
        if (!$ownerId) {
            return;
        }
        $stmt = $this->conn->prepare(
            'INSERT INTO zones (Zone_ID, Zone_Name, Barangay, Purok_Area, User_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        foreach ([
            [1, 'Zone 1', 'Barangay San Jose', 'Purok 1'],
            [2, 'Zone 2', 'Barangay San Jose', 'Purok 2'],
            [3, 'Zone 3', 'Barangay San Miguel', 'Purok 3'],
            [4, 'Zone 4', 'Barangay San Pedro', 'Purok 4'],
        ] as $zone) {
            $stmt->execute([...$zone, $ownerId]);
        }
    }

    public function addCollectionSchedule(): bool
    {
        try {
            $stmt = $this->conn->prepare(
                'INSERT INTO collection_schedule
                 (Zone_ID, User_id, CollectionDate, TimeSlot, Status)
                 VALUES (:zone_id, :user_id, :collection_date, :time_slot, :status)'
            );
            return $stmt->execute([
                ':zone_id' => $this->zoneId,
                ':user_id' => $this->userId,
                ':collection_date' => $this->collectionDate,
                ':time_slot' => $this->timeSlot,
                ':status' => $this->status,
            ]);
        } catch (PDOException $exception) {
            return false;
        }
    }

    public function getCollectionSchedules(): PDOStatement
    {
        $sql = 'SELECT
                    s.ScheduleID AS schedule_id,
                    s.CollectionDate AS collection_date,
                    s.User_id AS user_id,
                    s.Zone_ID AS zone_id,
                    s.TimeSlot AS time_slot,
                    s.Status AS status,
                    u.Fullname AS user_name,
                    CONCAT(z.Zone_Name, " - ", z.Barangay) AS zone_name,
                    c.VehiclePlateNo AS vehicle_plate
                FROM collection_schedule s
                LEFT JOIN users u ON s.User_id = u.User_id
                LEFT JOIN zones z ON s.Zone_ID = z.Zone_ID
                LEFT JOIN collectors c
                    ON c.User_id = s.User_id
                    AND c.Zone_ID = s.Zone_ID
                ORDER BY s.ScheduleID DESC';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    public function getCollectionSchedule(int $id): array|false
    {
        $stmt = $this->conn->prepare(
            'SELECT
                ScheduleID AS schedule_id,
                CollectionDate AS collection_date,
                User_id AS user_id,
                Zone_ID AS zone_id,
                TimeSlot AS time_slot,
                Status AS status
             FROM collection_schedule
             WHERE ScheduleID = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function updateCollectionSchedule(int $id): bool
    {
        $stmt = $this->conn->prepare(
            'UPDATE collection_schedule
             SET Zone_ID = :zone_id,
                 User_id = :user_id,
                 CollectionDate = :collection_date,
                 TimeSlot = :time_slot,
                 Status = :status
             WHERE ScheduleID = :id'
        );
        return $stmt->execute([
            ':zone_id' => $this->zoneId,
            ':user_id' => $this->userId,
            ':collection_date' => $this->collectionDate,
            ':time_slot' => $this->timeSlot,
            ':status' => $this->status,
            ':id' => $id,
        ]);
    }

    public function deleteCollectionSchedule(int $id): bool
    {
        try {
            $stmt = $this->conn->prepare(
                'DELETE FROM collection_schedule WHERE ScheduleID = :id'
            );
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() === 1;
        } catch (PDOException $exception) {
            return false;
        }
    }

    public function searchCollectionSchedules(string $keyword): PDOStatement
    {
        $stmt = $this->conn->prepare(
            'SELECT
                s.ScheduleID AS schedule_id,
                s.CollectionDate AS collection_date,
                s.User_id AS user_id,
                s.Zone_ID AS zone_id,
                s.TimeSlot AS time_slot,
                s.Status AS status,
                u.Fullname AS user_name,
                CONCAT(z.Zone_Name, " - ", z.Barangay) AS zone_name,
                c.VehiclePlateNo AS vehicle_plate
             FROM collection_schedule s
             LEFT JOIN users u ON s.User_id = u.User_id
             LEFT JOIN zones z ON s.Zone_ID = z.Zone_ID
             LEFT JOIN collectors c
                ON c.User_id = s.User_id
                     AND c.Zone_ID = s.Zone_ID
                 WHERE CAST(s.ScheduleID AS CHAR) LIKE :keyword
                     OR CAST(s.Zone_ID AS CHAR) LIKE :keyword
                OR CAST(s.User_id AS CHAR) LIKE :keyword
                OR s.CollectionDate LIKE :keyword
                     OR s.TimeSlot LIKE :keyword
                OR s.Status LIKE :keyword
                OR u.Fullname LIKE :keyword
                     OR z.Zone_Name LIKE :keyword
                OR z.Barangay LIKE :keyword
             ORDER BY s.ScheduleID DESC'
        );
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        return $stmt;
    }

    public function getUsers(): PDOStatement
    {
        $stmt = $this->conn->query(
            'SELECT User_id AS user_id, Fullname AS user_name
             FROM users ORDER BY Fullname ASC'
        );
        return $stmt;
    }

    public function getAllUsers(): PDOStatement
    {
        return $this->conn->query(
            'SELECT User_id AS user_id, Fullname AS full_name, Email AS email,
                    Role AS role, Contact_Number AS contact_number
             FROM users ORDER BY User_id DESC'
        );
    }

    public function getEntityPreviews(?int $zoneId = null): array
    {
        $queries = [
            'Users' => $zoneId === null
                ? 'SELECT User_id, Fullname, Email, Role, Contact_Number FROM users ORDER BY User_id DESC LIMIT 5'
                : 'SELECT DISTINCT u.User_id, u.Fullname, u.Email, u.Role, u.Contact_Number FROM users u INNER JOIN collection_schedule s ON s.User_id = u.User_id WHERE s.Zone_ID = ' . $zoneId . ' ORDER BY u.User_id DESC LIMIT 5',
            'Zones' => $zoneId === null
                ? 'SELECT Zone_ID, Zone_Name, Barangay, Purok_Area, User_id FROM zones ORDER BY Zone_ID DESC LIMIT 5'
                : 'SELECT Zone_ID, Zone_Name, Barangay, Purok_Area, User_id FROM zones WHERE Zone_ID = ' . $zoneId,
            'Violators' => 'SELECT v.ViolatorID, v.FullName, v.Address, v.Violation_Count FROM violators v ORDER BY v.ViolatorID DESC LIMIT 5',
            'Collectors' => 'SELECT Collector_ID, Zone_ID, User_id, VehiclePlateNo FROM collectors' . ($zoneId === null ? '' : ' WHERE Zone_ID = ' . $zoneId) . ' ORDER BY Collector_ID DESC LIMIT 5',
            'Residents' => 'SELECT ResidentID, User_id, Zone_ID, HouseholdNo, HomeAddress FROM residents' . ($zoneId === null ? '' : ' WHERE Zone_ID = ' . $zoneId) . ' ORDER BY ResidentID DESC LIMIT 5',
            'Collection Schedule' => 'SELECT ScheduleID, Zone_ID, User_id, CollectionDate, TimeSlot, Status FROM collection_schedule' . ($zoneId === null ? '' : ' WHERE Zone_ID = ' . $zoneId) . ' ORDER BY ScheduleID DESC LIMIT 5',
            'Complaints' => 'SELECT co.ComplaintID, co.ResidentID, co.Location, co.Description, co.PhotoEvidence, co.Status FROM complaints co INNER JOIN residents r ON r.ResidentID = co.ResidentID' . ($zoneId === null ? '' : ' WHERE r.Zone_ID = ' . $zoneId) . ' ORDER BY co.ComplaintID DESC LIMIT 5',
            'Violations' => 'SELECT vi.ViolationID, vi.ComplaintID, vi.ViolatorID, vi.FineAmount, vi.Status FROM violations vi INNER JOIN complaints co ON co.ComplaintID = vi.ComplaintID INNER JOIN residents r ON r.ResidentID = co.ResidentID' . ($zoneId === null ? '' : ' WHERE r.Zone_ID = ' . $zoneId) . ' ORDER BY vi.ViolationID DESC LIMIT 5',
            'Notifications' => 'SELECT DISTINCT n.NotificationID, n.User_id, n.Message, n.DateSent, n.Status FROM notifications n INNER JOIN collection_schedule s ON s.User_id = n.User_id' . ($zoneId === null ? '' : ' WHERE s.Zone_ID = ' . $zoneId) . ' ORDER BY n.NotificationID DESC LIMIT 5',
            'Activity Logs' => 'SELECT DISTINCT a.LogID, a.User_id, a.ActionTaken, a.Timestamp FROM activity_logs a INNER JOIN collection_schedule s ON s.User_id = a.User_id' . ($zoneId === null ? '' : ' WHERE s.Zone_ID = ' . $zoneId) . ' ORDER BY a.LogID DESC LIMIT 5',
        ];

        $previews = [];
        foreach ($queries as $name => $query) {
            $previews[$name] = $this->conn->query($query)->fetchAll();
        }
        return $previews;
    }

    public function addUser(string $fullName, string $email, string $password, string $role, ?string $contactNumber): bool
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO users (Fullname, Email, Password, Role, Contact_Number)
             VALUES (:full_name, :email, :password, :role, :contact_number)'
        );
        return $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $role,
            ':contact_number' => $contactNumber,
        ]);
    }

    public function updateUser(int $id, string $fullName, string $email, string $role, ?string $contactNumber, string $password = ''): bool
    {
        try {
            if ($password !== '') {
                $stmt = $this->conn->prepare(
                    'UPDATE users SET Fullname = ?, Email = ?, Password = ?, Role = ?, Contact_Number = ? WHERE User_id = ?'
                );
                return $stmt->execute([$fullName, $email, password_hash($password, PASSWORD_DEFAULT), $role, $contactNumber, $id]);
            }
            $stmt = $this->conn->prepare(
                'UPDATE users SET Fullname = ?, Email = ?, Role = ?, Contact_Number = ? WHERE User_id = ?'
            );
            return $stmt->execute([$fullName, $email, $role, $contactNumber, $id]);
        } catch (PDOException $exception) {
            return false;
        }
    }

    public function getUser(int $id): array|false
    {
        $stmt = $this->conn->prepare('SELECT User_id AS user_id, Fullname AS full_name, Email AS email, Role AS role, Contact_Number AS contact_number FROM users WHERE User_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function deleteUser(int $id): bool
    {
        try {
            $this->conn->beginTransaction();
            $queries = [
                'DELETE FROM violations WHERE ComplaintID IN (SELECT ComplaintID FROM complaints WHERE ResidentID IN (SELECT ResidentID FROM residents WHERE User_id = ?))',
                'DELETE FROM complaints WHERE ResidentID IN (SELECT ResidentID FROM residents WHERE User_id = ?)',
                'DELETE FROM activity_logs WHERE User_id = ?',
                'DELETE FROM notifications WHERE User_id = ?',
                'DELETE FROM residents WHERE User_id = ?',
                'DELETE FROM collection_schedule WHERE User_id = ? OR Zone_ID IN (SELECT Zone_ID FROM zones WHERE User_id = ?)',
                'DELETE FROM collectors WHERE User_id = ? OR Zone_ID IN (SELECT Zone_ID FROM zones WHERE User_id = ?)',
                'DELETE FROM zones WHERE User_id = ?',
                'DELETE FROM users WHERE User_id = ?',
            ];
            foreach ($queries as $query) {
                $values = substr_count($query, '?') === 2 ? [$id, $id] : [$id];
                $this->conn->prepare($query)->execute($values);
            }
            $deleted = $this->conn->commit();
            return $deleted;
        } catch (PDOException $exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function addEntityRecord(string $entity, array $data): bool
    {
        switch ($entity) {
            case 'violators':
                $sql = 'INSERT INTO violators (FullName, Address, Contact_Number, Violation_Count) VALUES (?, ?, ?, ?)';
                $values = [$data['full_name'], $data['address'], $data['contact_number'] ?: null, (int) $data['violation_count']];
                break;
            case 'collectors':
                $sql = 'INSERT INTO collectors (Zone_ID, User_id, VehiclePlateNo) VALUES (?, ?, ?)';
                $values = [(int) $data['zone_id'], (int) $data['user_id'], $data['vehicle_plate']];
                break;
            case 'residents':
                $sql = 'INSERT INTO residents (User_id, Zone_ID, HouseholdNo, HomeAddress) VALUES (?, ?, ?, ?)';
                $values = [(int) $data['user_id'], (int) $data['zone_id'], (int) $data['household_no'], $data['home_address']];
                break;
            case 'complaints':
                $sql = 'INSERT INTO complaints (ResidentID, Location, Description, DateReported, PhotoEvidence, Status) VALUES (?, ?, ?, ?, ?, ?)';
                $values = [(int) $data['resident_id'], $data['location'], $data['description'], $data['date_reported'], $data['photo_evidence'], $data['status']];
                break;
            case 'violations':
                $sql = 'INSERT INTO violations (ComplaintID, ViolatorID, FineAmount, DateIssued, Status) VALUES (?, ?, ?, ?, ?)';
                $values = [(int) $data['complaint_id'], (int) $data['violator_id'], (int) $data['fine_amount'], (int) $data['date_issued'], $data['status']];
                break;
            case 'notifications':
                $sql = 'INSERT INTO notifications (User_id, Message, DateSent, Status) VALUES (?, ?, ?, ?)';
                $values = [(int) $data['user_id'], $data['message'], $data['date_sent'], $data['status']];
                break;
            case 'activity_logs':
                $sql = 'INSERT INTO activity_logs (User_id, ActionTaken, `Timestamp`) VALUES (?, ?, ?)';
                $values = [(int) $data['user_id'], $data['action_taken'], $data['timestamp']];
                break;
            default:
                return false;
        }

        try {
            return $this->conn->prepare($sql)->execute($values);
        } catch (PDOException $exception) {
            return false;
        }
    }

    public function getZones(): PDOStatement
    {
        $stmt = $this->conn->query(
            'SELECT Zone_ID AS zone_id,
                    CONCAT(Zone_Name, " - ", Barangay) AS zone_name
             FROM zones ORDER BY Zone_Name ASC'
        );
        return $stmt;
    }
}

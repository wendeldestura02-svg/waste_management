CREATE DATABASE IF NOT EXISTS waste_management;
USE waste_management;

CREATE TABLE IF NOT EXISTS Users (
    User_id INT AUTO_INCREMENT PRIMARY KEY,
    Fullname VARCHAR(100) NOT NULL,
    Email VARCHAR(100) NOT NULL,
    Password VARCHAR(255) NOT NULL,
    Role VARCHAR(50) NOT NULL,
    Contact_Number VARCHAR(20) NULL
);

CREATE TABLE IF NOT EXISTS Zones (
    Zone_ID INT AUTO_INCREMENT PRIMARY KEY,
    Zone_Name VARCHAR(100) NOT NULL,
    Barangay VARCHAR(100) NOT NULL,
    Purok_Area VARCHAR(100) NOT NULL,
    User_id INT NOT NULL,
    CONSTRAINT fk_zones_user FOREIGN KEY (User_id) REFERENCES Users(User_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Violators (
    ViolatorID INT AUTO_INCREMENT PRIMARY KEY,
    FullName VARCHAR(100) NOT NULL,
    Address VARCHAR(255) NOT NULL,
    Contact_Number INT NULL,
    Violation_Count INT NULL
);

CREATE TABLE IF NOT EXISTS Collectors (
    Collector_ID INT AUTO_INCREMENT PRIMARY KEY,
    Zone_ID INT NOT NULL,
    User_id INT NOT NULL,
    VehiclePlateNo VARCHAR(50) NOT NULL,
    CONSTRAINT fk_collectors_zone FOREIGN KEY (Zone_ID) REFERENCES Zones(Zone_ID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_collectors_user FOREIGN KEY (User_id) REFERENCES Users(User_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Residents (
    ResidentID INT AUTO_INCREMENT PRIMARY KEY,
    User_id INT NOT NULL,
    Zone_ID INT NOT NULL,
    HouseholdNo INT NOT NULL,
    HomeAddress VARCHAR(255) NOT NULL,
    CONSTRAINT fk_residents_user FOREIGN KEY (User_id) REFERENCES Users(User_id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_residents_zone FOREIGN KEY (Zone_ID) REFERENCES Zones(Zone_ID)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Collection_Schedule (
    ScheduleID INT AUTO_INCREMENT PRIMARY KEY,
    Zone_ID INT NOT NULL,
    User_id INT NOT NULL,
    CollectionDate DATE NOT NULL,
    TimeSlot TIME NOT NULL,
    Status VARCHAR(100) NULL,
    CONSTRAINT fk_schedule_zone FOREIGN KEY (Zone_ID) REFERENCES Zones(Zone_ID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_schedule_user FOREIGN KEY (User_id) REFERENCES Users(User_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Complaints (
    ComplaintID INT AUTO_INCREMENT PRIMARY KEY,
    ResidentID INT NOT NULL,
    Location VARCHAR(255) NOT NULL,
    Description VARCHAR(255) NOT NULL,
    DateReported VARCHAR(100) NOT NULL,
    PhotoEvidence VARCHAR(255) NOT NULL,
    Status VARCHAR(100) NOT NULL,
    CONSTRAINT fk_complaints_resident FOREIGN KEY (ResidentID) REFERENCES Residents(ResidentID)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Violations (
    ViolationID INT AUTO_INCREMENT PRIMARY KEY,
    ComplaintID INT NOT NULL,
    ViolatorID INT NOT NULL,
    FineAmount INT NOT NULL,
    DateIssued INT NOT NULL,
    Status VARCHAR(100) NOT NULL,
    CONSTRAINT fk_violations_complaint FOREIGN KEY (ComplaintID) REFERENCES Complaints(ComplaintID)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_violations_violator FOREIGN KEY (ViolatorID) REFERENCES Violators(ViolatorID)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Notifications (
    NotificationID INT AUTO_INCREMENT PRIMARY KEY,
    User_id INT NOT NULL,
    Message VARCHAR(255) NOT NULL,
    DateSent VARCHAR(100) NULL,
    Status VARCHAR(100) NULL,
    CONSTRAINT fk_notifications_user FOREIGN KEY (User_id) REFERENCES Users(User_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS Activity_Logs (
    LogID INT AUTO_INCREMENT PRIMARY KEY,
    User_id INT NOT NULL,
    ActionTaken VARCHAR(255) NOT NULL,
    Timestamp TIME NOT NULL,
    CONSTRAINT fk_activity_logs_user FOREIGN KEY (User_id) REFERENCES Users(User_id)
        ON UPDATE CASCADE ON DELETE RESTRICT
);

INSERT INTO Users (Fullname, Email, Password, Role, Contact_Number) VALUES
('Juan Dela Cruz', 'juan@gmail.com', 'password123', 'Admin', 912345678),
('Maria Santos', 'maria@gmail.com', 'password123', 'Collector', 923456789),
('Pedro Garcia', 'pedro@gmail.com', 'password123', 'Resident', 934567890),
('Ana Reyes', 'ana@gmail.com', 'password123', 'Resident', 945678901),
('Carlos Mendoza', 'carlos@gmail.com', 'password123', 'Collector', 956789012);

INSERT INTO Zones (Zone_Name, Barangay, Purok_Area, User_id) VALUES
('Zone 1', 'Barangay San Jose', 'Purok 1', 1),
('Zone 2', 'Barangay San Jose', 'Purok 2', 1),
('Zone 3', 'Barangay San Miguel', 'Purok 3', 1),
('Zone 4', 'Barangay San Pedro', 'Purok 4', 1);

INSERT INTO Collectors (Zone_ID, User_id, VehiclePlateNo) VALUES
(1, 2, 'ABC-1234'), (2, 5, 'DEF-5678'), (3, 2, 'GHI-9012');

INSERT INTO Residents (User_id, Zone_ID, HouseholdNo, HomeAddress) VALUES
(3, 1, 101, 'Purok 1, Barangay San Jose'),
(4, 1, 102, 'Purok 1, Barangay San Jose'),
(3, 2, 201, 'Purok 2, Barangay San Jose'),
(4, 3, 301, 'Purok 3, Barangay San Miguel');

INSERT INTO Collection_Schedule (Zone_ID, User_id, CollectionDate, TimeSlot, Status) VALUES
(1, 2, '2026-08-28', '07:00:00', 'Scheduled'),
(2, 5, '2026-08-29', '08:00:00', 'Scheduled'),
(3, 2, '2026-08-30', '07:30:00', 'Completed'),
(4, 5, '2026-08-31', '09:00:00', 'Scheduled');

INSERT INTO Complaints (ResidentID, Location, Description, DateReported, PhotoEvidence, Status) VALUES
(1, 'Purok 1, Barangay San Jose', 'Garbage was not collected on the scheduled date.', '2026-08-25', 'complaint1.jpg', 'Pending'),
(2, 'Purok 1, Barangay San Jose', 'Overflowing garbage bins near residential area.', '2026-08-26', 'complaint2.jpg', 'Under Review'),
(3, 'Purok 2, Barangay San Jose', 'Waste was left along the roadside after collection.', '2026-08-26', 'complaint3.jpg', 'Resolved'),
(4, 'Purok 3, Barangay San Miguel', 'Collection vehicle did not arrive on schedule.', '2026-08-27', 'complaint4.jpg', 'Pending');

INSERT INTO Notifications (User_id, Message, DateSent, Status) VALUES
(3, 'Your waste collection complaint has been received.', '2026-08-25', 'Read'),
(4, 'Your complaint is currently under review.', '2026-08-26', 'Unread'),
(3, 'Waste collection schedule has been updated.', '2026-08-27', 'Unread'),
(2, 'You have a new collection schedule assigned.', '2026-08-27', 'Read');

INSERT INTO Activity_Logs (User_id, ActionTaken, Timestamp) VALUES
(1, 'Created a new collection zone', '08:30:00'),
(2, 'Updated collection schedule', '09:15:00'),
(3, 'Submitted a waste collection complaint', '10:20:00'),
(4, 'Submitted a waste collection complaint', '11:05:00'),
(1, 'Reviewed a complaint', '13:45:00');

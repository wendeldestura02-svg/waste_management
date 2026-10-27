-- transaction_demo.sql
-- Run this ONCE after database.sql (database: waste_management).
-- database.sql has no Violators rows, and Transaction 1 (Issue Violation)
-- needs at least one violator to pick from. No tables are changed.

USE waste_management;

INSERT INTO Violators (FullName, Address, Contact_Number, Violation_Count)
SELECT * FROM (
    SELECT 'Roberto Villanueva' AS FullName, 'Purok 1, Barangay San Jose' AS Address, 917000111 AS Contact_Number, 0 AS Violation_Count
    UNION ALL
    SELECT 'Lourdes Bautista', 'Purok 2, Barangay San Jose', 918000222, 1
    UNION ALL
    SELECT 'Eduardo Castillo', 'Purok 3, Barangay San Miguel', 919000333, 0
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM Violators);

-- Create Groups Table
CREATE TABLE IF NOT EXISTS Groups (
    GroupID INT AUTO_INCREMENT PRIMARY KEY,
    GroupName VARCHAR(255) NOT NULL,
    SupervisorID INT NOT NULL,
    Description TEXT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (SupervisorID) REFERENCES Users(UserID)
);

-- Create Student_Group relationship table
CREATE TABLE IF NOT EXISTS StudentGroups (
    StudentGroupID INT AUTO_INCREMENT PRIMARY KEY,
    GroupID INT NOT NULL,
    StudentID INT NOT NULL,
    JoinedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (GroupID) REFERENCES Groups(GroupID),
    FOREIGN KEY (StudentID) REFERENCES Users(UserID),
    UNIQUE (GroupID, StudentID)
);

-- Modify Projects table to associate with Groups instead of individual students
ALTER TABLE Projects 
ADD COLUMN GroupID INT NULL,
ADD FOREIGN KEY (GroupID) REFERENCES Groups(GroupID);

-- Add a table for file uploads with support for various file types
CREATE TABLE IF NOT EXISTS FileUploads (
    FileID INT AUTO_INCREMENT PRIMARY KEY,
    FileName VARCHAR(255) NOT NULL,
    FilePath VARCHAR(255) NOT NULL,
    FileType VARCHAR(100) NOT NULL,
    FileSize INT NOT NULL,
    UploadedBy INT NOT NULL,
    SubmissionID INT,
    UploadedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UploadedBy) REFERENCES Users(UserID),
    FOREIGN KEY (SubmissionID) REFERENCES Submissions(SubmissionID) ON DELETE CASCADE
);

-- Modify Submissions table to track review status
ALTER TABLE Submissions
ADD COLUMN ReviewStatus ENUM('Pending', 'Accepted', 'Rejected') DEFAULT 'Pending',
ADD COLUMN ReviewedBy INT NULL,
ADD COLUMN ReviewedAt TIMESTAMP NULL,
ADD FOREIGN KEY (ReviewedBy) REFERENCES Users(UserID);

-- Add column to track supervisor's group count
ALTER TABLE Users
ADD COLUMN GroupCount INT DEFAULT 0;

-- Add trigger to update GroupCount when a new group is created
DELIMITER //
CREATE TRIGGER after_group_insert
AFTER INSERT ON Groups
FOR EACH ROW
BEGIN
    UPDATE Users SET GroupCount = GroupCount + 1 WHERE UserID = NEW.SupervisorID;
END//
DELIMITER ;

-- Add trigger to update GroupCount when a group is deleted
DELIMITER //
CREATE TRIGGER after_group_delete
AFTER DELETE ON Groups
FOR EACH ROW
BEGIN
    UPDATE Users SET GroupCount = GroupCount - 1 WHERE UserID = OLD.SupervisorID;
END//
DELIMITER ;

-- Add indexes for performance
CREATE INDEX idx_groups_supervisor ON Groups(SupervisorID);
CREATE INDEX idx_studentgroups_group ON StudentGroups(GroupID);
CREATE INDEX idx_studentgroups_student ON StudentGroups(StudentID);
CREATE INDEX idx_projects_group ON Projects(GroupID);
CREATE INDEX idx_submissions_review ON Submissions(ReviewStatus);

-- Add Status column to Groups table
ALTER TABLE Groups ADD COLUMN Status ENUM('Active', 'Inactive') DEFAULT 'Active';

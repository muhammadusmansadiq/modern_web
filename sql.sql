-- Create Departments Table
CREATE TABLE Departments (
    DepartmentID INT AUTO_INCREMENT PRIMARY KEY,
    DepartmentName VARCHAR(255) NOT NULL UNIQUE
);

-- Insert Departments
INSERT INTO Departments (DepartmentName) VALUES
('Cyber Security'),
('Electrical Engineering (Power/Electronics/Telecom)'),
('Mechatronics Engineering'),
('Mechanical Engineering'),
('Computer Engineering'),
('Bio Medical Engineering'),
('Aerospace Engineering'),
('Avionics Engineering'),
('System Security'),
('Information Security'),
('Business Administration (BBA)'),
('Physics'),
('Computer Science'),
('Mathematics'),
('English'),
('Management Sciences'),
('Accounting and Finance'),
('Computer Games Design'),
('Information Technology'),
('Artificial Intelligence'),
('Data Science'),
('Software Engineering'),
('Strategic Studies'),
('Psychology'),
('Education'),
('Aviation Management'),
('Health Care Management'),
('Tourism and Hospitality Management'),
('Project Management'),
('Business Analytics'),
('International Relations (I.R)'),
('MBBS');

-- Create Roles Table
CREATE TABLE Roles (
    RoleID INT AUTO_INCREMENT PRIMARY KEY,
    RoleName VARCHAR(50) NOT NULL UNIQUE
);

-- Create UserStatus Table
CREATE TABLE UserStatus (
    StatusID INT AUTO_INCREMENT PRIMARY KEY,
    StatusName VARCHAR(50) NOT NULL UNIQUE
);

-- Insert User Status
INSERT INTO UserStatus (StatusName) VALUES ('Pending'), ('Approved'), ('Rejected'), ('Blocked'), ('Active');

-- Create Users Table
CREATE TABLE Users (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Username VARCHAR(50) NOT NULL UNIQUE,
    Email VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    StudentID VARCHAR(20) UNIQUE, -- Optional for students
    DepartmentID INT,
    RoleID INT,
    StatusID INT,
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (DepartmentID) REFERENCES Departments(DepartmentID),
    FOREIGN KEY (RoleID) REFERENCES Roles(RoleID),
    FOREIGN KEY (StatusID) REFERENCES UserStatus(StatusID)
);

-- Create Profile Table
CREATE TABLE Profile (
    ProfileID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    FirstName VARCHAR(100),
    LastName VARCHAR(100),
    ContactInfo VARCHAR(100),
    DOB DATE,
    CNIC VARCHAR(20) UNIQUE,
    ProfileImage VARCHAR(255),
    FOREIGN KEY (UserID) REFERENCES Users(UserID) ON DELETE CASCADE
);

-- Create Projects Table
CREATE TABLE Projects (
    ProjectID INT AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(255) NOT NULL,
    Description TEXT,
    Objectives TEXT,
    SupervisorID INT,
    StudentID INT,
    Status VARCHAR(50), -- e.g., 'Proposal Submitted', 'Draft Submitted', etc.
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (SupervisorID) REFERENCES Users(UserID),
    FOREIGN KEY (StudentID) REFERENCES Users(UserID)
);

-- Create Milestones Table
CREATE TABLE Milestones (
    MilestoneID INT AUTO_INCREMENT PRIMARY KEY,
    ProjectID INT,
    MilestoneType VARCHAR(50), -- e.g., 'Proposal Approval', 'Draft Submission', etc.
    DueDate DATE,
    Status VARCHAR(50), -- e.g., 'Pending', 'Completed', 'Overdue'
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ProjectID) REFERENCES Projects(ProjectID)
);

-- Create Submissions Table
CREATE TABLE Submissions (
    SubmissionID INT AUTO_INCREMENT PRIMARY KEY,
    ProjectID INT,
    SubmissionType VARCHAR(50), -- e.g., 'Proposal', 'Draft', 'Final Report'
    Version INT DEFAULT 1,
    FilePath VARCHAR(255), -- Path to the document file
    SubmittedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    MilestoneID INT, -- References the milestone this submission is related to
    FOREIGN KEY (ProjectID) REFERENCES Projects(ProjectID),
    FOREIGN KEY (MilestoneID) REFERENCES Milestones(MilestoneID)
);

-- Create Feedback Table (merged with Messages)
CREATE TABLE Feedback (
    FeedbackID INT AUTO_INCREMENT PRIMARY KEY,
    ProjectID INT,
    SenderID INT,
    ReceiverID INT,
    FeedbackText TEXT,
    FeedbackFilePath VARCHAR(255), -- Optional: Path to the feedback file
    Rating INT, -- Optional: 1-5 scale rating
    IsRead BOOLEAN DEFAULT FALSE,
    SentAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    SubmissionID INT, -- References the submission this feedback is related to
    FOREIGN KEY (ProjectID) REFERENCES Projects(ProjectID),
    FOREIGN KEY (SenderID) REFERENCES Users(UserID),
    FOREIGN KEY (ReceiverID) REFERENCES Users(UserID),
    FOREIGN KEY (SubmissionID) REFERENCES Submissions(SubmissionID)
);

-- Insert Roles
INSERT INTO Roles (RoleName) VALUES ('Admin'), ('Supervisor'), ('Student');

-- Insert Admin User
INSERT INTO Users (Username, Email, PasswordHash, RoleID, StatusID, DepartmentID)
VALUES (
    'admin@aiu.com',
    'admin@aiu.com',
    '$2y$10$zCI/GXkKvwjEWmsjjzHmYeiP5.kDIXxYF64AefHkYUlF/4KhJ5vpa', -- Hash for '12345'
    1, -- RoleID for Admin
    5,  -- StatusID for Active
    NULL -- No department for admin
);

ALTER TABLE Profile
MODIFY ProfileImage VARCHAR(255) DEFAULT 'https://via.placeholder.com/250';

ALTER TABLE Profile
ADD COLUMN gender VARCHAR(10);


CREATE TABLE ProjectHistory (
    HistoryID INT AUTO_INCREMENT PRIMARY KEY,
    ProjectID INT,
    Action VARCHAR(255), -- e.g., 'Proposal Submitted', 'Accepted', 'Rejected', etc.
    ActionDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UserID INT, -- The user who performed the action
    FOREIGN KEY (ProjectID) REFERENCES Projects(ProjectID),
    FOREIGN KEY (UserID) REFERENCES Users(UserID)
);


ALTER TABLE Milestones
CHANGE COLUMN MilestoneType MilestoneTitle VARCHAR(50),
ADD COLUMN Description TEXT;


ALTER TABLE ProjectHistory
ADD COLUMN Status VARCHAR(50),
ADD COLUMN DaysLate INT;


ALTER TABLE Submissions
ADD Remarks TEXT DEFAULT 'No remarks provided';

ALTER TABLE Submissions
ADD Status VARCHAR(50);
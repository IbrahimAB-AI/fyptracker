-- =============================================================================
-- FYPTracker: Final Year Project Supervision & Progress Management System
-- Department of Computer Science, Federal University of Lafia (FULafia)
-- Database Schema — MySQL / MariaDB
-- =============================================================================
-- Author      : FYPTracker Project
-- Version     : 1.0.0
-- Description : Full schema with tables, constraints, indexes, and seed data
-- Engine      : InnoDB (enforces FK constraints)
-- Charset     : utf8mb4 (full Unicode support)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 0. INITIALISATION
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS fyptracker
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE fyptracker;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS meetings;
DROP TABLE IF EXISTS milestones;
DROP TABLE IF EXISTS projects;
DROP TABLE IF EXISTS supervisors;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS system_config;
DROP TABLE IF EXISTS audit_logs;

SET FOREIGN_KEY_CHECKS = 1;


-- =============================================================================
-- 1. CORE TABLES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1.1 users — master account table for all roles
-- -----------------------------------------------------------------------------
CREATE TABLE users (
    user_id       INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(150)    NOT NULL,
    email         VARCHAR(255)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,              -- bcrypt via password_hash()
    role          ENUM(
                      'student',
                      'supervisor',
                      'admin'
                  )               NOT NULL DEFAULT 'student',
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,    -- soft-delete / suspend
    profile_photo VARCHAR(255)        NULL DEFAULT NULL, -- relative path under uploads/
    created_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                            ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id),
    UNIQUE  KEY uq_users_email (email),
    INDEX   idx_users_role    (role),
    INDEX   idx_users_active  (is_active)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Master account table — all roles share this table';


-- -----------------------------------------------------------------------------
-- 1.2 students — extended profile for role=student
-- -----------------------------------------------------------------------------
CREATE TABLE students (
    student_id    INT UNSIGNED    NOT NULL,              -- FK → users.user_id
    matric_number VARCHAR(20)     NOT NULL,              -- e.g. FUL/CS/2021/001
    department    VARCHAR(100)    NOT NULL DEFAULT 'Computer Science',
    level         SMALLINT        NOT NULL DEFAULT 400,  -- 100|200|300|400|500
    programme     VARCHAR(100)    NOT NULL DEFAULT 'B.Sc. Computer Science',

    PRIMARY KEY (student_id),
    UNIQUE  KEY uq_students_matric (matric_number),
    CONSTRAINT fk_students_user
        FOREIGN KEY (student_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Extended profile for students';


-- -----------------------------------------------------------------------------
-- 1.3 supervisors — extended profile for role=supervisor
-- -----------------------------------------------------------------------------
CREATE TABLE supervisors (
    supervisor_id   INT UNSIGNED  NOT NULL,              -- FK → users.user_id
    staff_id        VARCHAR(30)   NOT NULL,              -- e.g. FUL/CS/STAFF/001
    title           VARCHAR(50)   NOT NULL DEFAULT 'Dr.',-- Dr.|Prof.|Mr.|Mrs.|Ms.
    specialisation  VARCHAR(255)      NULL DEFAULT NULL,
    max_students    TINYINT       NOT NULL DEFAULT 5,    -- capacity limit

    PRIMARY KEY (supervisor_id),
    UNIQUE  KEY uq_supervisors_staff_id (staff_id),
    CONSTRAINT fk_supervisors_user
        FOREIGN KEY (supervisor_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Extended profile for supervisors';


-- -----------------------------------------------------------------------------
-- 1.4 projects — one project per student (FYP proposal → completion)
-- -----------------------------------------------------------------------------
CREATE TABLE projects (
    project_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    title             VARCHAR(255)    NOT NULL,
    description       TEXT                NULL,
    student_id        INT UNSIGNED    NOT NULL,
    supervisor_id     INT UNSIGNED        NULL DEFAULT NULL,
    status            ENUM(
                          'pending',      -- awaiting supervisor review
                          'approved',     -- supervisor approved
                          'rejected',     -- supervisor rejected (re-submit allowed)
                          'in_progress',  -- work ongoing
                          'completed'     -- all milestones done, project closed
                      )               NOT NULL DEFAULT 'pending',
    rejection_reason  TEXT                NULL,
    chapter_file      VARCHAR(255)        NULL DEFAULT NULL, -- uploads/projects/
    submission_date   DATE                NULL DEFAULT NULL,
    approval_date     DATE                NULL DEFAULT NULL,
    completion_date   DATE                NULL DEFAULT NULL,
    created_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (project_id),
    UNIQUE  KEY uq_projects_student (student_id),          -- one FYP per student
    INDEX   idx_projects_supervisor (supervisor_id),
    INDEX   idx_projects_status     (status),
    CONSTRAINT fk_projects_student
        FOREIGN KEY (student_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_projects_supervisor
        FOREIGN KEY (supervisor_id)
        REFERENCES users (user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='FYP proposals and their lifecycle status';


-- -----------------------------------------------------------------------------
-- 1.5 milestones — checkpoints within a project
-- -----------------------------------------------------------------------------
CREATE TABLE milestones (
    milestone_id        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    project_id          INT UNSIGNED    NOT NULL,
    title               VARCHAR(255)    NOT NULL,   -- e.g. "Chapter 1 Submission"
    description         TEXT                NULL,
    due_date            DATE            NOT NULL,
    completion_status   ENUM(
                            'not_started',
                            'in_progress',
                            'completed'
                        )               NOT NULL DEFAULT 'not_started',
    submission_file     VARCHAR(255)        NULL DEFAULT NULL, -- uploads/milestones/
    completed_at        DATETIME            NULL DEFAULT NULL,
    created_by          INT UNSIGNED    NOT NULL,  -- supervisor who created it
    created_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                    ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (milestone_id),
    INDEX   idx_milestones_project (project_id),
    INDEX   idx_milestones_due     (due_date),
    INDEX   idx_milestones_status  (completion_status),
    CONSTRAINT fk_milestones_project
        FOREIGN KEY (project_id)
        REFERENCES projects (project_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_milestones_creator
        FOREIGN KEY (created_by)
        REFERENCES users (user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Project milestones / chapter checkpoints';


-- -----------------------------------------------------------------------------
-- 1.6 meetings — scheduled supervision meetings
-- -----------------------------------------------------------------------------
CREATE TABLE meetings (
    meeting_id      INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    project_id      INT UNSIGNED    NOT NULL,
    scheduled_date  DATETIME        NOT NULL,
    venue           VARCHAR(255)        NULL DEFAULT NULL, -- room/online link
    agenda          TEXT                NULL,
    minutes         TEXT                NULL,   -- filled after meeting
    status          ENUM(
                        'scheduled',
                        'completed',
                        'cancelled',
                        'rescheduled'
                    )               NOT NULL DEFAULT 'scheduled',
    requested_by    INT UNSIGNED    NOT NULL,  -- student or supervisor user_id
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (meeting_id),
    INDEX   idx_meetings_project  (project_id),
    INDEX   idx_meetings_date     (scheduled_date),
    INDEX   idx_meetings_status   (status),
    CONSTRAINT fk_meetings_project
        FOREIGN KEY (project_id)
        REFERENCES projects (project_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_meetings_requester
        FOREIGN KEY (requested_by)
        REFERENCES users (user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Scheduled and completed supervision meetings';


-- -----------------------------------------------------------------------------
-- 1.7 feedback — supervisor feedback on milestones
-- -----------------------------------------------------------------------------
CREATE TABLE feedback (
    feedback_id     INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    milestone_id    INT UNSIGNED    NOT NULL,
    supervisor_id   INT UNSIGNED    NOT NULL,
    comment         TEXT            NOT NULL,
    rating          TINYINT UNSIGNED    NULL DEFAULT NULL CHECK (rating BETWEEN 1 AND 5),
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (feedback_id),
    INDEX   idx_feedback_milestone   (milestone_id),
    INDEX   idx_feedback_supervisor  (supervisor_id),
    CONSTRAINT fk_feedback_milestone
        FOREIGN KEY (milestone_id)
        REFERENCES milestones (milestone_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,
    CONSTRAINT fk_feedback_supervisor
        FOREIGN KEY (supervisor_id)
        REFERENCES users (user_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Supervisor feedback entries per milestone';


-- -----------------------------------------------------------------------------
-- 1.8 notifications — in-app notification inbox
-- -----------------------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    message         TEXT            NOT NULL,
    link            VARCHAR(255)        NULL DEFAULT NULL, -- relative URL to route to
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (notification_id),
    INDEX   idx_notifications_user    (user_id),
    INDEX   idx_notifications_unread  (user_id, is_read),
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='In-app notification inbox for all roles';


-- =============================================================================
-- 2. SUPPORTING TABLES
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 2.1 system_config — key-value store for admin-configurable settings
-- -----------------------------------------------------------------------------
CREATE TABLE system_config (
    config_key      VARCHAR(100)    NOT NULL,
    config_value    TEXT                NULL,
    description     VARCHAR(255)        NULL,
    updated_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (config_key)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Admin-configurable system settings (key-value)';


-- -----------------------------------------------------------------------------
-- 2.2 audit_logs — lightweight activity trail for admin review
-- -----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    log_id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED        NULL DEFAULT NULL, -- NULL = system/guest
    action          VARCHAR(100)    NOT NULL,  -- e.g. 'login', 'proposal_submitted'
    target_table    VARCHAR(100)        NULL,
    target_id       INT UNSIGNED        NULL,
    ip_address      VARCHAR(45)         NULL,  -- supports IPv6
    user_agent      VARCHAR(255)        NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (log_id),
    INDEX   idx_audit_user   (user_id),
    INDEX   idx_audit_action (action),
    INDEX   idx_audit_time   (created_at),
    CONSTRAINT fk_audit_user
        FOREIGN KEY (user_id)
        REFERENCES users (user_id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci
  COMMENT='Lightweight audit trail for admin review';


-- =============================================================================
-- 3. SEED DATA — TESTING
-- =============================================================================
-- Passwords for all seed accounts: Password123!
-- Hash generated with: password_hash('Password123!', PASSWORD_BCRYPT)
-- -----------------------------------------------------------------------------

-- 3.1 Admin account
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Dr. Aisha Bello',      'admin@fyptracker.fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'admin');


-- 3.2 Supervisor accounts
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Dr. Emeka Okonkwo',    'e.okonkwo@fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'supervisor'),
('Dr. Fatima Usman',     'f.usman@fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'supervisor'),
('Mr. Chukwudi Nwosu',   'c.nwosu@fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'supervisor');


-- 3.3 Student accounts
INSERT INTO users (full_name, email, password_hash, role) VALUES
('Abraham Oche',         'a.oche@student.fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'student'),
('Blessing Adamu',       'b.adamu@student.fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'student'),
('Chinedu Obi',          'c.obi@student.fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'student'),
('Hauwa Suleiman',       'h.suleiman@student.fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'student'),
('Ifeanyi Okafor',       'i.okafor@student.fulafia.edu.ng',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uH95ZJ5WC',
 'student');


-- 3.4 Students extended profiles
-- user_ids: admin=1, supervisors=2,3,4, students=5,6,7,8,9
INSERT INTO students (student_id, matric_number, department, level) VALUES
(5, 'FUL/CS/2021/001', 'Computer Science', 400),
(6, 'FUL/CS/2021/002', 'Computer Science', 400),
(7, 'FUL/CS/2021/003', 'Computer Science', 400),
(8, 'FUL/CS/2021/004', 'Computer Science', 400),
(9, 'FUL/CS/2021/005', 'Computer Science', 400);


-- 3.5 Supervisors extended profiles
INSERT INTO supervisors (supervisor_id, staff_id, title, specialisation, max_students) VALUES
(2, 'FUL/CS/STAFF/001', 'Dr.',  'Artificial Intelligence, Machine Learning',        5),
(3, 'FUL/CS/STAFF/002', 'Dr.',  'Software Engineering, Database Systems',           5),
(4, 'FUL/CS/STAFF/003', 'Mr.',  'Network Security, Distributed Computing',          4);


-- 3.6 Projects — varied statuses for testing all portal views
INSERT INTO projects
    (title, description, student_id, supervisor_id, status, submission_date, approval_date)
VALUES
(
    'FYPTracker: A Web-Based FYP Supervision and Progress Management System',
    'A role-based platform to digitalise the FYP supervision lifecycle at the Department of Computer Science, FULafia. The system replaces paper-based processes with structured digital workflows for students, supervisors, and the FYP coordinator.',
    5, 2, 'in_progress', '2025-01-15', '2025-01-22'
),
(
    'AI-Powered Lecture Video Summarisation System',
    'A PHP/Python system that transcribes uploaded lecture videos using OpenAI Whisper, then extracts key summaries using TextRank NLP, and generates downloadable PDF reports for students.',
    6, 2, 'approved', '2025-01-18', '2025-01-25'
),
(
    'E-Learning Platform for Rural Secondary Schools in Nasarawa State',
    'A lightweight offline-capable web application to deliver course materials and assessments to students in areas with limited internet connectivity, using progressive web app (PWA) technology.',
    7, 3, 'pending', '2025-02-01', NULL
),
(
    'Cybersecurity Awareness Training Portal for SMEs in Nigeria',
    'An interactive web portal providing cybersecurity training modules, quizzes, and certificates for small and medium enterprise employees, targeting phishing and social engineering threats.',
    8, 3, 'rejected', '2025-01-20', NULL
),
(
    'Real-Time Bus Tracking System for FULafia Campus Shuttle',
    'A GPS-based bus tracking web application allowing students to view live shuttle locations on a map, reducing uncertainty and wait times at campus bus stops.',
    9, 4, 'in_progress', '2025-01-10', '2025-01-17'
);

-- Update rejection reason for rejected project
UPDATE projects
   SET rejection_reason = 'The proposal scope is too broad for a single FYP. Please narrow the focus to one specific aspect, such as phishing simulation or policy compliance tracking, and resubmit.'
 WHERE student_id = 8;


-- 3.7 Milestones (for approved/in_progress projects)
-- Project 1 (project_id=1) — FYPTracker
INSERT INTO milestones (project_id, title, description, due_date, completion_status, completed_at, created_by) VALUES
(1, 'Chapter 1 — Introduction',
    'Submit a complete Chapter 1 covering background, problem statement, objectives, scope, and significance of the study.',
    '2025-02-15', 'completed', '2025-02-13 10:30:00', 2),

(1, 'Chapter 2 — Literature Review',
    'Submit a 15-source literature review covering related systems, theoretical frameworks, and research gaps.',
    '2025-03-01', 'completed', '2025-02-28 14:00:00', 2),

(1, 'Chapter 3 — Methodology',
    'Submit the system methodology chapter, including SSADM DFDs, ER diagram, system architecture, and tools justification.',
    '2025-03-20', 'completed', '2025-03-18 09:15:00', 2),

(1, 'Chapter 4 — System Design & Implementation',
    'Submit the full implementation chapter: database schema, module code walk-through, and system screenshots.',
    '2025-04-20', 'in_progress', NULL, 2),

(1, 'Chapter 5 — Testing, Results & Conclusion',
    'Submit the testing chapter (unit, integration, UAT), results analysis, conclusion, and recommendations.',
    '2025-05-10', 'not_started', NULL, 2),

(1, 'Final Submission & Presentation',
    'Submit the bound project report and present to the examination panel.',
    '2025-05-30', 'not_started', NULL, 2);


-- Project 2 (project_id=2) — Lecture Video Summariser
INSERT INTO milestones (project_id, title, description, due_date, completion_status, completed_at, created_by) VALUES
(2, 'Chapter 1 — Introduction',      'Scope, objectives, and background.',
    '2025-02-20', 'completed', '2025-02-19 11:00:00', 2),
(2, 'Chapter 2 — Literature Review', '12-source literature review.',
    '2025-03-05', 'in_progress', NULL, 2),
(2, 'Chapter 3 — Methodology',       'Agile methodology, DFDs, ER diagram.',
    '2025-03-25', 'not_started', NULL, 2);


-- Project 5 (project_id=5) — Bus Tracking
INSERT INTO milestones (project_id, title, description, due_date, completion_status, completed_at, created_by) VALUES
(5, 'Chapter 1 — Introduction',      'Scope, objectives, significance.',
    '2025-02-10', 'completed', '2025-02-09 16:00:00', 4),
(5, 'Chapter 2 — Literature Review', 'Survey of GPS tracking and PWA systems.',
    '2025-02-28', 'completed', '2025-02-27 10:30:00', 4),
(5, 'Chapter 3 — Methodology',       'System design, tools, architecture.',
    '2025-03-18', 'in_progress', NULL, 4);


-- 3.8 Meetings
INSERT INTO meetings (project_id, scheduled_date, venue, agenda, minutes, status, requested_by) VALUES
-- Completed meetings for project 1
(1, '2025-01-25 10:00:00', 'Dr. Okonkwo Office, Block B, Room 204',
    'Review of submitted proposal; discuss scope refinements and initial chapter timeline.',
    'Proposal accepted. Student to adjust system scope to exclude mobile app. Chapter 1 deadline set for 15 Feb 2025.',
    'completed', 5),

(1, '2025-02-20 11:00:00', 'Dr. Okonkwo Office, Block B, Room 204',
    'Review Chapter 1 submission and provide feedback. Discuss Chapter 2 structure.',
    'Chapter 1 approved with minor corrections. Literature review should include at least 5 Nigerian/African context sources. Next meeting after Chapter 2 submission.',
    'completed', 2),

(1, '2025-03-05 10:30:00', 'Google Meet — link shared via email',
    'Chapter 2 review session.',
    'Literature review accepted. Suggested adding a comparative table of related systems. Chapter 3 to follow SSADM strictly as per departmental requirement.',
    'completed', 5),

-- Scheduled upcoming meeting for project 1
(1, '2025-04-05 10:00:00', 'Dr. Okonkwo Office, Block B, Room 204',
    'Chapter 3 feedback review and implementation planning for Chapter 4.',
    NULL, 'scheduled', 5),

-- Meeting for project 5
(5, '2025-02-15 14:00:00', 'Mr. Nwosu Office, Block C, Room 107',
    'Review of Chapter 1 and GPS module selection discussion.',
    'Chapter 1 approved. Leaflet.js recommended for the map component. MQTT protocol to be used for real-time data.',
    'completed', 9);


-- 3.9 Feedback
INSERT INTO feedback (milestone_id, supervisor_id, comment, rating) VALUES
-- Milestone 1 (Chapter 1 of project 1)
(1, 2,
 'Good introduction overall. The problem statement is clear and well-grounded. However, the objectives should be SMART — please revise objectives 3 and 4 to be more measurable. The scope section correctly excludes mobile applications. Approved.',
 4),

-- Milestone 2 (Chapter 2 of project 1)
(2, 2,
 'Solid literature review with good coverage of related systems. The comparative table you added is excellent. Ensure that all in-text citations match the reference list — I noticed two discrepancies. APA format must be strictly maintained. Approved.',
 4),

-- Milestone 3 (Chapter 3 of project 1)
(3, 2,
 'The methodology chapter is well-structured. SSADM DFDs (Level 0 and Level 1) are correctly drawn. The ER diagram is normalised to 3NF as required. Minor issue: the justification for choosing PHP over other frameworks needs to be expanded. Approved with minor revision.',
 5),

-- Milestone 7 (Chapter 1 of project 2)
(7, 2,
 'Chapter 1 is acceptable. The background section could be strengthened with statistics on lecture content accessibility challenges in Nigerian universities. Approved.',
 3),

-- Milestone 10 (Chapter 1 of project 5)
(10, 4,
 'Chapter 1 is well-written. The problem statement correctly identifies the campus transport inefficiency. Approved. Proceed to Chapter 2.',
 5);


-- 3.10 Notifications
INSERT INTO notifications (user_id, message, link, is_read) VALUES
-- Student 1 (Abraham, user_id=5)
(5, 'Your project proposal "FYPTracker" has been approved by Dr. Emeka Okonkwo.',
    'student/dashboard.php', 1),
(5, 'New feedback received on milestone: Chapter 3 — Methodology.',
    'student/milestones.php', 1),
(5, 'Meeting scheduled for 5 April 2025 at 10:00 AM. Venue: Dr. Okonkwo Office.',
    'student/meetings.php', 0),
(5, 'Milestone due in 5 days: Chapter 4 — System Design & Implementation (Due: 20 Apr 2025).',
    'student/milestones.php', 0),

-- Supervisor Dr. Okonkwo (user_id=2)
(2, 'New project proposal submitted by Abraham Oche for review.',
    'supervisor/review_proposals.php', 1),
(2, 'Abraham Oche has marked milestone "Chapter 3 — Methodology" as completed.',
    'supervisor/milestones.php', 1),
(2, 'Meeting request from Abraham Oche for 5 April 2025.',
    'supervisor/meetings.php', 0),

-- Student 2 (Blessing, user_id=6)
(6, 'Your project proposal has been approved. You may now view your milestones.',
    'student/milestones.php', 1),
(6, 'New feedback received on milestone: Chapter 1 — Introduction.',
    'student/milestones.php', 0),

-- Student 3 (Chinedu, user_id=7)
(7, 'Your project proposal is under review by Dr. Fatima Usman.',
    'student/dashboard.php', 0),

-- Student 4 (Hauwa, user_id=8)
(8, 'Your project proposal has been rejected. Please read the rejection reason and resubmit.',
    'student/submit_proposal.php', 0),

-- Student 5 (Ifeanyi, user_id=9)
(9, 'New feedback received on milestone: Chapter 1 — Introduction.',
    'student/milestones.php', 1),
(9, 'Milestone due in 7 days: Chapter 3 — Methodology (Due: 18 Mar 2025).',
    'student/milestones.php', 0),

-- Admin (user_id=1)
(1, 'New user registration: Abraham Oche (Student) — pending supervisor assignment.',
    'admin/assign_supervisors.php', 1),
(1, 'System report for March 2025 is ready for download.',
    'admin/reports.php', 0);


-- 3.11 System configuration defaults
INSERT INTO system_config (config_key, config_value, description) VALUES
('site_name',              'FYPTracker',                    'Application display name'),
('institution_name',       'Federal University of Lafia',   'Full institution name'),
('department_name',        'Department of Computer Science','Department name'),
('faculty_name',           'Faculty of Computing',          'Faculty name'),
('academic_session',       '2024/2025',                     'Current academic session'),
('fyp_submission_deadline','2025-05-30',                    'Global FYP final submission deadline (YYYY-MM-DD)'),
('max_upload_size_mb',     '10',                            'Maximum file upload size in megabytes'),
('allowed_file_types',     'pdf,docx',                      'Comma-separated allowed upload extensions'),
('admin_email',            'admin@fyptracker.fulafia.edu.ng','System sender email address'),
('smtp_host',              'smtp.gmail.com',                 'PHPMailer SMTP host'),
('smtp_port',              '587',                            'PHPMailer SMTP port (TLS)'),
('notifications_enabled',  '1',                              'Enable in-app notifications (1=yes, 0=no)'),
('email_notifications',    '1',                              'Enable email notifications via PHPMailer (1=yes, 0=no)');


-- 3.12 Audit log seed entries (sample trail)
INSERT INTO audit_logs (user_id, action, target_table, target_id, ip_address) VALUES
(5,  'login',               NULL,        NULL, '127.0.0.1'),
(5,  'proposal_submitted',  'projects',  1,    '127.0.0.1'),
(2,  'login',               NULL,        NULL, '127.0.0.1'),
(2,  'proposal_approved',   'projects',  1,    '127.0.0.1'),
(2,  'milestone_created',   'milestones',1,    '127.0.0.1'),
(5,  'milestone_completed', 'milestones',1,    '127.0.0.1'),
(2,  'feedback_given',      'feedback',  1,    '127.0.0.1'),
(1,  'user_created',        'users',     6,    '127.0.0.1'),
(1,  'supervisor_assigned', 'projects',  2,    '127.0.0.1');


-- =============================================================================
-- 4. USEFUL VIEWS (optional — for reports and dashboards)
-- =============================================================================

-- 4.1 Student project overview (used by admin dashboard)
CREATE OR REPLACE VIEW vw_student_project_summary AS
SELECT
    u.user_id,
    u.full_name                          AS student_name,
    s.matric_number,
    p.project_id,
    p.title                              AS project_title,
    p.status                             AS project_status,
    sv.full_name                         AS supervisor_name,
    COUNT(m.milestone_id)                AS total_milestones,
    SUM(m.completion_status = 'completed')   AS completed_milestones,
    SUM(m.completion_status = 'in_progress') AS inprogress_milestones,
    SUM(m.completion_status = 'not_started') AS pending_milestones,
    ROUND(
        SUM(m.completion_status = 'completed') /
        NULLIF(COUNT(m.milestone_id), 0) * 100, 1
    )                                    AS progress_pct
FROM users u
JOIN students    s   ON s.student_id   = u.user_id
LEFT JOIN projects   p   ON p.student_id   = u.user_id
LEFT JOIN users      sv  ON sv.user_id     = p.supervisor_id
LEFT JOIN milestones m   ON m.project_id   = p.project_id
WHERE u.role = 'student'
GROUP BY u.user_id, p.project_id;


-- 4.2 Supervisor workload summary
CREATE OR REPLACE VIEW vw_supervisor_workload AS
SELECT
    u.user_id                            AS supervisor_id,
    u.full_name                          AS supervisor_name,
    sp.title,
    sp.specialisation,
    sp.max_students,
    COUNT(DISTINCT p.project_id)         AS assigned_students,
    sp.max_students - COUNT(DISTINCT p.project_id) AS available_slots,
    SUM(p.status = 'pending')            AS pending_reviews,
    SUM(p.status = 'in_progress')        AS active_projects,
    SUM(p.status = 'completed')          AS completed_projects
FROM users u
JOIN supervisors sp  ON sp.supervisor_id = u.user_id
LEFT JOIN projects p ON p.supervisor_id  = u.user_id
WHERE u.role = 'supervisor'
GROUP BY u.user_id;


-- 4.3 Upcoming milestones (next 30 days)
CREATE OR REPLACE VIEW vw_upcoming_milestones AS
SELECT
    m.milestone_id,
    m.title                              AS milestone_title,
    m.due_date,
    m.completion_status,
    DATEDIFF(m.due_date, CURDATE())      AS days_remaining,
    p.project_id,
    p.title                              AS project_title,
    u.full_name                          AS student_name,
    sv.full_name                         AS supervisor_name
FROM milestones m
JOIN projects p  ON p.project_id  = m.project_id
JOIN users    u  ON u.user_id     = p.student_id
LEFT JOIN users sv ON sv.user_id  = p.supervisor_id
WHERE m.completion_status != 'completed'
  AND m.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
ORDER BY m.due_date ASC;


-- =============================================================================
-- DONE
-- =============================================================================
-- Tables created : users, students, supervisors, projects, milestones,
--                  meetings, feedback, notifications, system_config, audit_logs
-- Views created  : vw_student_project_summary, vw_supervisor_workload,
--                  vw_upcoming_milestones
-- Seed accounts  : 1 admin | 3 supervisors | 5 students
-- Default password (all accounts): Password123!
-- =============================================================================

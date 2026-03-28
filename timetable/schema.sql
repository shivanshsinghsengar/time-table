CREATE DATABASE IF NOT EXISTS timetable_db;
USE timetable_db;

CREATE TABLE IF NOT EXISTS tt_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_name VARCHAR(200) NOT NULL,
    school_start TIME NOT NULL,
    school_end TIME NOT NULL,
    period_duration INT NOT NULL,       -- in minutes
    lunch_start TIME NOT NULL,
    lunch_end TIME NOT NULL,
    days_per_week INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tt_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    class_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (session_id) REFERENCES tt_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tt_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    periods_per_week INT NOT NULL DEFAULT 5,
    FOREIGN KEY (session_id) REFERENCES tt_sessions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tt_teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    teacher_name VARCHAR(100) NOT NULL,
    subject_id INT NOT NULL,
    lunch_start TIME NOT NULL,
    lunch_end TIME NOT NULL,
    FOREIGN KEY (session_id) REFERENCES tt_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES tt_subjects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tt_timetable (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    class_id INT NOT NULL,
    day VARCHAR(20) NOT NULL,
    period_number INT NOT NULL,
    period_start TIME NOT NULL,
    period_end TIME NOT NULL,
    subject_id INT,
    teacher_id INT,
    is_lunch TINYINT(1) DEFAULT 0,
    is_free TINYINT(1) DEFAULT 0,
    FOREIGN KEY (session_id) REFERENCES tt_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id)   REFERENCES tt_classes(id)  ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES tt_subjects(id) ON DELETE SET NULL,
    FOREIGN KEY (teacher_id) REFERENCES tt_teachers(id) ON DELETE SET NULL
);

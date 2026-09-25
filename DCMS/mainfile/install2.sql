CREATE DATABASE IF NOT EXISTS debate_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE debate_manager;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS team_totals, score_entry, participates_in, assignment_judge, debate_assignment, debate_round, judge, debater, participant, team, room, competition_registration, competition, institution, auth_user;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE institution (
  inst_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(80) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE competition (
  competition_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  description TEXT NULL,
  host_inst_id INT NOT NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status ENUM('upcoming','active','completed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comp_institution FOREIGN KEY(host_inst_id) REFERENCES institution(inst_id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE participant (
  participant_id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(160) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(40),
  age INT,
  skill_level VARCHAR(50),
  inst_id INT NULL,
  CONSTRAINT fk_participant_inst FOREIGN KEY(inst_id) REFERENCES institution(inst_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE competition_registration (
  registration_id INT AUTO_INCREMENT PRIMARY KEY,
  competition_id INT NOT NULL,
  participant_id INT NOT NULL,
  registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_comp_part (competition_id, participant_id),
  CONSTRAINT fk_cr_comp FOREIGN KEY(competition_id) REFERENCES competition(competition_id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_part FOREIGN KEY(participant_id) REFERENCES participant(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE team (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  competition_id INT NOT NULL,
  team_name VARCHAR(120) NOT NULL,
  inst_id INT NULL,
  CONSTRAINT fk_team_comp FOREIGN KEY(competition_id) REFERENCES competition(competition_id) ON DELETE CASCADE,
  CONSTRAINT fk_team_inst FOREIGN KEY(inst_id) REFERENCES institution(inst_id) ON DELETE SET NULL,
  UNIQUE KEY uq_comp_team (competition_id, team_name)
) ENGINE=InnoDB;

CREATE TABLE debater (
  participant_id INT PRIMARY KEY,
  team_id INT NULL,
  skill_level VARCHAR(50),
  CONSTRAINT fk_debater_participant FOREIGN KEY(participant_id) REFERENCES participant(participant_id) ON DELETE CASCADE,
  CONSTRAINT fk_debater_team FOREIGN KEY(team_id) REFERENCES team(team_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE judge (
  participant_id INT PRIMARY KEY,
  judge_experience VARCHAR(100),
  CONSTRAINT fk_judge_participant FOREIGN KEY(participant_id) REFERENCES participant(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE room (
  room_id INT AUTO_INCREMENT PRIMARY KEY,
  competition_id INT NOT NULL,
  number VARCHAR(30) NOT NULL,
  building VARCHAR(120) NOT NULL,
  CONSTRAINT fk_room_comp FOREIGN KEY(competition_id) REFERENCES competition(competition_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE debate_round (
  round_id INT AUTO_INCREMENT PRIMARY KEY,
  competition_id INT NOT NULL,
  round_name VARCHAR(120) NOT NULL,
  round_num INT NOT NULL,
  start_time DATETIME NOT NULL,
  CONSTRAINT fk_round_comp FOREIGN KEY(competition_id) REFERENCES competition(competition_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE debate_assignment (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  room_id INT NOT NULL,
  round_id INT NOT NULL,
  motion TEXT NULL,
  motion_status ENUM('draft','released') NOT NULL DEFAULT 'draft',
  CONSTRAINT fk_assignment_room FOREIGN KEY(room_id) REFERENCES room(room_id) ON DELETE RESTRICT,
  CONSTRAINT fk_assignment_round FOREIGN KEY(round_id) REFERENCES debate_round(round_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE assignment_judge (
  assignment_id INT NOT NULL,
  participant_id INT NOT NULL,
  PRIMARY KEY(assignment_id, participant_id),
  CONSTRAINT fk_aj_assignment FOREIGN KEY(assignment_id) REFERENCES debate_assignment(assignment_id) ON DELETE CASCADE,
  CONSTRAINT fk_aj_judge FOREIGN KEY(participant_id) REFERENCES judge(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE participates_in (
  team_id INT NOT NULL,
  assignment_id INT NOT NULL,
  side ENUM('Proposition','Opposition') NOT NULL,
  PRIMARY KEY(team_id, assignment_id),
  UNIQUE KEY uq_assignment_side(assignment_id, side),
  CONSTRAINT fk_pi_team FOREIGN KEY(team_id) REFERENCES team(team_id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_assignment FOREIGN KEY(assignment_id) REFERENCES debate_assignment(assignment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE score_entry (
  score_id INT AUTO_INCREMENT PRIMARY KEY,
  speaker_score DECIMAL(6,2) NOT NULL,
  participant_id INT NOT NULL,
  team_id INT NOT NULL,
  assignment_id INT NOT NULL,
  CONSTRAINT fk_score_participant FOREIGN KEY(participant_id) REFERENCES debater(participant_id) ON DELETE CASCADE,
  CONSTRAINT fk_score_team FOREIGN KEY(team_id) REFERENCES team(team_id) ON DELETE CASCADE,
  CONSTRAINT fk_score_assignment FOREIGN KEY(assignment_id) REFERENCES debate_assignment(assignment_id) ON DELETE CASCADE,
  UNIQUE KEY uq_speaker_assignment(participant_id, assignment_id)
) ENGINE=InnoDB;

CREATE TABLE team_totals (
  team_id INT NOT NULL,
  assignment_id INT NOT NULL,
  total_points DECIMAL(8,2) NOT NULL,
  team_rank INT NULL,
  PRIMARY KEY(team_id, assignment_id),
  CONSTRAINT fk_tt_team FOREIGN KEY(team_id) REFERENCES team(team_id) ON DELETE CASCADE,
  CONSTRAINT fk_tt_assignment FOREIGN KEY(assignment_id) REFERENCES debate_assignment(assignment_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auth_user (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  participant_id INT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auth_participant FOREIGN KEY(participant_id) REFERENCES participant(participant_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

INSERT INTO institution(name,type) VALUES
('BRAC University','University'),
('Canadian University','University'),
('East West University','University'),
('North South University','University'),
('Dhaka Debate Society','Club');

-- Competitions
INSERT INTO competition(competition_id, name, description, host_inst_id, start_date, end_date, status) VALUES
(1, 'BRAC University Inter-University Debate Championship 2026', 'Annual flagship British Parliamentary & Asian Parliamentary debate tournament hosted at BRAC University Merul Badda Campus.', 1, '2026-08-20', '2026-08-22', 'active'),
(2, 'North South University National IV 2026', 'Premier national debating tournament organized by North South University Debate Club (NSUDC).', 4, '2026-09-05', '2026-09-07', 'active'),
(3, 'East West University Novice Open 2026', 'Regional open debate championship aimed at novice and intermediate level debaters across Bangladesh.', 3, '2026-10-15', '2026-10-17', 'upcoming');

-- Rooms for Competition 1 (BRAC University)
INSERT INTO room(competition_id, number, building) VALUES 
(1, 'UB0204', 'BRACU Main Academic Building'),
(1, 'UB0301', 'BRACU Main Academic Building'),
(1, 'UB0410', 'BRACU Annex Building'),
(1, 'UB0502', 'BRACU Auditorium Complex');

-- Rooms for Competition 2 (North South University)
INSERT INTO room(competition_id, number, building) VALUES 
(2, 'NAC-601', 'NSU North Academic Building'),
(2, 'NAC-514', 'NSU North Academic Building'),
(2, 'SAC-302', 'NSU South Academic Building'),
(2, 'LIB-405', 'NSU Library Building');

-- Rooms for Competition 3 (East West University)
INSERT INTO room(competition_id, number, building) VALUES 
(3, 'EWU-A101', 'East West Main Campus Building A'),
(3, 'EWU-B205', 'East West Campus Building B');

-- Rounds for Competition 1 (BRACU)
INSERT INTO debate_round(competition_id, round_name, round_num, start_time) VALUES
(1, 'Opening Round', 1, '2026-08-20 09:00:00'),
(1, 'Quarter Final', 2, '2026-08-20 12:30:00'),
(1, 'Semi Final', 3, '2026-08-21 10:00:00'),
(1, 'Grand Final', 4, '2026-08-21 15:00:00');

-- Rounds for Competition 2 (NSU)
INSERT INTO debate_round(competition_id, round_name, round_num, start_time) VALUES
(2, 'Preliminary Round 1', 1, '2026-09-05 10:00:00'),
(2, 'Preliminary Round 2', 2, '2026-09-05 14:00:00'),
(2, 'Championship Final', 3, '2026-09-06 16:00:00');

-- Rounds for Competition 3 (EWU)
INSERT INTO debate_round(competition_id, round_name, round_num, start_time) VALUES
(3, 'Round of 16', 1, '2026-10-15 09:30:00'),
(3, 'Quarter Finals', 2, '2026-10-15 14:00:00');

-- Teams for Competition 1 (BRACU)
INSERT INTO team(competition_id, team_name, inst_id) VALUES 
(1, 'Apex Orators', 1),
(1, 'Logic League', 2),
(1, 'Immortal Debaters', 1),
(1, 'Reason Rebels', 2);

-- Teams for Competition 2 (NSU)
INSERT INTO team(competition_id, team_name, inst_id) VALUES 
(2, 'North Star Debaters', 4),
(2, 'Zenith Dialectics', 4),
(2, 'Veritas Vanguard', 1),
(2, 'Quantum Quorum', 3);

-- Participants
INSERT INTO participant(email,name,phone,age,skill_level,inst_id) VALUES
('alfi@gmail.com','Alfi Shahariar','01711111111',24,'Advanced',1),
('nabila@gmail.com','Nabila Rahman','01722222222',21,'Advanced',2),
('rafi@gmail.com','Rafi Hasan','01733333333',23,'Intermediate',1),
('tania@gmail.com','Tania Akter','01744444444',22,'Advanced',2),
('Samia@gmail.com','Samia Karim','01755555555',28,'JUDGE',3),
('Imran@gmail.com','Imran Hossain','01766666666',31,'JUDGE',3),
('Mahir@gmail.com','Mahir Hossain','01777777777',36,'JUDGE',1),
('leon@gmail.com','Leon Gosh','01788888888',24,'Advanced',1),
('debojit@gmail.com','Debojit Saha','01799999999',24,'Advanced',1),
('farhan@gmail.com','Farhan Chowdhury','01712345678',22,'Advanced',2),
('sadia@gmail.com','Sadia Sultana','01723456789',21,'Intermediate',2),
('aayan@gmail.com','Aayan Ahmed','01734567890',23,'Advanced',4),
('mehzabin@gmail.com','Mehzabin Chowdhury','01745678901',20,'Advanced',4),
('tanvir@gmail.com','Tanvir Mahmud','01756789012',24,'Intermediate',4),
('anika@gmail.com','Anika Tabassum','01767890123',22,'Advanced',4),
('kazi@gmail.com','Kazi Mahbub','01778901234',25,'Advanced',5),
('humaira@gmail.com','Humaira Zaman','01789012345',21,'Advanced',5),
('rifat@gmail.com','Rifat Hossain','01790123456',23,'Intermediate',5),
('tasnim@gmail.com','Tasnim Alam','01701234567',22,'Advanced',5);

-- Competition Registrations (Participants enrolling in competitions)
INSERT INTO competition_registration(competition_id, participant_id) VALUES
-- BRACU Competition registrants
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10), (1, 11),
-- NSU Competition registrants (including cross-university registration!)
(2, 1), (2, 12), (2, 13), (2, 14), (2, 15), (2, 5), (2, 7), (2, 16), (2, 17), (2, 18), (2, 19),
-- EWU Competition registrants
(3, 1), (3, 3), (3, 14), (3, 18);

-- Debater Roles
INSERT INTO debater (participant_id, team_id, skill_level) VALUES
(1, 1, 'Advanced'),
(2, 2, 'Advanced'),
(3, 3, 'Intermediate'),
(4, 4, 'Advanced'),
(8, 1, 'Advanced'),
(9, 1, 'Advanced'),
(10, 2, 'Advanced'),
(11, 2, 'Intermediate'),
(12, 5, 'Advanced'),
(13, 5, 'Advanced'),
(14, 6, 'Intermediate'),
(15, 6, 'Advanced'),
(16, 7, 'Advanced'),
(17, 7, 'Advanced'),
(18, 8, 'Intermediate'),
(19, 8, 'Advanced');

-- Judges
INSERT INTO judge(participant_id,judge_experience) VALUES 
(5,'5 years national judging experience'),
(6,'7 years WUDC accredited judge'),
(7,'10 years chief adjudicator');

-- Debate Assignments for BRACU (Comp 1)
INSERT INTO debate_assignment(date,room_id,round_id,motion,motion_status) VALUES
('2026-08-20', 1, 1, 'This house believes that Science is a blessing for Humanity', 'released'),
('2026-08-20', 2, 1, 'This house would regulate social media platforms', 'released'),
('2026-08-20', 3, 2, 'This house supports universal basic income', 'released'),
('2026-08-21', 1, 3, 'This house would phase out non-renewable energy sources', 'draft'),
('2026-08-21', 2, 4, 'This house believes AI development should require global regulation', 'draft');

-- Debate Assignments for NSU (Comp 2)
INSERT INTO debate_assignment(date,room_id,round_id,motion,motion_status) VALUES
('2026-09-05', 5, 5, 'This house would ban private healthcare institutions', 'released'),
('2026-09-05', 6, 5, 'This house believes that space exploration should be privatized', 'draft'),
('2026-09-06', 7, 7, 'This house opposes the rise of algorithmic governance in public policy', 'draft');

-- Participates In (Team allocations for matches)
-- Comp 1 matches
INSERT INTO participates_in(team_id,assignment_id,side) VALUES
(1, 1, 'Proposition'), (2, 1, 'Opposition'),
(3, 2, 'Proposition'), (4, 2, 'Opposition'),
(1, 3, 'Proposition'), (3, 3, 'Opposition'),
(2, 4, 'Proposition'), (4, 4, 'Opposition'),
(1, 5, 'Proposition'), (2, 5, 'Opposition');

-- Comp 2 matches
INSERT INTO participates_in(team_id,assignment_id,side) VALUES
(5, 6, 'Proposition'), (6, 6, 'Opposition'),
(7, 7, 'Proposition'), (8, 7, 'Opposition'),
(5, 8, 'Proposition'), (7, 8, 'Opposition');

-- Assignment Judges
INSERT INTO assignment_judge VALUES 
(1, 5), (1, 6), (2, 6), (3, 7), (4, 5), (5, 6),
(6, 5), (6, 7), (7, 7), (8, 5);

-- Score Entries
INSERT INTO score_entry(speaker_score,participant_id,team_id,assignment_id) VALUES 
(78, 1, 1, 1), (82, 2, 2, 1), (75, 3, 3, 2), (80, 4, 4, 2),
(85, 12, 5, 6), (81, 14, 6, 6);

-- Team Totals
INSERT INTO team_totals(team_id,assignment_id,total_points,team_rank) VALUES 
(1, 1, 155, 1), (2, 1, 150, 2), (3, 2, 145, 1), (4, 2, 142, 2),
(5, 6, 168, 1), (6, 6, 159, 2);

-- Demo auth accounts: Admin@123 and User@123
INSERT INTO auth_user(participant_id,email,password_hash,role,status) VALUES
(NULL,'admin@debate.local','$2y$12$yKhe/fZCZ.zO6YEZUgfC1.ws5S9.9iqNipjATwjqhl9.T9/pzyAx2','admin','approved'),
(1,'alfi@example.com','$2y$12$mZEtgQZ0HpUVYRG7kpe5nu1IJCGfaEzLSVWyXd9MQRiGbd.BBJKKy','user','approved'),
(12,'aayan@example.com','$2y$12$mZEtgQZ0HpUVYRG7kpe5nu1IJCGfaEzLSVWyXd9MQRiGbd.BBJKKy','user','approved');
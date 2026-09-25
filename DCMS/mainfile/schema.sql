CREATE DATABASE IF NOT EXISTS debate_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE debate_manager;
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS team_totals, score_entry, participates_in, assignment_judge, debate_assignment, debate_round, judge, debater, participant, team, room, institution, auth_user;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE institution (
  inst_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type VARCHAR(80) NOT NULL
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

CREATE TABLE debater (
  participant_id INT PRIMARY KEY,
  team_id INT NULL,
  skill_level VARCHAR(50),
  CONSTRAINT fk_debater_participant FOREIGN KEY(participant_id) REFERENCES participant(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE judge (
  participant_id INT PRIMARY KEY,
  judge_experience VARCHAR(100),
  CONSTRAINT fk_judge_participant FOREIGN KEY(participant_id) REFERENCES participant(participant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE team (
  team_id INT AUTO_INCREMENT PRIMARY KEY,
  team_name VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

ALTER TABLE debater ADD CONSTRAINT fk_debater_team FOREIGN KEY(team_id) REFERENCES team(team_id) ON DELETE SET NULL;

CREATE TABLE room (
  room_id INT AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(30) NOT NULL,
  building VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE debate_round (
  round_id INT AUTO_INCREMENT PRIMARY KEY,
  round_name VARCHAR(120) NOT NULL,
  round_num INT NOT NULL,
  start_time DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE debate_assignment (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  room_id INT NOT NULL,
  round_id INT NOT NULL,
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
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auth_participant FOREIGN KEY(participant_id) REFERENCES participant(participant_id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO institution(name,type) VALUES
('BRAC University','University'),('North South University','University'),('Dhaka Debate Society','Club');
INSERT INTO team(team_name) VALUES ('Apex Orators'),('Logic League'),('Dhaka Debaters'),('Reason Rebels');
INSERT INTO room(number,building) VALUES ('Room 301','Main Academic Building'),('Auditorium A','Auditorium Complex'),('Room 204','Main Academic Building');
INSERT INTO debate_round(round_name,round_num,start_time) VALUES
('Opening Round',1,'2026-08-20 09:00:00'),('Quarter Final',2,'2026-08-20 12:30:00'),('Semi Final',3,'2026-08-21 10:00:00'),('Grand Final',4,'2026-08-21 15:00:00');

INSERT INTO participant(email,name,phone,age,skill_level,inst_id) VALUES
('alfi@example.com','Alfi Shahariar','01700000000',22,'Advanced',1),
('nabila@example.com','Nabila Rahman','01700000001',21,'Advanced',2),
('rafi@example.com','Rafi Hasan','01700000002',23,'Intermediate',1),
('tania@example.com','Tania Akter','01700000003',22,'Advanced',2),
('judge1@example.com','Samia Karim','01700000004',28,NULL,3),
('judge2@example.com','Imran Hossain','01700000005',31,NULL,3),
('judge3@example.com','Mahir Chowdhury','01700000006',34,NULL,1),
('member@example.com','Farhan Islam','01700000007',22,'Intermediate',1);
INSERT INTO debater(participant_id,team_id,skill_level) VALUES (1,1,'Advanced'),(2,2,'Advanced'),(3,3,'Intermediate'),(4,4,'Advanced');
INSERT INTO judge(participant_id,judge_experience) VALUES (5,'5 years'),(6,'7 years'),(7,'10 years');

INSERT INTO debate_assignment(date,room_id,round_id) VALUES
('2026-08-20',1,1),('2026-08-20',2,1),('2026-08-20',3,2),('2026-08-21',1,3),('2026-08-21',2,4);
INSERT INTO participates_in(team_id,assignment_id,side) VALUES
(1,1,'Proposition'),(2,1,'Opposition'),(3,2,'Proposition'),(4,2,'Opposition'),(1,3,'Proposition'),(3,3,'Opposition'),(2,4,'Proposition'),(4,4,'Opposition'),(1,5,'Proposition'),(2,5,'Opposition');
INSERT INTO assignment_judge VALUES (1,5),(1,6),(2,6),(3,7),(4,5),(5,6);
INSERT INTO score_entry(speaker_score,participant_id,team_id,assignment_id) VALUES (78,1,1,1),(82,2,2,1),(75,3,3,2),(80,4,4,2);
INSERT INTO team_totals(team_id,assignment_id,total_points,team_rank) VALUES (1,1,155,1),(2,1,150,2),(3,2,145,1),(4,2,142,2);

-- Demo auth accounts. Passwords: Admin@123 and User@123
INSERT INTO auth_user(participant_id,email,password_hash,role) VALUES
(NULL,'admin@debate.local','$2y$12$yKhe/fZCZ.zO6YEZUgfC1.ws5S9.9iqNipjATwjqhl9.T9/pzyAx2','admin'),
(1,'alfi@example.com','$2y$12$mZEtgQZ0HpUVYRG7kpe5nu1IJCGfaEzLSVWyXd9MQRiGbd.BBJKKy','user');

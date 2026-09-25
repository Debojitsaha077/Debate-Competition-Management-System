# Debate Event & Delegation Manager — XAMPP Edition

This project is a PHP + MySQL/MariaDB website designed for XAMPP. It follows the supplied EER/schema as the source model for the event data while adding one technical `auth_user` table for web authentication.

## Run on XAMPP
1. Install XAMPP with Apache, MySQL and PHP.
2. Extract this folder into `C:\xampp\htdocs\debate-event-manager`.
3. Start **Apache** and **MySQL** in XAMPP Control Panel.
4. Open `http://localhost/phpmyadmin`.
5. Import `install.sql`.
6. Open `http://localhost/debate-event-manager/`.

### Demo accounts
User:
- Email: `alfi@example.com`
- Password: `User@123`

Admin:
- URL: `http://localhost/debate-event-manager/admin-login.php`
- Email: `admin@debate.local`
- Password: `Admin@123`

## What the user sees
- Sign up / login
- Dashboard
- Schedule
- Search and filters for assignment/team/round/room/date/side
- Debate detail page
- Published speaker scores
- Published team totals and rankings
- Participant profile

## What the admin can edit
- Rounds
- Debate assignments
- Rooms
- Teams
- Institutions
- Participants
- Debater/Judge records
- Judges assigned to debates
- Speaker scores
- Official team totals and ranks

Regular users have no edit controls and all admin pages require an `admin` session.

## Important note about the supplied model
The EER identifies `TEAM_TOTALS` and `SCORE_ENTRY`, but it does not specify a formula for calculating total points from speaker scores. Therefore, the site treats team total as an **official admin-entered value** and recalculates `TeamRank` within each assignment by descending total. This avoids inventing a scoring rule not present in the source model.

# TimeTable Generator

A simple PHP timetable generator for schools and colleges.

## Project Structure

The main app lives in:

`timetable/`

Important files:

- `timetable/index.html`
- `timetable/generate.php`
- `timetable/db.php`
- `timetable/schema.sql`
- `vercel.json`

## Run Locally With XAMPP

1. Install XAMPP.
2. Open `XAMPP Control Panel`.
3. Start `Apache`.
4. Copy this project into your XAMPP `htdocs` folder.

Example on this machine:

- Source: `D:\time-table`
- Target: `D:\xampp\htdocs\time-table`

5. Open the project in your browser:

`http://localhost/time-table/timetable/index.html`

## Database Setup

This project includes MySQL schema files, but the current app flow does not actively save generated timetable data into the database yet.

To create the database anyway:

1. Start `MySQL` in XAMPP.
2. Open `http://localhost/phpmyadmin/`
3. Create a database named:

`timetable_db`

4. Import:

`timetable/schema.sql`

Expected tables:

- `tt_sessions`
- `tt_classes`
- `tt_subjects`
- `tt_teachers`
- `tt_timetable`

## Deploy To Vercel

This project includes a root `vercel.json` file for deployment.

### Steps

1. Push this project to GitHub.
2. Log in to Vercel.
3. Import the GitHub repository.
4. Deploy the project.

### Notes

- `/` is routed to `timetable/index.html`
- `generate.php` is routed through the PHP runtime
- If you want a database on Vercel, you will need an external hosted MySQL database

## Important Note About Database Usage

`timetable/generate.php` includes `db.php`, but it does not currently call the database connection or insert timetable data into MySQL.

That means:

- the form and timetable generation can still work
- the database tables may remain empty

## Author

Local project prepared for XAMPP and Vercel deployment.

# Student Result Management System

A web-based application for managing student academic records and results. Built as a BCA 6th Semester project.

## Tech Stack

- **Frontend:** HTML, CSS, JavaScript, Tailwind CSS
- **Backend:** PHP 8.x
- **Database:** MySQL / MariaDB
- **Server:** Apache / Nginx (XAMPP compatible)

## File Structure

```
├── Admin/                    # Admin panel (52 files)
│   ├── admin_dashboard.php   # Dashboard with stats & charts
│   ├── classes.php           # Manage classes/sections
│   ├── students.php          # Student records
│   ├── teachers.php          # Teacher records
│   ├── exams.php             # Exam management
│   ├── subject.php           # Subject management
│   ├── result.php            # Central results hub
│   ├── manage_results.php    # Filter/search/publish results
│   ├── students_result.php   # Per-student result view
│   ├── grade_sheet.php       # Grade sheet generator
│   ├── class_topers.php      # Class toppers report
│   ├── student_ledger.php    # Academic ledger
│   ├── users.php             # User management
│   ├── upload_results.php    # CSV bulk upload
│   ├── manual_entry.php      # Manual result entry
│   ├── manage_grades.php     # Grading system config
│   ├── manage_templates.php  # Print templates
│   ├── manage_settings.php   # System settings
│   ├── profile.php           # Admin profile
│   ├── database_update.php   # Schema migration tool
│   ├── publish_results.php   # Publish results
│   ├── unpublish_results.php # Unpublish results
│   ├── print_result.php      # Print result cards
│   ├── sidebar.php           # Shared sidebar
│   ├── topBar.php            # Shared top bar
│   ├── mobile_sidebar.php    # Mobile sidebar
│   └── process_*.php, save_*.php, get_*.php  # AJAX handlers
├── Student/                  # Student dashboard
│   ├── student_dashboard.php # Dashboard with GPA chart
│   ├── student_profile.php   # Profile management
│   ├── grade_sheet.php       # View grade sheets
│   ├── view_grade_sheet.php  # Detailed result view
│   └── includes/
│       ├── student_sidebar.php
│       └── top_navigation.php
├── Teacher/                  # Teacher panel
│   ├── teacher_dashboard.php # Dashboard
│   ├── teacher_profile.php   # Profile management
│   ├── add_result.php        # Add student results
│   ├── edit_results.php      # Edit results (bulk)
│   ├── student_results.php   # View student results
│   ├── grade_sheet.php       # Grade sheet view
│   ├── view_students.php     # Class list
│   └── includes/
│       ├── teacher_sidebar.php
│       └── teacher_topbar.php
├── php/                      # Core processing scripts
│   ├── login_process.php     # Login authentication
│   ├── register_process.php  # User registration
│   └── auth_process.php      # Admin auth
├── includes/                 # Shared includes
│   ├── config.php            # Database connection
│   ├── db_connetc.php        # DB connection (includes config.php)
│   └── logout.php            # Logout handler
├── css/                      # Stylesheets
│   ├── global.css
│   ├── style.css
│   └── tailwind.css
├── js/                       # JavaScript
│   ├── dashboard.js
│   ├── teachers.js
│   └── talwind.js
├── Database/
│   └── result_management.sql # Full database schema + sample data
├── setup/
│   └── setup.php             # One-click database setup
├── uploads/                  # User uploads
│   ├── profile_images/       # Profile photos
│   ├── profiles/
│   ├── templates/            # Print templates
│   └── logo/                 # School logo
├── login.php                 # Login page
├── register.php              # Registration page
├── index.php                 # Redirects to login.php
└── README.md
```

## Installation

### Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite or Nginx

### Steps

1. **Clone the project** into your web root:
   ```bash
   git clone <repo-url> /var/www/html/student_result
   ```

2. **Set up the database** (choose one):
   - Import `Database/result_management.sql` into MySQL:
     ```bash
     mysql -u root -p < Database/result_management.sql
     ```
   - Or visit `http://localhost/student_result/setup/setup.php`

3. **Configure database** (if different from defaults):
   Edit `includes/config.php`:
   ```php
   $host = "localhost";
   $user = "root";
   $pass = "";
   $dbname = "result_management";
   ```

4. **Set permissions** on upload directories:
   ```bash
   chmod -R 775 uploads/
   ```

5. **Access the application** at `http://localhost/student_result/`

### Default Credentials

| Role    | Username | Password  |
|---------|----------|-----------|
| Admin   | admin    | admin123  |

Students and teachers can self-register from the login page.

## Features

- Role-based dashboards (Admin, Teacher, Student)
- Student registration with class/batch assignment
- Teacher registration with department/qualification
- Exam creation and management
- Manual and CSV bulk result entry
- Automatic grade/GPA calculation
- Result publishing workflow
- Grade sheets and report cards
- Class toppers and performance analytics
- Profile management with photo upload
- Remember-me login
- Session-based authentication

## Database Schema

The `result_management` database includes tables for:
- `users`, `students`, `teachers` — user management
- `classes`, `sections`, `subjects` — academic structure
- `exams`, `results`, `resultdetails` — exam results
- `classsubjects`, `teachersubjects` — assignments
- `grading_system`, `student_performance` — grading
- `academic_years`, `activity_logs`, `result_uploads`, `batch_operations` — support

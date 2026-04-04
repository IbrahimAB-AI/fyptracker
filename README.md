# FYPTracker
**Final Year Project Supervision & Progress Management System**
Department of Computer Science — Federal University of Lafia (FULafia)

---

## Project Overview
FYPTracker is a role-based web application that digitalises the FYP supervision lifecycle at FULafia's Department of Computer Science. It replaces manual, paper-based processes with a structured digital platform for students, supervisors, and the FYP Coordinator.

**Three roles:** Student · Supervisor · Admin (FYP Coordinator)

---

## Tech Stack
- **Frontend:** HTML5, CSS3, JavaScript (Lucide icons via CDN)
- **Backend:** PHP 8.0+ (PDO — no raw mysqli)
- **Database:** MySQL / MariaDB
- **Fonts:** Inter + JetBrains Mono (Google Fonts CDN)
- **Local server:** Laragon (Windows) or PHP built-in server

---

## Folder Structure
```
fyptracker/
├── index.php                          ← Landing page (login + register)
├── database.sql                       ← Full schema + seed data
├── README.md
│
├── config/
│   └── db.php                         ← PDO connection singleton
│
├── auth/
│   ├── login.php                      ← Login handler (POST)
│   ├── logout.php                     ← Session destroy + redirect
│   └── register.php                   ← Student self-registration (POST)
│
├── includes/
│   ├── functions.php                  ← Session, CSRF, flash, audit, notifications
│   ├── header.php                     ← HTML head, meta tags, fonts, theme
│   ├── navbar.php                     ← Sidebar (desktop) + bottom tab bar (mobile)
│   ├── topbar.php                     ← Page topbar with title, theme toggle, bell
│   ├── footer.php                     ← Closes layout, loads main.js
│   │
│   ├── repositories/
│   │   ├── ProjectRepository.php      ← All project/milestone/meeting/feedback CRUD
│   │   ├── SupervisorRepository.php   ← Supervisor-scoped queries
│   │   └── AdminRepository.php        ← User management, stats, report data
│   │
│   └── services/
│       ├── FileUploadService.php      ← MIME/ext/size validation, safe storage
│       └── ValidationService.php     ← All form validation rules centralised
│
├── student/
│   ├── dashboard.php                  ← Project status, progress, milestones, feedback
│   ├── submit_proposal.php            ← Submit / view / resubmit proposal
│   ├── milestones.php                 ← Chapter tracker, status update, view feedback
│   ├── meetings.php                   ← Request meetings, view history + minutes
│   └── notifications.php             ← Inbox, mark read, pagination
│
├── supervisor/
│   ├── dashboard.php                  ← Workload overview, student progress table
│   ├── review_proposals.php           ← Approve / reject proposals with reason
│   ├── milestones.php                 ← Create milestones, update status, give feedback
│   ├── meetings.php                   ← Document minutes, complete / cancel meetings
│   └── feedback.php                  ← Feedback hub, all feedback given
│
├── admin/
│   ├── dashboard.php                  ← System stats, supervisor workload, quick actions
│   ├── manage_users.php               ← Paginated users, create supervisor, suspend
│   ├── assign_supervisors.php         ← Assign / reassign supervisors to students
│   └── reports.php                   ← Department report, PDF export via FPDF
│
├── assets/
│   ├── css/
│   │   └── style.css                  ← Full design system (dark/light, mobile-first)
│   ├── js/
│   │   └── main.js                    ← Theme, sidebar, modals, bottom sheets, touch
│   └── img/                           ← (reserved for future images)
│
├── uploads/
│   ├── proposals/                     ← Submitted proposal documents
│   ├── milestones/                    ← Milestone submission files
│   └── chapters/                     ← Chapter documents
│
└── exports/                           ← Generated PDF reports
```

---

## Setup — Option A: Laragon (Windows, Recommended)

1. Install [Laragon](https://laragon.org/download/)
2. Place this folder in `C:\laragon\www\fyptracker\`
3. Start Laragon (Apache + MySQL)
4. Import database:
   - Open HeidiSQL from Laragon menu
   - Create a database named `fyptracker`
   - File → Load SQL File → select `database.sql` → Execute
5. Visit `http://localhost/fyptracker/`

**Phone preview on same WiFi:**
- Find your PC IP: open CMD → `ipconfig`
- On phone browser: `http://192.168.x.x/fyptracker/`

---

## Setup — Option B: GitHub Codespaces

Open the terminal in VS Code and run:

```bash
# Install dependencies
sudo apt-get update && sudo apt-get install -y mysql-server php-mysql php-mbstring

# Start MySQL
sudo service mysql start

# Create database and import schema
sudo mysql -u root -e "CREATE DATABASE IF NOT EXISTS fyptracker;"
sudo mysql -u root fyptracker < database.sql

# Set MySQL root password to empty (matches config/db.php default)
sudo mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY ''; FLUSH PRIVILEGES;"

# Start PHP built-in server
php -S 0.0.0.0:8000
```

Then in the **Ports** tab:
- Find port `8000`
- Right-click → Port Visibility → **Public**
- Click the globe icon to open in browser

---

## Setup — Option C: cPanel / Shared Hosting

1. Upload all files to `public_html/fyptracker/` via File Manager or FTP
2. Create a MySQL database via cPanel → MySQL Databases
3. Import `database.sql` via phpMyAdmin
4. Edit `config/db.php` with your host credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'your_db_name');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   ```
5. Visit `https://yourdomain.com/fyptracker/`

---

## Default Login Credentials
> All demo accounts use password: **Password123!**

| Role | Email |
|------|-------|
| Admin | admin@fyptracker.fulafia.edu.ng |
| Supervisor | e.okonkwo@fulafia.edu.ng |
| Supervisor | f.usman@fulafia.edu.ng |
| Student | a.oche@student.fulafia.edu.ng |
| Student | b.adamu@student.fulafia.edu.ng |

---

## PDF Export (FPDF)
The reports page exports PDF via FPDF. To enable:

1. Download FPDF from https://www.fpdf.org/
2. Place `fpdf.php` in `includes/fpdf/fpdf.php`

---

## Security Notes
- All passwords hashed with `password_hash()` / `password_verify()`
- All DB queries use PDO prepared statements — no raw SQL concatenation
- CSRF tokens on every form
- Role-based session checks at the top of every protected page
- File uploads validated for MIME type, extension, and size (max 10MB)

---

## Academic Session
2024 / 2025 — Department of Computer Science, FULafia

# NHIS Pregnancy Exemption Registration and Records Management System
### Twifo Praso, Central Region, Ghana

> **Prototype — Internal Records Management Solution**
> This system is a proposed digital records management solution for the identified manual pregnancy-related record-keeping process at the **NHIS Twifo Praso District Office**. It is not officially connected to the NHIS national database and is not an official NHIS platform.

---

## 1. Project Description

The NHIS office at Twifo Praso, Ghana currently processes pregnant women for the relevant pregnancy exemption/registration digitally. However, after the digital processing stage, staff record important information manually in notebooks.

This system replaces that manual notebook-based record-keeping process with a centralized, searchable, and secure electronic database. It was developed as an internship/prototype project to demonstrate how the identified workflow gap can be addressed with a simple, locally-run web application.

---

## 2. Problem Being Solved

Manual notebook recording after digital processing causes:
- Duplication of data entry effort
- Difficult search and retrieval of records
- Risk of damaged or lost records
- Inability to generate statistics or reports easily
- Difficulty monitoring and tracking registrations
- Incomplete or inconsistent records

---

## 3. Features

- **Secure Login** with role-based access (Administrator / Staff)
- **Dashboard** with live statistics and Chart.js visualizations
- **Pregnancy Registration Form** capturing personal, pregnancy, and NHIS processing information
- **Automatic Record Number Generation** (e.g. NHIS-TP-2024-00001)
- **Duplicate Detection** — warns when an NHIS membership number already exists
- **All Records View** with pagination, sorting, and filters
- **Advanced Search** by name, NHIS number, phone, record number, community, reference number
- **View / Edit / Delete** records (with role-based permissions)
- **Reports Module** with date range, community, facility, staff, and status filters
- **CSV / Excel Export** of filtered records
- **Printable record detail pages**
- **User Management** — create, edit, activate/deactivate staff accounts
- **Audit Log** — tracks all important actions with user, timestamp, IP address
- **System Settings** page for administrators
- **Password Change** with forced change on first login
- **Session Timeout** for security
- **Fully offline** — runs on XAMPP, no internet required after setup

---

## 4. Technologies Used

| Component     | Technology              |
|---------------|-------------------------|
| Backend       | PHP 8.x                 |
| Database      | MySQL 5.7+ / MariaDB    |
| Frontend      | HTML5, CSS3, Bootstrap 5.3 |
| Charts        | Chart.js 4.4            |
| Icons         | Bootstrap Icons 1.11    |
| Web Server    | Apache (via XAMPP)      |
| Fonts         | Google Fonts (Inter)    |

---

## 5. System Requirements

- Windows 10/11 (or compatible OS)
- XAMPP 8.x or later (Apache + MySQL + PHP)
- Modern web browser (Chrome, Firefox, Edge)
- Minimum 2GB RAM
- No internet connection required after initial setup (CDN assets cached by browser on first load; for fully offline use, assets can be downloaded — see Section 11)

---

## 6. XAMPP Installation

1. Download XAMPP from: https://www.apachefriends.org/
2. Run the installer and install to the default path: `C:\xampp\`
3. During installation, ensure **Apache** and **MySQL** are selected
4. Complete the installation

---

## 7. Database Setup

### Step 1: Start XAMPP Services
1. Open the **XAMPP Control Panel** (Start → XAMPP → XAMPP Control Panel)
2. Click **Start** next to **Apache**
3. Click **Start** next to **MySQL**
4. Both services should show green "Running" status

### Step 2: Open phpMyAdmin
1. Open your web browser
2. Navigate to: `http://localhost/phpmyadmin`
3. You should see the phpMyAdmin interface

### Step 3: Import the Database
1. In phpMyAdmin, click **New** in the left sidebar to create a new database
   - **OR** click the **Import** tab at the top
2. Click **Choose File** and navigate to:
   ```
   C:\xampp\htdocs\NHIS\database\nhis_pregnancy.sql
   ```
3. Click **Go** (or **Import**)
4. You should see a success message: "Import has been successfully finished"
5. The `nhis_pregnancy` database now appears in the left sidebar

---

## 8. Project Folder Placement

The project should already be in the correct location:
```
C:\xampp\htdocs\NHIS\
```

If you need to move it, copy the entire `NHIS` folder into `C:\xampp\htdocs\`.

### Verify the structure:
```
C:\xampp\htdocs\NHIS\
├── index.php
├── login.php
├── logout.php
├── dashboard.php
├── profile.php
├── settings.php
├── config/
│   └── database.php
├── includes/
│   ├── auth.php
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   └── functions.php
├── admin/
│   ├── users.php
│   ├── add_user.php
│   ├── edit_user.php
│   ├── edit_user.php
│   ├── toggle_user.php
│   └── audit_logs.php
├── pregnancy/
│   ├── add.php
│   ├── edit.php
│   ├── view.php
│   ├── delete.php
│   └── check_duplicate.php
├── records/
│   ├── index.php
│   └── search.php
├── reports/
│   ├── index.php
│   └── export.php
├── assets/
│   ├── css/style.css
│   └── js/app.js
└── database/
    └── nhis_pregnancy.sql
```

---

## 9. How to Start Apache and MySQL

1. Open **XAMPP Control Panel** from your desktop or Start Menu
2. Click **Start** next to Apache
3. Click **Start** next to MySQL
4. Confirm both show "Running" in green

> If port 80 is already in use (by Skype, IIS, etc.), Apache may fail to start. In that case, either close the conflicting application or change Apache's port in the XAMPP config.

---

## 10. How to Access the System

Once Apache and MySQL are running:

Open your browser and go to:
```
http://localhost/NHIS
```

You will be redirected to the login page automatically.

---

## 11. Demo Login Credentials

> **IMPORTANT: Change these passwords immediately after first login or before real use.**

| Role          | Username         | Password     |
|---------------|------------------|--------------|
| Administrator | `admin`          | `Admin@1234` |
| Staff         | `abena.mensah`   | `Admin@1234` |
| Staff         | `kofi.agyemang`  | `Admin@1234` |

The admin account is flagged `must_change_password = 1` and will prompt for a password change on first login.

---

## 12. Database Configuration

If your MySQL setup uses a different username or password (not default XAMPP), edit:
```
C:\xampp\htdocs\NHIS\config\database.php
```

Change these lines:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password (default XAMPP: empty)
define('DB_NAME', 'nhis_pregnancy');
```

---

## 13. User Roles

### Administrator
- Full system access
- Create, edit, activate/deactivate user accounts
- View, edit, and delete any record
- View audit logs
- Generate all reports
- Manage system settings
- Access all dashboard statistics

### Staff (Data Entry Officer)
- Login and view dashboard
- Register new pregnant women
- Search and view records
- Edit records (where permitted)
- Generate reports
- Export records to CSV
- Cannot access User Management, Audit Logs, or System Settings
- Cannot delete records (unless administrator enables this in Settings)

---

## 14. Demo Workflow (for Presentation)

Follow this workflow to demonstrate the system:

1. **Login** → Use `admin` / `Admin@1234`
2. **Dashboard** → View statistics cards and charts
3. **New Registration** → Fill in the pregnancy registration form and save
4. **All Records** → Browse the records list with filters
5. **Search Records** → Search by name or NHIS number
6. **View Record** → Click the eye icon to see full record details
7. **Edit Record** → Update a field and save
8. **Reports** → Generate a date-range report and export CSV
9. **Audit Log** → Show all actions recorded by the system
10. **User Management** → Create a new staff account
11. **Settings** → Show system configuration
12. **Logout**

This workflow clearly demonstrates how the system replaces the manual notebook process.

---

## 15. Troubleshooting

### "Database Connection Error"
- Ensure MySQL is running in XAMPP Control Panel
- Verify the `nhis_pregnancy` database was imported
- Check credentials in `config/database.php`

### "Page Not Found" (404)
- Ensure Apache is running
- Confirm the project folder is at `C:\xampp\htdocs\NHIS\`
- URL should be: `http://localhost/NHIS`

### Charts not showing
- Check internet connection (Chart.js loads from CDN on first use)
- For offline use, download Chart.js and Bootstrap from their CDNs and replace the CDN links in `includes/header.php` and `includes/footer.php`

### Cannot log in
- Verify the database was imported successfully
- The admin password hash in the SQL file corresponds to `Admin@1234`
- If needed, generate a new hash in PHP: `echo password_hash('YourNewPassword', PASSWORD_DEFAULT);` and update the `users` table directly in phpMyAdmin

### Session expires quickly
- Go to Settings → increase Session Timeout value

---

## 16. Database Backup Instructions

Regular backups are essential. This database contains sensitive beneficiary information.

### Manual Backup via phpMyAdmin:
1. Open `http://localhost/phpmyadmin`
2. Click on `nhis_pregnancy` in the left sidebar
3. Click the **Export** tab at the top
4. Select **Quick** export method, format: **SQL**
5. Click **Go** — this downloads a `.sql` file
6. **Store this file securely** — it contains personal health information

### Recommended Backup Schedule:
- Daily during active use
- Before any system updates or changes
- After significant data entry sessions

### Backup Storage Security:
- Do NOT store backup files in publicly accessible folders
- Consider encrypting backup files
- Store copies offsite (external drive, secure cloud storage with appropriate access controls)

### Restore from Backup:
1. Open phpMyAdmin
2. Drop the existing `nhis_pregnancy` database (or use a clean one)
3. Create a new database named `nhis_pregnancy`
4. Import your backup `.sql` file

---

## 17. Security Notes

- All passwords are hashed using PHP `password_hash()` with `PASSWORD_DEFAULT`
- All database queries use PDO prepared statements (SQL injection prevention)
- Sessions are regenerated on login to prevent session fixation
- CSRF tokens protect all forms
- Role-based access control restricts administrative functions
- Input is sanitized and validated on both client and server side
- Audit logs record all significant actions

---

## 18. Disclaimer

This system is a **prototype / internship project** developed to demonstrate how the identified manual pregnancy-related record-keeping process at the NHIS Twifo Praso District Office can be digitized.

- It is **not** officially connected to the NHIS national database
- It is **not** an official NHIS platform or product
- It is intended for **internal local use** as a records management aid
- It should be reviewed and formally approved by appropriate NHIS authorities before being used for official purposes
- It is designed to be extensible and could potentially integrate with existing NHIS systems in the future if proper authorization and APIs become available

---

*NHIS-PERS v1.0.0 | Developed as an Internship Project | NHIS Twifo Praso District Office*

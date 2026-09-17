# BudgetWise — Personal Finance Tracker

**BCA Major Project · Final Submission Build · May 2026**

BudgetWise is a multi-user, web-based personal finance management system developed as a BCA major project. It allows users to record income and expenses, plan category-wise budgets, track savings goals, attach receipt images, and analyse financial activity through reports and charts. The system also includes role-based administration and automatic notifications for budget and savings milestones.

## Project Snapshot

| Area | Details |
|---|---|
| Application type | Multi-user personal finance web application |
| Backend | PHP 8.x (procedural) |
| Database | MySQL 8 / MySQLi |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Charts | Chart.js |
| Local server | XAMPP |
| Database tables | 12 |
| Functional modules | 10 |
| User-facing pages | 20 |
| Roles | Regular User, Administrator |
| Reports / exports | CSV, Excel (.xls), browser print-to-PDF |
| Themes | Dark and Light |

## Key Features

- User registration and login
- Bcrypt password hashing and session-based authentication
- Income and expense transaction management
- Category and payment-method management
- Monthly category-wise budget planning
- Budget-vs-actual analysis with notifications
- Savings goals with milestone notifications
- Receipt attachment support
- Dashboard summaries and Chart.js visualisations
- Monthly, category, and custom date-range reports
- CSV, Excel, and browser print-to-PDF exports
- Profile and settings management
- Admin dashboard, user management, and audit logs

## Application Modules

1. **Authentication** — registration, login, logout, and password recovery
2. **Dashboard & Analytics** — financial summaries, charts, and recent activity
3. **Transaction Management** — add, edit, delete, filter, and attach receipts
4. **Category Management** — default and user-created categories
5. **Budget Management** — monthly limits and budget-vs-actual comparison
6. **Savings Goals** — targets, contributions, progress, and milestones
7. **Reports & Analytics** — monthly, category, and custom-range reporting
8. **Notifications** — budget and savings alerts
9. **Profile & Settings** — profile, password, preferences, and payment methods
10. **Administration** — platform-wide aggregates, user management, and logs

## Database Design

The project contains **12 tables** grouped into four logical areas:

### Identity & Access
- `users`
- `user_profiles`
- `settings`

### Financial Records
- `categories`
- `payment_methods`
- `transactions`
- `budgets`

### Goals & Attachments
- `savings_goals`
- `attachments`

### System-Written Data
- `notifications`
- `report_logs`
- `admin_logs`

The schema uses foreign keys, unique constraints, indexes, prepared statements, and user-scoped ownership checks. Category and payment-method references can be cleared without deleting the related transaction, while user-owned records cascade with user deletion as defined in the schema.

## Project Structure

```text
budget-app/
├── admin/
├── assets/
├── budgets/
├── categories/
├── database/
│   └── setup.sql
├── includes/
├── notifications/
├── profile/
├── reports/
├── savings goals/
├── transaction/
├── uploads/
│   ├── profile_images/
│   └── receipts/
├── dashboard.php
├── forgot_password.php
├── index.php
├── login.php
├── logout.php
├── register.php
├── .gitignore
└── README.md
```

> The `savings goals` directory name is retained from the original project structure because the application references that path.

## How to Run Locally with XAMPP

### 1. Install XAMPP

Install XAMPP with Apache and MySQL.

### 2. Copy the project

Copy the project folder into:

```text
C:\xampp\htdocs\budget-app\
```

### 3. Start services

Open XAMPP Control Panel and start:

- Apache
- MySQL

### 4. Create the database

Open:

```text
http://localhost/phpmyadmin
```

Import:

```text
budget-app/database/setup.sql
```

The setup script creates the database, tables, seeded demo users, and sample data.

### 5. Open the application

Visit:

```text
http://localhost/budget-app/
```

### Local demo accounts

The project includes seeded accounts for local demonstration:

```text
Administrator
Username: admin
Password: admin123

Regular User
Username: demo
Password: admin123
```

These credentials are intended for **local XAMPP demonstration only**. Change the passwords before deploying the application anywhere public.

## Configuration Notes

The current build is configured for a standard local XAMPP MySQL installation:

```php
DB_HOST = localhost
DB_USER = root
DB_PASS = ''
DB_NAME = budget_app
```

The database configuration file also contains a development-oriented `DEBUG` setting. For a public deployment, use environment variables or another secure secret-management approach and disable verbose database errors.

## Security & Engineering Practices Demonstrated

The project demonstrates several defensive programming concepts:

- Prepared statements with MySQLi
- Bcrypt password hashing and verification
- Session ID regeneration at authentication-sensitive points
- Output escaping with `htmlspecialchars`
- Server-side input validation
- Email validation
- MIME-type and file-size checks for uploads
- Randomised stored upload filenames
- User-ownership checks before record edits/deletes
- Role-aware administrator access control
- Audit logging for administrator actions and report activity
- Database constraints and indexes

## Known Limitations

This is a **college / portfolio project**, not a production financial service. The current build documentation identifies several areas that would need further work for public deployment:

- No CSRF token protection
- No login throttling / rate limiting
- No pagination for the transaction list
- Database credentials are stored in the application configuration file
- Currency and language settings are stored but the current build displays amounts as rupees
- PDF export uses the browser's native print-to-PDF workflow

## Academic Project Context

**Project:** BudgetWise — Personal Finance Tracker  
**Level:** BCA Major Project  
**Technology stack:** HTML5, CSS3, JavaScript, PHP 8.x, MySQL 8, MySQLi, Chart.js  
**Environment:** XAMPP  
**Development model:** Procedural PHP, no PHP framework, no build tool, no external PHP library

## Portfolio Note

This repository is intended to demonstrate practical application development, database design, CRUD operations, authentication, reporting, and data visualisation as part of a BCA portfolio.

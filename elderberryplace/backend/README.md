# 🏥 ICT30017 - DigiStDesign — Elderberry Place

This project is part of **ICT30017** at Swinburne, TP2 2025. It provides a simple PHP/MySQL web app for managing aged care services and staff.
---
**Mercury is running PHP 5.4.16**
---
## API-based V2.
- /api/v1: Controllers contain functions. routes.php defines routes. /api/index.php is our router.

- Pages in root dir (/) are our views.
---
## 📦 Getting Started (Mercury Setup)

Follow these steps to run your own copy:

1. **Clone the repository** to your local machine.

2. **Set up environment configuration:**
   - Copy the example:
     ```bash
     cp config/.env.php.example config/.env.php
     ```
   - Edit `config/.env.php` and fill in:
     - Your `STUDENT_ID`
     - Your **Mercury password**

3. **Deploy to Mercury server:**
   - Upload all files to:
     ```
     /home/students/accounts/s{{YOUR_STUDENT_ID}}/ict30017/www/htdocs/
     ```

4. **Initialize the database:**
   - Visit this URL in your browser:
     ```
     https://mercury.swin.edu.au/ict30017/{{YOUR_STUDENT_ID}}/setup_db.php
     ```
   - Click the buttons to set up the database tables.

---
## 📦 Getting Started (Local Setup)

1. **Install Laragon** to your local machine.

2. **Download PHP 5.4.16** from https://windows.php.net/downloads/releases/archives/

3. **Download MySQL 5.7.44** from https://downloads.mysql.com/archives/community/

4. Extract both zips to respective folder, e.g. C:\laragon\bin\php\

5. Open the settings menu (gear icon) inside Laragon and go to **Services & Ports tab**

6. Deselect Apache, select nginx

7. Right click anywhere on main window and change MySQL/PHP versions to the ones downloaded, above

8. From the MySQL menu click "create database" --> "s{{STUDENT_ID}}_db"

9. **Clone the repository** to Laragon www folder, e.g. C:\Laragon\www\ElderberryPlace

10. **Visit localhost:8080\ElderberryPlace\setup_db.php** and run database init script.

---
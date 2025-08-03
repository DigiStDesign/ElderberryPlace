# 🏥 ICT30017 - DigiStDesign — Elderberry Place

This project is part of **ICT30017** at Swinburne, TP2 2025. It provides a simple PHP/MySQL web app for managing aged care services and staff.
---
**Mercury is running PHP 5.4.16**
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
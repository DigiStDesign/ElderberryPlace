# ElderberryPlace

Vue 3 (Vite) **frontend** + PHP **REST API** backend.

This README walks you through **local setup end‑to‑end**, including MySQL, the PHP API, the Vue app, CSRF/CORS, and role‑based redirects.

---

## Project layout

```
elderberryplace/
├─ backend/                  # PHP codebase
│  ├─ api/
│  │  ├─ v1/                 # controllers + route map
│  │  │  ├─ AuthController.php
│  │  │  └─ routes.php
│  │  └─ lib/                # http helpers, auth helpers, db helpers
│  │     └─ http.php         # CORS set here (for local dev)
│  ├─ config/                # db config (.env.php)
│  ├─ sql/                   # init_db.sql schema + seeds
│  └─ index.php              # front controller (receives /api/index.php?r=v1/...)
└─ frontend/                 # Vue 3 + Vite app
   ├─ src/
   │  ├─ api/                # axios client + API modules (auth, residents, ...)
   │  ├─ components/         # AppHeader, AppNav, DataTable, etc.
   │  ├─ pages/              # LoginView, HomeView, ResidentsList, StaffList, ...
   │  ├─ router/             # routes + guards (role-based redirects)
   │  └─ stores/             # Pinia auth store
   ├─ index.html
   ├─ package.json
   └─ vite.config.ts (optional proxy; see below)
```

---

## Requirements

- **Node.js 18+** (LTS recommended)
- **npm 9+**
- **PHP 7.4+** (PHP 8.x recommended)
- **MySQL 8.x** (or MariaDB). Examples below assume MySQL.

> macOS users: Homebrew makes installs easy.

---

## Database (MySQL) setup

1) **Install + start MySQL** (macOS via Homebrew):

```bash
brew update
brew install mysql
brew services start mysql
```

2) **Create DB + user** (run in Terminal):

```bash
mysql -u root -p
```
At the `mysql>` prompt, run:
```sql
CREATE DATABASE elderberry CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'elderuser'@'localhost' IDENTIFIED BY 'testpw';
GRANT ALL PRIVILEGES ON elderberry.* TO 'elderuser'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

3) **Import schema + demo data**:

```bash
mysql -u elderuser -p elderberry < backend/sql/init_db.sql
# password: testpw
```

This seeds demo users (passwords are **md5** in demo DB; backend compares accordingly):
- `admin / admin`
- `staff / staff`
- `resident / resident`
- `visitor / visitor`

4) **Configure backend DB credentials** in `backend/config/.env.php`:

```php
<?php
$DB_HOST = '127.0.0.1';   // use 127.0.0.1 to force TCP
$DB_PORT = 3306;
$DB_NAME = 'elderberry';
$DB_USER = 'elderuser';
$DB_PASS = 'testpw';
```

---

## Backend (PHP API)

From repo root:

```bash
cd backend
php -S 127.0.0.1:8000
```

### Important: local CORS for dev
We **enable CORS** for the Vite dev server in `backend/api/lib/http.php`:
```php
// Allows http://localhost:5173 to call the API during local dev
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, X-CSRF-Token");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
```
> These headers are only for local development; adjust for production.

### API base path
The front controller expects: `http://127.0.0.1:8000/api/index.php?r=v1/...`

Key routes (see `backend/api/v1/routes.php`):
- `GET  v1/csrf` → `{ ok, data: { csrf } }`
- `POST v1/auth/login` body `{ username, password }` → `{ ok, data: { user, csrf } }`
- `POST v1/auth/logout`
- `GET  v1/me` → `{ ok, data: { user } }`
- Residents/Staff/Visitors/Categories/Services/Schedule… (see file for full list)

### Auth notes
- `AuthController.php` uses PHP sessions + returns `{ ok:true, data:{ user, csrf } }` on login.
- Demo DB stores `password_hash` as md5 (for demo only) and checks case‑insensitively.

---

## Frontend (Vue 3 + Vite)

From repo root:

```bash
cd frontend
npm install
npm run dev
```
Open the shown URL (usually `http://localhost:5173`).

### Axios client baseURL (no proxy mode)
We currently **call the PHP API directly** from the browser. See `frontend/src/api/client.js`:
```js
import axios from 'axios'

const api = axios.create({
  baseURL: 'http://127.0.0.1:8000/api/index.php?r=v1', // IMPORTANT: no leading slash after r=
  headers: { 'Content-Type': 'application/json' },
  withCredentials: true, // send PHP session cookie
})

// Attach CSRF to non‑GET requests if available
api.interceptors.request.use(cfg => {
  const csrf = localStorage.getItem('csrf') || window.__CSRF
  if (csrf) cfg.headers['X-CSRF-Token'] = csrf
  return cfg
})

export default api
```
> Because we call across ports (5173 → 8000), CORS headers are required (see backend section above).

### Optional: Vite proxy mode
As an alternative to CORS, you can proxy `/api` in `vite.config.ts` so the browser thinks it’s same‑origin:
```ts
export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': { target: 'http://127.0.0.1:8000', changeOrigin: true }
    }
  }
})
```
If you choose proxy mode, change Axios baseURL to `/api/index.php?r=v1`.

---

## Role‑based redirects (what happens after login)

- Frontend reads `user.role` from the login response and redirects:
  - **admin**   → `/home`
  - **staff**   → `/staff`
  - **resident**→ `/residents`

Where it’s implemented:
- `src/pages/LoginView.vue` → determines landing after login (with robust role normalization)
- `src/router/guards.js`    → central helpers + route guards
  - `requireAuth` blocks unauthenticated access
  - `redirectIfAuthed` sends logged‑in users away from `/login`
  - `requireRole([roles])` optional role enforcement per route

---

## End‑to‑end local run (quick steps)

1. **Start DB** (if not running):
   ```bash
   brew services start mysql
   ```
2. **Start backend**:
   ```bash
   cd backend
   php -S 127.0.0.1:8000
   ```
3. **Start frontend** (new terminal):
   ```bash
   cd frontend
   npm run dev
   ```
4. **Open** `http://localhost:5173` and log in with a demo user (e.g., `staff / staff`).

You should be redirected based on role: `/home`, `/staff`, or `/residents`.

---

## Scripts (cheat‑sheet)

**Backend**
```bash
php -S 127.0.0.1:8000
```

**Frontend**
```bash
npm install
npm run dev
npm run build
npm run preview
```

**MySQL**
```bash
brew services start mysql
brew services stop mysql
mysql -u elderuser -p elderberry
```

---



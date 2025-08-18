# ElderberryPlace

Modern **Vue 3 (Vite)** frontend + **PHP** REST API.

## Project layout

```
elderberryplace/
├─ backend/                  # PHP codebase 
│  ├─ api/                   # /api/v1 controllers, routing, libs
│  ├─ includes/, config/, sql/
│  └─ index.php …
└─ frontend/                 # Vue 3 + Vite app
   ├─ src/
   │  ├─ api/                # axios client + API modules
   │  ├─ pages/              # route views (Residents, etc.)
   │  ├─ components/         # reusable UI
   │  ├─ router/             # routes + guards
   │  └─ stores/             # Pinia (auth)
   ├─ index.html
   ├─ package.json
   └─ vite.config.ts         # dev proxy to PHP API
```

---

## Prerequisites

- **Node.js** 18+ (LTS recommended)
- **npm** 9+
- **PHP** 7.4+ (PHP 8.x recommended)
- A local DB that matches `backend/config/db.php` (PDO)

---

## 1) Backend – PHP API

From `elderberryplace/backend`:

### Configure DB
- Copy `config/.env.php.example` → `config/.env.php` and fill in DB creds  
  (see example below).
- Initialize schema if needed: run SQL in `sql/init_db.sql` on your DB.

### Start the PHP dev server
```bash
cd backend
php -S 127.0.0.1:8000
```

API base will be `http://127.0.0.1:8000/api/v1`.

**Key files**
- Routes: `backend/api/v1/routes.php`
- Auth/session helpers: `backend/api/lib/auth_api.php`, `backend/api/lib/http.php`
- Controllers: `backend/api/v1/*Controller.php`

### Example `config/.env.php.example`
```php
<?php
// Copy to .env.php and edit
return array(
  'DB_DSN'  => 'mysql:host=127.0.0.1;dbname=elderberry;charset=utf8mb4',
  'DB_USER' => 'root',
  'DB_PASS' => 'password'
);
```

*Your `config/db.php` should read from this file or define a PDO.*  

---

## 2) Frontend – Vue 3 + Vite

From `elderberryplace/frontend`:

### Install
```bash
cd frontend
npm install
```

### Dev proxy (avoid CORS)
Create `vite.config.ts`:

```ts
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true
      }
    }
  }
})
```

> This forwards `/api/v1/...` to the PHP server you run at `127.0.0.1:8000`.

### Run
```bash
npm run dev
```
Open the shown URL (usually `http://localhost:5173`).

---

## Auth & CSRF (how the app talks to the API)

- **Session cookie**: set by PHP on `/auth/login` (via proxy, same-origin in dev).
- **CSRF**: required for non-GET methods via header `X-CSRF-Token`.
  - After login, backend returns `{ ok:true, data:{ user, csrf } }`.
  - Frontend stores `csrf` and injects it on POST/PUT/DELETE (Axios interceptor).
  - Alternatively available from `GET /api/v1/csrf`.

**Relevant frontend code**
- `src/stores/auth.js` — login/logout, stores `user` + `csrf`, `init()` loads `/me` + `/csrf`.
- `src/api/auth.js` — wraps `/auth/login`, `/me`, `/csrf`, `/auth/logout`.
- `src/api/client.js` — Axios with `withCredentials: true` and CSRF header injection.

---

## Main routes (high level)

(See exact details in `backend/api/v1/routes.php`.)

- **Auth**
  - `GET /csrf` → `{ ok, data:{ csrf } }`
  - `POST /auth/login` body `{ username, password }` → `{ ok, data:{ user, csrf } }`
  - `POST /auth/logout`
  - `GET /me` → `{ ok, data:{ user } }`
- **Residents**
  - `GET /residents`
  - `POST /residents`
  - `GET /residents/:id`
  - `PUT /residents/:id`
  - `DELETE /residents/:id`
- **Other modules** (placeholders in frontend)
  - `/staff`, `/visitors`, `/services`, `/visits`,
    `/service-schedule`, `/categories`, `/relationships`

---

## Running both together (dev)

1. **Terminal A**
   ```bash
   cd backend
   php -S 127.0.0.1:8000
   ```
2. **Terminal B**
   ```bash
   cd frontend
   npm run dev
   ```
3. Open `http://localhost:5173`, log in with a valid **username/password** (not email).

---

## Build & preview (frontend)

```bash
cd frontend
npm run build
npm run preview
```

This outputs static files to `frontend/dist/`.

---

## Deploying later on Mercury (summary)

- Upload `frontend/dist/` to your web directory (e.g., `/~username/elderberry/`).
- If using **history mode** (default): add an `.htaccess` SPA fallback:

```
RewriteEngine On
RewriteBase /~username/elderberry/
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
RewriteRule . index.html [L]
```

- Or switch to hash history in `router/index.js`.
- Host the PHP API under a sibling path (e.g., `/~username/elderberry-api/`) and adjust frontend API base if needed.


## Scripts (quick ref)

**Backend**
```bash
php -S 127.0.0.1:8000
```

**Frontend**
```bash
npm run dev
npm run build
npm run preview
```

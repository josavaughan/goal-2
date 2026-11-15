# Professional Development Goal Tracker

A small, self-hosted web app for tracking progress toward professional development goals
(certifications, CEUs/PDUs, degrees, courses, study hours).

- **Public front page** — a bar chart of percent complete for each goal, plus a details
  table. Anyone with the address can view it; nobody can change anything there.
- **Private admin area** (login required) —
  - create, edit and delete goals (name, description, target amount, unit, start date, deadline,
    display order, public/private)
  - a progress log for each goal: add, edit and delete entries (date, description, units)
  - CSV export of the whole log (for backups and CEU/PDU renewal paperwork)
  - change your password
- **No judgments** — the app reports only facts: units logged, percent of target, start date
  and deadline. It never labels a goal as ahead, behind, late or on track.
- **Readable** — large type, high contrast, a light/dark switch and an **A+** larger-text
  switch (both remembered per browser), keyboard-friendly, works at phone width.

Built with plain PHP (8.0+) and either SQLite or MySQL. No build step, no framework,
no external scripts or fonts — every file needed is in this folder.

---

## Installing on HostGator (cPanel)

### 1. Upload the files

1. Log in to cPanel and open **File Manager**.
2. Decide where the app will live:
   - the main site: `public_html/`
   - or a sub-folder, e.g. `public_html/goals/` (the site will be `https://yourdomain.com/goals/`)
3. Upload a ZIP of this folder, then right-click it → **Extract**. Make sure hidden files
   (`.htaccess`) came along — in File Manager use **Settings → Show Hidden Files**.

### 2. Create `config.php`

1. In File Manager, right-click `config.sample.php` → **Copy** → name the copy `config.php`.
2. Right-click `config.php` → **Edit**. Set `site_title`, `site_subtitle`, and `timezone`
   (`America/Denver` is Mountain Time).
3. Choose a database:
   - **SQLite (simplest, recommended)** — leave `'driver' => 'sqlite'`. Nothing else to do.
     The data lives in `data/goals.sqlite`, which the web server refuses to serve.
   - **MySQL** — in cPanel open **MySQL Databases**: create a database, create a user,
     and add the user to the database with **All Privileges**. Then set
     `'driver' => 'mysql'` and fill in the name, user and password. HostGator adds your
     cPanel username as a prefix, e.g. `jdoe_goals`.

### 3. Check PHP version

cPanel → **MultiPHP Manager** (or **Select PHP Version**) → set the domain to **PHP 8.1 or newer**.
The `pdo_sqlite` (or `pdo_mysql`) extension must be on; it is on by default at HostGator.

### 4. Run the installer

1. Visit `https://yourdomain.com/install.php` (or `/goals/install.php`).
2. Create your username and a password of at least 10 characters.
3. **Delete `install.php`** from the server in File Manager. (It refuses to run again once an
   account exists, but there is no reason to leave it.)

### 5. Turn on HTTPS

cPanel → **SSL/TLS Status** → run AutoSSL if the domain isn't covered yet. Always log in
over `https://` so your password is encrypted in transit.

---

## Using it

| Task | Where |
|---|---|
| View the chart | The site's home page — no login |
| Log in | **Log in** link, top right |
| Add a goal | **Manage goals → + New goal** |
| Record progress | **Manage goals → Log progress** on a goal → fill in date, units and description |
| Fix a mistake | On the goal's log page, **Edit** or **Delete** the entry. A negative number of units also works as a correction. |
| Hide a goal from the public | Edit the goal and untick **Show on the public chart** |
| Change order on the chart | Edit the goal's **Display order** (lower numbers show first; ties sort by deadline) |
| Back up | **Manage goals → Export log (CSV)**; also download `data/goals.sqlite` (or use cPanel **Backup** for MySQL) |

**Percent complete** = sum of logged units ÷ target × 100. The bar stops at 100%, but the number shows
any overage (e.g. 110%).


---

## Security notes

- Passwords are stored with PHP's `password_hash` (bcrypt), never in plain text.
- Every form that changes data is protected against cross-site request forgery.
- Logins are limited to 5 failed attempts per IP address per 15 minutes.
- All database queries use prepared statements; all output is escaped.
- `.htaccess` files block web access to `config.php`, `inc/` and `data/`.
- CSV export neutralizes text that a spreadsheet could interpret as a formula.

## Files

```
index.php            public chart
login.php, logout.php
install.php          one-time setup (delete after use)
config.sample.php    copy to config.php
admin/index.php      goal list
admin/goal.php       create / edit / delete a goal
admin/log.php        progress log for one goal (add / edit / delete entries)
admin/account.php    change password
admin/export.php     CSV download
inc/bootstrap.php    database, session, helpers, progress math
inc/layout.php       page header / footer
assets/style.css     all styling
data/                SQLite database lives here (web access blocked)
```

# 🛍️ 4K STORE • Modern Fashion E-Commerce Web Application
> **SN STORE** — គេហទំព័រពាណិជ្ជកម្មអេឡិចត្រូនិចលក់សម្លៀកបំពាក់ទាន់សម័យ និងប្រព័ន្ធគ្រប់គ្រងអាជីវកម្ម (B2C Full-stack E-Commerce Platform)

[![PHP](https://img.shields.io/badge/PHP-8.2-777bb4?style=flat-square&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479a1?style=flat-square&logo=mysql&logoColor=white)](https://mysql.com)
[![Bakong KHQR](https://img.shields.io/badge/Payment-Bakong_KHQR-red?style=flat-square)](https://bakong.nbc.gov.kh)
[![Language](https://img.shields.io/badge/i18n-Khmer%20%7C%20English-success?style=flat-square)](#)
[![Theme](https://img.shields.io/badge/Theme-Light%20%7C%20Dark-darkgreen?style=flat-square)](#)

---

## 👥 ព័ត៌មានក្រុមការងារ (Team & Project Info)
* **ឈ្មោះគម្រោង (Project Name):** 4K STORE • Modern Fashion E-Commerce Platform
* **មុខវិជ្ជា (Subject):** Web Development & Database Engineering (E-Commerce Final Project)
* **ស្ថាប័ន / ឆ្នាំសិក្សា:** 2026 - 2027
* **សមាសភាពក្រុម (Authors):**
  1. **Sonich Rem (ស៊ុននិច រ៉ែម)** — *Lead Developer & System Architect* (GitHub: [@remsonich](https://github.com/remsonich))
  2. **Chan Kakada (ចាន់ កក្កដា)** — *Frontend Developer & UI/UX Designer*
* **សៀវភៅគម្រោងពេញលេញ (Full Project Book):**
  - 📖 **[PROJECT_DOCUMENTATION.md](PROJECT_DOCUMENTATION.md)** (ឯកសារផ្លូវការ ១០ ជំពូក តាមលំនាំគ្រូណែនាំ)
  - 🌐 **[PROJECT_BOOK.html](PROJECT_BOOK.html)** (ទម្រង់ HTML អាចបើកក្នុង Browser ចុច `Ctrl + P` រក្សាទុកជា PDF ឬចម្លងចូល Word)

---

## 🌟 លក្ខណៈពិសេសសំខាន់ៗ (Key Features)

### 1. Storefront (អតិថិជន / Client UI)
* **Product Catalog & Dynamic Filter:** ស្វែងរក និងច្រោះផលិតផលតាមប្រភេទ (Men, Women, Accessories) និងលំដាប់តម្លៃ។
* **Interactive Shopping Cart:** បញ្ចូលកន្ត្រកទំនិញ, កែប្រែចំនួន (+/-), គណនាតម្លៃសរុប និងប្រព័ន្ធបញ្ចុះតម្លៃ Coupon Code។
* **Bakong KHQR Payment Flow:** បង្កើតកូដ KHQR ស្វ័យប្រវត្តិតាមស្តង់ដារធនាគារជាតិ, ផ្ទាំង Mock Simulation បញ្ជាក់ការទូទាត់ភ្លាមៗ និងទម្រង់ Upload វិក្កយបត្រ (Receipt Slip)។
* **Authentication System:**
  - ចុះឈ្មោះ និងចូលប្រើដោយ Email / Password (ការពារដោយ `password_hash` Bcrypt)
  - Google Sign-In One-Tap & Button Integration (Google Identity Services)
* **Customer Profile:** មើលប្រវត្តិការបញ្ជាទិញ (Order History), តាមដានស្ថានភាពដឹកជញ្ជូន, កែប្រែព័ត៌មានផ្ទាល់ខ្លួន និងប្ដូរពាក្យសម្ងាត់។
* **Customer Service & Contact:** ទម្រង់ផ្ញើសារសាកសួរ និងតំណភ្ជាប់ Telegram / Live Support ផ្ទាល់។
* **i18n & Theming:** គាំទ្រភាសាខ្មែរ និងអង់គ្លេស, ប្ដូរ Light Mode / Dark Mode ភ្លាមៗដោយគ្មានការព្រិចភ្នែក (Zero-flash theme engine)។

### 2. Backoffice Admin Panel (អ្នកគ្រប់គ្រងហាង)
* **Executive Dashboard:** មើលរបាយការណ៍ចំណូលសរុប (Total Revenue), ចំនួន Orders, តម្លៃទំនិញក្នុងស្តុក និងចំនួនអតិថិជន។
* **Product Management (CRUD):** បន្ថែម, កែប្រែ, លុប និងគ្រប់គ្រងស្តុកផលិតផល (+/- Stock)។
* **Order & KHQR Verification:** ពិនិត្យវិក្កយបត្របង់ប្រាក់ KHQR, ផ្ទៀងផ្ទាត់ និងកែប្រែស្ថានភាព Order (`pending`, `paid`, `shipped`, `delivered`, `cancelled`)។
* **Customer Service Hub:** ឆ្លើយតប និងគ្រប់គ្រងសារដែលអតិថិជនបានផ្ញើមក។
* **Store Settings & Content CMS:** កែប្រែឈ្មោះហាង, ឡូហ្គោ, លេខទូរស័ព្ទ, Telegram Link និង Page Notes / Announcement Text ផ្ទាល់លើ UI។
* **Security & Auditing:** ប្រព័ន្ធការពារ Brute-Force Rate Limiting (ចាក់សោរ IP & Identity ពេលព្យាយាមខុសលើស ៥ ដង), CSRF Token Protection និង Admin Activity Audit Trail។
* **Database Backup:** ទាញយក SQL Backup និងនាំចេញទិន្នន័យបានដោយសុវត្ថិភាព។

---

## 🏗️ រចនាសម្ព័ន្ធបច្ចេកវិទ្យា (System Architecture)

```
       +-------------------------------------------------------------+
       |                  CLIENT DEVICES & BROWSERS                  |
       |             (Responsive: Mobile, Tablet, Desktop)          |
       +------------------------------+------------------------------+
                                      | HTTP/HTTPS
                                      v
       +-------------------------------------------------------------+
       |               APACHE WEB SERVER (XAMPP / PROD)              |
       |  .htaccess Security Headers • Static Assets Caching • Router|
       +------------------------------+------------------------------+
                                      |
         +----------------------------+----------------------------+
         |                                                         |
         v                                                         v
+-------------------------------+         +--------------------------------+
|    STOREFRONT UI (home/)      |         |     BACKOFFICE ADMIN (admin/)  |
| - index.php (Product Catalog) |         | - index.php (Dashboard & CRUD) |
| - checkout.php (KHQR Flow)    |         | - security.php (Audit & Logins)|
| - login.php / profile.php     |         | - operations.php / backup.php  |
| - customer-service/           |         | - store-management.php (CMS)   |
+---------------+---------------+         +----------------+---------------+
                |                                          |
                +---------------------+--------------------+
                                      |
                                      v
       +-------------------------------------------------------------+
       |                   CORE APPLICATION API (api/)               |
       |  - db.php (MySQLi Wrapper)      - i18n.php (Khmer/English)  |
       |  - user-auth.php / admin-auth   - telegram-order.php        |
       |  - cart.php / products.php      - admin-operations.php      |
       +------------------------------+------------------------------+
                                      |
                    +-----------------+-----------------+
                    |                                   |
                    v                                   v
       +--------------------------+        +--------------------------+
       |   MYSQL DATABASE (RDBMS) |        |     EXTERNAL SERVICES    |
       |   - 14 Normalized Tables |        | - Telegram Bot Alerts    |
       |   - InnoDB Engine        |        | - Google Identity (GIS)  |
       |   - Foreign Key Cascades |        | - Mock Bakong KHQR Engine|
       +--------------------------+        +--------------------------+
```

---

## 🗄️ រចនាសម្ព័ន្ធទិន្នន័យ (Database Schema)

មូលដ្ឋានទិន្នន័យ `ecommerce` ត្រូវបានរៀបចំតាមកម្រិតស្តង់ដារ 3NF (Third Normal Form) ដែលមានតារាងសរុបចំនួន **១៤ តារាង**៖

| ល.រ | ឈ្មោះតារាង (Table) | បរិយាយ និងតួនាទី (Description) | កូនសោរសំខាន់ (PK / FK) |
|:---:|:---|:---|:---|
| 1 | `users` | គណនីអតិថិជន (Email, Password, Name, Google ID) | `PK: id` |
| 2 | `account_sessions` | រក្សាទុក Login Remember Tokens របស់អតិថិជន | `PK: id`, `FK: user_id` |
| 3 | `admins` | គណនីអ្នកគ្រប់គ្រងប្រព័ន្ធ (Admin & Superadmin) | `PK: id` |
| 4 | `admin_sessions` | សម័យកាល Session របស់អ្នកគ្រប់គ្រង | `PK: id`, `FK: admin_id` |
| 5 | `admin_login_attempts` | កត់ត្រាការ Sign-in បរាជ័យដើម្បីការពារ Brute-force | `PK: id` |
| 6 | `admin_audit_log` | កំណត់ត្រាសកម្មភាពសំខាន់ៗរបស់ Admin | `PK: id`, `FK: admin_id` |
| 7 | `categories` | ប្រភេទផលិតផល (Men, Women, Accessories) | `PK: id` |
| 8 | `products` | ព័ត៌មានលម្អិតនៃផលិតផល, តម្លៃ និងចំនួនស្តុក | `PK: id`, `FK: category_id` |
| 9 | `orders` | បញ្ជីការបញ្ជាទិញ (Total, Status, Payment, Info) | `PK: id`, `FK: user_id` |
| 10 | `order_items` | មុខទំនិញក្នុង Order នីមួយៗ (Quantity, Price) | `PK: id`, `FK: order_id, product_id` |
| 11 | `cart_items` | កន្ត្រកទំនិញបណ្ដោះអាសន្នរបស់អតិថិជន | `PK: id`, `FK: user_id, product_id` |
| 12 | `coupons` | លេខកូដបញ្ចុះតម្លៃ (Discount Percentage / Amount) | `PK: id` |
| 13 | `customer_messages` | សារទំនាក់ទំនងពីទំព័រ Customer Service | `PK: id`, `FK: user_id` |
| 14 | `system_settings` | ការកំណត់ហាងទូទៅ (ឈ្មោះហាង, Logo, Telegram) | `PK: id` |

---

## 🧪 លទ្ធផលនៃការធ្វើតេស្ត (Testing & Test Cases)

ប្រព័ន្ធត្រូវបានធ្វើតេស្តផ្ទៀងផ្ទាត់គ្រប់មុខងារស្នូលទាំងអស់ (All 11 Test Cases **PASSED**):

* **TC-01 (Storefront Browsing):** ចូលទំព័រដើម និងមើលទំនិញទាំងអស់ — **PASS**
* **TC-02 (Category Filter):** ច្រោះផលិតផលតាម Men / Women / Accessories — **PASS**
* **TC-03 (Customer Registration):** បង្កើតគណនីថ្មីជាមួយ Email និង Password — **PASS**
* **TC-04 (Customer Sign-In):** ចូលប្រើប្រព័ន្ធជាមួយគណនីអតិថិជន — **PASS**
* **TC-05 (Shopping Cart):** បញ្ចូលទំនិញទៅកន្ត្រក និងកែប្រែចំនួនទំនិញ — **PASS**
* **TC-06 (Checkout & KHQR):** បញ្ចូលអាសយដ្ឋាន និងបង្កើតកូដ Bakong KHQR — **PASS**
* **TC-07 (Payment Confirmation):** បញ្ជាក់ការទូទាត់ និងផ្ញើ Telegram Alert — **PASS**
* **TC-08 (Admin Authentication):** ចូលប្រើ Dashboard ជាមួយគណនី Admin — **PASS**
* **TC-09 (Product CRUD):** បន្ថែម, កែប្រែ និងគ្រប់គ្រងស្តុកទំនិញ — **PASS**
* **TC-10 (Order Management):** ផ្ទៀងផ្ទាត់វិក្កយបត្រ និងផ្លាស់ប្ដូរស្ថានភាព Order — **PASS**
* **TC-11 (i18n & Theme Switch):** ប្ដូរភាសាខ្មែរ-អង់គ្លេស និង Dark Mode — **PASS**

---

## 🚀 របៀបតម្លើង និងដំណើរការគម្រោង (Setup & Installation)

### 1. តម្រូវការប្រព័ន្ធ (Prerequisites)
* **Web Server:** Apache (XAMPP / WampServer / Laragon)
* **PHP:** កំណែ 8.1 ឬខ្ពស់ជាងនេះ (បើក Extensions: `mysqli`, `openssl`, `curl`, `mbstring`)
* **Database:** MySQL 5.7+ ឬ MariaDB 10.4+

### 2. ជំហានតម្លើង (Step-by-Step Setup)

1. **ទាញយកកូដពី GitHub Repository:**
   ```bash
   git clone https://github.com/remsonich/E-Commerce_Webiste_Store.git
   ```

2. **ចម្លងគម្រោងទៅកាន់ថត Web Server:**
   * សម្រាប់ XAMPP: ដាក់នៅក្នុង `C:/xampp/htdocs/Ecommerce`

3. **តម្លើងមូលដ្ឋានទិន្នន័យ (Import Database):**
   * បើកកម្មវិធី **phpMyAdmin** (`http://localhost/phpmyadmin`)
   * បង្កើត Database ថ្មីឈ្មោះ `ecommerce` (Collation: `utf8mb4_unicode_ci`)
   * ចូលទៅកាន់ផ្ទាំង **Import** រួចជ្រើសរើសឯកសារ:
     `databases/ecommerce.sql`
   * ចុច **Go / Import** ដើម្បីបញ្ចូលរចនាសម្ព័ន្ធតារាង និងទិន្នន័យគំរូ។

4. **កំណត់ការតភ្ជាប់ Database (Configuration):**
   * ប្រព័ន្ធកំណត់តាមលំនាំដើមក្នុង `api/db.php` សម្រាប់ XAMPP (`localhost`, user: `root`, password: ទទេ)។
   * ប្រសិនបើចង់ផ្លាស់ប្ដូរ អ្នកអាចបង្កើតឯកសារ `.env` នៅក្នុង Root Directory:
     ```dotenv
     APP_ENV=local
     ECOMMERCE_DB_HOST=localhost
     ECOMMERCE_DB_NAME=ecommerce
     ECOMMERCE_DB_USER=root
     ECOMMERCE_DB_PASSWORD=
     ```

5. **បើកដំណើរការកម្មវិធី (Run Application):**
   * 🌐 **ទំព័រអតិថិជន (Storefront):** `http://localhost/Ecommerce/`
   * ⚙️ **ទំព័រអ្នកគ្រប់គ្រង (Admin Dashboard):** `http://localhost/Ecommerce/admin/`

---

## 🔑 គណនីសម្រាប់តេស្តសាកល្បង (Demo Credentials)

| ផ្នែក (Area) | Username / Email | Password | តួនាទី (Role) |
|:---|:---|:---|:---|
| **Admin Dashboard** | `kakadachan541@gmail.com` | `12345678` | System Admin |
| **Admin Dashboard** | `netsmos100` | `12345678` | Lead Admin |
| **Storefront Customer**| `kakadachan541@gmail.com` | `12345678` | Verified Customer |

---

## 📁 រចនាសម្ព័ន្ធថតឯកសារ (Directory Tree)

```text
Ecommerce/
├── admin/                      # Backoffice Admin Dashboard
│   ├── index.php               # Admin Main Router & Catalog
│   ├── primary-navigation.php  # Sidebar & Navigation
│   ├── operations.php          # Order Verification & Invoicing
│   ├── security.php            # Audit Trail & Session Security
│   ├── store-management.php    # Store CMS & Content Settings
│   └── backup.php              # Database Backup Utility
├── api/                        # Backend APIs & Business Logic
│   ├── db.php                  # Database Connection (MySQLi)
│   ├── admin-auth.php          # Admin Authentication & Lockout Engine
│   ├── user-auth.php           # Customer Auth & Google One-Tap
│   ├── cart.php                # Shopping Cart API
│   ├── products.php            # Products API & Filter
│   ├── telegram-order.php      # Telegram Bot Notifications
│   └── i18n.php                # Language Translation (KM/EN)
├── customer-service/           # Customer Support & Inquiries
│   └── index.php               # Contact Form & Help Center
├── databases/                  # Database Scripts
│   └── ecommerce.sql           # Complete Database Dump
├── home/                       # Storefront Client Pages
│   ├── index.php               # Homepage & Product Showcase
│   ├── checkout.php            # Order Placement & KHQR Flow
│   ├── login.php               # Login & Register Modal/Page
│   ├── profile.php             # User Profile & Order History
│   └── navbar.php              # Store Header & Cart Drawer
├── img/                        # Product & Banner Images
├── PROJECT_DOCUMENTATION.md    # Complete 10-Chapter Academic Project Book
├── PROJECT_BOOK.html           # Printable Academic Book (PDF/Word ready)
└── README.md                   # Repository Documentation
```

---

## 📜 អាជ្ញាប័ណ្ណ (License)
គម្រោងនេះត្រូវបានអភិវឌ្ឍឡើងសម្រាប់គោលបំណងសិក្សាស្រាវជ្រាវ (Academic Final Project) ក្រោមការអនុញ្ញាតរបស់សាកលវិទ្យាល័យ និងក្រុមការងារ។ រក្សាសិទ្ធិគ្រប់យ៉ាង © 2026 4K STORE / SN STORE។

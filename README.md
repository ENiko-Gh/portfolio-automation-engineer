# Edison Guamialama — IT Engineer & Remote Consultant Portfolio

[![PHP](https://img.shields.io/badge/PHP-8.2-blue)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)](https://mysql.com)
[![License](https://img.shields.io/badge/License-Private-red)](LICENSE)

> Full-stack IT portfolio with visitor management, CRM, AI assistant, and appointment scheduling.
---

## 🧠 Philosophy: Evidence Driven Consulting

This portfolio is not a CV. It's the **first laboratory** of my operational platform.

- **No assumptions:** Every claim is backed by a reproducible lab.
- **No theory:** Every service listed has a live demo (available under NDA).
- **No marketing:** Metrics are verifiable through Grafana dashboards.

> *"Evidencia antes que suposición"* — This rule governs every project.

---

## 🛠 Stack

- **Frontend:** HTML5, CSS3, JavaScript (vanilla)
- **Backend:** PHP 8.2, MySQL
- **Libraries:** PHPMailer, Twilio SDK, AOS.js
- **Security:** CORS, CSRF, Rate limiting, bcrypt

## 🚀 Quick Start (Local)

```bash
# 1. Clone
git clone https://github.com/TU_USUARIO/portfolio-automation-engineer.git

# 2. Configure environment
cp .env.example .env
# Edit .env with your real credentials

# 3. Install dependencies
composer install

# 4. Database setup
# Import database/schema.sql in phpMyAdmin

# 5. Serve
# Place in XAMPP htdocs and open http://localhost/portfolio-automation-engineer
```

## ⚙️ Environment Variables

Copy `.env.example` to `.env` and configure:

| Variable | Description |
|----------|-------------|
| `DB_*` | MySQL connection |
| `SMTP_*` | Gmail SMTP credentials |
| `TWILIO_*` | WhatsApp via Twilio |
| `APP_URL` | Your domain URL |
| `APP_DEBUG` | Debug mode (false in production) |

## 📁 Project Structure

```
portfolio-automation-engineer/
├── admin/          # Admin panel (dashboard, CRM, settings)
├── api/            # REST API endpoints
│   ├── config.php  # Loaded from .env
│   └── ...
├── assets/         # Images, PDFs, videos
├── css/            # Stylesheets
├── includes/       # Shared PHP (DB, email, WhatsApp, security)
├── js/             # JavaScript
├── vendor/         # Composer dependencies (NOT in git)
├── .env.example    # Environment template
└── index.html      # Main portfolio page
```

## 🔐 Security

- No credentials in code (Platform Rules 001-007)
- All secrets in `.env` (never committed)
- bcrypt password hashing
- CSRF protection on admin forms
- Rate limiting on APIs
- SQL injection prevention via PDO prepared statements
- XSS protection via htmlspecialchars

## 🔒 Demo & BackOffice Access (Private Lab)
The full version of this portfolio includes:

Admin dashboard with CRM, analytics, and automation.

AI assistant powered by n8n and Node-RED.

Real-time monitoring with Grafana and Prometheus.

Due to confidentiality agreements (NDA) with client projects, the complete BackOffice is not public.
However, it is fully operational in my private lab (Dell server) and can be demonstrated on demand during a live meeting.

📅 Book a 15-min demo via Calendly

## 📬 Contact

**Edison Nicolas Guamialama Haro**
- 🌐 Portfolio: [tudominio.com](https://tudominio.com)
- 📅 Book a call: [calendly.com/edhissonguami](https://calendly.com/edhissonguami)
- 💬 WhatsApp: Available via
   portfolio contact form

---

*IT Engineer · ITIN · ESPE · Remote Consultant*

# 🐾 PawAdopt — Pet Adoption Platform

**PawAdopt** ("Paw-Adopt — Give a Paw. Give a Home.") is a full-stack pet adoption web application that connects **pet adopters** with **animal shelters and veterinary partners**, with an admin back-office for moderation and oversight.

🌐 **Live demo:** **[https://pawadopt-web.onrender.com](https://pawadopt-web.onrender.com)**

---

## ✨ Why Use PawAdopt?

### For Adopters
- **Browse pets from anywhere** — search and filter available pets by species, with photos and shelter details, instead of visiting multiple shelters in person.
- **Apply online in minutes** — submit an adoption application directly to the shelter and track its status (Submitted → Under Review → Approved/Rejected) from your dashboard.
- **Save favorites** — bookmark pets you love and revisit them later.
- **Stay informed** — in-app notifications and messages keep you connected with the shelter; book adoption appointments without back-and-forth phone calls.
- **Personalize your experience** — the built-in Customize UI panel lets each user pick a theme, accent color, typography, and accessibility options.

### For Shelters & Veterinary Partners
- **Manage your shelter digitally** — list pets with photos and health details, review incoming applications in one queue, and message adopters directly.
- **Zero paperwork scheduling** — appointment slots with built-in double-booking prevention.
- **Verification built in** — shelter documents are reviewed by an admin before your listings go live, building trust with adopters.
- **Escalation path** — report concerns straight to platform admins from the dashboard.

### For Everyone
- **Secure by design** — hashed passwords, session-based auth, role-based access control, CSRF protection, and per-user data isolation enforced server-side.
- **Mobile friendly** — responsive layout with a drawer navigation for phones.
- **AI assistant** — PawBot, a chat helper, answers common questions about adopting and using the platform.

---

## 🚀 How to Use It

### 1. Visit the live app
Open **[https://pawadopt-web.onrender.com](https://pawadopt-web.onrender.com)** — the free tier may take **30–60 seconds to wake up** on the first visit.

### 2. Create an account
Click **Sign Up** and choose your role:

| Role | What you can do |
|---|---|
| **Adopter** | Browse pets, save favorites, apply for adoption, message shelters, book appointments |
| **Shelter / Veterinary** | Manage pets, review applications, respond to adopters, verify shelter documents |
| **Admin** | Platform moderation, user/shelter management, audit logs (assigned by the operator) |

### 3. Adopter walkthrough
1. **Browse** — open *Browse* to see available pets; use filters to narrow by species.
2. **Save** — tap ❤️ on pets you're interested in.
3. **Apply** — on a pet's page, submit an adoption application (one active application per pet; duplicates are blocked automatically).
4. **Track** — follow your application status under *My Applications*.
5. **Meet** — once approved, book an appointment slot with the shelter.

### 4. Shelter walkthrough
1. **Sign up** as a shelter — your listing shows *Verification Pending* until an admin reviews your documents.
2. **Add pets** — list each animal with photos and details.
3. **Review** — accept or reject applications from your dashboard queue.
4. **Chat** — message adopters and confirm appointment slots.
5. **Customize** — use *🎨 Customize UI* to brand your dashboard.

> 💡 **Note for demo visitors:** accounts on the live demo are for the site operator. Please **register your own account** to explore both adopter and shelter flows — registration is instant and free.

---

## 🛠️ Running It Yourself (Local Development)

### Requirements
- PHP 8.1+ (8.2 recommended)
- MySQL 8 / MariaDB 10.6+
- Apache/Nginx (or XAMPP/WAMP for local development)
- A free [Brevo](https://www.brevo.com) account (transactional email) and a [Google AI Studio](https://aistudio.google.com) key (AI assistant) — both optional for basic use

### Setup
1. **Place the files** in your web root (XAMPP: `C:\xampp\htdocs\PawAdopt\`).
2. **Create the database** and import the SQL files from `sql/` in numeric order (the main schema first, then the auxiliary tables).
3. **Configure environment variables** — copy `.env.example` to `.env` and fill in your own values:

   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=pawadopt
   DB_USER=your_db_user
   DB_PASS=your_db_password
   APP_URL=http://localhost:8080/PawAdopt
   APP_ENV=development

   # Optional integrations
   GEMINI_API_KEY=your_gemini_key
   GEMINI_MODEL=gemini-3.8-flash
   BREVO_API_KEY=your_brevo_key
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_ENCRYPTION=tls
   SMTP_USERNAME=your_email@example.com
   SMTP_PASSWORD=your_email_app_password
   SMTP_FROM_EMAIL=your_email@example.com
   SMTP_FROM_NAME=PawAdopt
   ```

   > 🔒 **Never commit `.env` to version control.** All credentials are read from the environment at runtime — no secrets are stored in the source code.

4. **Run** and open the app in your browser.

### Deploying
The repo ships with a `Dockerfile` and `render.yaml` Blueprint for one-click hosting on [Render](https://render.com) (Docker runtime) with an external MySQL database. See the deployment guide included in the repo for the full walkthrough.

---

## 🔐 Security Highlights

- Passwords hashed with **bcrypt** (`password_hash` / `password_verify`)
- **Role-based access control** enforced server-side on every endpoint (`requireLogin()` / `requireRole()`)
- **Per-user data isolation** — all queries scoped by session user ID
- **CSRF tokens** on state-changing forms
- **Unique constraints** at the database level to prevent duplicate applications, bookings, favorites, and chats
- **Secrets management** — all API keys and credentials supplied via environment variables only
- **Audit logging** of security-relevant events (logins, registrations, password resets)

---

## 📄 License & Credits

Built as a capstone project. Pet photos belong to their respective shelters.

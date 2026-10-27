# PawAdopt — README: Setup Guide (Ships Inside the Zip)

**This README is bundled inside `PawAdopt_Secured_1024.zip` together with all three
documents — the zip is the single complete download.** This guide walks you through
downloading the zip, moving it to a new computer's Desktop (Windows, macOS, or Linux),
extracting it, and running the project.

> All three documents listed below are **already inside this zip**, next to this file.
> The download links still work if you ever need standalone copies from your Genspark chat.

---

## 1. What you have (the exact 4 files)

| # | File | What it is | Size | In this zip? / standalone link |
|---|------|------------|------|---------------|
| 1 | **PawAdopt_Secured_1024.zip** | THIS archive — the complete, security-hardened PawAdopt project + all documents (453 files) | 42 MB | ✅ you're inside it |
| 2 | **PawAdopt_Security_Report.docx** | Word document: full code security review (secrets found, ownership table, route guards) | 42 KB | ✅ included — also at https://www.genspark.ai/api/files/s/E0DaAB0y |
| 3 | **PawAdopt_Secrets_Inventory.xlsx** | Excel spreadsheet: every API key, its original file location, and its env-var replacement | 8 KB | ✅ included — also at https://www.genspark.ai/api/files/s/StKC8aGV |
| 4 | **PawAdopt_Deployment_Guide.docx** | Word document: step-by-step instructions to deploy the updated PHP build | 39 KB | ✅ included — also at https://www.genspark.ai/api/files/s/vf4324cc |

> **Security note:** all secret values in these documents are masked (e.g. `AQ.Ab8****`).
> The zip itself contains **zero** plaintext keys — verified by a full-tree grep before delivery.

---

## 2. Step 1 — Download the files on the new computer

1. On the new computer, open any browser (Chrome, Edge, Firefox, Safari).
2. If you are signed in to Genspark in that browser, simply click each of the four links
   in the table above — or open this chat there and click the links in the earlier message.
3. Each file saves automatically to your **Downloads** folder.
   - The zip may take a minute or two (42 MB).
   - Some browsers scan large zips first; wait until it says "Done".

### If a download is blocked
- **Chrome:** the file appears at the bottom with a shield icon → click the ▲ arrow →
  **Keep** → **Keep anyway** (only for files from your own Genspark account).
- **Edge:** "PawAdopt_Secured_1024.zip is not commonly downloaded" → click **…** →
  **Keep** → **Show more** → **Keep anyway**.
- **Firefox:** downloads panel → right-click the blocked item → **Allow Download**.
- **macOS Gatekeeper:** the zip is not an app, so no Gatekeeper warning applies —
  if Safari says the file "cannot be opened because it is from an unidentified developer",
  that message is for *apps*, not zips; just double-click the zip to extract it.

> The file-wrapper links above are private: they open only for **your signed-in Genspark
> account**. If a link opens a login or "no access" page, sign in with the same account you
> used in this chat, then click the link again.

---

## 3. Step 2 — Move the files from Downloads to the Desktop

### Windows 10 / 11
1. Press **Win + E** to open File Explorer.
2. Left sidebar → **Downloads**.
3. Select all four files (click the first, hold **Ctrl**, click the others).
4. Right-click → **Cut**, then go to **Desktop** in the sidebar, right-click → **Paste**.
   *(Or drag them onto the Desktop window.)*

### macOS
1. Open **Finder** → **Downloads**.
2. Select the four files (hold **Cmd** and click each).
3. Press **Cmd + C**, click **Desktop** in the sidebar, press **Cmd + Option + V**
   (move here), or simply drag them onto the Desktop.

### Linux (Ubuntu / Fedora, etc.)
1. Open your file manager → **Downloads**.
2. Select the four files (**Ctrl + click**).
3. **Ctrl + X**, open **Desktop**, **Ctrl + V** — or drag and drop.

---

## 4. Step 3 — What opens each file

| File | Extension | Opens with (Windows) | Opens with (macOS) | Opens with (Linux) |
|------|-----------|----------------------|--------------------|--------------------|
| PawAdopt_Secured_1024.zip | `.zip` | Double-click → **Extract all** (built-in); or 7-Zip | Double-click → Archive Utility (built-in) | `unzip`, File Roller, or Ark (built-in on most distros) |
| PawAdopt_Security_Report.docx | `.docx` | **Microsoft Word**, or free: Word Online / Google Docs | Pages, Word, or Google Docs | LibreOffice Writer (free) |
| PawAdopt_Secrets_Inventory.xlsx | `.xlsx` | **Microsoft Excel**, or free: Excel Online / Google Sheets | Numbers, Excel, or Google Sheets | LibreOffice Calc (free) |
| PawAdopt_Deployment_Guide.docx | `.docx` | Same as the report | Same as the report | Same as the report |

**No Word or Excel installed?** All three documents open free in a browser at
[docs.google.com](https://docs.google.com) (upload → opens as Docs/Sheets), or install
[LibreOffice](https://www.libreoffice.org) (free, offline, works on all three systems).

---

## 5. Step 4 — Set up the PawAdopt project on the new machine

The zip is a **PHP 8 + MySQL web application** — it is not a desktop program you double-click.
To run it locally on the new computer:

### Prerequisites (install once)
| Need | Windows | macOS | Linux |
|------|---------|-------|-------|
| PHP 8.x | [XAMPP](https://www.apachefriends.org) (includes PHP + MySQL + Apache) | XAMPP or `brew install php` | `sudo apt install php mysql-server` |
| MySQL | Included in XAMPP | Included in XAMPP | `sudo apt install mysql-server` |
| Browser | Any | Any | Any |

### Run it
1. **Extract** `PawAdopt_Secured_1024.zip`.
   - XAMPP: extract the `PawAdopt` folder into `C:\xampp\htdocs\` (Windows) or
     `/Applications/XAMPP/htdocs/` (macOS).
2. **Start Apache + MySQL** in the XAMPP Control Panel.
3. **Create the database:** open `http://localhost/phpmyadmin` → **New** → name it
   `pawadopt` → **Import** these SQL files in order:
   `sql/pawadopt.sql` → the other `sql/*.sql` feature files →
   `sql/security_hardening.sql` → `sql/ui_customization.sql`.
4. **Set your secrets (no code edits needed):** copy `.env.example` to `.env` inside the
   `PawAdopt` folder and fill in `GEMINI_API_KEY` and `SMTP_PASSWORD` (and DB values if
   they differ from the defaults). Local defaults (`127.0.0.1:3307`, `root`, empty
   password) work out of the box with XAMPP.
5. **Open** `http://localhost:8080/PawAdopt/` (or `http://localhost/PawAdopt/` depending
   on your Apache port) — the landing page should load.

> **Going live instead?** Follow `PawAdopt_Deployment_Guide.docx` — it covers Render.com
> and other free hosts, plus the exact environment variables to set. Reminder: Cloudflare
> Pages / Netlify / Vercel **cannot run PHP**; use Cloudflare only as DNS/CDN.

---

## 6. Troubleshooting

| Problem | Fix |
|---------|-----|
| Download link says "no access" or shows a login page | The links are private to your Genspark account — sign in with the same account used in this chat, then retry. |
| Zip won't extract / "corrupt file" | Re-download; check the size is ~42 MB. A partial download is the usual cause. |
| Windows SmartScreen warns about the zip | It only flags large downloads from the web. Click **More info → Run anyway** — you downloaded it yourself from your own account. |
| DOCX opens as gibberish in Notepad | Right-click → **Open with** → Word / LibreOffice / browser; `.docx` needs a word processor. |
| Blank page at `localhost/PawAdopt` | Apache/MySQL not started in XAMPP, wrong port, or the database wasn't imported (see step 5 above). |
| Chat assistant or OTP e-mail doesn't work | `GEMINI_API_KEY` / `SMTP_PASSWORD` are not set — copy `.env.example` to `.env` and fill them in (see the Deployment Guide). |
| Uploaded pet photos disappear after redeploy | On Render, attach a persistent disk at `/opt/render/project/uploads` (Deployment Guide §2). |
| Antivirus quarantines the extracted folder | The build is plain PHP source — restore it and add an exclusion; it contains no executables. |

---

## 7. Quick checklist

- [ ] Downloaded all 4 files (links in section 1)
- [ ] Moved them to the **Desktop** (section 2/3 — Win: Cut/Paste · Mac: Cmd+C, Cmd+Opt+V · Linux: Ctrl+X/V)
- [ ] Extracted `PawAdopt_Secured_1024.zip` (double-click or Extract All)
- [ ] Read `PawAdopt_Security_Report.docx` and **rotated the two exposed keys** (Gemini + Gmail app-password)
- [ ] Checked `PawAdopt_Secrets_Inventory.xlsx` for the env-var names
- [ ] Followed `PawAdopt_Deployment_Guide.docx` to deploy locally or to a host

*Delivered October 24, 2026 · bundled October 26, 2026 · PawAdopt Secured build 1024 · 453 files, nothing removed.*

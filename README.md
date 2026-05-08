# Invoice-Generator

A lightweight invoice management system built with **PHP, MySQL, and JavaScript** that allows users to create, calculate, and print professional invoices with dynamic tax handling and business configuration.

---

## 🚀 Features

* Dynamic invoice item rows (add/remove)
* Automatic subtotal, tax, and total calculation
* Multi-tax configuration (GST, PST, etc.)
* Business profile settings (name, logo, contact)
* Auto-generated invoice numbers
* Save invoices to database
* Print-ready invoice layout

---

## 🛠️ Tech Stack

* PHP
* MySQL
* JavaScript
* HTML/CSS
* Local server: Laragon

---

## ⚙️ Setup (Local)

1. Clone the repository

```bash
https://github.com/nazrawielias/Invoice-Generator.git
```

2. Move project to Laragon `www` folder

```bash
C:\laragon\www\invoice-system
```

3. Start Laragon and run:

```bash
http://localhost/invoice-system/invoice.php
```

4. Create database:

```sql
CREATE DATABASE mydb;
```

Tables are created automatically on first run.

---

## 🧠 How It Works

* Configure business details and taxes in **Settings page**
* Create invoices using dynamic item rows
* System calculates totals + tax breakdown in real time
* Data is stored in MySQL
* Invoice is generated and printed instantly

---

## 📌 Notes

* Designed for local environment (can be hosted with minor changes)
* Uses JSON to store items and tax data
* No authentication system included

---

## 👨‍💻 Author

Nazrawi Elias
Portfolio: https://nazrawiportofolio.vercel.app/

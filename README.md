# Meditaj - Advanced Telemedicine Booking & Consultation System

Meditaj is a comprehensive, production-ready WordPress telemedicine plugin designed to handle end-to-end patient booking journeys, online/offline instant video calls, scheduled consultations, checkout gateways, and provider payouts. 

Powered by **Agora RTC Web SDK** (Version 007 AccessToken v2) and integrated with **SSLCommerz Payment Gateway**, it bridges patients, doctors, and platform administrators with robust, secure WebRTC technologies.

---

## 🚀 Key Features

* **Interactive Patient Booking Flow**: Seamless multi-step journey (Specialty Selection → Specialty Filter Results → Doctor Profile → Dynamic Slot Calendar Grid → Checkout & Confirmation Receipts).
* **Instant Video Consultations**: Real-time status toggles for doctors (Green indicator for Online/Call Now, Grey for Offline/Scheduled Only).
* **Secure WebRTC Calling**: Interactive local/remote video streams, mute controls, camera toggles, active calling timers, and patient reviews screen.
* **SSLCommerz Integration**: Real-time transaction validation, IPN callback handlers, and a local webhook auto-confirmation simulator for development.
* **Doctor & Patient Portals**: Frontend portals allowing doctors to manage schedules/profiles and patients to track consultation ledger, transactions, and historical reviews.
* **Back-Office Admin Center**: Consisting of metric cards (percentage growth indicators), dual-axis Chart.js trend lines, doughnut shares, list tables, and a patient registry.
* **Cron Reminders**: Automates email notifications to patients and doctors 30 minutes before any scheduled session.

---

## 🛠️ Shortcodes Reference

Simply paste these shortcodes into any WordPress page editor to render the corresponding portals.

| Shortcode | Description | Intended Users |
| :--- | :--- | :--- |
| `[meditaj_booking_flow]` | Renders the complete patient-facing consultation booking system. | Patients / Guests |
| `[meditaj_patient_dashboard]` | Renders patient consultation ledger, payments history, active call join buttons, and post-session rating forms. | Logged-in Patients |
| `[meditaj_doctor_dashboard]` | Renders doctor calendar, today's consultations, profile editor, active slots grid settings, and payouts display. | Logged-in Doctors |
| `[meditaj_doctor_registration]` | Renders frontend onboarding application form for new healthcare providers. | Doctors (Onboarding) |

---

## 📂 Database Architecture (Custom SQL Tables)

Meditaj operates 5 relational database tables, optimized for performant queries and indices:

1. **`wp_meditaj_doctors_meta`**: Stores provider profiles, BMDC licenses, experience, consultation pricing, average ratings, and payout details.
2. **`wp_meditaj_schedules`**: Stores doctors' base schedules, slot sizes, and break durations.
3. **`wp_meditaj_appointments`**: Main booking ledger containing patient details, dates, times, payment records, video room identifiers, and status enums.
4. **`wp_meditaj_reviews`**: Stores client ratings (1-5 stars) and comments left for completed consultation slots.
5. **`wp_meditaj_transactions`**: System payments ledger tracking merchant gateways, gateway transactions IDs, amounts, and payout releases.

---

## 📡 REST API Routes

All private REST routes require authenticated cookie session headers (`X-WP-Nonce`) for protection.

### Patient & Booking Endpoints
* `GET /wp-json/meditaj/v1/specialties` - Retrieve all specialties with custom icons.
* `GET /wp-json/meditaj/v1/doctors` - Query approved doctor directory.
* `GET /wp-json/meditaj/v1/doctors/{id}/slots?date=YYYY-MM-DD` - Retrieve availability slot array.
* `POST /wp-json/meditaj/v1/appointments` - Create a pending consultation booking.
* `POST /wp-json/meditaj/v1/video/token` - Generate short-lived secure Agora WebRTC RTC token.
* `POST /wp-json/meditaj/v1/appointments/{id}/complete` - Mark a consultation call as completed.
* `POST /wp-json/meditaj/v1/appointments/{id}/reviews` - Submit patient feedback rating (1-5) and comment.

### Doctor Dashboard Endpoints
* `GET /wp-json/meditaj/v1/doctor/me/appointments` - Query assigned consultations lists.
* `GET /wp-json/meditaj/v1/doctor/me/slots` - Query active slots layout.
* `POST /wp-json/meditaj/v1/doctor/me/slots` - Save multi-slot configurations.
* `POST /wp-json/meditaj/v1/doctor/me/profile` - Update profile text bio or photo uploads.
* `GET /wp-json/meditaj/v1/doctor/me/stats` - Retrieve personal analytics cards data.

---

## ⚙️ Administration Setup & Configurations

To activate all modules, navigate to **Meditaj → Settings** in the WordPress admin panel:

1. **SSLCommerz Payout Settings**:
   * Input your Store ID and Store Password.
   * Toggle the **Sandbox Mode** checkbox to switch between live checkouts and sandbox testing.
2. **Agora API Credentials**:
   * Paste your **Agora App ID** and **Agora App Certificate** copied from the Agora Console.
   * Enable "Secured Mode" in your Agora project configurations to allow short-lived Version 007 token authentication.
3. **Platform Commission Configuration**:
   * Set the platform service fee cut (e.g. `15` for 15% platform cut). Platform earnings on the back-office dashboard will recalculate dynamically according to this input.

---

## ⚠️ Important Live Server Checklist

Before deploying this system onto production servers, ensure you follow these precautions:
* **HTTPS Context**: Agora WebRTC APIs require a secure connection (`https://`). Cameras and microphones will fail to initialize on client browsers if served over `http://` (with exception of `localhost`).
* **WP-Cron Execution**: Setup a system-level cron job on your host control panel calling `wp-cron.php` every 10 minutes to guarantee timely dispatch of the 30-minute reminder emails.
* **SMTP Configured**: Install a reliable SMTP plugin (like WP Mail SMTP) to verify that confirmation and reminder emails avoid spam filters.

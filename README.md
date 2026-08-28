# EG Care - Advanced Telemedicine Booking & Consultation System

EG Care is a comprehensive, production-ready WordPress telemedicine plugin designed to handle end-to-end patient booking journeys, online/offline instant video calls, scheduled consultations, checkout gateways, and provider payouts. 

Powered by **Agora RTC Web SDK** (Version 007 AccessToken v2) and integrated with **SSLCommerz Payment Gateway**, it bridges patients, doctors, and platform administrators with robust, secure WebRTC technologies.

---

## 🚀 Key Features

* **Interactive Patient Booking Flow**: The doctor list first, filtered by specialty, availability, fee and keyword; then the doctor's own page with a date strip and their sitting hours; then checkout and the confirmation receipt. Choosing a specialty is a filter, not a step you have to pass through.
* **Instant Video Consultations**: Real-time status toggles for doctors (Green indicator for Online/Call Now, Grey for Offline/Scheduled Only).
* **Instant Doctor Requests**: A patient who does not want to choose a doctor describes the problem and waits; the request goes into an open queue, every doctor on instant duty sees it, and the first to accept gets it. Accepting creates the appointment, so payment and the video call run through the same machinery as any other booking.
* **Saved Family Members**: Patients keep a list of the people they book for - parent, child, spouse - and every appointment snapshots who was seen and how old they were that day.
* **Doctor Profile Pages**: Every approved doctor has a page of their own at `/doctors/{slug}/`, showing the profile and the booking panel together.
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
| `[eg_care_booking_flow]` | Renders the complete patient-facing consultation booking system. | Patients / Guests |
| `[eg_care_patient_dashboard]` | Renders patient consultation ledger, payments history, active call join buttons, and post-session rating forms. | Logged-in Patients |
| `[eg_care_doctor_dashboard]` | Renders doctor calendar, today's consultations, profile editor, active slots grid settings, and payouts display. | Logged-in Doctors |
| `[eg_care_doctor_registration]` | Renders frontend onboarding application form for new healthcare providers. | Doctors (Onboarding) |
| `[eg_care_instant_doctor]` | Renders the instant consultation request screen: the form, the waiting screen, and the handover to checkout once a doctor accepts. | Logged-in Patients |

`[eg_care_booking_flow]` also takes an optional `doctor` attribute - `[eg_care_booking_flow doctor="41"]` opens the flow on that one doctor. A doctor's own page uses it that way; you rarely need to write it by hand.

---

## 📂 Database Architecture (Custom SQL Tables)

EG Care operates 7 relational database tables, optimized for performant queries and indices:

1. **`wp_eg_care_doctors_meta`**: Stores provider profiles, BMDC licenses, experience, consultation pricing, average ratings, and payout details.
2. **`wp_eg_care_schedules`**: Stores doctors' base schedules, slot sizes, and break durations.
3. **`wp_eg_care_appointments`**: Main booking ledger containing patient details, dates, times, payment records, video room identifiers, and status enums.
4. **`wp_eg_care_reviews`**: Stores client ratings (1-5 stars) and comments left for completed consultation slots.
5. **`wp_eg_care_transactions`**: System payments ledger tracking merchant gateways, gateway transactions IDs, amounts, and payout releases.
6. **`wp_eg_care_family_members`**: The people a patient books for. Stores a date of birth rather than an age, so the row cannot go stale; the age at the time of a booking is copied onto the appointment.
7. **`wp_eg_care_instant_requests`**: The open queue of patients asking for whoever is free. A request lives here until a doctor claims it or it times out - only a claim creates an appointment, so a request nobody answered never clutters the booking ledger.

---

## 📡 REST API Routes

All private REST routes require authenticated cookie session headers (`X-WP-Nonce`) for protection.

### Patient & Booking Endpoints
* `GET /wp-json/eg-care/v1/specialties` - Retrieve all specialties with custom icons.
* `GET /wp-json/eg-care/v1/doctors` - Query the approved doctor directory. Takes `specialty` (comma separated slugs), `availability` (`online`, `scheduled`, or both), `search`, `page` and `per_page`; the size of the whole result set comes back in the `X-WP-Total` header.
* `GET /wp-json/eg-care/v1/doctors/{id}/slots?date=YYYY-MM-DD` - Retrieve the day's slots plus the doctor's sitting hours.
* `GET /wp-json/eg-care/v1/doctors/{id}/schedule-days` - Which weekdays the doctor sits on, so the date strip can grey out the rest.
* `GET /wp-json/eg-care/v1/patient/me/family` - List the people this patient books for.
* `POST /wp-json/eg-care/v1/patient/me/family` - Add one.
* `PUT|DELETE /wp-json/eg-care/v1/patient/me/family/{id}` - Edit or remove one.
* `POST /wp-json/eg-care/v1/appointments/{id}/reports` - Attach a medical report (one file per request, up to the configured limit).
* `POST /wp-json/eg-care/v1/appointments` - Create a pending consultation booking.
* `POST /wp-json/eg-care/v1/video/token` - Generate short-lived secure Agora WebRTC RTC token.
* `POST /wp-json/eg-care/v1/appointments/{id}/complete` - Mark a consultation call as completed.
* `POST /wp-json/eg-care/v1/appointments/{id}/reviews` - Submit patient feedback rating (1-5) and comment.

### Doctor Dashboard Endpoints
* `GET /wp-json/eg-care/v1/doctor/me/appointments` - Query assigned consultations lists.
* `GET /wp-json/eg-care/v1/doctor/me/slots` - Query active slots layout.
* `POST /wp-json/eg-care/v1/doctor/me/slots` - Save multi-slot configurations.
* `POST /wp-json/eg-care/v1/doctor/me/profile` - Update profile text bio or photo uploads.
* `GET /wp-json/eg-care/v1/doctor/me/stats` - Retrieve personal analytics cards data.

### Instant Request Endpoints
* `POST /wp-json/eg-care/v1/instant-requests` - Ask for whoever is free. Returns the request already waiting if the patient has one, so a double click cannot queue two.
* `GET /wp-json/eg-care/v1/instant-requests/{id}` - Where the request has got to. Readable only by the patient who made it.
* `DELETE /wp-json/eg-care/v1/instant-requests/{id}` - Withdraw a request nobody has accepted yet.
* `GET /wp-json/eg-care/v1/doctor/me/instant-requests` - The open queue. Patient phone numbers are withheld until a request is yours.
* `POST /wp-json/eg-care/v1/instant-requests/{id}/claim` - Accept one. A single conditional UPDATE decides it, so two doctors pressing Accept together cannot both win.
* `GET|POST /wp-json/eg-care/v1/doctor/me/instant-duty` - Read or set whether you are taking instant requests.

---

## ⚙️ Administration Setup & Configurations

To activate all modules, navigate to **EG Care → Settings** in the WordPress admin panel:

1. **SSLCommerz Payout Settings**:
   * Input your Store ID and Store Password.
   * Toggle the **Sandbox Mode** checkbox to switch between live checkouts and sandbox testing.
2. **Agora API Credentials**:
   * Paste your **Agora App ID** and **Agora App Certificate** copied from the Agora Console.
   * Enable "Secured Mode" in your Agora project configurations to allow short-lived Version 007 token authentication.
3. **Platform Calculations**:
   * **Patient Service Charge (%)** - added on top of the consultation fee at checkout. The patient pays fee + this percentage.
   * **Instant Request Fee (BDT)** - what an instant request costs. One price for everybody, because the patient has to be told what it costs before any doctor has answered. A doctor's own instant call fee still applies when a patient picks that doctor by name.
   * **Platform Commission (%)** - withheld from the doctor at payout. A different number from the service charge: one is charged to the patient, the other is deducted from the doctor.

---

### Pages To Create

Each shortcode needs a page of its own. A typical site has four:

| Page | Shortcode | Notes |
| :--- | :--- | :--- |
| Find a Doctor | `[eg_care_booking_flow]` | The doctor list. Filters appear in the URL as `eg_specialty`, `eg_availability` and `eg_search`, so a filtered list can be shared. |
| Instant Doctor | `[eg_care_instant_doctor]` | Ask for whoever is free. Link it from the main menu next to Find a Doctor. |
| My Consultations | `[eg_care_patient_dashboard]` | |
| Doctor Dashboard | `[eg_care_doctor_dashboard]` | |

Doctor profile pages need no page of their own: the post type is public, and the plugin serves `/doctors/{slug}/` itself unless your theme ships a `single-doctors.php`. If those URLs 404 after activating, visit **Settings → Permalinks** once to flush the rewrite rules.

### Turning Instant Requests On

1. Set the **Instant Request Fee** in EG Care → Settings.
2. Publish a page carrying `[eg_care_instant_doctor]`.
3. Each doctor who wants these patients opens their dashboard and turns on **Take Instant Requests**, next to **Go Online**. Both switches have to be on: a doctor who left the duty switch on but went offline is not there to answer.

A request waits 15 minutes. If nobody accepts it in that time it closes itself, the patient is told plainly, and nothing is charged - the patient only pays once a doctor has accepted.

## ⚠️ Important Live Server Checklist

Before deploying this system onto production servers, ensure you follow these precautions:
* **HTTPS Context**: Agora WebRTC APIs require a secure connection (`https://`). Cameras and microphones will fail to initialize on client browsers if served over `http://` (with exception of `localhost`).
* **WP-Cron Execution**: Setup a system-level cron job on your host control panel calling `wp-cron.php` every 10 minutes to guarantee timely dispatch of the 30-minute reminder emails.
* **SMTP Configured**: Install a reliable SMTP plugin (like WP Mail SMTP) to verify that confirmation and reminder emails avoid spam filters.

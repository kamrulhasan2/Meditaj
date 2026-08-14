# Meditaj UI Flow & Architecture Guidelines

Always keep these primary UI flows in mind for the Meditaj system:

---

## 1. UI Flow - 1 (Doctor Booking Flow)
1. **Specialty List Screen**: Users land on a screen listing all specialties as interactive cards (e.g. Cardiology, Neurology, Pediatrics).
2. **Specialty Search Results**: Clicking a specialty term displays a grid of approved doctors under that specialty.
3. **Doctor Profile & Slots Calendar**: Clicking a doctor opens their full profile, which includes:
   - Bio, degrees, experience, and consultation fees.
   - Average rating and total reviews.
   - A date picker that queries and displays dynamic hourly slots (e.g. 09:00, 09:30). Users select a slot to book.

---

## 2. UI Flow - 2 (Instant Call Flow)
1. **Directory Page Grid**: Displays doctors divided into:
   - **Doctors Online (Instant Call)**: Shown with green indicator. Users can call instantly.
   - **Doctors Offline (Scheduled Only)**: Shown with standard statuses.
2. **Instant Call Action**: Clicking an online doctor triggers a quick setup call interface.

---

## 3. Back-Office Admin Dashboard
- A consolidated overview panel in `wp-admin` under the **Meditaj** menu, showing:
  - Analytics counters (Total Doctors, Total Patients, Total Appointments, Total Earnings).
  - Recent Registrations (pending approvals review).
  - Latest Appointments ledger.

---

## 4. Onboarding & Member Portals
- **Doctor Register**: Simplified Doctor-only frontend onboarding form (captured details like NID, Mobile, BMDC expiration, Degrees, and Bank/Mobile payout details).
- **Doctor's Dashboard**: A dedicated frontend login portal for doctors to view appointments, manage schedules, and initiate video calls.

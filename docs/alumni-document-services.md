# Alumni document services

## Workflow

1. Admin opens **Alumni Hub → Document services** (`alumni-document-admin.php`).
2. Set each school-approved service fee, any fixed physical delivery fee, permitted delivery methods, requirements and supported delivery locations. Enable the service. Seeded services start disabled with no fee assumed.
3. An approved alumnus signs in and chooses **Request documents** (`alumni-document-requests.php`). They choose the service and destination, supply recipient/contact and receipt email, and submit for review.
4. Admin opens the numbered request, confirms identity/records, document availability and suitability of the delivery quote, then chooses **Approve — allow payment**. Unavailable requests can be rejected with a reason before payment.
5. The alumnus opens the numbered request and pays using the existing Paystack checkout. For free services, approval goes directly to processing.
6. Verified payment changes the request to Processing. Staff prepare the document and update Ready, Dispatched (email/postal), or Completed (including school collection). Notes and tracking references are visible to the alumnus.

### In-app notifications

- New submissions create an unread `ALUMNI_DOCUMENT_REQUEST_SUBMITTED` entry in the admin dashboard's existing Notifications feed, with a **Review Document Request** link. The entry is committed with the request and is not duplicated by resubmission.
- Authorised administrators also see a **Document requests (N)** menu reminder for requests still awaiting review. This includes requests submitted before this notification update and clears as they are reviewed, regardless of whether audit entries are marked read.
- Once a paid service is approved, the alumnus sees **Approved — you can now pay** on the alumni community and document-request pages, with the total and a **Review & pay** link. It is private to that active account and clears once payment is recorded. Free services never generate a payment prompt.
- Alerts update on page load/refresh. These are in-app notifications, not SMS, email or browser push messages; they do not incur messaging charges.
- When deploying this update, include `alumni-document-notifications.php` and the updated `admin.php`, `menu.php`, `alumni-community.php`, `alumni-document-utils.php` and `alumni-document-requests.php` together.

WAEC certificate requests refer to school-held originals, not newly generated or replacement certificates. The school must verify identity and applicable release requirements. WAEC's guidance: <https://waecgh.org/home/certificates-overview/>.

## Deployment and activation

- Back up the site and database through your usual process before deploying.
- Deploy `alumni-document-utils.php`, `alumni-document-view.php`, `alumni-document-requests.php`, `alumni-document-admin.php`, `alumni-document-paystack-callback.php`, `css/alumni-documents.css`, `js/alumni-documents.js` and the updated `alumni-community.php`, `alumni-hub.php`, `user-management-utils.php`, `online-admission-paystack-webhook.php`. Preserve server-local payment credentials; do not replace them with test credentials.
- New InnoDB tables `tblalumnidocservice`, `tblalumnidocrequest` and `tblalumnidocevent` are created on the first authorised page visit. The application DB account needs CREATE permission for this setup. Existing alumni and payment records are not migrated or rewritten.
- The module uses `online_admission_paystack_config()` and the existing payment gateway setup. It does not add another payment provider or a new secret-key store.
- Confirm in Paystack that the existing webhook points to the deployed HTTPS `online-admission-paystack-webhook.php` on your site (for this domain: `https://ayirebishs.com/online-admission-paystack-webhook.php`). This handler keeps the existing routes and additionally handles `ALMDOC_` references. Do not replace an existing webhook with a different endpoint without checking its other uses.
- The callback URL is sent with each transaction: `alumni-document-paystack-callback.php`. Keep it publicly reachable via HTTPS; it verifies ownership after sign-in. If the session expires, sign back in and use **Check payment** on the request. The signed webhook can still confirm payment without a browser session.
- Test an end-to-end request on staging using Paystack test mode. Test-paid requests are labelled and blocked from fulfilment. Then use the existing production live credentials before accepting actual requests. Automated tests do not contact Paystack, make charges, or establish that production credentials/webhooks work.
- Admin access follows the existing Alumni Management module permission and `um_is_admin_manager()` restriction. Alumni must have an active approved profile; they can only access their own requests.

## Payment and operational safeguards

- Prices are stored as integer pesewas and snapshotted at submission. Service edits only affect new requests. No browser-supplied amount is trusted.
- Each request keeps a single random gateway reference. Duplicate form submissions reuse the request token; repeat checkout uses the same reference/URL. An initialization timeout does not generate a new payment reference.
- Payments are accepted only from server-side verification or the signature-validated webhook, matching success status, reference, amount, GHS currency, gateway mode and transaction ID. Row locks, a unique gateway ID and event history make replay idempotent. Paid/completed state cannot regress from delayed failure responses.
- If initialization succeeds remotely but the checkout URL cannot be saved, the same-reference retry may be refused by Paystack. Contact the school to reconcile that reference in the gateway before creating another request; do not blindly start a replacement payment.
- No manual "mark paid" control is provided. Use **Check payment** to reconcile gateway status. Pending/integrity-failed payments must be investigated before asking the alumnus to pay again.
- Personal destination details are shown only within the authenticated member/admin workflow, not in the public directory or gateway metadata. Requests use prepared SQL, CSRF checks and escaped output. No card details are stored.
- Staff send emails and physical documents outside this module, then record dispatch/completion. No automatic email attachments, SMS notifications, courier booking, refunds or official certificate generation are included. Alumni must check their account for updates.
- For paid requests that cannot be fulfilled, use Needs Attention with a clear update and handle any refund through the school's existing gateway/finance process. Reversed/refund events are not automatically reconciled by this module; staff must check the gateway before release and follow their reconciliation process.
- Limit access to authorised staff and establish the school's own retention/archiving policy for address/contact data. Do not use real personal details in test orders.

## Local checks

`C:\xampp\php\php.exe tests\alumni-documents-regression.php --preview`

This CLI-only suite uses connection-local temporary tables and mocked gateway calls. Preview HTML files contain synthetic data and can be passed to `tests/waec-comparison-browser.ps1` for desktop/mobile layout and delivery-field checks.

Payment guidance consulted: <https://paystack.com/docs/payments/verify-payments/> and <https://paystack.com/docs/payments/webhooks/>.

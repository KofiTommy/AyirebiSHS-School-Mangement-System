# Paystack live launch

The online-admission portal already creates a Paystack transaction, verifies the transaction server-side, validates its reference/amount/currency, and accepts signed `charge.success` webhooks.

## 1. Configure the live keys

In the Paystack Dashboard, complete business verification and switch to **Live** mode. Copy the matching `pk_live_...` and `sk_live_...` keys.

On the server, preferably set `PAYSTACK_PUBLIC_KEY` and `PAYSTACK_SECRET_KEY` as environment variables. For XAMPP/shared hosting, copy `online-admission-paystack-config.example.php` to `online-admission-paystack-config.local.php` and replace both values with the live keys. The local file is ignored by Git.

Never share or commit the `sk_live_...` secret key. If a key was ever exposed, rotate it in Paystack before launch.

## 2. Set the webhook

In Paystack Dashboard → Settings → API Keys & Webhooks, set the webhook URL to:

`https://YOUR-DOMAIN/online-admission-paystack-webhook.php`

It must be a publicly reachable HTTPS URL. Paystack signs each event; this application rejects unsigned or invalid signatures.

## 3. Check the public URLs

Confirm all three URLs use your real HTTPS domain and not `localhost`:

- `https://YOUR-DOMAIN/online-admission.php`
- `https://YOUR-DOMAIN/online-admission-paystack-callback.php`
- `https://YOUR-DOMAIN/online-admission-paystack-webhook.php`

The callback URL is generated automatically from the address the applicant uses. Configure your web server to redirect HTTP to HTTPS so the generated address is always secure.

## 4. Enable payment only when ready

Sign in to `online-admission-admin.php`, open **Admission Settings → Paystack Settings**, then:

1. Confirm it says `Paystack Ready` and `LIVE MODE`.
2. Set the admission fee and currency (`GHS`).
3. Enable **Online admission payment** and save.
4. Ensure the public portal is enabled, and that your posted-student list is correct.

## 5. First real-payment check

Use one internal test applicant and make a small genuine payment. Confirm it appears as **success** in the payment list, the candidate receives/uses the verification token, and the webhook response is recorded by Paystack. Refund the test charge from Paystack if appropriate.

Do not use the in-app **Sandbox Tester** with live keys: it is deliberately blocked in live mode.

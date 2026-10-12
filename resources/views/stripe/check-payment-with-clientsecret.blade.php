<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Yourlist — Stripe Payment Test</title>
  <script src="https://js.stripe.com/v3/"></script>
  <style>
    body { font-family: system-ui, -apple-system, Segoe UI, sans-serif; max-width: 560px; margin: 40px auto; padding: 0 18px; color: #172033; }
    h1 { font-size: 1.5rem; }
    p { line-height: 1.5; color: #4b5563; }
    label { display: block; font-weight: 600; margin: 18px 0 6px; }
    input { width: 100%; box-sizing: border-box; padding: 11px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; }
    button { width: 100%; padding: 12px; margin-top: 18px; border: 0; border-radius: 8px; background: #635bff; color: white; font: inherit; font-weight: 650; cursor: pointer; }
    button:disabled { opacity: .55; cursor: wait; }
    #payment-element { margin-top: 18px; }
    #message { margin-top: 16px; padding: 12px; border-radius: 8px; white-space: pre-wrap; }
    .note { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; font-size: .92rem; }
    .error { color: #991b1b; background: #fef2f2; }
    .success { color: #166534; background: #f0fdf4; }
  </style>
</head>
<body>
  <h1>Yourlist — Stripe Payment Test</h1>
  <p>This temporary page confirms an existing PaymentIntent using Stripe.js. Use only with Stripe test-mode credentials and a test-mode PaymentIntent.</p>
  <div class="note">Never enter your Stripe secret key (sk_...). This page needs only your publishable key (pk_test_...) and the PaymentIntent client secret.</div>

  <form id="setup-form">
    <label for="publishable-key">Stripe publishable test key</label>
    <input
      id="publishable-key"
      autocomplete="off"
      placeholder="pk_test_..."
      value="{{ $publishableKey }}"
      required
    >
    <label for="client-secret">PaymentIntent client secret</label>
    <input id="client-secret" autocomplete="off" placeholder="pi_..._secret_..." required>
    <button type="submit" id="load-button">Load payment form</button>
  </form>

  <form id="payment-form" hidden>
    <div id="payment-element"></div>
    <button type="submit" id="pay-button">Pay with test card</button>
  </form>
  <div id="message" role="status" aria-live="polite"></div>

  <script>
    let stripe;
    let elements;

    const setupForm = document.getElementById('setup-form');
    const paymentForm = document.getElementById('payment-form');
    const loadButton = document.getElementById('load-button');
    const payButton = document.getElementById('pay-button');
    const message = document.getElementById('message');

    function showMessage(text, type = '') {
      message.className = type;
      message.textContent = text;
    }

    setupForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      loadButton.disabled = true;
      showMessage('Loading secure payment form…');

      try {
        const publishableKey = document.getElementById('publishable-key').value.trim();
        const clientSecret = document.getElementById('client-secret').value.trim();

        if (!publishableKey.startsWith('pk_test_')) {
          throw new Error('For this test page, enter a Stripe test publishable key beginning with pk_test_.');
        }
        if (!clientSecret.startsWith('pi_') || !clientSecret.includes('_secret_')) {
          throw new Error('Enter the client_secret for the existing PaymentIntent, not just its pi_ ID.');
        }

        stripe = Stripe(publishableKey);
        elements = stripe.elements({ clientSecret });
        const paymentElement = elements.create('payment');
        paymentElement.mount('#payment-element');

        setupForm.hidden = true;
        paymentForm.hidden = false;
        showMessage('Payment form loaded. Use Stripe test card 4242 4242 4242 4242, any future expiry date, and any 3-digit CVC.');
      } catch (error) {
        showMessage(error.message || 'Could not load the payment form.', 'error');
        loadButton.disabled = false;
      }
    });

    paymentForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      payButton.disabled = true;
      showMessage('Confirming payment…');

      try {
        const result = await stripe.confirmPayment({
          elements,
          confirmParams: {
            return_url: window.location.href
          },
          redirect: 'if_required'
        });

        if (result.error) {
          showMessage(result.error.message || 'Payment could not be completed.', 'error');
          payButton.disabled = false;
          return;
        }

        showMessage(
          'Stripe has accepted the confirmation request. Check your Stripe Dashboard and Laravel webhook/database to verify the final status. A browser result alone is not proof that your order is paid.',
          'success'
        );
      } catch (error) {
        showMessage(error.message || 'An unexpected error occurred.', 'error');
        payButton.disabled = false;
      }
    });
  </script>
</body>
</html>

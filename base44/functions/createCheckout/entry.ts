import { createClientFromRequest } from 'npm:@base44/sdk@0.8.6';
import Stripe from 'npm:stripe@17.5.0';

const stripe = new Stripe(Deno.env.get('STRIPE_SECRET_KEY'));

Deno.serve(async (req) => {
  try {
    const base44 = createClientFromRequest(req);
    const user = await base44.auth.me();

    if (!user) {
      return Response.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const { package_tier, amount, user_id, user_email } = await req.json();

    // Determine price ID based on package
    const priceId = package_tier === 'complete' 
      ? 'price_1SwDLsLqEloUpaGVGLspCJo8'  // Complete $87
      : 'price_1SwDLsLqEloUpaGVuPzXXMgC'; // Basic $47

    const session = await stripe.checkout.sessions.create({
      payment_method_types: ['card'],
      line_items: [{
        price: priceId,
        quantity: 1,
      }],
      mode: 'payment',
      success_url: `${req.headers.get('origin')}/payment-success?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${req.headers.get('origin')}/order-bump`,
      customer_email: user_email,
      client_reference_id: user_id,
      metadata: {
        base44_app_id: Deno.env.get("BASE44_APP_ID"),
        user_id: user_id,
        user_email: user_email,
        package_tier: package_tier,
        credits_to_add: '1',
        amount: (amount / 100).toString()
      }
    });

    return Response.json({ url: session.url });
  } catch (error) {
    console.error('Checkout creation error:', error);
    return Response.json({ error: error.message }, { status: 500 });
  }
});
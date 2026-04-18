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

    const { generation_id } = await req.json();

    // Verify this generation belongs to the user and is Basic
    const generation = await base44.entities.Generation.get(generation_id);
    
    if (!generation || generation.user_id !== user.id) {
      return Response.json({ error: 'Unauthorized' }, { status: 403 });
    }

    if (generation.package_tier !== 'basic') {
      return Response.json({ error: 'This generation is already Complete package' }, { status: 400 });
    }

    const session = await stripe.checkout.sessions.create({
      payment_method_types: ['card'],
      line_items: [{
        price: 'price_1SwDLsLqEloUpaGVDzSNycij', // Upgrade $50
        quantity: 1,
      }],
      mode: 'payment',
      success_url: `${req.headers.get('origin')}/upgrade-success?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${req.headers.get('origin')}/dashboard`,
      customer_email: user.email,
      client_reference_id: user.id,
      metadata: {
        base44_app_id: Deno.env.get("BASE44_APP_ID"),
        user_id: user.id,
        generation_id: generation_id,
        transaction_type: 'upgrade',
        original_tier: 'basic',
        new_tier: 'complete',
        amount: '50'
      }
    });

    return Response.json({ url: session.url });
  } catch (error) {
    console.error('Upgrade checkout error:', error);
    return Response.json({ error: error.message }, { status: 500 });
  }
});
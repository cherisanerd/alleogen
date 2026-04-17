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

    const { session_id } = await req.json();

    // Retrieve session from Stripe
    const session = await stripe.checkout.sessions.retrieve(session_id);

    if (session.payment_status !== 'paid') {
      return Response.json({ error: 'Upgrade payment was not completed.' }, { status: 400 });
    }

    const generationId = session.metadata.generation_id;
    const userId = session.metadata.user_id;

    // Get the generation
    const generation = await base44.asServiceRole.entities.Generation.get(generationId);

    if (!generation || generation.user_id !== userId) {
      return Response.json({ error: 'Invalid generation' }, { status: 403 });
    }

    // Calculate new expiration (90 days from now)
    const newExpiration = new Date();
    newExpiration.setDate(newExpiration.getDate() + 90);

    // Update generation to Complete
    await base44.asServiceRole.entities.Generation.update(generationId, {
      package_tier: 'complete',
      upgraded_at: new Date().toISOString(),
      expires_at: newExpiration.toISOString(),
      regeneration_needed: true
    });

    // Log upgrade transaction
    await base44.asServiceRole.entities.CreditsTransaction.create({
      user_id: userId,
      transaction_type: 'upgrade',
      credits_change: 0,
      generation_id: generationId,
      payment_id: session.payment_intent,
      amount_paid: 50.00,
      stripe_session_id: session.id,
      package_tier: 'complete',
      notes: 'Upgraded from Basic to Complete'
    });

    return Response.json({
      success: true,
      website_url: generation.website_url,
      expires_at: newExpiration.toISOString()
    });
  } catch (error) {
    console.error('Upgrade processing error:', error);
    return Response.json({ error: error.message }, { status: 500 });
  }
});
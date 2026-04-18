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
      return Response.json({ error: 'Payment was not completed. Please try again.' }, { status: 400 });
    }

    // Check if already processed
    const existing = await base44.asServiceRole.entities.ProcessedPayment.filter({
      stripe_session_id: session.id
    });

    if (existing.length > 0) {
      // Already processed, return success
      return Response.json({
        success: true,
        package_tier: session.metadata.package_tier,
        amount: session.metadata.amount,
        email: session.customer_email
      });
    }

    const userId = session.metadata.user_id;
    const packageTier = session.metadata.package_tier;
    const amountPaid = parseFloat(session.metadata.amount);

    // Update user credits and plan type
    const currentUser = await base44.asServiceRole.entities.User.get(userId);
    await base44.asServiceRole.entities.User.update(userId, {
      credits_remaining: (currentUser.credits_remaining || 0) + 1,
      plan_type: packageTier
    });

    // Log transaction
    await base44.asServiceRole.entities.CreditsTransaction.create({
      user_id: userId,
      transaction_type: 'purchase',
      credits_change: 1,
      payment_id: session.payment_intent,
      amount_paid: amountPaid,
      stripe_session_id: session.id,
      package_tier: packageTier,
      notes: `Purchased ${packageTier} package`
    });

    // Mark as processed
    await base44.asServiceRole.entities.ProcessedPayment.create({
      stripe_session_id: session.id,
      user_id: userId,
      amount: amountPaid,
      package_tier: packageTier
    });

    return Response.json({
      success: true,
      package_tier: packageTier,
      amount: amountPaid,
      email: session.customer_email
    });
  } catch (error) {
    console.error('Payment processing error:', error);
    return Response.json({ error: error.message }, { status: 500 });
  }
});
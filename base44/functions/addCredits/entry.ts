import { createClientFromRequest } from 'npm:@base44/sdk@0.8.6';

Deno.serve(async (req) => {
  try {
    const base44 = createClientFromRequest(req);
    const user = await base44.auth.me();

    if (!user || user.role !== 'admin') {
      return Response.json({ error: 'Forbidden: Admin access required' }, { status: 403 });
    }

    const { user_id, credits } = await req.json();

    if (!user_id || !credits || credits < 1) {
      return Response.json({ error: 'Invalid parameters' }, { status: 400 });
    }

    // Get target user
    const targetUser = await base44.asServiceRole.entities.User.get(user_id);

    // Add credits
    await base44.asServiceRole.entities.User.update(user_id, {
      credits_remaining: (targetUser.credits_remaining || 0) + credits
    });

    // Log transaction
    await base44.asServiceRole.entities.CreditsTransaction.create({
      user_id: user_id,
      transaction_type: 'purchase',
      credits_change: credits,
      amount_paid: 0,
      notes: `Admin added ${credits} credits`
    });

    return Response.json({
      success: true,
      new_balance: (targetUser.credits_remaining || 0) + credits
    });
  } catch (error) {
    console.error('Add credits error:', error);
    return Response.json({ error: error.message }, { status: 500 });
  }
});
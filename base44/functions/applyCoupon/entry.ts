import { createClientFromRequest } from 'npm:@base44/sdk@0.8.6';

Deno.serve(async (req) => {
  try {
    const base44 = createClientFromRequest(req);
    const user = await base44.auth.me();

    if (!user) {
      return Response.json({ error: 'Unauthorized' }, { status: 401 });
    }

    const { coupon_code } = await req.json();

    // Check if user already used a coupon
    if (user.coupon_used) {
      return Response.json({ 
        error: 'You have already used a coupon code' 
      }, { status: 400 });
    }

    // Find coupon in database
    const coupons = await base44.asServiceRole.entities.Coupon.filter({
      code: coupon_code?.toUpperCase(),
      is_active: true
    });

    if (!coupons || coupons.length === 0) {
      return Response.json({ 
        error: 'Invalid or inactive coupon code' 
      }, { status: 400 });
    }

    const coupon = coupons[0];

    // Add credits to user
    const newCredits = (user.credits_remaining || 0) + coupon.credits;
    await base44.auth.updateMe({
      credits_remaining: newCredits,
      coupon_used: true
    });

    // Update coupon usage count
    await base44.asServiceRole.entities.Coupon.update(coupon.id, {
      uses_count: (coupon.uses_count || 0) + 1
    });

    // Log transaction
    await base44.asServiceRole.entities.CreditsTransaction.create({
      user_id: user.id,
      transaction_type: 'purchase',
      credits_change: coupon.credits,
      amount_paid: 0,
      notes: `Coupon code: ${coupon_code.toUpperCase()}`
    });

    return Response.json({ 
      success: true,
      credits_added: coupon.credits,
      new_total: newCredits
    });
  } catch (error) {
    console.error('Apply coupon error:', error);
    return Response.json({ 
      error: error.message || 'Failed to apply coupon' 
    }, { status: 500 });
  }
});
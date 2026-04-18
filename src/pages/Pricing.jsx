import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Check, Sparkles, Zap, CreditCard, TrendingUp, ArrowRight, Tag, Loader2 } from 'lucide-react';
import { motion } from 'framer-motion';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';

export default function Pricing() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [processingBasic, setProcessingBasic] = useState(false);
  const [processingComplete, setProcessingComplete] = useState(false);
  const [couponCode, setCouponCode] = useState('');
  const [applyingCoupon, setApplyingCoupon] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    loadUser();
  }, []);

  const loadUser = async () => {
    try {
      const currentUser = await base44.auth.me();
      setUser(currentUser);
    } catch (error) {
      base44.auth.redirectToLogin();
    } finally {
      setLoading(false);
    }
  };

  const handleApplyCoupon = async () => {
    if (!couponCode.trim()) {
      toast.error('Please enter a coupon code');
      return;
    }

    setApplyingCoupon(true);
    try {
      const response = await base44.functions.invoke('applyCoupon', {
        coupon_code: couponCode
      });

      if (response.data.success) {
        toast.success(`${response.data.credits_added} free credits added!`);
        await loadUser();
        setCouponCode('');
      }
    } catch (error) {
      const errorMsg = error.response?.data?.error || 'Failed to apply coupon';
      toast.error(errorMsg);
    } finally {
      setApplyingCoupon(false);
    }
  };

  const handlePurchase = async (tier) => {
    if (tier === 'basic') {
      setProcessingBasic(true);
    } else {
      setProcessingComplete(true);
    }

    try {
      const { data } = await base44.functions.invoke('createCheckout', {
        package_tier: tier
      });

      if (data.error) {
        toast.error(data.error);
        setProcessingBasic(false);
        setProcessingComplete(false);
        return;
      }

      if (data.url) {
        window.location.href = data.url;
      }
    } catch (error) {
      console.error('Checkout error:', error);
      toast.error('Failed to create checkout session. Please try again.');
      setProcessingBasic(false);
      setProcessingComplete(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#7700CC]"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4 sm:px-6 lg:px-8">
      <div className="max-w-6xl mx-auto">
        {/* Header */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="text-center mb-16"
        >
          <div className="inline-flex items-center gap-2 px-4 py-2 bg-[#7700CC] text-white rounded-full text-sm subheading mb-6">
            <Sparkles className="w-4 h-4" />
            Simple, Transparent Pricing
          </div>
          <h1 className="heading text-4xl md:text-5xl font-bold text-[#180029] mb-4">
            Choose Your AEO Package
          </h1>
          <p className="text-lg text-[#180029]/70 max-w-2xl mx-auto">
            One-time payment. No subscriptions. Get everything you need to optimize your website for AI discovery.
          </p>
        </motion.div>

        {/* Current Credits Display */}
        {user && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
            className="flex justify-center mb-12"
          >
            <div className="flex items-center gap-2 bg-white px-6 py-3 rounded-xl shadow-md">
              <CreditCard className="w-5 h-5 text-[#7700CC]" />
              <span className="text-sm text-[#180029]/70">Current Credits:</span>
              <span className="subheading text-xl text-[#180029]">
                {user.is_super_admin ? '∞' : (user.credits_remaining || 0)}
              </span>
            </div>
          </motion.div>
        )}

        {/* Pricing Cards */}
        <div className="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
          {/* Basic Package */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
          >
            <Card className="border-2 border-gray-200 shadow-xl hover:shadow-2xl transition-all h-full">
              <CardHeader className="bg-gradient-to-br from-[#F4743B]/10 to-[#F4743B]/5 border-b">
                <div className="flex items-center justify-between mb-2">
                  <CardTitle className="heading text-2xl text-[#180029]">
                    Basic Package
                  </CardTitle>
                  <Zap className="w-8 h-8 text-[#F4743B]" />
                </div>
                <div className="mt-4">
                  <span className="heading text-5xl font-bold text-[#180029]">$47</span>
                  <span className="text-[#180029]/70 ml-2">one-time</span>
                </div>
                <p className="text-sm text-[#180029]/70 mt-2">
                  Perfect for getting started with AEO
                </p>
              </CardHeader>
              <CardContent className="p-8">
                <div className="space-y-4 mb-8">
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">7 essential AEO files</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">AI-optimized llm.txt</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">Custom robots.txt for AI crawlers</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">AI-specific sitemap</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">Schema.org structured data</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">Implementation guide</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">90-day download access</span>
                  </div>
                </div>

                <Button
                  onClick={() => handlePurchase('basic')}
                  disabled={processingBasic}
                  className="w-full h-14 bg-[#F4743B] hover:bg-[#F4743B]/90 text-white text-lg subheading"
                >
                  {processingBasic ? (
                    <>
                      <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-3"></div>
                      Processing...
                    </>
                  ) : (
                    'Get Basic Package'
                  )}
                </Button>
              </CardContent>
            </Card>
          </motion.div>

          {/* Complete Package */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
          >
            <Card className="border-2 border-[#7700CC] shadow-xl hover:shadow-2xl transition-all h-full relative">
              <div className="absolute -top-4 left-1/2 transform -translate-x-1/2">
                <div className="bg-[#7700CC] text-white px-4 py-1 rounded-full text-sm subheading">
                  Most Popular
                </div>
              </div>
              <CardHeader className="bg-gradient-to-br from-[#7700CC]/10 to-[#47007A]/5 border-b">
                <div className="flex items-center justify-between mb-2">
                  <CardTitle className="heading text-2xl text-[#180029]">
                    Complete Package
                  </CardTitle>
                  <Sparkles className="w-8 h-8 text-[#7700CC]" />
                </div>
                <div className="mt-4">
                  <span className="heading text-5xl font-bold text-[#180029]">$87</span>
                  <span className="text-[#180029]/70 ml-2">one-time</span>
                </div>
                <p className="text-sm text-[#180029]/70 mt-2">
                  Maximum AI visibility and optimization
                </p>
              </CardHeader>
              <CardContent className="p-8">
                <div className="space-y-4 mb-8">
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029] font-medium">Everything in Basic, plus:</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">13-15 comprehensive files</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">Extended llms.txt & llms-full.txt</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">humans.txt file</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">security.txt configuration</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">.well-known/ai.json</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">LocalBusiness schema (if applicable)</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">Verification checklist</span>
                  </div>
                  <div className="flex items-start gap-3">
                    <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                    <span className="text-[#180029]">90-day download access</span>
                  </div>
                </div>

                <Button
                  onClick={() => handlePurchase('complete')}
                  disabled={processingComplete}
                  className="w-full h-14 bg-[#7700CC] hover:bg-[#47007A] text-white text-lg subheading"
                >
                  {processingComplete ? (
                    <>
                      <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-3"></div>
                      Processing...
                    </>
                  ) : (
                    'Get Complete Package'
                  )}
                </Button>
              </CardContent>
            </Card>
          </motion.div>
        </div>

        {/* Upgrade Info */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.4 }}
          className="mt-16 text-center"
        >
          <Card className="border-2 border-[#F4743B] bg-gradient-to-br from-[#F4743B]/5 to-white max-w-2xl mx-auto shadow-xl">
            <CardContent className="p-8">
              <div className="flex items-center justify-center gap-3 mb-4">
                <div className="w-12 h-12 bg-[#F4743B] rounded-full flex items-center justify-center">
                  <TrendingUp className="w-6 h-6 text-white" />
                </div>
                <h3 className="heading text-2xl font-semibold text-[#180029]">
                  Already have a Basic package?
                </h3>
              </div>
              <p className="text-lg text-[#180029]/70 mb-6">
                Upgrade to Complete for just <span className="font-bold text-[#F4743B]">$50</span> from your dashboard and get 6-8 additional files plus extended documentation.
              </p>
              <div className="flex flex-col sm:flex-row gap-3 justify-center">
                <Button
                  onClick={() => navigate('/dashboard')}
                  className="bg-[#F4743B] hover:bg-[#F4743B]/90 text-white h-12 px-8"
                >
                  Upgrade from Dashboard
                  <ArrowRight className="w-4 h-4 ml-2" />
                </Button>
              </div>
            </CardContent>
          </Card>
        </motion.div>

        {/* FAQ Section */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.5 }}
          className="mt-20"
        >
          <h2 className="heading text-3xl font-bold text-[#180029] text-center mb-12">
            Frequently Asked Questions
          </h2>
          <div className="grid md:grid-cols-2 gap-6 max-w-4xl mx-auto">
            <Card className="border border-gray-200">
              <CardContent className="p-6">
                <h3 className="subheading text-lg font-semibold text-[#180029] mb-2">
                  What's the difference between packages?
                </h3>
                <p className="text-sm text-[#180029]/70">
                  Basic includes 7 essential files for AI optimization. Complete includes 13-15 files with extended data, additional schema types, and comprehensive documentation.
                </p>
              </CardContent>
            </Card>

            <Card className="border border-gray-200">
              <CardContent className="p-6">
                <h3 className="subheading text-lg font-semibold text-[#180029] mb-2">
                  How long do I have access?
                </h3>
                <p className="text-sm text-[#180029]/70">
                  You have 90 days to download your package files after generation. Download them as many times as you need during this period.
                </p>
              </CardContent>
            </Card>

            <Card className="border border-gray-200">
              <CardContent className="p-6">
                <h3 className="subheading text-lg font-semibold text-[#180029] mb-2">
                  Can I upgrade later?
                </h3>
                <p className="text-sm text-[#180029]/70">
                  Yes! If you start with Basic, you can upgrade to Complete for $50 from your dashboard. The upgrade regenerates all files with the enhanced package.
                </p>
              </CardContent>
            </Card>

            <Card className="border border-gray-200">
              <CardContent className="p-6">
                <h3 className="subheading text-lg font-semibold text-[#180029] mb-2">
                  Is this a subscription?
                </h3>
                <p className="text-sm text-[#180029]/70">
                  No! This is a one-time payment per package. No recurring charges. Generate your files, download them, and implement them on your website.
                </p>
              </CardContent>
            </Card>
          </div>
        </motion.div>

        {/* Coupon Section */}
        {user && !user.coupon_used && (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6 }}
            className="mt-16"
          >
            <Card className="max-w-2xl mx-auto border-2 border-[#7700CC]/20 bg-gradient-to-r from-[#7700CC]/5 to-[#F4743B]/5">
              <CardContent className="p-8">
                <div className="text-center mb-6">
                  <div className="inline-flex items-center justify-center bg-[#7700CC] text-white p-4 rounded-full mb-4">
                    <Tag className="w-6 h-6" />
                  </div>
                  <h3 className="heading text-2xl font-bold text-[#180029] mb-2">Have a Coupon Code?</h3>
                  <p className="text-[#180029]/70">Enter your coupon and apply it to receive free credits</p>
                </div>
                <div className="flex flex-col sm:flex-row gap-3 max-w-md mx-auto">
                  <Input
                    placeholder="Enter coupon code"
                    value={couponCode}
                    onChange={(e) => setCouponCode(e.target.value)}
                    disabled={applyingCoupon}
                    className="flex-1"
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        handleApplyCoupon();
                      }
                    }}
                  />
                  <Button
                    onClick={handleApplyCoupon}
                    disabled={applyingCoupon || !couponCode.trim()}
                    className="bg-[#7700CC] hover:bg-[#47017a] text-white px-8"
                  >
                    {applyingCoupon ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin mr-2" />
                        Applying...
                      </>
                    ) : (
                      'Apply Code'
                    )}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </motion.div>
        )}
      </div>
    </div>
  );
}
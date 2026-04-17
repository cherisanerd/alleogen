import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Check, Zap, ArrowRight, Sparkles } from 'lucide-react';
import { motion } from 'framer-motion';
import { useNavigate } from 'react-router-dom';

export default function OrderBump() {
  const [user, setUser] = useState(null);
  const [selectedPlan, setSelectedPlan] = useState('basic');
  const [upgradeChecked, setUpgradeChecked] = useState(false);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    loadUserAndPlan();
  }, []);

  const loadUserAndPlan = async () => {
    try {
      const currentUser = await base44.auth.me();
      setUser(currentUser);

      // Get selected plan from localStorage
      const plan = localStorage.getItem('selectedPlan') || 'basic';
      setSelectedPlan(plan);
    } catch (error) {
      // Not logged in, redirect to login
      base44.auth.redirectToLogin(window.location.origin + '/order-bump');
    }
  };

  const handleCheckout = async () => {
    setLoading(true);

    try {
      // Determine final package and price
      const finalPackage = selectedPlan === 'complete' || upgradeChecked ? 'complete' : 'basic';
      const finalPrice = finalPackage === 'complete' ? 8700 : 4700; // cents

      // Call backend to create Stripe checkout session
      const { data } = await base44.functions.invoke('createCheckout', {
        package_tier: finalPackage,
        amount: finalPrice,
        user_id: user.id,
        user_email: user.email
      });

      if (data.url) {
        // Redirect to Stripe checkout
        window.location.href = data.url;
      } else {
        throw new Error('Failed to create checkout session');
      }
    } catch (error) {
      console.error('Checkout error:', error);
      alert('Failed to create checkout session. Please try again.');
      setLoading(false);
    }
  };

  const getCurrentPrice = () => {
    if (selectedPlan === 'complete') return 87;
    return upgradeChecked ? 87 : 47;
  };

  const upgradeFeatures = [
    'llms.txt & llms-full.txt (extended versions)',
    'humans.txt & security.txt',
    '.well-known/ai.json',
    'Webpage schema & LocalBusiness schema',
    'Verification checklist',
    'Platform-specific implementation guidance',
    'Detailed customization'
  ];

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4 sm:px-6 lg:px-8">
      <div className="max-w-3xl mx-auto">
        {selectedPlan === 'basic' ? (
          /* Upsell for Basic */
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
          >
            <Card className="border-2 border-[#F4743B] shadow-2xl">
              <CardHeader className="bg-gradient-to-r from-[#F4743B]/10 to-[#F4743B]/5 border-b">
                <div className="flex items-center gap-3">
                  <Zap className="w-8 h-8 text-[#F4743B]" />
                  <CardTitle className="heading text-3xl text-[#180029]">
                    Wait! Upgrade to Complete Package
                  </CardTitle>
                </div>
              </CardHeader>
              <CardContent className="p-8">
                <div className="mb-6">
                  <p className="text-lg text-[#180029]/70 mb-2">You selected:</p>
                  <p className="heading text-2xl text-[#180029]">Basic Package - $47</p>
                </div>

                <Card className="bg-gradient-to-br from-[#7700CC]/5 to-[#47007A]/5 border-2 border-[#7700CC]/20 mb-6">
                  <CardContent className="p-6">
                    <div className="flex items-center gap-3 mb-4">
                      <Sparkles className="w-6 h-6 text-[#7700CC]" />
                      <h3 className="heading text-xl text-[#180029]">
                        Add Complete Package for just $40 more
                      </h3>
                    </div>

                    <p className="subheading text-sm text-[#180029]/70 mb-4">
                      You get 6 additional files:
                    </p>

                    <div className="space-y-3">
                      {upgradeFeatures.map((feature, idx) => (
                        <div key={idx} className="flex items-start gap-3">
                          <Check className="w-5 h-5 text-[#7700CC] flex-shrink-0 mt-0.5" />
                          <span className="text-[#180029]">{feature}</span>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>

                <div className="bg-white rounded-lg p-6 border-2 border-gray-200 mb-6">
                  <label className="flex items-start gap-4 cursor-pointer">
                    <Checkbox
                      checked={upgradeChecked}
                      onCheckedChange={setUpgradeChecked}
                      className="mt-1"
                    />
                    <div>
                      <p className="subheading text-lg text-[#180029] mb-1">
                        ✓ Yes! Upgrade to Complete for $87
                      </p>
                      <p className="text-sm text-[#180029]/60">
                        Get all 13 files + premium features
                      </p>
                    </div>
                  </label>
                </div>

                <Button
                  onClick={handleCheckout}
                  disabled={loading}
                  className="w-full h-14 bg-[#7700CC] hover:bg-[#47007A] text-white text-lg subheading"
                >
                  {loading ? (
                    'Processing...'
                  ) : (
                    <>
                      Continue to Checkout - ${getCurrentPrice()}
                      <ArrowRight className="w-5 h-5 ml-2" />
                    </>
                  )}
                </Button>

                <div className="mt-6 p-4 bg-[#F4743B]/10 rounded-lg">
                  <p className="text-center text-sm text-[#F4743B] subheading">
                    💡 Save $50 vs upgrading later ($97)
                  </p>
                </div>
              </CardContent>
            </Card>
          </motion.div>
        ) : (
          /* Confirmation for Complete */
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
          >
            <Card className="border-2 border-[#7700CC] shadow-2xl">
              <CardHeader className="bg-gradient-to-r from-[#7700CC]/10 to-[#47007A]/5 border-b">
                <div className="flex items-center gap-3">
                  <Check className="w-8 h-8 text-[#7700CC]" />
                  <CardTitle className="heading text-3xl text-[#180029]">
                    Great Choice! Complete Package
                  </CardTitle>
                </div>
              </CardHeader>
              <CardContent className="p-8">
                <div className="mb-6">
                  <p className="text-lg text-[#180029]/70 mb-2">You selected:</p>
                  <p className="heading text-2xl text-[#180029]">Complete Package - $87</p>
                </div>

                <Card className="bg-gradient-to-br from-[#7700CC]/5 to-[#47007A]/5 border-2 border-[#7700CC]/20 mb-6">
                  <CardContent className="p-6">
                    <p className="subheading text-sm text-[#180029]/70 mb-4">
                      You're getting:
                    </p>

                    <div className="space-y-3">
                      <div className="flex items-center gap-3">
                        <Check className="w-5 h-5 text-[#7700CC]" />
                        <span className="text-[#180029]">13 comprehensive AEO files</span>
                      </div>
                      <div className="flex items-center gap-3">
                        <Check className="w-5 h-5 text-[#7700CC]" />
                        <span className="text-[#180029]">Platform-specific implementation guide</span>
                      </div>
                      <div className="flex items-center gap-3">
                        <Check className="w-5 h-5 text-[#7700CC]" />
                        <span className="text-[#180029]">Detailed customization & verification checklist</span>
                      </div>
                      <div className="flex items-center gap-3">
                        <Check className="w-5 h-5 text-[#7700CC]" />
                        <span className="text-[#180029]">90-day download access</span>
                      </div>
                      <div className="flex items-center gap-3">
                        <Check className="w-5 h-5 text-[#7700CC]" />
                        <span className="text-[#180029]">Priority support</span>
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Button
                  onClick={handleCheckout}
                  disabled={loading}
                  className="w-full h-14 bg-[#7700CC] hover:bg-[#47007A] text-white text-lg subheading"
                >
                  {loading ? (
                    'Processing...'
                  ) : (
                    <>
                      Continue to Checkout - $87
                      <ArrowRight className="w-5 h-5 ml-2" />
                    </>
                  )}
                </Button>
              </CardContent>
            </Card>
          </motion.div>
        )}
      </div>
    </div>
  );
}
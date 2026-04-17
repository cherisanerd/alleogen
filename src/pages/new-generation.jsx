import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ArrowRight, CreditCard, Package, AlertCircle, Sparkles, Tag, Loader2 } from 'lucide-react';
import { motion } from 'framer-motion';
import { toast } from 'sonner';
import { useNavigate } from 'react-router-dom';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export default function NewGeneration() {
  const [user, setUser] = useState(null);
  const [websiteUrl, setWebsiteUrl] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [showNoCreditsDialog, setShowNoCreditsDialog] = useState(false);
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
        setShowNoCreditsDialog(false);
        setCouponCode('');
      }
    } catch (error) {
      const errorMsg = error.response?.data?.error || 'Failed to apply coupon';
      toast.error(errorMsg);
    } finally {
      setApplyingCoupon(false);
    }
  };

  const validateUrl = (url) => {
    if (!url.trim()) {
      return 'Please enter a website URL';
    }

    let testUrl = url.trim();
    
    // Auto-add https:// if missing
    if (!testUrl.startsWith('http://') && !testUrl.startsWith('https://')) {
      testUrl = 'https://' + testUrl;
      setWebsiteUrl(testUrl);
    }

    // Basic URL validation
    try {
      new URL(testUrl);
      return null;
    } catch {
      return 'Please enter a valid website URL';
    }
  };

  const handleAnalyze = async () => {
    // Validate URL
    const urlError = validateUrl(websiteUrl);
    if (urlError) {
      setError(urlError);
      return;
    }

    // Check credits (skip for super admin)
    if (!user?.is_super_admin && (user?.credits_remaining || 0) < 1) {
      setShowNoCreditsDialog(true);
      return;
    }

    setError('');
    setLoading(true);

    try {
      // Create analysis record and start analysis
      const { data } = await base44.functions.invoke('analyzeWebsite', {
        website_url: websiteUrl,
        user_id: user.id
      });

      if (data.error) {
        toast.error(data.error);
        setLoading(false);
        return;
      }

      // Navigate to questionnaire with analysis ID
      navigate(`/Questionnaire?analysis_id=${data.analysis_id}`);
    } catch (err) {
      console.error('Analysis error:', err);
      const errorMsg = err.response?.data?.error || err.message || 'Failed to start analysis. Please try again.';
      toast.error(errorMsg);
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4 sm:px-6 lg:px-8">
      <div className="max-w-3xl mx-auto">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          {/* Header */}
          <div className="text-center mb-12">
            <div className="inline-flex items-center gap-2 px-4 py-2 bg-[#7700CC] text-white rounded-full text-sm subheading mb-6">
              <Sparkles className="w-4 h-4" />
              AI-Powered Analysis
            </div>
            <h1 className="heading text-4xl md:text-5xl font-bold text-[#180029] mb-4">
              Create Your AEO Package
            </h1>
            <p className="text-lg text-[#180029]/70">
              We'll analyze your website and auto-fill as much as possible
            </p>
          </div>

          {/* Credits Display */}
          {user && (
            <div className="flex justify-center gap-4 mb-8">
              <div className="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-sm">
                <CreditCard className="w-5 h-5 text-[#7700CC]" />
                <span className="text-sm text-[#180029]/70">Credits:</span>
                <span className="subheading text-[#180029]">
                  {user.is_super_admin ? '∞' : (user.credits_remaining || 0)}
                </span>
              </div>
              <div className="flex items-center gap-2 bg-white px-4 py-2 rounded-lg shadow-sm">
                <Package className="w-5 h-5 text-[#F4743B]" />
                <span className="text-sm text-[#180029]/70">Package:</span>
                <span className="subheading text-[#180029] capitalize">{user.plan_type || 'none'}</span>
              </div>
            </div>
          )}

          {/* Main Card */}
          <Card className="border-2 border-gray-200 shadow-2xl">
            <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5 border-b">
              <CardTitle className="heading text-2xl text-[#180029]">
                Step 1: Enter Your Website
              </CardTitle>
            </CardHeader>
            <CardContent className="p-8">
              <div className="space-y-6">
                <div className="space-y-2">
                  <Label htmlFor="website" className="subheading text-[#180029]">
                    Website URL
                  </Label>
                  <Input
                    id="website"
                    type="text"
                    placeholder="https://www.example.com"
                    value={websiteUrl}
                    onChange={(e) => {
                      setWebsiteUrl(e.target.value);
                      setError('');
                    }}
                    className="h-12 text-lg border-gray-300 focus:border-[#7700CC] focus:ring-[#7700CC]"
                    disabled={loading}
                  />
                  {error && (
                    <div className="flex items-center gap-2 text-red-600 text-sm mt-2">
                      <AlertCircle className="w-4 h-4" />
                      {error}
                    </div>
                  )}
                </div>

                <div className="bg-[#7700CC]/5 rounded-lg p-4">
                  <p className="text-sm text-[#180029]/70 flex items-start gap-2">
                    <span>💡</span>
                    <span>
                      We'll analyze your website and auto-fill business information to save you time. You can review and edit everything before generating your files.
                    </span>
                  </p>
                </div>

                <Button
                  onClick={handleAnalyze}
                  disabled={loading || !websiteUrl.trim()}
                  className="w-full h-14 bg-[#7700CC] hover:bg-[#47007A] text-white text-lg subheading"
                >
                  {loading ? (
                    <>
                      <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-white mr-3"></div>
                      Analyzing Website...
                    </>
                  ) : (
                    <>
                      Analyze Website
                      <ArrowRight className="w-5 h-5 ml-2" />
                    </>
                  )}
                </Button>
              </div>
            </CardContent>
          </Card>
        </motion.div>

        {/* No Credits Dialog */}
        <Dialog open={showNoCreditsDialog} onOpenChange={setShowNoCreditsDialog}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="heading text-2xl text-[#180029]">
                No Credits Remaining
              </DialogTitle>
              <DialogDescription className="text-[#180029]/70">
                You need to purchase credits or use a coupon code to generate an AEO package.
              </DialogDescription>
            </DialogHeader>
            
            {!user?.coupon_used && (
              <div className="py-4 border-y border-gray-200">
                <div className="flex items-center gap-2 mb-3">
                  <Tag className="w-4 h-4 text-[#7700CC]" />
                  <p className="text-sm font-medium text-[#180029]">Have a coupon code?</p>
                </div>
                <div className="flex gap-2">
                  <Input
                    placeholder="Enter code"
                    value={couponCode}
                    onChange={(e) => setCouponCode(e.target.value)}
                    disabled={applyingCoupon}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        handleApplyCoupon();
                      }
                    }}
                  />
                  <Button
                    onClick={handleApplyCoupon}
                    disabled={applyingCoupon}
                    variant="outline"
                    className="whitespace-nowrap"
                  >
                    {applyingCoupon ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin mr-2" />
                        Applying...
                      </>
                    ) : (
                      'Apply'
                    )}
                  </Button>
                </div>
                <p className="text-xs text-[#180029]/60 mt-2">
                  Use code WELCOME3 for 3 free credits
                </p>
              </div>
            )}

            <DialogFooter className="flex-col sm:flex-row gap-3">
              <Button
                variant="outline"
                onClick={() => setShowNoCreditsDialog(false)}
              >
                Cancel
              </Button>
              <Button
                onClick={() => navigate('/pricing')}
                className="bg-[#7700CC] hover:bg-[#47007A] text-white"
              >
                Buy Credits
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  );
}
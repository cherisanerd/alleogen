import React, { useEffect, useState } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, Loader2, ArrowRight, Calendar } from 'lucide-react';
import { motion } from 'framer-motion';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';

export default function UpgradeSuccess() {
  const [searchParams] = useSearchParams();
  const [loading, setLoading] = useState(true);
  const [upgradeData, setUpgradeData] = useState(null);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    processUpgrade();
  }, []);

  const processUpgrade = async () => {
    const sessionId = searchParams.get('session_id');
    
    if (!sessionId) {
      setError('No session ID provided');
      setLoading(false);
      return;
    }

    try {
      const { data } = await base44.functions.invoke('processUpgrade', {
        session_id: sessionId
      });

      if (data.error) {
        setError(data.error);
        toast.error(data.error);
      } else {
        setUpgradeData(data);
        toast.success('Package upgraded successfully!');
      }
    } catch (err) {
      console.error('Upgrade processing error:', err);
      const errorMsg = 'Failed to process upgrade. Please contact support.';
      setError(errorMsg);
      toast.error(errorMsg);
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="w-16 h-16 text-[#7700CC] animate-spin mx-auto mb-4" />
          <p className="text-[#180029]/70 subheading">Processing your upgrade...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] py-16 px-4">
        <div className="max-w-2xl mx-auto">
          <Card className="border-2 border-red-200 shadow-lg">
            <CardContent className="p-8 text-center">
              <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span className="text-3xl">⚠️</span>
              </div>
              <h2 className="heading text-2xl text-[#180029] mb-4">Upgrade Error</h2>
              <p className="text-[#180029]/70 mb-6">{error}</p>
              <Button
                onClick={() => navigate('/dashboard')}
                className="bg-[#7700CC] hover:bg-[#47007A] text-white"
              >
                Return to Dashboard
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4">
      <div className="max-w-2xl mx-auto">
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
        >
          <Card className="border-2 border-[#F4743B] shadow-2xl">
            <CardHeader className="bg-gradient-to-r from-[#F4743B]/10 to-[#F4743B]/5 border-b">
              <div className="text-center">
                <motion.div
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
                  className="w-20 h-20 bg-[#F4743B] rounded-full flex items-center justify-center mx-auto mb-4"
                >
                  <CheckCircle className="w-12 h-12 text-white" />
                </motion.div>
                <CardTitle className="heading text-3xl text-[#180029]">
                  Upgrade Successful!
                </CardTitle>
              </div>
            </CardHeader>
            <CardContent className="p-8">
              <div className="text-center mb-8">
                <p className="text-lg text-[#180029]/70 mb-6">
                  Your package has been upgraded to Complete.
                </p>

                <div className="bg-[#F4743B]/5 rounded-lg p-6 space-y-4 mb-6">
                  <div className="flex justify-between items-center">
                    <span className="text-[#180029]/70">Website:</span>
                    <span className="subheading text-[#180029]">
                      {upgradeData?.website_url}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-[#180029]/70">New Package:</span>
                    <span className="subheading text-[#180029]">Complete Package</span>
                  </div>
                  {upgradeData?.expires_at && (
                    <div className="flex justify-between items-center">
                      <span className="text-[#180029]/70">Download Until:</span>
                      <span className="subheading text-[#180029] flex items-center gap-2">
                        <Calendar className="w-4 h-4" />
                        {formatDate(upgradeData.expires_at)}
                      </span>
                    </div>
                  )}
                </div>

                <div className="bg-gradient-to-r from-[#7700CC]/10 to-[#47007A]/5 rounded-lg p-6">
                  <p className="text-center text-[#180029] mb-2">
                    ✨ Your Complete package includes all 13 files
                  </p>
                  <p className="text-sm text-[#180029]/60">
                    Download access extended by 90 days from today
                  </p>
                </div>
              </div>

              <Button
                onClick={() => navigate('/dashboard')}
                className="w-full h-14 bg-[#7700CC] hover:bg-[#47007A] text-white text-lg subheading"
              >
                View Updated Package
                <ArrowRight className="w-5 h-5 ml-2" />
              </Button>
            </CardContent>
          </Card>
        </motion.div>
      </div>
    </div>
  );
}
import React, { useEffect, useState } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle, Loader2, ArrowRight } from 'lucide-react';
import { motion } from 'framer-motion';
import { useNavigate, useSearchParams } from 'react-router-dom';

export default function PaymentSuccess() {
  const [searchParams] = useSearchParams();
  const [loading, setLoading] = useState(true);
  const [paymentData, setPaymentData] = useState(null);
  const [error, setError] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    processPayment();
  }, []);

  const processPayment = async () => {
    const sessionId = searchParams.get('session_id');
    
    if (!sessionId) {
      setError('No session ID provided');
      setLoading(false);
      return;
    }

    try {
      // Call backend to process the payment
      const { data } = await base44.functions.invoke('processPayment', {
        session_id: sessionId
      });

      if (data.error) {
        setError(data.error);
      } else {
        setPaymentData(data);
      }
    } catch (err) {
      console.error('Payment processing error:', err);
      setError('Failed to process payment. Please contact support.');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <div className="text-center">
          <Loader2 className="w-16 h-16 text-[#7700CC] animate-spin mx-auto mb-4" />
          <p className="text-[#180029]/70 subheading">Processing your payment...</p>
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
              <h2 className="heading text-2xl text-[#180029] mb-4">Payment Error</h2>
              <p className="text-[#180029]/70 mb-6">{error}</p>
              <Button
                onClick={() => navigate('/pricing')}
                className="bg-[#7700CC] hover:bg-[#47007A] text-white"
              >
                Return to Pricing
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4">
      <div className="max-w-2xl mx-auto">
        <motion.div
          initial={{ opacity: 0, scale: 0.95 }}
          animate={{ opacity: 1, scale: 1 }}
        >
          <Card className="border-2 border-[#7700CC] shadow-2xl">
            <CardHeader className="bg-gradient-to-r from-[#7700CC]/10 to-[#47007A]/5 border-b">
              <div className="text-center">
                <motion.div
                  initial={{ scale: 0 }}
                  animate={{ scale: 1 }}
                  transition={{ delay: 0.2, type: "spring", stiffness: 200 }}
                  className="w-20 h-20 bg-[#7700CC] rounded-full flex items-center justify-center mx-auto mb-4"
                >
                  <CheckCircle className="w-12 h-12 text-white" />
                </motion.div>
                <CardTitle className="heading text-3xl text-[#180029]">
                  Payment Successful!
                </CardTitle>
              </div>
            </CardHeader>
            <CardContent className="p-8">
              <div className="text-center mb-8">
                <p className="text-lg text-[#180029]/70 mb-6">
                  Thank you for your purchase!
                </p>

                <div className="bg-[#7700CC]/5 rounded-lg p-6 space-y-4">
                  <div className="flex justify-between items-center">
                    <span className="text-[#180029]/70">Package:</span>
                    <span className="subheading text-[#180029] capitalize">
                      {paymentData?.package_tier} Package
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-[#180029]/70">Amount Paid:</span>
                    <span className="subheading text-[#180029]">
                      ${paymentData?.amount}
                    </span>
                  </div>
                  <div className="flex justify-between items-center">
                    <span className="text-[#180029]/70">Credits Added:</span>
                    <span className="subheading text-[#180029]">1</span>
                  </div>
                </div>
              </div>

              <div className="bg-gradient-to-r from-[#7700CC]/10 to-[#47007A]/5 rounded-lg p-6 mb-8">
                <p className="text-center text-[#180029] mb-2">
                  🎉 You're ready to generate your AEO package!
                </p>
              </div>

              <Button
                onClick={() => navigate('/file-generator')}
                className="w-full h-14 bg-[#7700CC] hover:bg-[#47007A] text-white text-lg subheading"
              >
                Start Generation
                <ArrowRight className="w-5 h-5 ml-2" />
              </Button>

              <p className="text-sm text-[#180029]/60 text-center mt-4">
                Receipt sent to: {paymentData?.email}
              </p>
            </CardContent>
          </Card>
        </motion.div>
      </div>
    </div>
  );
}
import React, { useState } from 'react';
import { base44 } from '@/api/base44Client';
import { useAuth } from '@/lib/AuthContext';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';

export default function AccountCancel() {
  const { user, checkAppState } = useAuth();
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      const { data } = await base44.functions.invoke('requestCancel', { reason });
      toast.success('Cancellation request sent. Your files remain available until the end of the billing period.');
      if (data?.redirect_url) {
        window.location.href = data.redirect_url;
      } else {
        await checkAppState();
        navigate('/dashboard');
      }
    } catch (err) {
      toast.error(err?.data?.error || err?.message || 'Could not cancel. Please contact support.');
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4">
      <div className="max-w-xl mx-auto">
        <Card className="border-2 border-gray-200 shadow-xl">
          <CardHeader className="bg-gradient-to-r from-[#F4743B]/10 to-[#F4743B]/5 border-b">
            <div className="flex items-center gap-2">
              <AlertTriangle className="w-5 h-5 text-[#F4743B]" />
              <CardTitle className="heading text-xl text-[#180029]">Cancel subscription</CardTitle>
            </div>
          </CardHeader>
          <CardContent className="p-8 space-y-6">
            <div className="text-sm text-[#180029]/80 leading-relaxed space-y-3">
              <p>
                Canceling will keep your subscription active through the current billing period.
                After it ends, your files will remain downloadable for <strong>30 days</strong>, then be deleted.
              </p>
              <p>
                You can re-subscribe anytime before the deletion date to keep everything.
              </p>
              {user?.subscription_status === 'canceling' && (
                <p className="p-3 rounded bg-amber-50 border border-amber-200 text-amber-900">
                  A cancellation request is already in flight. Check your email for the confirmation from GoHighLevel.
                </p>
              )}
            </div>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="text-sm font-medium text-[#180029] mb-2 block">
                  Tell us why (optional)
                </label>
                <Textarea
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="min-h-[100px]"
                  placeholder="Anything we could do differently?"
                  maxLength={500}
                />
              </div>
              <div className="flex gap-3">
                <Button type="button" variant="outline" onClick={() => navigate('/dashboard')} disabled={submitting}>
                  Keep subscription
                </Button>
                <Button
                  type="submit"
                  disabled={submitting || user?.subscription_status === 'canceling'}
                  className="flex-1 bg-[#F4743B] hover:bg-[#d95e2a] text-white gap-2"
                >
                  {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
                  Confirm cancellation
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

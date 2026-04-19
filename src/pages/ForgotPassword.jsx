import React, { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Mail } from 'lucide-react';
import { Link } from 'react-router-dom';

const API_BASE = import.meta.env.VITE_API_BASE || '/tools/alleogen/api';

export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email) return;
    setSubmitting(true);
    try {
      await fetch(`${API_BASE}/auth/request-password-reset`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });
      // Always show the same confirmation — the backend never reveals
      // whether the address exists, and neither do we.
      setDone(true);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center px-4">
      <Card className="w-full max-w-md border-2 border-gray-200 shadow-xl">
        <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5 border-b">
          <CardTitle className="heading text-2xl text-[#180029]">Reset your password</CardTitle>
          <p className="text-sm text-[#180029]/70">
            We'll email you a link to set a new one.
          </p>
        </CardHeader>
        <CardContent className="p-8">
          {done ? (
            <div className="space-y-4 text-sm text-[#180029]/80 leading-relaxed">
              <div className="flex items-start gap-3">
                <Mail className="w-5 h-5 mt-0.5 text-[#7700CC] flex-shrink-0" />
                <p>
                  If an account exists for <strong>{email}</strong>, a reset link is on its way.
                  Check your inbox (and spam). The link expires in 60 minutes.
                </p>
              </div>
              <div className="pt-4 border-t">
                <Link to="/login" className="text-sm text-[#7700CC] hover:underline">
                  &larr; Back to sign in
                </Link>
              </div>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <Label htmlFor="email">Email</Label>
                <Input
                  id="email"
                  type="email"
                  autoComplete="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  className="h-11"
                />
              </div>
              <Button
                type="submit"
                disabled={submitting}
                className="w-full h-11 bg-[#7700CC] hover:bg-[#47007A] text-white gap-2"
              >
                {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
                {submitting ? 'Sending…' : 'Send reset link'}
              </Button>
              <div className="text-center pt-2">
                <Link to="/login" className="text-xs text-[#180029]/60 hover:text-[#180029]">
                  Back to sign in
                </Link>
              </div>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

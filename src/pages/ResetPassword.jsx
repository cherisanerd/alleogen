import React, { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Lock, CheckCircle2 } from 'lucide-react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';

const API_BASE = import.meta.env.VITE_API_BASE || '/tools/alleogen/api';

export default function ResetPassword() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') || '';
  const navigate = useNavigate();
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [done, setDone] = useState(false);

  useEffect(() => {
    if (!token) {
      toast.error('This reset link is invalid.');
    }
  }, [token]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (password.length < 10) {
      toast.error('Password must be at least 10 characters.');
      return;
    }
    if (password !== confirm) {
      toast.error('Passwords do not match.');
      return;
    }
    setSubmitting(true);
    try {
      const res = await fetch(`${API_BASE}/auth/reset-password`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, new_password: password }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) {
        throw new Error(data?.error || 'Could not reset password.');
      }
      setDone(true);
      setTimeout(() => navigate('/login'), 2500);
    } catch (err) {
      toast.error(err.message || 'Could not reset password.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center px-4">
      <Card className="w-full max-w-md border-2 border-gray-200 shadow-xl">
        <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5 border-b">
          <CardTitle className="heading text-2xl text-[#180029]">Set a new password</CardTitle>
        </CardHeader>
        <CardContent className="p-8">
          {!token ? (
            <p className="text-sm text-[#180029]/70">
              This link is missing its reset token. <Link to="/account/forgot" className="text-[#7700CC] hover:underline">Request a fresh one.</Link>
            </p>
          ) : done ? (
            <div className="text-sm text-[#180029]/80 space-y-3">
              <div className="flex items-start gap-3">
                <CheckCircle2 className="w-5 h-5 mt-0.5 text-green-600 flex-shrink-0" />
                <p>Password updated. Redirecting you to sign in…</p>
              </div>
            </div>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <Label htmlFor="password">New password</Label>
                <Input
                  id="password"
                  type="password"
                  autoComplete="new-password"
                  minLength={10}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  className="h-11"
                />
                <p className="text-xs text-[#180029]/60 mt-1">Minimum 10 characters.</p>
              </div>
              <div>
                <Label htmlFor="confirm">Confirm new password</Label>
                <Input
                  id="confirm"
                  type="password"
                  autoComplete="new-password"
                  value={confirm}
                  onChange={(e) => setConfirm(e.target.value)}
                  required
                  className="h-11"
                />
              </div>
              <Button
                type="submit"
                disabled={submitting}
                className="w-full h-11 bg-[#7700CC] hover:bg-[#47007A] text-white gap-2"
              >
                {submitting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Lock className="w-4 h-4" />}
                {submitting ? 'Saving…' : 'Set password'}
              </Button>
            </form>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

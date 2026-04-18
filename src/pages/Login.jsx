import React, { useState } from 'react';
import { base44 } from '@/api/base44Client';
import { useAuth } from '@/lib/AuthContext';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, LogIn } from 'lucide-react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { toast } from 'sonner';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { checkAppState } = useAuth();

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!email || !password) return;
    setSubmitting(true);
    try {
      await base44.auth.login(email, password);
      await checkAppState();
      const returnUrl = searchParams.get('return');
      if (returnUrl) {
        window.location.href = returnUrl;
      } else {
        navigate('/dashboard');
      }
    } catch (err) {
      toast.error(err?.data?.error || err?.message || 'Login failed.');
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center px-4">
      <Card className="w-full max-w-md border-2 border-gray-200 shadow-xl">
        <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5 border-b">
          <CardTitle className="heading text-2xl text-[#180029]">Sign in</CardTitle>
          <p className="text-sm text-[#180029]/70">Access your SEO + AEO + GEO dashboard.</p>
        </CardHeader>
        <CardContent className="p-8">
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
            <div>
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="h-11"
              />
            </div>
            <Button
              type="submit"
              disabled={submitting}
              className="w-full h-11 bg-[#7700CC] hover:bg-[#47007A] text-white gap-2"
            >
              {submitting ? <Loader2 className="w-4 h-4 animate-spin" /> : <LogIn className="w-4 h-4" />}
              {submitting ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>
          <p className="text-xs text-[#180029]/60 mt-6 leading-relaxed">
            No account yet? Subscriptions are provisioned automatically when you purchase through our GoHighLevel checkout — we'll email you a temporary password.
          </p>
        </CardContent>
      </Card>
    </div>
  );
}

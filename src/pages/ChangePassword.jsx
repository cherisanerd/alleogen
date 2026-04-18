import React, { useState } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Shield } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'sonner';

export default function ChangePassword() {
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (newPassword.length < 10) {
      toast.error('New password must be at least 10 characters.');
      return;
    }
    if (newPassword !== confirmPassword) {
      toast.error('New password and confirmation do not match.');
      return;
    }
    setSubmitting(true);
    try {
      await base44.auth.changePassword(currentPassword, newPassword);
      toast.success('Password updated.');
      navigate('/dashboard');
    } catch (err) {
      toast.error(err?.data?.error || err?.message || 'Could not change password.');
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4">
      <div className="max-w-md mx-auto">
        <Card className="border-2 border-gray-200 shadow-xl">
          <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5 border-b">
            <div className="flex items-center gap-2">
              <Shield className="w-5 h-5 text-[#7700CC]" />
              <CardTitle className="heading text-xl text-[#180029]">Change password</CardTitle>
            </div>
          </CardHeader>
          <CardContent className="p-8">
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <Label htmlFor="current">Current password</Label>
                <Input
                  id="current"
                  type="password"
                  autoComplete="current-password"
                  value={currentPassword}
                  onChange={(e) => setCurrentPassword(e.target.value)}
                  required
                  className="h-11"
                />
              </div>
              <div>
                <Label htmlFor="new">New password</Label>
                <Input
                  id="new"
                  type="password"
                  autoComplete="new-password"
                  value={newPassword}
                  onChange={(e) => setNewPassword(e.target.value)}
                  required
                  minLength={10}
                  className="h-11"
                />
                <p className="text-xs text-[#180029]/60 mt-1">At least 10 characters.</p>
              </div>
              <div>
                <Label htmlFor="confirm">Confirm new password</Label>
                <Input
                  id="confirm"
                  type="password"
                  autoComplete="new-password"
                  value={confirmPassword}
                  onChange={(e) => setConfirmPassword(e.target.value)}
                  required
                  className="h-11"
                />
              </div>
              <div className="flex gap-3 pt-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => navigate('/dashboard')}
                  disabled={submitting}
                >
                  Cancel
                </Button>
                <Button
                  type="submit"
                  disabled={submitting}
                  className="flex-1 bg-[#7700CC] hover:bg-[#47007A] text-white gap-2"
                >
                  {submitting && <Loader2 className="w-4 h-4 animate-spin" />}
                  {submitting ? 'Saving…' : 'Update password'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

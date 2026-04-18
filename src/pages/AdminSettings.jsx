import React, { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2, Save, Settings as SettingsIcon } from 'lucide-react';
import { toast } from 'sonner';
import { motion } from 'framer-motion';

const GROUP_ORDER   = ['pricing', 'credits', 'storage', 'stripe', 'ghl', 'email', 'general'];
const GROUP_LABELS  = {
  pricing: 'Pricing',
  credits: 'Credits',
  storage: 'Storage & Reminders',
  stripe:  'Stripe',
  ghl:     'GoHighLevel',
  email:   'Email',
  general: 'Other',
};

export default function AdminSettings() {
  const [loading, setLoading] = useState(true);
  const [saving, setSaving]   = useState(false);
  const [groups, setGroups]   = useState({});
  const [values, setValues]   = useState({});

  useEffect(() => { load(); }, []);

  const load = async () => {
    try {
      const res = await fetch('/tools/alleogen/api/admin/settings/list', {
        credentials: 'include',
      });
      if (!res.ok) throw new Error('Failed to load settings');
      const data = await res.json();
      const byGroup = data.by_group || {};
      const seed = {};
      for (const list of Object.values(byGroup)) {
        for (const row of list) seed[row.setting_key] = row.setting_value ?? '';
      }
      setGroups(byGroup);
      setValues(seed);
    } catch (err) {
      toast.error(err?.message || 'Could not load settings.');
    } finally {
      setLoading(false);
    }
  };

  const onChange = (key, value) => setValues((v) => ({ ...v, [key]: value }));

  const onSave = async () => {
    setSaving(true);
    try {
      const res = await fetch('/tools/alleogen/api/admin/settings/update', {
        method: 'PUT',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ updates: values }),
      });
      if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err?.error || 'Save failed');
      }
      toast.success('Settings saved.');
    } catch (err) {
      toast.error(err?.message || 'Could not save settings.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <Loader2 className="w-10 h-10 text-[#7700CC] animate-spin" />
      </div>
    );
  }

  const orderedGroups = GROUP_ORDER
    .map((g) => [g, groups[g]])
    .filter(([, rows]) => Array.isArray(rows) && rows.length > 0)
    .concat(
      Object.entries(groups).filter(([g]) => !GROUP_ORDER.includes(g))
    );

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
          <div className="flex items-center gap-3 mb-2">
            <SettingsIcon className="w-7 h-7 text-[#7700CC]" />
            <h1 className="heading text-4xl font-bold text-[#180029]">Admin settings</h1>
          </div>
          <p className="text-[#180029]/60 mb-12">
            Non-secret values only. Stripe/GHL/DB secrets live in <code>api/config.local.php</code>.
          </p>
        </motion.div>

        <div className="space-y-8">
          {orderedGroups.map(([group, rows]) => (
            <Card key={group} className="border-2 border-gray-200 rounded-2xl">
              <CardHeader className="bg-[#F5F5F7]">
                <CardTitle className="heading text-xl text-[#180029]">
                  {GROUP_LABELS[group] || group}
                </CardTitle>
              </CardHeader>
              <CardContent className="p-6 space-y-4">
                {rows.map((row) => (
                  <div key={row.setting_key} className="grid md:grid-cols-[2fr_3fr] gap-4 items-start">
                    <div>
                      <Label className="text-sm text-[#180029]">{row.label}</Label>
                      <code className="text-xs text-[#180029]/50 block mt-1">{row.setting_key}</code>
                    </div>
                    <Input
                      value={values[row.setting_key] ?? ''}
                      onChange={(e) => onChange(row.setting_key, e.target.value)}
                      className="h-11 rounded-xl"
                    />
                  </div>
                ))}
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="flex justify-end mt-8">
          <Button
            onClick={onSave}
            disabled={saving}
            className="bg-[#7700CC] hover:bg-[#47007A] text-white gap-2 h-12 px-8"
          >
            {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
            {saving ? 'Saving…' : 'Save changes'}
          </Button>
        </div>
      </div>
    </div>
  );
}

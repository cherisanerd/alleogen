import React, { useEffect, useState } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Calendar, CreditCard, Package, FileText, ArrowRight, Download, TrendingUp } from 'lucide-react';
import { motion } from 'framer-motion';
import { Link } from 'react-router-dom';
import { toast } from 'sonner';

export default function Dashboard() {
  const [user, setUser] = useState(null);
  const [generations, setGenerations] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadUser();
    loadGenerations();
  }, []);

  const loadUser = async () => {
    try {
      const currentUser = await base44.auth.me();
      setUser(currentUser);
    } catch (error) {
      base44.auth.redirectToLogin();
    } finally {
      setLoading(false);
    }
  };

  const loadGenerations = async () => {
    try {
      const user = await base44.auth.me();
      const gens = await base44.entities.Generation.filter({
        user_id: user.id,
        status: 'completed'
      }, '-created_date');
      setGenerations(gens);
    } catch (error) {
      console.error('Failed to load generations:', error);
    }
  };

  const handleUpgrade = async (generationId) => {
    try {
      const { data } = await base44.functions.invoke('createUpgradeCheckout', {
        generation_id: generationId
      });

      if (data.url) {
        window.location.href = data.url;
      }
    } catch (error) {
      console.error('Upgrade error:', error);
      toast.error('Failed to create upgrade checkout. Please try again.');
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#7700CC]"></div>
      </div>
    );
  }

  const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  };

  const getPlanBadgeColor = (plan) => {
    switch (plan) {
      case 'pro': return 'bg-[#7700CC] text-white';
      case 'basic': return 'bg-[#F4743B] text-white';
      case 'enterprise': return 'bg-[#47007A] text-white';
      default: return 'bg-gray-200 text-[#180029]';
    }
  };

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        {/* Welcome Section - Minimalist */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-20"
        >
          <h1 className="heading text-5xl md:text-6xl font-bold text-[#180029] mb-2 tracking-tight">
            Welcome back
          </h1>
        </motion.div>

        {/* Stats - Minimal Cards */}
        <div className="grid md:grid-cols-3 gap-4 mb-16">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.1 }}
          >
            <div className="bg-[#F5F5F7] rounded-2xl p-6 hover:bg-[#ECECEF] transition-colors">
              <p className="text-sm text-[#180029]/50 mb-2">Credits</p>
              <p className="heading text-4xl font-semibold text-[#180029]">
                {user?.is_super_admin ? '∞' : (user?.credits_remaining || 0)}
              </p>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.15 }}
          >
            <div className="bg-[#F5F5F7] rounded-2xl p-6 hover:bg-[#ECECEF] transition-colors">
              <p className="text-sm text-[#180029]/50 mb-2">Package</p>
              <p className="heading text-2xl font-semibold text-[#180029] capitalize">
                {user?.plan_type || 'None'}
              </p>
            </div>
          </motion.div>

          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
          >
            <div className="bg-[#F5F5F7] rounded-2xl p-6 hover:bg-[#ECECEF] transition-colors">
              <p className="text-sm text-[#180029]/50 mb-2">Member since</p>
              <p className="heading text-xl font-semibold text-[#180029]">
                {formatDate(user?.created_date)}
              </p>
            </div>
          </motion.div>
        </div>

        {/* Primary Action - Apple Style */}
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.25 }}
          className="mb-20"
        >
          <Link to="/new-generation">
            <div className="bg-gradient-to-br from-[#7700CC] to-[#47007A] rounded-3xl p-12 text-white text-center hover:scale-[1.02] transition-transform cursor-pointer">
              <h2 className="heading text-3xl md:text-4xl font-bold mb-3">
                Create New Package
              </h2>
              <p className="text-white/80 text-lg mb-6">
                Generate AI-optimized files for your website
              </p>
              <div className="inline-flex items-center gap-2 text-white/90">
                <span className="text-lg">Get started</span>
                <ArrowRight className="w-5 h-5" />
              </div>
            </div>
          </Link>
        </motion.div>

        {/* Generations History */}
        {generations.length > 0 ? (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
          >
            <h2 className="heading text-2xl font-semibold text-[#180029] mb-6">
              Your Packages
            </h2>
            <div className="space-y-3">
              {generations.map((gen, index) => {
                const daysRemaining = gen.expires_at 
                  ? Math.ceil((new Date(gen.expires_at) - new Date()) / (1000 * 60 * 60 * 24))
                  : 0;
                const isExpired = daysRemaining <= 0;

                return (
                  <motion.div
                    key={gen.id}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: 0.3 + (index * 0.05) }}
                  >
                    <div className="bg-[#F5F5F7] rounded-2xl p-6 hover:bg-[#ECECEF] transition-colors">
                      <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div className="flex-grow">
                          <div className="flex items-center gap-3 mb-2">
                            <h3 className="heading text-xl font-semibold text-[#180029]">
                              {gen.website_url}
                            </h3>
                            <span className={`px-3 py-1 rounded-full text-xs font-medium ${gen.package_tier === 'complete' ? 'bg-[#7700CC] text-white' : 'bg-[#180029] text-white'}`}>
                              {gen.package_tier === 'complete' ? 'Complete' : 'Basic'}
                            </span>
                          </div>
                          <div className="flex flex-wrap gap-4 text-sm text-[#180029]/60">
                            <span>{formatDate(gen.created_date)}</span>
                            {gen.expires_at && !isExpired && (
                              <span>• {daysRemaining} days left</span>
                            )}
                            {isExpired && (
                              <span className="text-red-600">• Expired</span>
                            )}
                          </div>
                        </div>

                        <div className="flex flex-col sm:flex-row gap-2">
                          {!isExpired && gen.zip_url && (
                            <Button
                              onClick={() => window.open(gen.zip_url, '_blank')}
                              className="bg-[#180029] hover:bg-[#180029]/90 text-white rounded-xl"
                            >
                              Download
                            </Button>
                          )}
                          {!isExpired && gen.package_tier === 'basic' && !gen.upgraded_at && (
                            <Button
                              onClick={() => handleUpgrade(gen.id)}
                              variant="outline"
                              className="rounded-xl border-[#180029]/20"
                            >
                              Upgrade · $50
                            </Button>
                          )}
                        </div>
                      </div>
                    </div>
                  </motion.div>
                );
              })}
            </div>
          </motion.div>
        ) : (
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="text-center py-20"
          >
            <div className="w-20 h-20 bg-[#F5F5F7] rounded-full flex items-center justify-center mx-auto mb-6">
              <FileText className="w-10 h-10 text-[#180029]/30" />
            </div>
            <h2 className="heading text-2xl font-semibold text-[#180029] mb-3">
              No packages yet
            </h2>
            <p className="text-[#180029]/60 mb-8 max-w-md mx-auto">
              Create your first AEO package to optimize your website for AI discovery
            </p>
            <Link to="/new-generation">
              <Button className="bg-[#180029] hover:bg-[#180029]/90 text-white rounded-xl h-12 px-8">
                Create Your First Package
              </Button>
            </Link>
          </motion.div>
        )}
      </div>
    </div>
  );
}
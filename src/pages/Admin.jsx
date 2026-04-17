import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Users, CreditCard, Loader2, Search, Plus, FileText, X, Download, ExternalLink, Tag, Trash2, ToggleLeft, ToggleRight } from 'lucide-react';
import { motion } from 'framer-motion';

export default function Admin() {
  const [user, setUser] = useState(null);
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedUser, setSelectedUser] = useState(null);
  const [creditsToAdd, setCreditsToAdd] = useState('');
  const [processing, setProcessing] = useState(false);
  const [userGenerations, setUserGenerations] = useState({});
  const [viewingUser, setViewingUser] = useState(null);
  const [coupons, setCoupons] = useState([]);
  const [newCouponCode, setNewCouponCode] = useState('');
  const [newCouponCredits, setNewCouponCredits] = useState('');
  const [addingCoupon, setAddingCoupon] = useState(false);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const currentUser = await base44.auth.me();
      
      if (currentUser.role !== 'admin') {
        window.location.href = '/dashboard';
        return;
      }

      setUser(currentUser);
      await loadUsers();
      await loadCoupons();
      setLoading(false);
    } catch (error) {
      console.error('Failed to load:', error);
      base44.auth.redirectToLogin();
    }
  };

  const loadUsers = async () => {
    try {
      const allUsers = await base44.entities.User.list('-created_date', 100);
      setUsers(allUsers);
    } catch (error) {
      console.error('Failed to load users:', error);
    }
  };

  const loadCoupons = async () => {
    try {
      const allCoupons = await base44.entities.Coupon.list('-created_date', 50);
      setCoupons(allCoupons);
    } catch (error) {
      console.error('Failed to load coupons:', error);
    }
  };

  const handleAddCoupon = async () => {
    if (!newCouponCode.trim() || !newCouponCredits) return;

    setAddingCoupon(true);
    try {
      await base44.entities.Coupon.create({
        code: newCouponCode.trim().toUpperCase(),
        credits: parseInt(newCouponCredits),
        is_active: true
      });
      
      setNewCouponCode('');
      setNewCouponCredits('');
      await loadCoupons();
    } catch (error) {
      console.error('Failed to add coupon:', error);
    } finally {
      setAddingCoupon(false);
    }
  };

  const handleToggleCoupon = async (coupon) => {
    try {
      await base44.entities.Coupon.update(coupon.id, {
        is_active: !coupon.is_active
      });
      await loadCoupons();
    } catch (error) {
      console.error('Failed to toggle coupon:', error);
    }
  };

  const handleDeleteCoupon = async (couponId) => {
    if (!confirm('Are you sure you want to delete this coupon?')) return;
    
    try {
      await base44.entities.Coupon.delete(couponId);
      await loadCoupons();
    } catch (error) {
      console.error('Failed to delete coupon:', error);
    }
  };

  const loadUserGenerations = async (userId) => {
    try {
      const generations = await base44.entities.Generation.filter(
        { user_id: userId },
        '-created_date',
        50
      );
      setUserGenerations(prev => ({ ...prev, [userId]: generations }));
    } catch (error) {
      console.error('Failed to load generations:', error);
    }
  };

  const handleViewGenerations = async (user) => {
    setViewingUser(user);
    if (!userGenerations[user.id]) {
      await loadUserGenerations(user.id);
    }
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  };

  const getStatusColor = (status) => {
    const colors = {
      'completed': 'bg-green-100 text-green-700',
      'generating': 'bg-blue-100 text-blue-700',
      'failed': 'bg-red-100 text-red-700',
      'questionnaire_incomplete': 'bg-gray-100 text-gray-700'
    };
    return colors[status] || 'bg-gray-100 text-gray-700';
  };

  const handleAddCredits = async () => {
    if (!selectedUser || !creditsToAdd) return;

    setProcessing(true);
    try {
      const { data } = await base44.functions.invoke('addCredits', {
        user_id: selectedUser.id,
        credits: parseInt(creditsToAdd)
      });

      if (data.success) {
        await loadUsers();
        setSelectedUser(null);
        setCreditsToAdd('');
        alert(`Successfully added ${creditsToAdd} credits to ${selectedUser.email}`);
      }
    } catch (error) {
      console.error('Failed to add credits:', error);
      alert('Failed to add credits. Please try again.');
    } finally {
      setProcessing(false);
    }
  };

  const filteredUsers = users.filter(u => 
    u.email.toLowerCase().includes(searchQuery.toLowerCase()) ||
    u.full_name?.toLowerCase().includes(searchQuery.toLowerCase())
  );

  if (loading) {
    return (
      <div className="min-h-screen bg-white flex items-center justify-center">
        <Loader2 className="w-12 h-12 text-[#7700CC] animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-white">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <h1 className="heading text-5xl font-bold text-[#180029] mb-2 tracking-tight">
            Admin Dashboard
          </h1>
          <p className="text-[#180029]/60 text-lg mb-12">
            Manage users, credits, and coupon codes
          </p>
        </motion.div>

        {/* Stats */}
        <div className="grid md:grid-cols-3 gap-4 mb-12">
          <div className="bg-[#F5F5F7] rounded-2xl p-6">
            <p className="text-sm text-[#180029]/50 mb-2">Total Users</p>
            <p className="heading text-4xl font-semibold text-[#180029]">
              {users.length}
            </p>
          </div>
          <div className="bg-[#F5F5F7] rounded-2xl p-6">
            <p className="text-sm text-[#180029]/50 mb-2">Active Plans</p>
            <p className="heading text-4xl font-semibold text-[#180029]">
              {users.filter(u => u.plan_type).length}
            </p>
          </div>
          <div className="bg-[#F5F5F7] rounded-2xl p-6">
            <p className="text-sm text-[#180029]/50 mb-2">Total Credits</p>
            <p className="heading text-4xl font-semibold text-[#180029]">
              {users.reduce((sum, u) => sum + (u.credits_remaining || 0), 0)}
            </p>
          </div>
        </div>

        {/* Coupon Management Section */}
        <Card className="mb-12 border-2 border-gray-200 rounded-2xl">
          <CardHeader className="bg-[#F5F5F7]">
            <CardTitle className="heading text-xl text-[#180029] flex items-center gap-2">
              <Tag className="w-5 h-5" />
              Coupon Codes
            </CardTitle>
          </CardHeader>
          <CardContent className="p-6">
            {/* Add New Coupon */}
            <div className="bg-[#F5F5F7] rounded-xl p-4 mb-6">
              <h3 className="subheading text-sm text-[#180029] mb-4">Add New Coupon</h3>
              <div className="grid md:grid-cols-3 gap-4">
                <div className="space-y-2">
                  <Label className="text-sm text-[#180029]/70">Coupon Code</Label>
                  <Input
                    value={newCouponCode}
                    onChange={(e) => setNewCouponCode(e.target.value.toUpperCase())}
                    placeholder="e.g., WELCOME3"
                    className="h-12 rounded-xl"
                  />
                </div>
                <div className="space-y-2">
                  <Label className="text-sm text-[#180029]/70">Credits</Label>
                  <Input
                    type="number"
                    min="1"
                    value={newCouponCredits}
                    onChange={(e) => setNewCouponCredits(e.target.value)}
                    placeholder="Number of credits"
                    className="h-12 rounded-xl"
                  />
                </div>
                <div className="space-y-2">
                  <Label className="text-sm text-[#180029]/70">&nbsp;</Label>
                  <Button
                    onClick={handleAddCoupon}
                    disabled={addingCoupon || !newCouponCode.trim() || !newCouponCredits}
                    className="w-full h-12 bg-[#7700CC] hover:bg-[#47017a] text-white rounded-xl"
                  >
                    {addingCoupon ? (
                      <>
                        <Loader2 className="w-4 h-4 animate-spin mr-2" />
                        Adding...
                      </>
                    ) : (
                      <>
                        <Plus className="w-4 h-4 mr-2" />
                        Add Coupon
                      </>
                    )}
                  </Button>
                </div>
              </div>
            </div>

            {/* Coupons List */}
            <div className="space-y-3">
              {coupons.length === 0 ? (
                <p className="text-center text-[#180029]/50 py-8">No coupons created yet</p>
              ) : (
                coupons.map((coupon) => (
                  <div
                    key={coupon.id}
                    className="flex items-center justify-between p-4 border border-gray-200 rounded-xl hover:bg-[#F5F5F7] transition-colors"
                  >
                    <div className="flex items-center gap-4 flex-1">
                      <div className="bg-[#7700CC]/10 text-[#7700CC] px-4 py-2 rounded-lg font-mono font-bold">
                        {coupon.code}
                      </div>
                      <div className="flex flex-col">
                        <span className="text-sm font-semibold text-[#180029]">
                          {coupon.credits} {coupon.credits === 1 ? 'Credit' : 'Credits'}
                        </span>
                        <span className="text-xs text-[#180029]/50">
                          Used {coupon.uses_count || 0} times
                        </span>
                      </div>
                      {coupon.is_active ? (
                        <span className="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">
                          Active
                        </span>
                      ) : (
                        <span className="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded-full">
                          Inactive
                        </span>
                      )}
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleToggleCoupon(coupon)}
                        className="text-[#180029]/70 hover:text-[#180029]"
                      >
                        {coupon.is_active ? (
                          <ToggleRight className="w-5 h-5" />
                        ) : (
                          <ToggleLeft className="w-5 h-5" />
                        )}
                      </Button>
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => handleDeleteCoupon(coupon.id)}
                        className="text-red-500 hover:text-red-700"
                      >
                        <Trash2 className="w-4 h-4" />
                      </Button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>

        {/* Add Credits Section */}
        <Card className="mb-12 border-2 border-gray-200 rounded-2xl">
          <CardHeader className="bg-[#F5F5F7]">
            <CardTitle className="heading text-xl text-[#180029]">Add Credits</CardTitle>
          </CardHeader>
          <CardContent className="p-6">
            <div className="grid md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label className="text-sm text-[#180029]/70">Select User</Label>
                <select
                  value={selectedUser?.id || ''}
                  onChange={(e) => {
                    const user = users.find(u => u.id === e.target.value);
                    setSelectedUser(user);
                  }}
                  className="w-full h-12 px-4 border border-gray-200 rounded-xl focus:outline-none focus:border-[#7700CC]"
                >
                  <option value="">Choose a user...</option>
                  {users.map(u => (
                    <option key={u.id} value={u.id}>
                      {u.email} ({u.credits_remaining || 0} credits)
                    </option>
                  ))}
                </select>
              </div>
              <div className="space-y-2">
                <Label className="text-sm text-[#180029]/70">Credits to Add</Label>
                <Input
                  type="number"
                  min="1"
                  value={creditsToAdd}
                  onChange={(e) => setCreditsToAdd(e.target.value)}
                  placeholder="Enter amount"
                  className="h-12 rounded-xl"
                />
              </div>
            </div>
            <Button
              onClick={handleAddCredits}
              disabled={!selectedUser || !creditsToAdd || processing}
              className="mt-4 bg-[#7700CC] hover:bg-[#47007A] text-white rounded-xl h-12"
            >
              {processing ? (
                <>
                  <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                  Adding...
                </>
              ) : (
                <>
                  <Plus className="w-4 h-4 mr-2" />
                  Add Credits
                </>
              )}
            </Button>
          </CardContent>
        </Card>

        {/* Users List */}
        <div>
          <div className="flex justify-between items-center mb-6">
            <h2 className="heading text-2xl font-semibold text-[#180029]">All Users</h2>
            <div className="relative w-64">
              <Search className="absolute left-3 top-3 w-5 h-5 text-[#180029]/40" />
              <Input
                type="text"
                placeholder="Search users..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="pl-10 h-11 rounded-xl border-gray-200"
              />
            </div>
          </div>

          <div className="space-y-2">
            {filteredUsers.map((u, index) => (
              <motion.div
                key={u.id}
                initial={{ opacity: 0, y: 10 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: index * 0.03 }}
                className="bg-[#F5F5F7] rounded-xl p-4 hover:bg-[#ECECEF] transition-colors"
              >
                <div className="flex items-center justify-between">
                  <div className="flex-grow">
                    <div className="flex items-center gap-3">
                      <p className="font-medium text-[#180029]">{u.email}</p>
                      {u.is_super_admin && (
                        <span className="px-2 py-1 bg-[#7700CC] text-white rounded text-xs">
                          SUPER ADMIN
                        </span>
                      )}
                      {u.role === 'admin' && !u.is_super_admin && (
                        <span className="px-2 py-1 bg-[#180029] text-white rounded text-xs">
                          ADMIN
                        </span>
                      )}
                    </div>
                    <div className="flex gap-4 text-sm text-[#180029]/60 mt-1">
                      <span>Credits: {u.credits_remaining || 0}</span>
                      {u.plan_type && (
                        <span>• Plan: {u.plan_type}</span>
                      )}
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      onClick={() => handleViewGenerations(u)}
                      variant="outline"
                      size="sm"
                      className="rounded-lg"
                    >
                      <FileText className="w-4 h-4 mr-1" />
                      Reports
                    </Button>
                    <Button
                      onClick={() => {
                        setSelectedUser(u);
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                      }}
                      variant="outline"
                      size="sm"
                      className="rounded-lg"
                    >
                      Add Credits
                    </Button>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        </div>

        {/* User Generations Modal */}
        {viewingUser && (
          <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <motion.div
              initial={{ opacity: 0, scale: 0.95 }}
              animate={{ opacity: 1, scale: 1 }}
              className="bg-white rounded-2xl max-w-5xl w-full max-h-[80vh] overflow-hidden shadow-2xl"
            >
              <div className="flex justify-between items-center p-6 border-b">
                <div>
                  <h3 className="heading text-2xl font-semibold text-[#180029]">
                    Generations for {viewingUser.email}
                  </h3>
                  <p className="text-sm text-[#180029]/60 mt-1">
                    {userGenerations[viewingUser.id]?.length || 0} total generations
                  </p>
                </div>
                <Button
                  onClick={() => setViewingUser(null)}
                  variant="ghost"
                  size="icon"
                  className="rounded-full"
                >
                  <X className="w-5 h-5" />
                </Button>
              </div>
              <div className="p-6 overflow-y-auto max-h-[calc(80vh-120px)]">
                {!userGenerations[viewingUser.id] ? (
                  <div className="flex justify-center py-12">
                    <Loader2 className="w-8 h-8 text-[#7700CC] animate-spin" />
                  </div>
                ) : userGenerations[viewingUser.id].length === 0 ? (
                  <div className="text-center py-12 text-[#180029]/60">
                    No generations found for this user
                  </div>
                ) : (
                  <div className="space-y-3">
                    {userGenerations[viewingUser.id].map((gen) => (
                      <div
                        key={gen.id}
                        className="bg-[#F5F5F7] rounded-xl p-5 hover:bg-[#ECECEF] transition-colors"
                      >
                        <div className="flex items-start justify-between gap-4">
                          <div className="flex-grow">
                            <div className="flex items-center gap-3 mb-2">
                              <h4 className="font-semibold text-[#180029]">
                                {gen.business_name || 'Unnamed Business'}
                              </h4>
                              <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(gen.status)}`}>
                                {gen.status.replace('_', ' ')}
                              </span>
                              <span className="px-2 py-1 bg-[#7700CC]/10 text-[#7700CC] rounded-full text-xs font-medium">
                                {gen.package_tier}
                              </span>
                            </div>
                            <div className="space-y-1 text-sm text-[#180029]/70">
                              <p className="flex items-center gap-2">
                                <ExternalLink className="w-4 h-4" />
                                {gen.website_url}
                              </p>
                              <p>Created: {formatDate(gen.created_date)}</p>
                              {gen.completed_at && (
                                <p>Completed: {formatDate(gen.completed_at)}</p>
                              )}
                              {gen.download_count > 0 && (
                                <p>Downloads: {gen.download_count}</p>
                              )}
                            </div>
                          </div>
                          {gen.status === 'completed' && gen.zip_url && (
                            <a
                              href={gen.zip_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="flex items-center gap-2 px-4 py-2 bg-[#7700CC] text-white rounded-lg hover:bg-[#47007A] transition-colors"
                            >
                              <Download className="w-4 h-4" />
                              Download
                            </a>
                          )}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </motion.div>
          </div>
        )}
      </div>
    </div>
  );
}
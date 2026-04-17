import React, { useEffect, useState } from 'react';
import { base44 } from '@/api/base44Client';
import { LogOut, User, Menu, X } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function Layout({ children, currentPageName }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  useEffect(() => {
    loadUser();
  }, []);

  const loadUser = async () => {
    try {
      const currentUser = await base44.auth.me();
      setUser(currentUser);
    } catch (error) {
      // User not logged in
      if (currentPageName !== 'FileGenerator') {
        base44.auth.redirectToLogin();
      }
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = () => {
    base44.auth.logout();
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-[#7700CC]"></div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#EEF0F2]">
      {/* Header */}
      <header className="border-b border-[#47017a]/20" style={{backgroundColor: '#47017a'}}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-20">
            {/* Logo */}
            <a href="/new-generation" className="flex items-center gap-4">
              <img 
                src="https://media.base44.com/images/public/697ffaa18d262aea02e19ed6/981260bf9_AEOFileGenerator2.jpg" 
                alt="AEO LLM Search Files Generator"
                className="h-12 object-contain"
              />
            </a>

            {/* Desktop Navigation */}
            {user && currentPageName !== 'Admin' && (
              <div className="hidden md:flex items-center gap-6">
                <a
                  href="/Dashboard"
                  className="text-white/90 hover:text-white transition-colors subheading text-sm"
                >
                  Dashboard
                </a>
                <a
                  href="/MyGenerations"
                  className="text-white/90 hover:text-white transition-colors subheading text-sm"
                >
                  My Generations
                </a>

                <div className="flex items-center gap-3 text-white/90">
                  <User className="w-5 h-5" />
                  <span className="subheading text-sm">{user.full_name || user.email}</span>
                </div>
                <Button
                  onClick={handleLogout}
                  variant="ghost"
                  className="text-white hover:bg-white/10 gap-2"
                >
                  <LogOut className="w-4 h-4" />
                  Logout
                </Button>
              </div>
            )}

            {/* Mobile menu button */}
            {user && currentPageName !== 'Admin' && (
              <button
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className="md:hidden text-white p-2"
              >
                {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
              </button>
            )}
          </div>

          {/* Mobile Navigation */}
          {user && mobileMenuOpen && (
            <div className="md:hidden pb-4 border-t border-white/10 mt-2 pt-4">
              <div className="flex flex-col gap-3">
                <a
                  href="/Dashboard"
                  className="text-white/90 hover:text-white px-2 py-2"
                >
                  Dashboard
                </a>
                <a
                  href="/MyGenerations"
                  className="text-white/90 hover:text-white px-2 py-2"
                >
                  My Generations
                </a>

                <div className="flex items-center gap-3 text-white/90 px-2 pt-2 border-t border-white/10">
                  <User className="w-5 h-5" />
                  <span className="subheading text-sm">{user.full_name || user.email}</span>
                </div>
                <Button
                  onClick={handleLogout}
                  variant="ghost"
                  className="text-white hover:bg-white/10 gap-2 justify-start"
                >
                  <LogOut className="w-4 h-4" />
                  Logout
                </Button>
              </div>
            </div>
          )}
        </div>
      </header>

      {/* Main Content */}
      <main>
        {children}
      </main>

      {/* Footer */}
      <footer className="bg-[#180029] text-white/80 py-12 mt-16">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
            {/* Quick Links */}
            <div>
              <h3 className="heading text-white text-lg font-semibold mb-4">Quick Links</h3>
              <ul className="space-y-2">
                <li>
                  <a href="/dashboard" className="hover:text-white transition-colors">Dashboard</a>
                </li>
                <li>
                  <a href="/new-generation" className="hover:text-white transition-colors">New Generation</a>
                </li>

              </ul>
            </div>

            {/* Resources */}
            <div>
              <h3 className="heading text-white text-lg font-semibold mb-4">Resources</h3>
              <ul className="space-y-2">
                <li>
                  <a href="/file-generator" className="hover:text-white transition-colors">File Generator</a>
                </li>
                {user?.role === 'admin' && (
                  <li>
                    <a href="/admin" className="hover:text-white transition-colors">Admin</a>
                  </li>
                )}
              </ul>
            </div>

            {/* Company */}
            <div>
              <h3 className="heading text-white text-lg font-semibold mb-4">Company</h3>
              <p className="text-sm">
                AEO Search File Generator<br />
                AI-Optimized SEO Files
              </p>
            </div>
          </div>

          <div className="border-t border-white/20 pt-6 text-center">
            <p className="text-sm">© 2026 AEO Search File Generator. All rights reserved.</p>
          </div>
        </div>
      </footer>
    </div>
  );
}
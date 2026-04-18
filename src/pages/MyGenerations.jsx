import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Loader2, Download, ExternalLink, Plus } from 'lucide-react';
import { motion } from 'framer-motion';
import { useNavigate } from 'react-router-dom';

export default function MyGenerations() {
  const [user, setUser] = useState(null);
  const [generations, setGenerations] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const currentUser = await base44.auth.me();
      setUser(currentUser);

      const userGens = await base44.entities.Generation.filter(
        { user_id: currentUser.id },
        '-created_date',
        50
      );
      setGenerations(userGens);
      setLoading(false);
    } catch (error) {
      console.error('Failed to load:', error);
      base44.auth.redirectToLogin();
    }
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
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

  const handleDownload = async (generation) => {
    try {
      await base44.entities.Generation.update(generation.id, {
        downloaded_at: new Date().toISOString(),
        download_count: (generation.download_count || 0) + 1
      });
      window.open(generation.zip_url, '_blank');
      loadData();
    } catch (error) {
      console.error('Download tracking failed:', error);
      window.open(generation.zip_url, '_blank');
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <Loader2 className="w-12 h-12 text-[#7700CC] animate-spin" />
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#EEF0F2]">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <div className="flex justify-between items-start mb-12">
            <div>
              <h1 className="heading text-5xl font-bold text-[#180029] mb-2 tracking-tight">
                My Generations
              </h1>
              <p className="text-[#180029]/60 text-lg">
                View and download your AEO packages
              </p>
            </div>
            <Button
              onClick={() => navigate('/new-generation')}
              className="bg-[#7700CC] hover:bg-[#47007A] text-white gap-2"
            >
              <Plus className="w-4 h-4" />
              New Generation
            </Button>
          </div>

          {generations.length === 0 ? (
            <Card className="p-12 text-center border-2 border-gray-200">
              <p className="text-[#180029]/60 text-lg mb-6">
                You haven't created any generations yet
              </p>
              <Button
                onClick={() => navigate('/new-generation')}
                className="bg-[#7700CC] hover:bg-[#47007A] text-white gap-2"
              >
                <Plus className="w-4 h-4" />
                Create Your First Generation
              </Button>
            </Card>
          ) : (
            <div className="space-y-4">
              {generations.map((gen, index) => (
                <motion.div
                  key={gen.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: index * 0.05 }}
                >
                  <Card className="p-6 border-2 border-gray-200 hover:border-[#7700CC]/30 transition-colors">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex-grow">
                        <div className="flex items-center gap-3 mb-3">
                          <h3 className="heading text-xl font-semibold text-[#180029]">
                            {gen.business_name || 'Unnamed Business'}
                          </h3>
                          <span className={`px-3 py-1 rounded-full text-xs font-medium ${getStatusColor(gen.status)}`}>
                            {gen.status.replace('_', ' ')}
                          </span>
                          <span className="px-3 py-1 bg-[#7700CC]/10 text-[#7700CC] rounded-full text-xs font-medium uppercase">
                            {gen.package_tier}
                          </span>
                        </div>
                        
                        <div className="space-y-2 text-sm text-[#180029]/70">
                          <p className="flex items-center gap-2">
                            <ExternalLink className="w-4 h-4" />
                            <a 
                              href={gen.website_url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className="hover:text-[#7700CC] transition-colors"
                            >
                              {gen.website_url}
                            </a>
                          </p>
                          <p>Created: {formatDate(gen.created_date)}</p>
                          {gen.status === 'completed' && gen.expires_at && (
                            <p>
                              Expires: {formatDate(gen.expires_at)}
                            </p>
                          )}
                          {gen.download_count > 0 && (
                            <p>Downloaded: {gen.download_count} time{gen.download_count !== 1 ? 's' : ''}</p>
                          )}
                        </div>
                      </div>
                      
                      <div className="flex flex-col gap-2">
                        {gen.status === 'completed' && gen.zip_url && (
                          <Button
                            onClick={() => handleDownload(gen)}
                            className="bg-[#7700CC] hover:bg-[#47007A] text-white gap-2"
                          >
                            <Download className="w-4 h-4" />
                            Download
                          </Button>
                        )}
                        {gen.status === 'generating' && (
                          <Button
                            onClick={() => navigate(`/GenerationProgress?generation_id=${gen.id}`)}
                            variant="outline"
                          >
                            View Progress
                          </Button>
                        )}
                        {gen.status === 'questionnaire_incomplete' && (
                          <Button
                            onClick={() => navigate(`/questionnaire?analysis_id=${gen.analysis_id}`)}
                            variant="outline"
                          >
                            Continue
                          </Button>
                        )}
                        {gen.status === 'failed' && (
                          <Button
                            onClick={() => navigate(`/review?generation_id=${gen.id}`)}
                            variant="outline"
                          >
                            Try Again
                          </Button>
                        )}
                        {gen.status === 'completed' && gen.package_tier === 'basic' && (
                          <Button
                            onClick={() => navigate(`/dashboard`)}
                            variant="outline"
                            size="sm"
                          >
                            Upgrade
                          </Button>
                        )}
                      </div>
                    </div>
                  </Card>
                </motion.div>
              ))}
            </div>
          )}
        </motion.div>
      </div>
    </div>
  );
}
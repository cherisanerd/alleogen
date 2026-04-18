import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { CheckCircle2, Circle, Loader2, XCircle, Download, ArrowLeft } from 'lucide-react';
import { motion } from 'framer-motion';
import { useSearchParams, useNavigate } from 'react-router-dom';

export default function GenerationProgress() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [generation, setGeneration] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadGeneration();
    const interval = setInterval(loadGeneration, 2000); // Poll every 2 seconds
    return () => clearInterval(interval);
  }, []);

  const loadGeneration = async () => {
    const generationId = searchParams.get('generation_id');
    
    if (!generationId) {
      setError('Missing generation ID');
      setLoading(false);
      return;
    }

    try {
      const user = await base44.auth.me();
      const gen = await base44.entities.Generation.get(generationId);
      
      if (gen.user_id !== user.id) {
        setError('Unauthorized access');
        setLoading(false);
        return;
      }

      setGeneration(gen);
      setLoading(false);
    } catch (err) {
      console.error('Failed to load generation:', err);
      setError('Failed to load generation status');
      setLoading(false);
    }
  };

  const handleDownload = () => {
    if (generation?.zip_url) {
      window.open(generation.zip_url, '_blank');
      
      // Update download stats
      base44.entities.Generation.update(generation.id, {
        downloaded_at: new Date().toISOString(),
        download_count: (generation.download_count || 0) + 1
      });
    }
  };

  // Determine which steps are complete based on status
  const getSteps = () => {
    const status = generation?.status || 'questionnaire_incomplete';
    
    const steps = [
      {
        name: 'Preparing Generation',
        key: 'preparing',
        status: 'complete'
      },
      {
        name: 'Generating Files',
        key: 'generating_files',
        status: status === 'generating' ? 'in_progress' : status === 'completed' ? 'complete' : status === 'failed' ? 'error' : 'pending'
      },
      {
        name: 'Creating ZIP Package',
        key: 'creating_zip',
        status: status === 'generating' ? 'pending' : status === 'completed' ? 'complete' : status === 'failed' ? 'error' : 'pending'
      },
      {
        name: 'Upload Complete',
        key: 'upload',
        status: status === 'completed' ? 'complete' : status === 'failed' ? 'error' : 'pending'
      },
      {
        name: 'Ready to Download',
        key: 'ready',
        status: status === 'completed' ? 'complete' : status === 'failed' ? 'error' : 'pending'
      }
    ];

    return steps;
  };

  const getStatusIcon = (status) => {
    switch (status) {
      case 'complete':
        return <CheckCircle2 className="w-6 h-6 text-green-600" />;
      case 'in_progress':
        return <Loader2 className="w-6 h-6 text-[#7700CC] animate-spin" />;
      case 'error':
        return <XCircle className="w-6 h-6 text-red-600" />;
      default:
        return <Circle className="w-6 h-6 text-gray-300" />;
    }
  };

  const getStatusColor = (status) => {
    switch (status) {
      case 'complete':
        return 'text-green-600';
      case 'in_progress':
        return 'text-[#7700CC]';
      case 'error':
        return 'text-red-600';
      default:
        return 'text-gray-400';
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <Loader2 className="w-12 h-12 text-[#7700CC] animate-spin" />
      </div>
    );
  }

  if (error) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] py-16 px-4">
        <div className="max-w-2xl mx-auto text-center">
          <XCircle className="w-16 h-16 text-red-600 mx-auto mb-4" />
          <h1 className="heading text-2xl font-bold text-[#180029] mb-4">Error</h1>
          <p className="text-[#180029]/70 mb-6">{error}</p>
          <Button onClick={() => navigate('/dashboard')} className="bg-[#7700CC] hover:bg-[#47007A]">
            <ArrowLeft className="w-4 h-4 mr-2" />
            Back to Dashboard
          </Button>
        </div>
      </div>
    );
  }

  const steps = getSteps();
  const isComplete = generation?.status === 'completed';
  const hasFailed = generation?.status === 'failed';

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4 sm:px-6 lg:px-8">
      <div className="max-w-3xl mx-auto">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <div className="text-center mb-12">
            <h1 className="heading text-4xl font-bold text-[#180029] mb-4">
              {isComplete ? 'Package Ready!' : hasFailed ? 'Generation Failed' : 'Generating Your Package'}
            </h1>
            <p className="text-lg text-[#180029]/70">
              {isComplete 
                ? 'Your AEO package has been successfully generated' 
                : hasFailed 
                ? 'There was an error generating your package' 
                : 'Please wait while we create your files...'}
            </p>
          </div>

          <Card className="border-2 border-gray-200 shadow-lg mb-8">
            <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5">
              <CardTitle className="heading text-xl text-[#180029]">
                Generation Progress
              </CardTitle>
            </CardHeader>
            <CardContent className="p-8">
              <div className="space-y-6">
                {steps.map((step, index) => (
                  <motion.div
                    key={step.key}
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: index * 0.1 }}
                    className="flex items-center gap-4"
                  >
                    <div className="flex-shrink-0">
                      {getStatusIcon(step.status)}
                    </div>
                    <div className="flex-1">
                      <p className={`text-lg font-medium ${getStatusColor(step.status)}`}>
                        {step.name}
                      </p>
                    </div>
                  </motion.div>
                ))}
              </div>

              {hasFailed && (
                <div className="mt-8 p-4 bg-red-50 border border-red-200 rounded-lg">
                  <p className="text-sm text-red-800">
                    An error occurred during generation. Please try again or contact support if the issue persists.
                  </p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Action Buttons */}
          <div className="flex justify-between items-center">
            <Button
              onClick={() => navigate('/dashboard')}
              variant="outline"
              className="gap-2"
            >
              <ArrowLeft className="w-4 h-4" />
              Back to Dashboard
            </Button>

            {isComplete && (
              <Button
                onClick={handleDownload}
                className="bg-[#7700CC] hover:bg-[#47007A] text-white gap-2 px-8"
              >
                <Download className="w-4 h-4" />
                Download Package
              </Button>
            )}

            {hasFailed && (
              <Button
                onClick={() => navigate(`/review?generation_id=${generation.id}`)}
                className="bg-[#F4743B] hover:bg-[#F4743B]/90 text-white gap-2"
              >
                Try Again
              </Button>
            )}
          </div>

          {/* Package Details */}
          {generation && (
            <Card className="mt-8 border border-gray-200">
              <CardContent className="p-6">
                <div className="grid md:grid-cols-3 gap-4 text-sm">
                  <div>
                    <p className="text-[#180029]/60 mb-1">Website</p>
                    <p className="text-[#180029] font-medium">{generation.website_url}</p>
                  </div>
                  <div>
                    <p className="text-[#180029]/60 mb-1">Package Type</p>
                    <p className="text-[#180029] font-medium capitalize">{generation.package_tier}</p>
                  </div>
                  <div>
                    <p className="text-[#180029]/60 mb-1">Business</p>
                    <p className="text-[#180029] font-medium">{generation.business_name}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )}
        </motion.div>
      </div>
    </div>
  );
}
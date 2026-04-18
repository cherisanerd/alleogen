import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { ArrowLeft, Edit, Loader2, Sparkles, AlertCircle } from 'lucide-react';
import { motion } from 'framer-motion';
import { toast } from 'sonner';
import { useNavigate, useSearchParams } from 'react-router-dom';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

export default function Review() {
  const [searchParams] = useSearchParams();
  const [loading, setLoading] = useState(true);
  const [generating, setGenerating] = useState(false);
  const [generation, setGeneration] = useState(null);
  const [showConfirmDialog, setShowConfirmDialog] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    loadGeneration();
  }, []);

  const loadGeneration = async () => {
    const generationId = searchParams.get('generation_id');
    
    if (!generationId) {
      navigate('/new-generation');
      return;
    }

    try {
      const user = await base44.auth.me();
      const gen = await base44.entities.Generation.get(generationId);
      
      if (gen.user_id !== user.id) {
        navigate('/new-generation');
        return;
      }

      setGeneration(gen);
      setLoading(false);
    } catch (error) {
      console.error('Failed to load generation:', error);
      navigate('/new-generation');
    }
  };

  const handleGenerate = async () => {
    setGenerating(true);

    try {
      await base44.functions.invoke('generateFiles', {
        generation_id: generation.id
      });

      // Navigate immediately to progress page
      navigate(`/GenerationProgress?generation_id=${generation.id}`);
    } catch (error) {
      console.error('Generation error:', error);
      toast.error('Failed to start generation. Please try again.');
      setGenerating(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <Loader2 className="w-12 h-12 text-[#7700CC] animate-spin" />
      </div>
    );
  }

  const answers = generation?.questionnaire_data || {};

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-16 px-4 sm:px-6 lg:px-8">
      <div className="max-w-4xl mx-auto">
        <motion.div
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          <div className="text-center mb-12">
            <h1 className="heading text-4xl font-bold text-[#180029] mb-4">
              Review Your Information
            </h1>
            <p className="text-lg text-[#180029]/70">
              Please review before we generate your AEO package
            </p>
          </div>

          <div className="space-y-6">
            {/* Business Information */}
            <Card className="border-2 border-gray-200 shadow-lg">
              <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5">
                <div className="flex justify-between items-center">
                  <CardTitle className="heading text-xl text-[#180029]">
                    Business Information
                  </CardTitle>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/questionnaire?analysis_id=${generation.analysis_id}`)}
                    className="gap-2 text-[#7700CC] hover:text-[#47007A]"
                  >
                    <Edit className="w-4 h-4" />
                    Edit
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="p-6 grid md:grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-[#180029]/60 mb-1">Business Name</p>
                  <p className="text-[#180029] font-medium">{answers.businessName}</p>
                </div>
                <div>
                  <p className="text-xs text-[#180029]/60 mb-1">Industry</p>
                  <p className="text-[#180029] font-medium capitalize">{answers.industry}</p>
                </div>
                <div className="md:col-span-2">
                  <p className="text-xs text-[#180029]/60 mb-1">Products/Services</p>
                  <p className="text-[#180029]">{answers.productsServices}</p>
                </div>
                {answers.yearEstablished && (
                  <div>
                    <p className="text-xs text-[#180029]/60 mb-1">Year Established</p>
                    <p className="text-[#180029] font-medium">{answers.yearEstablished}</p>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Location & Contact */}
            <Card className="border-2 border-gray-200 shadow-lg">
              <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5">
                <div className="flex justify-between items-center">
                  <CardTitle className="heading text-xl text-[#180029]">
                    Location & Contact
                  </CardTitle>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/questionnaire?analysis_id=${generation.analysis_id}`)}
                    className="gap-2 text-[#7700CC] hover:text-[#47007A]"
                  >
                    <Edit className="w-4 h-4" />
                    Edit
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="p-6 grid md:grid-cols-2 gap-4">
                <div>
                  <p className="text-xs text-[#180029]/60 mb-1">Physical Location</p>
                  <p className="text-[#180029] font-medium">
                    {answers.hasPhysicalLocation === 'yes' ? 'Yes' : 'No'}
                  </p>
                </div>
                {answers.hasPhysicalLocation === 'yes' && answers.address && (
                  <div className="md:col-span-2">
                    <p className="text-xs text-[#180029]/60 mb-1">Address</p>
                    <p className="text-[#180029]">{answers.address}</p>
                  </div>
                )}
                <div>
                  <p className="text-xs text-[#180029]/60 mb-1">Contact Email</p>
                  <p className="text-[#180029]">{answers.contactEmail}</p>
                </div>
                {answers.socialLinks && (
                  <div className="md:col-span-2">
                    <p className="text-xs text-[#180029]/60 mb-1">Social Media</p>
                    <p className="text-[#180029] text-sm whitespace-pre-line">{answers.socialLinks}</p>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Search & Citations (Q10-Q13) */}
            {(answers.proofPoints || answers.topicOwnership || answers.competitorDiff || answers.knowledgePanel) && (
              <Card className="border-2 border-gray-200 shadow-lg">
                <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5">
                  <div className="flex justify-between items-center">
                    <CardTitle className="heading text-xl text-[#180029]">
                      Search &amp; Citations
                    </CardTitle>
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => navigate(`/questionnaire?analysis_id=${generation.analysis_id}`)}
                      className="gap-2 text-[#7700CC] hover:text-[#47007A]"
                    >
                      <Edit className="w-4 h-4" />
                      Edit
                    </Button>
                  </div>
                </CardHeader>
                <CardContent className="p-6 space-y-4">
                  {answers.proofPoints && (
                    <div>
                      <p className="text-xs text-[#180029]/60 mb-1">Proof points (Q10)</p>
                      <p className="text-[#180029] whitespace-pre-line">{answers.proofPoints}</p>
                    </div>
                  )}
                  {answers.topicOwnership && (
                    <div>
                      <p className="text-xs text-[#180029]/60 mb-1">Topic ownership (Q11)</p>
                      <p className="text-[#180029] whitespace-pre-line">{answers.topicOwnership}</p>
                    </div>
                  )}
                  {answers.competitorDiff && (
                    <div>
                      <p className="text-xs text-[#180029]/60 mb-1">Differentiation (Q12)</p>
                      <p className="text-[#180029] whitespace-pre-line">{answers.competitorDiff}</p>
                    </div>
                  )}
                  {answers.knowledgePanel && (
                    <div>
                      <p className="text-xs text-[#180029]/60 mb-1">Knowledge panel (Q13)</p>
                      <p className="text-[#180029] whitespace-pre-line">{answers.knowledgePanel}</p>
                    </div>
                  )}
                </CardContent>
              </Card>
            )}

            {/* Platform & Preferences */}
            <Card className="border-2 border-gray-200 shadow-lg">
              <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5">
                <div className="flex justify-between items-center">
                  <CardTitle className="heading text-xl text-[#180029]">
                    Platform & Preferences
                  </CardTitle>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => navigate(`/questionnaire?analysis_id=${generation.analysis_id}`)}
                    className="gap-2 text-[#7700CC] hover:text-[#47007A]"
                  >
                    <Edit className="w-4 h-4" />
                    Edit
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="p-6 space-y-4">
                <div>
                  <p className="text-xs text-[#180029]/60 mb-1">Platform</p>
                  <p className="text-[#180029] font-medium">{answers.platform}</p>
                </div>
                <div>
                  <p className="text-xs text-[#180029]/60 mb-2">AI Crawlers Allowed</p>
                  <div className="flex flex-wrap gap-2">
                    {Object.entries(answers.aiCrawlers || {}).filter(([k, v]) => v).map(([key]) => (
                      <span key={key} className="px-3 py-1 bg-[#7700CC]/10 text-[#7700CC] rounded-full text-sm capitalize">
                        {key === 'chatgpt' ? 'ChatGPT' : key === 'others' ? 'Others' : key}
                      </span>
                    ))}
                  </div>
                </div>
                <div>
                  <p className="text-xs text-[#180029]/60 mb-2">Content Focus</p>
                  <div className="flex flex-wrap gap-2">
                    {Object.entries(answers.contentFocus || {}).filter(([k, v]) => v).map(([key]) => (
                      <span key={key} className="px-3 py-1 bg-[#F4743B]/10 text-[#F4743B] rounded-full text-sm capitalize">
                        {key}
                      </span>
                    ))}
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Action Buttons */}
          <div className="flex justify-between mt-8">
            <Button
              onClick={() => navigate(`/Questionnaire?analysis_id=${generation.analysis_id}`)}
              variant="outline"
              className="gap-2"
            >
              <ArrowLeft className="w-4 h-4" />
              Back to Edit
            </Button>

            <Button
              onClick={() => setShowConfirmDialog(true)}
              disabled={generating}
              className="bg-[#7700CC] hover:bg-[#47007A] text-white gap-2 px-8"
            >
              {generating ? (
                <>
                  <Loader2 className="w-4 h-4 animate-spin" />
                  Generating...
                </>
              ) : (
                <>
                  <Sparkles className="w-4 h-4" />
                  Generate Package
                </>
              )}
            </Button>
          </div>
        </motion.div>

        {/* Confirmation Dialog */}
        <Dialog open={showConfirmDialog} onOpenChange={setShowConfirmDialog}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle className="heading text-2xl text-[#180029]">
                Ready to Generate?
              </DialogTitle>
              <DialogDescription className="text-[#180029]/70">
                This will use 1 credit to generate your {generation?.package_tier} package for {generation?.website_url}
              </DialogDescription>
            </DialogHeader>
            <div className="bg-[#F4743B]/10 rounded-lg p-4 my-4">
              <div className="flex items-start gap-2">
                <AlertCircle className="w-5 h-5 text-[#F4743B] flex-shrink-0 mt-0.5" />
                <p className="text-sm text-[#180029]">
                  You cannot edit information after generation starts. Make sure all details are correct.
                </p>
              </div>
            </div>
            <DialogFooter className="flex-col sm:flex-row gap-3">
              <Button
                variant="outline"
                onClick={() => setShowConfirmDialog(false)}
              >
                Cancel
              </Button>
              <Button
                onClick={() => {
                  setShowConfirmDialog(false);
                  handleGenerate();
                }}
                className="bg-[#7700CC] hover:bg-[#47007A] text-white"
              >
                Yes, Generate Package
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>
    </div>
  );
}
import React, { useState, useEffect } from 'react';
import { base44 } from '@/api/base44Client';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ArrowRight, ArrowLeft, Check, Loader2, Sparkles, Zap, SkipForward } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { useNavigate, useSearchParams } from 'react-router-dom';

const SECTIONS = [
  { label: 'Your Business', number: 1 },
  { label: 'Your Authority', number: 2 },
  { label: 'For AI Systems', number: 3 },
];

function buildScrapedDefaults(extracted, userEmail) {
  const socialLinks = Array.isArray(extracted.socialLinks)
    ? extracted.socialLinks.join('\n')
    : (extracted.socialLinks || '');

  let productsServices = '';
  if (extracted.namedProductsWithDesc?.length > 0) {
    productsServices = extracted.namedProductsWithDesc.map(p => p.name + (p.description ? `: ${p.description}` : '')).join('\n');
  } else if (extracted.namedProducts?.length > 0) {
    productsServices = extracted.namedProducts.join(', ');
  }

  return {
    // Interview answers
    businessName: extracted.businessName || '',
    coreDescription: '',
    productsServices,
    founderExpert: extracted.founderName ? `${extracted.founderName}${extracted.founderRole ? `, ${extracted.founderRole}` : ''}` : '',
    proprietaryFrameworks: (extracted.frameworks || []).join(', '),
    credibilitySignals: (extracted.socialProofSignals || []).join('; '),
    priorityMessage: '',
    commonQuestion: '',
    misconceptions: '',
    // Silent scraped context for file generation
    contactEmail: extracted.contactEmail || userEmail || '',
    address: extracted.address || '',
    hasPhysicalLocation: extracted.address ? 'yes' : 'no',
    socialLinks,
    platform: extracted.platform || 'Custom/Other',
    aiCrawlers: { chatgpt: true, claude: true, perplexity: true, gemini: true, others: true },
    contentFocus: { products: false, expertise: false, story: false, team: false },
  };
}

export default function Questionnaire() {
  const [searchParams] = useSearchParams();
  const [loading, setLoading] = useState(true);
  const [analysis, setAnalysis] = useState(null);
  const [generation, setGeneration] = useState(null);
  const [currentSection, setCurrentSection] = useState(0); // 0-indexed
  const [answers, setAnswers] = useState({});
  const [errors, setErrors] = useState({});
  const [scrapedBusinessName, setScrapedBusinessName] = useState('');
  const [scrapedProducts, setScrapedProducts] = useState('');
  const navigate = useNavigate();

  useEffect(() => { loadAnalysis(); }, []);

  useEffect(() => {
    const interval = setInterval(() => {
      if (generation?.id) saveProgress();
    }, 30000);
    return () => clearInterval(interval);
  }, [answers, generation]);

  const loadAnalysis = async () => {
    const analysisId = searchParams.get('analysis_id');
    if (!analysisId) { navigate('/new-generation'); return; }

    try {
      const user = await base44.auth.me();
      const analysisData = await base44.entities.Analysis.get(analysisId);
      if (analysisData.user_id !== user.id) { navigate('/new-generation'); return; }
      setAnalysis(analysisData);

      const generations = await base44.entities.Generation.filter({ analysis_id: analysisId, user_id: user.id });
      let savedAnswers = null;
      if (generations.length > 0) {
        setGeneration(generations[0]);
        if (generations[0].questionnaire_data && Object.keys(generations[0].questionnaire_data).length > 0) {
          savedAnswers = generations[0].questionnaire_data;
        }
      }

      if (analysisData.extracted_data) {
        const extracted = analysisData.extracted_data;
        setScrapedBusinessName(extracted.businessName || '');
        const defaults = buildScrapedDefaults(extracted, user.email);
        // Build scraped products hint
        if (extracted.namedProducts?.length > 0) {
          setScrapedProducts(extracted.namedProducts.join(', '));
        } else if (extracted.namedProductsWithDesc?.length > 0) {
          setScrapedProducts(extracted.namedProductsWithDesc.map(p => p.name).join(', '));
        }
        setAnswers(savedAnswers ? { ...defaults, ...savedAnswers } : defaults);
      } else if (savedAnswers) {
        setAnswers(savedAnswers);
      }

      setLoading(false);
    } catch (error) {
      console.error('Failed to load analysis:', error);
      navigate('/new-generation');
    }
  };

  const saveProgress = async () => {
    if (!generation?.id) return;
    try {
      await base44.entities.Generation.update(generation.id, { questionnaire_data: answers });
    } catch (error) {
      console.error('Auto-save failed:', error);
    }
  };

  const updateAnswer = (field, value) => {
    setAnswers(prev => ({ ...prev, [field]: value }));
    setErrors(prev => ({ ...prev, [field]: '' }));
  };

  const skipField = (field) => {
    setAnswers(prev => ({ ...prev, [field]: '' }));
  };

  const validateSection = (sectionIndex) => {
    const newErrors = {};
    if (sectionIndex === 0) {
      if (!answers.businessName?.trim()) newErrors.businessName = 'Business name is required.';
      if (!answers.coreDescription?.trim()) newErrors.coreDescription = 'A short description is required.';
    }
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleNext = () => {
    if (!validateSection(currentSection)) return;
    saveProgress();
    setCurrentSection(s => s + 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleBack = () => {
    setCurrentSection(s => s - 1);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const handleSubmit = async () => {
    if (!validateSection(currentSection)) return;
    await saveProgress();
    navigate(`/Review?generation_id=${generation.id}`);
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center">
        <Loader2 className="w-12 h-12 text-[#7700CC] animate-spin" />
      </div>
    );
  }

  const section = SECTIONS[currentSection];

  return (
    <div className="min-h-screen bg-[#EEF0F2] py-12 px-4 sm:px-6 lg:px-8">
      <div className="max-w-2xl mx-auto">

        {/* Intro */}
        <motion.div initial={{ opacity: 0, y: -8 }} animate={{ opacity: 1, y: 0 }} className="mb-8 text-center">
          <div className="inline-flex items-center gap-2 bg-green-50 border border-green-200 text-green-800 text-xs font-semibold px-3 py-1.5 rounded-full mb-4">
            <Zap className="w-3.5 h-3.5" /> Website analyzed
          </div>
          <p className="text-[#180029]/70 text-base max-w-lg mx-auto leading-relaxed">
            We analyzed your website and pulled what we could. Now we just need about <strong>5 minutes</strong> from you to fill in what only you know.
          </p>
        </motion.div>

        {/* Section Progress */}
        <div className="mb-6">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-semibold text-[#7700CC] uppercase tracking-wide">
              Section {currentSection + 1} of {SECTIONS.length}: {section.label}
            </span>
            <span className="text-xs text-[#180029]/50">{currentSection + 1}/{SECTIONS.length}</span>
          </div>
          <div className="flex gap-1.5">
            {SECTIONS.map((s, i) => (
              <div key={i} className="flex-1 h-1.5 rounded-full overflow-hidden bg-gray-200">
                <motion.div
                  className="h-full bg-gradient-to-r from-[#7700CC] to-[#47007A]"
                  initial={{ width: '0%' }}
                  animate={{ width: i < currentSection ? '100%' : i === currentSection ? '60%' : '0%' }}
                  transition={{ duration: 0.4 }}
                />
              </div>
            ))}
          </div>
        </div>

        <Card className="border-2 border-gray-200 shadow-2xl">
          <CardHeader className="bg-gradient-to-r from-[#7700CC]/5 to-[#47007A]/5 border-b pb-5">
            <CardTitle className="heading text-2xl text-[#180029]">{section.label}</CardTitle>
          </CardHeader>
          <CardContent className="p-8">
            <AnimatePresence mode="wait">

              {/* SECTION 1: Your Business */}
              {currentSection === 0 && (
                <motion.div key="s1" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} className="space-y-8">

                  {/* Q1 */}
                  <QuestionBlock
                    number="Q1"
                    label="What's your business name?"
                    required
                    hint={scrapedBusinessName ? `We found "${scrapedBusinessName}" — is that right, or is there a shorter/cleaner version?` : undefined}
                    error={errors.businessName}
                    autoDetected={!!scrapedBusinessName}
                  >
                    <Input
                      value={answers.businessName || ''}
                      onChange={(e) => updateAnswer('businessName', e.target.value)}
                      className={`h-12 ${scrapedBusinessName ? 'bg-green-50 border-green-300' : ''}`}
                      placeholder="Your Business Name"
                    />
                  </QuestionBlock>

                  {/* Q2 */}
                  <QuestionBlock
                    number="Q2"
                    label="Describe your business in 1–2 sentences."
                    required
                    hint="Who you help and what result you deliver."
                    error={errors.coreDescription}
                  >
                    <Textarea
                      value={answers.coreDescription || ''}
                      onChange={(e) => updateAnswer('coreDescription', e.target.value)}
                      placeholder="Who you help and what result you deliver."
                      className="min-h-[100px]"
                      maxLength={400}
                    />
                    <p className="text-xs text-[#180029]/50">{(answers.coreDescription || '').length}/400</p>
                  </QuestionBlock>

                  {/* Q3 */}
                  <QuestionBlock
                    number="Q3"
                    label="List your actual product or program names."
                    optional
                    hint={scrapedProducts ? `We found: ${scrapedProducts} — anything missing, renamed, or different?` : "List each product, service, or program name — one per line or comma-separated."}
                    autoDetected={!!scrapedProducts}
                    onSkip={() => skipField('productsServices')}
                    skipped={answers.productsServices === '' && scrapedProducts !== ''}
                  >
                    <Textarea
                      value={answers.productsServices || ''}
                      onChange={(e) => updateAnswer('productsServices', e.target.value)}
                      className={`min-h-[100px] ${scrapedProducts && answers.productsServices ? 'bg-green-50 border-green-300' : ''}`}
                      placeholder="e.g., The Launch Accelerator, 1:1 Coaching, SEO Starter Pack"
                      maxLength={500}
                    />
                  </QuestionBlock>
                </motion.div>
              )}

              {/* SECTION 2: Your Authority */}
              {currentSection === 1 && (
                <motion.div key="s2" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} className="space-y-8">

                  {/* Q4 */}
                  <QuestionBlock
                    number="Q4"
                    label="Who is the founder or lead expert?"
                    optional
                    autoDetected={!!answers.founderExpert}
                    onSkip={() => skipField('founderExpert')}
                  >
                    <Textarea
                      value={answers.founderExpert || ''}
                      onChange={(e) => updateAnswer('founderExpert', e.target.value)}
                      className={`min-h-[90px] ${answers.founderExpert ? 'bg-green-50 border-green-300' : ''}`}
                      placeholder="Name + one sentence about their background or expertise."
                      maxLength={300}
                    />
                  </QuestionBlock>

                  {/* Q5 */}
                  <QuestionBlock
                    number="Q5"
                    label="Do you have any named frameworks, methodologies, or systems?"
                    optional
                    autoDetected={!!answers.proprietaryFrameworks}
                    onSkip={() => skipField('proprietaryFrameworks')}
                  >
                    <Textarea
                      value={answers.proprietaryFrameworks || ''}
                      onChange={(e) => updateAnswer('proprietaryFrameworks', e.target.value)}
                      className={`min-h-[90px] ${answers.proprietaryFrameworks ? 'bg-green-50 border-green-300' : ''}`}
                      placeholder="e.g., The Perfect Prompt Framework™, The 5-Step Sales System"
                      maxLength={300}
                    />
                  </QuestionBlock>

                  {/* Q6 */}
                  <QuestionBlock
                    number="Q6"
                    label="What credibility signals should AI systems know about?"
                    optional
                    autoDetected={!!answers.credibilitySignals}
                    onSkip={() => skipField('credibilitySignals')}
                  >
                    <Textarea
                      value={answers.credibilitySignals || ''}
                      onChange={(e) => updateAnswer('credibilitySignals', e.target.value)}
                      className={`min-h-[90px] ${answers.credibilitySignals ? 'bg-green-50 border-green-300' : ''}`}
                      placeholder="Community size, students served, awards, press mentions — whatever you're proud of."
                      maxLength={400}
                    />
                  </QuestionBlock>
                </motion.div>
              )}

              {/* SECTION 3: For AI Systems */}
              {currentSection === 2 && (
                <motion.div key="s3" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} className="space-y-8">

                  {/* Q7 */}
                  <QuestionBlock
                    number="Q7"
                    label="What's the #1 thing you want AI systems to know about your business?"
                    optional
                    hint="This becomes a priority context block in your AI files."
                    onSkip={() => skipField('priorityMessage')}
                  >
                    <Textarea
                      value={answers.priorityMessage || ''}
                      onChange={(e) => updateAnswer('priorityMessage', e.target.value)}
                      className="min-h-[100px]"
                      placeholder="This becomes a priority context block in your AI files."
                      maxLength={400}
                    />
                  </QuestionBlock>

                  {/* Q8 */}
                  <QuestionBlock
                    number="Q8"
                    label="What's the most common question people ask you?"
                    optional
                    hint="This becomes FAQ content in your AI files."
                    onSkip={() => skipField('commonQuestion')}
                  >
                    <Textarea
                      value={answers.commonQuestion || ''}
                      onChange={(e) => updateAnswer('commonQuestion', e.target.value)}
                      className="min-h-[90px]"
                      placeholder="This becomes FAQ content in your AI files."
                      maxLength={400}
                    />
                  </QuestionBlock>

                  {/* Q9 */}
                  <QuestionBlock
                    number="Q9"
                    label="Any misconceptions about your business you want to correct?"
                    optional
                    hint="AI systems will use this to give accurate answers about you."
                    onSkip={() => skipField('misconceptions')}
                  >
                    <Textarea
                      value={answers.misconceptions || ''}
                      onChange={(e) => updateAnswer('misconceptions', e.target.value)}
                      className="min-h-[90px]"
                      placeholder="AI systems will use this to give accurate answers about you."
                      maxLength={400}
                    />
                  </QuestionBlock>
                </motion.div>
              )}
            </AnimatePresence>

            {/* Navigation */}
            <div className="flex justify-between mt-10 pt-6 border-t">
              <Button onClick={handleBack} variant="outline" disabled={currentSection === 0} className="gap-2">
                <ArrowLeft className="w-4 h-4" /> Back
              </Button>
              {currentSection < SECTIONS.length - 1 ? (
                <Button onClick={handleNext} className="bg-[#7700CC] hover:bg-[#47007A] text-white gap-2">
                  Next: {SECTIONS[currentSection + 1].label} <ArrowRight className="w-4 h-4" />
                </Button>
              ) : (
                <Button onClick={handleSubmit} className="bg-[#F4743B] hover:bg-[#d95e2a] text-white gap-2 px-6">
                  <Sparkles className="w-4 h-4" /> Generate My AI Files
                </Button>
              )}
            </div>
          </CardContent>
        </Card>

        <p className="text-xs text-center text-[#180029]/50 mt-4">💾 Progress auto-saved</p>
      </div>
    </div>
  );
}

function QuestionBlock({ number, label, required, optional, hint, error, autoDetected, onSkip, children }) {
  return (
    <div className="space-y-2">
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1">
          <div className="flex items-center gap-2 flex-wrap mb-1">
            <span className="text-xs font-bold text-[#7700CC] bg-[#7700CC]/10 px-2 py-0.5 rounded-full">{number}</span>
            <Label className="subheading text-[#180029] text-sm leading-snug">
              {label}
              {required && <span className="text-red-500 ml-1">*</span>}
              {optional && <span className="text-[#180029]/40 font-normal ml-1 text-xs">(optional)</span>}
            </Label>
            {autoDetected && (
              <span className="text-xs text-green-600 flex items-center gap-1 font-medium">
                <Check className="w-3 h-3" /> Auto-detected
              </span>
            )}
          </div>
          {hint && <p className="text-xs text-[#180029]/60 mb-2 leading-relaxed">{hint}</p>}
        </div>
        {optional && onSkip && (
          <button
            type="button"
            onClick={onSkip}
            className="text-xs text-[#180029]/40 hover:text-[#180029]/70 flex items-center gap-1 shrink-0 mt-1 transition-colors"
          >
            <SkipForward className="w-3.5 h-3.5" /> Skip
          </button>
        )}
      </div>
      {children}
      {error && <p className="text-sm text-red-600">{error}</p>}
    </div>
  );
}
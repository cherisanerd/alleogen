import React, { useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { base44, tokenStore } from '@/api/base44Client';
import { Loader2, AlertCircle } from 'lucide-react';

/**
 * /g/:token
 *
 * One-timer entry point after a Stripe purchase. Stores the access
 * token in localStorage so every follow-up API call authenticates
 * with the Bearer header, then routes the caller to the right page
 * based on the generation's current status.
 */
export default function TokenLanding() {
  const { token } = useParams();
  const navigate = useNavigate();
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!token) {
      setError('Missing access token.');
      return;
    }
    tokenStore.set(token);

    (async () => {
      try {
        // /auth/me with a token returns the generation envelope.
        const res = await base44.auth.me();
        const gen = res?._tokenGeneration;
        if (!gen) {
          setError('This link is no longer valid. If you just purchased, check your email for a fresh link.');
          return;
        }
        switch (gen.status) {
          case 'pending_payment':
            navigate('/pricing');
            break;
          case 'questionnaire_incomplete':
            navigate(gen.analysis_id
              ? `/Questionnaire?analysis_id=${gen.analysis_id}`
              : '/new-generation');
            break;
          case 'generating':
            navigate(`/GenerationProgress?generation_id=${gen.id}`);
            break;
          case 'completed':
            navigate(`/GenerationProgress?generation_id=${gen.id}`);
            break;
          case 'failed':
            navigate(`/Review?generation_id=${gen.id}`);
            break;
          default:
            navigate('/new-generation');
        }
      } catch (err) {
        setError(err?.data?.error || err?.message || 'Could not load your package.');
      }
    })();
  }, [token, navigate]);

  return (
    <div className="min-h-screen bg-[#EEF0F2] flex items-center justify-center px-4">
      {error ? (
        <div className="max-w-md text-center">
          <AlertCircle className="w-10 h-10 text-red-500 mx-auto mb-3" />
          <p className="text-[#180029] font-medium mb-2">Link problem</p>
          <p className="text-sm text-[#180029]/70">{error}</p>
        </div>
      ) : (
        <div className="text-center text-[#180029]/70 text-sm flex items-center gap-2">
          <Loader2 className="w-5 h-5 animate-spin text-[#7700CC]" />
          Preparing your package…
        </div>
      )}
    </div>
  );
}

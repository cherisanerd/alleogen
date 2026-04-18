import { Toaster } from "@/components/ui/toaster"
import { QueryClientProvider } from '@tanstack/react-query'
import { queryClientInstance } from '@/lib/query-client'
import NavigationTracker from '@/lib/NavigationTracker'
import { pagesConfig } from './pages.config'
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import PageNotFound from './lib/PageNotFound';
import { AuthProvider, useAuth } from '@/lib/AuthContext';
import UserNotRegisteredError from '@/components/UserNotRegisteredError';
import TokenLanding from './pages/TokenLanding';

const { Pages, Layout, mainPage } = pagesConfig;
const mainPageKey = mainPage ?? Object.keys(Pages)[0];
const MainPage = mainPageKey ? Pages[mainPageKey] : <></>;

// Pages that render outside the authenticated shell. These also skip the
// Layout wrapper so they don't require a logged-in user.
const PUBLIC_ROUTES = new Set(['Login', 'login', 'Pricing', 'new-generation', 'file-generator']);

const LayoutWrapper = ({ children, currentPageName }) => Layout ?
  <Layout currentPageName={currentPageName}>{children}</Layout>
  : <>{children}</>;

const AuthenticatedApp = () => {
  const { isLoadingAuth, isLoadingPublicSettings, authError } = useAuth();
  const path = typeof window !== 'undefined' ? window.location.pathname : '';
  // BASE_URL is '/tools/alleogen/' in prod and '/' in dev — trim it.
  const basePath = (import.meta.env.BASE_URL || '/').replace(/\/$/, '');
  const relPath = path.startsWith(basePath) ? path.slice(basePath.length) : path;
  const firstSegment = (relPath.split('/').filter(Boolean)[0] || '').toLowerCase();
  const isPublic = PUBLIC_ROUTES.has(firstSegment) || firstSegment === 'g' || firstSegment === '';

  if (isLoadingPublicSettings || isLoadingAuth) {
    return (
      <div className="fixed inset-0 flex items-center justify-center">
        <div className="w-8 h-8 border-4 border-slate-200 border-t-slate-800 rounded-full animate-spin"></div>
      </div>
    );
  }

  if (!isPublic && authError?.type === 'user_not_registered') {
    return <UserNotRegisteredError />;
  }

  return (
    <Routes>
      <Route path="/" element={
        <LayoutWrapper currentPageName={mainPageKey}>
          <MainPage />
        </LayoutWrapper>
      } />
      <Route path="/g/:token" element={<TokenLanding />} />
      {Object.entries(Pages).map(([routePath, Page]) => (
        <Route
          key={routePath}
          path={`/${routePath}`}
          element={
            <LayoutWrapper currentPageName={routePath}>
              <Page />
            </LayoutWrapper>
          }
        />
      ))}
      <Route path="*" element={<PageNotFound />} />
    </Routes>
  );
};


function App() {

  return (
    <AuthProvider>
      <QueryClientProvider client={queryClientInstance}>
        <Router>
          <NavigationTracker />
          <AuthenticatedApp />
        </Router>
        <Toaster />
      </QueryClientProvider>
    </AuthProvider>
  )
}

export default App

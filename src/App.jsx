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

// Pages that don't require auth and therefore render WITHOUT the
// Layout wrapper. The Layout's own auth check would otherwise bounce
// them to /login and create a redirect loop (especially on /login
// itself).
const PUBLIC_ROUTES = new Set([
  '',               // mainPage (Pricing)
  'Login', 'login',
  'Pricing',
  'new-generation',
  'file-generator',
]);

// Routes under /account/ that don't require an existing session:
// the password-reset flow has to be reachable when the user has
// forgotten the password they'd otherwise log in with.
const PUBLIC_ACCOUNT_SUBROUTES = new Set(['forgot', 'reset']);

const isPublicRoute = (pageName) => {
  const parts = (pageName || '').split('/');
  const segment = parts[0] || '';
  if (PUBLIC_ROUTES.has(segment) || segment === 'g') return true;
  if (segment === 'account' && PUBLIC_ACCOUNT_SUBROUTES.has(parts[1] || '')) return true;
  return false;
};

const LayoutWrapper = ({ children, currentPageName }) => {
  if (!Layout || isPublicRoute(currentPageName)) return <>{children}</>;
  return <Layout currentPageName={currentPageName}>{children}</Layout>;
};

const AuthenticatedApp = () => {
  const { isLoadingAuth, isLoadingPublicSettings, authError } = useAuth();
  const path = typeof window !== 'undefined' ? window.location.pathname : '';
  // BASE_URL is '/tools/alleogen/' in prod and '/' in dev — trim it.
  const basePath = (import.meta.env.BASE_URL || '/').replace(/\/$/, '');
  const relPath = path.startsWith(basePath) ? path.slice(basePath.length) : path;
  const firstSegment = (relPath.split('/').filter(Boolean)[0] || '').toLowerCase();
  const isPublic = isPublicRoute(firstSegment) || firstSegment === '';

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
  // Vite injects BASE_URL as '/tools/alleogen/' in prod and '/' in dev.
  // React Router wants the basename without a trailing slash.
  const basename = (import.meta.env.BASE_URL || '/').replace(/\/$/, '');

  return (
    <AuthProvider>
      <QueryClientProvider client={queryClientInstance}>
        <Router basename={basename}>
          <NavigationTracker />
          <AuthenticatedApp />
        </Router>
        <Toaster />
      </QueryClientProvider>
    </AuthProvider>
  )
}

export default App

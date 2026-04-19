/**
 * pages.config.js - Page routing configuration
 * 
 * This file is AUTO-GENERATED. Do not add imports or modify PAGES manually.
 * Pages are auto-registered when you create files in the ./pages/ folder.
 * 
 * THE ONLY EDITABLE VALUE: mainPage
 * This controls which page is the landing page (shown when users visit the app).
 * 
 * Example file structure:
 * 
 *   import HomePage from './pages/HomePage';
 *   import Dashboard from './pages/Dashboard';
 *   import Settings from './pages/Settings';
 *   
 *   export const PAGES = {
 *       "HomePage": HomePage,
 *       "Dashboard": Dashboard,
 *       "Settings": Settings,
 *   }
 *   
 *   export const pagesConfig = {
 *       mainPage: "HomePage",
 *       Pages: PAGES,
 *   };
 * 
 * Example with Layout (wraps all pages):
 *
 *   import Home from './pages/Home';
 *   import Settings from './pages/Settings';
 *   import __Layout from './Layout.jsx';
 *
 *   export const PAGES = {
 *       "Home": Home,
 *       "Settings": Settings,
 *   }
 *
 *   export const pagesConfig = {
 *       mainPage: "Home",
 *       Pages: PAGES,
 *       Layout: __Layout,
 *   };
 *
 * To change the main page from HomePage to Dashboard, use find_replace:
 *   Old: mainPage: "HomePage",
 *   New: mainPage: "Dashboard",
 *
 * The mainPage value must match a key in the PAGES object exactly.
 */
import AccountCancel from './pages/AccountCancel';
import Admin from './pages/Admin';
import AdminSettings from './pages/AdminSettings';
import ChangePassword from './pages/ChangePassword';
import Dashboard from './pages/Dashboard';
import ForgotPassword from './pages/ForgotPassword';
import GenerationProgress from './pages/GenerationProgress';
import Login from './pages/Login';
import MyGenerations from './pages/MyGenerations';
import OrderBump from './pages/OrderBump';
import PaymentSuccess from './pages/PaymentSuccess';
import Pricing from './pages/Pricing';
import Questionnaire from './pages/Questionnaire';
import ResetPassword from './pages/ResetPassword';
import Review from './pages/Review';
import UpgradeSuccess from './pages/UpgradeSuccess';
import fileGenerator from './pages/file-generator';
import newGeneration from './pages/new-generation';
import __Layout from './Layout.jsx';


export const PAGES = {
    "Admin": Admin,
    "admin/settings": AdminSettings,
    "Dashboard": Dashboard,
    "GenerationProgress": GenerationProgress,
    "Login": Login,
    "MyGenerations": MyGenerations,
    "OrderBump": OrderBump,
    "PaymentSuccess": PaymentSuccess,
    "Pricing": Pricing,
    "Questionnaire": Questionnaire,
    "Review": Review,
    "UpgradeSuccess": UpgradeSuccess,
    "account/cancel": AccountCancel,
    "account/forgot": ForgotPassword,
    "account/password": ChangePassword,
    "account/reset": ResetPassword,
    "file-generator": fileGenerator,
    "new-generation": newGeneration,
    "login": Login,
}

export const pagesConfig = {
    mainPage: "Pricing",
    Pages: PAGES,
    Layout: __Layout,
};
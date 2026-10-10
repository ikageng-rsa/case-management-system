/**
 * routes/api.php equivalent. Mounted at /api, so the app's
 * EXPO_PUBLIC_API_BASE_URL should end in /api.
 *
 *   Public:           POST /auth/login, POST /auth/password-reset
 *   auth:sanctum:     everything else (Bearer token)
 *   role gates:       mirror TAB_ACCESS in src/constants/roles.ts
 */
const express = require('express');
const multer = require('multer');

const authenticate = require('../app/Http/Middleware/authenticate');
const role = require('../app/Http/Middleware/role');

const Auth = require('../app/Http/Controllers/AuthController');
const Users = require('../app/Http/Controllers/UserController');
const Cases = require('../app/Http/Controllers/CaseController');
const Narrations = require('../app/Http/Controllers/NarrationController');
const Documents = require('../app/Http/Controllers/DocumentController');
const Billing = require('../app/Http/Controllers/BillingController');
const Darzations = require('../app/Http/Controllers/DarzationController');
const Reports = require('../app/Http/Controllers/ReportController');

const router = express.Router();
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 20 * 1024 * 1024 } });

const ALL = ['Admin', 'Director', 'CA', 'Secretary', 'Messenger'];
const CASE_STAFF = ['Admin', 'Director', 'CA', 'Secretary'];
const BILLING_STAFF = ['Admin', 'Director', 'CA'];
const ADMINS = ['Admin', 'Director'];

router.get('/health', (req, res) => res.json({ status: 'ok', time: new Date().toISOString() }));

// ---- public ----
router.post('/auth/login', Auth.login);
router.post('/auth/password-reset', Auth.passwordReset);

// ---- auth:sanctum ---- (applied per route so unknown URLs still 404 like Laravel, not 401)
const auth = authenticate;

router.get('/auth/me', auth, Auth.me);
router.post('/auth/logout', auth, Auth.logout);

router.get('/users', auth, role(...ADMINS), Users.index);
router.get('/reports/dashboard', auth, role(...ALL), Reports.dashboard);

router.get('/cases', auth, role(...CASE_STAFF), Cases.index);
router.post('/cases', auth, role(...CASE_STAFF), Cases.store);
router.get('/cases/:caseId', auth, role(...CASE_STAFF), Cases.show);
router.patch('/cases/:caseId/status', auth, role(...CASE_STAFF), Cases.updateStatus);

router.get('/cases/:caseId/narrations', auth, role(...CASE_STAFF), Narrations.index);
router.post('/cases/:caseId/narrations', auth, role(...CASE_STAFF), Narrations.store);

router.get('/cases/:caseId/documents', auth, role(...ALL), Documents.index);
router.post('/cases/:caseId/documents', auth, role(...CASE_STAFF), upload.single('file'), Documents.store);

router.get('/cases/:caseId/billing', auth, role(...BILLING_STAFF), Billing.index);
router.patch('/billing/:entryId/mark-billed', auth, role(...BILLING_STAFF), Billing.markBilled);

router.get('/darzations', auth, role(...CASE_STAFF), Darzations.index);
router.get('/darzations/overdue', auth, role(...CASE_STAFF), Darzations.overdue);
router.post('/darzations', auth, role(...CASE_STAFF), Darzations.store);

module.exports = router;

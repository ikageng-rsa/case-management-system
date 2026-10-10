const crypto = require('crypto');
const db = require('../../../database/db');
const validate = require('../Requests/validate');
const CaseController = require('./CaseController');

const ACTIVITY_TYPES = ['Call', 'Draft', 'Appearance', 'Travel', 'Other'];
const DEFAULT_RATE = 850; // flat mock rate; the real backend prices by tariff scale

exports.index = (req, res) => {
  const found = CaseController.findOr404(req, res);
  if (!found) return;
  res.json(db.table('narrations').filter(n => n.caseId === found.id).sort((a, b) => b.createdAt.localeCompare(a.createdAt)));
};

exports.store = (req, res) => {
  const found = CaseController.findOr404(req, res);
  if (!found) return;

  const data = validate(req, res, {
    activityType: `required|in:${ACTIVITY_TYPES.join(',')}`,
    description: 'required|string|max:2000',
    billable: 'required|boolean',
    tariffCode: 'nullable|string|max:20',
  });
  if (!data) return;

  // authorId always comes from the token, never from the client (mass-assignment safety).
  const created = db.insert('narrations', {
    id: crypto.randomUUID(),
    caseId: found.id,
    authorId: req.user.id,
    activityType: data.activityType,
    description: data.description,
    billable: data.billable,
    ...(data.tariffCode ? { tariffCode: data.tariffCode } : {}),
    createdAt: new Date().toISOString(),
  });

  // "Observer": a billable narration automatically produces an unbilled billing entry.
  if (created.billable) {
    db.insert('billing_entries', {
      id: crypto.randomUUID(),
      caseId: found.id,
      narrationId: created.id,
      tariffScale: created.tariffCode ? `Scale ${created.tariffCode}` : 'Standard',
      amount: DEFAULT_RATE,
      billed: false,
    });
  }
  res.status(201).json(created);
};

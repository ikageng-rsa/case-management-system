const crypto = require('crypto');
const db = require('../../../database/db');
const validate = require('../Requests/validate');
const { fileReference } = require('../../Support/fileReference');

const STATUSES = ['Open', 'In Progress', 'On Hold', 'Closed', 'Archived'];

// Route-model binding: 404 like Laravel when the id doesn't exist.
exports.findOr404 = (req, res) => {
  const found = db.find('cases', req.params.caseId);
  if (!found) {
    res.status(404).json({ message: `No query results for model [App\\Models\\CaseFile] ${req.params.caseId}` });
  }
  return found;
};

exports.index = (req, res) => {
  const { status, search } = req.query;
  const term = typeof search === 'string' ? search.trim().toLowerCase() : '';

  const rows = db
    .table('cases')
    .filter(c => !status || c.status === status)
    .filter(c => !term || [c.fileReference, c.clientName, c.matterType, c.opposingParty].some(v => (v || '').toLowerCase().includes(term)))
    .sort((a, b) => b.openedDate.localeCompare(a.openedDate));

  res.json(rows);
};

exports.show = (req, res) => {
  const found = exports.findOr404(req, res);
  if (found) res.json(found);
};

exports.store = (req, res) => {
  const data = validate(req, res, {
    clientName: 'required|string|max:255',
    matterType: 'required|string|max:100',
    opposingParty: 'nullable|string|max:255',
  });
  if (!data) return;

  const existingClient = db.table('cases').find(c => c.clientName.toLowerCase() === data.clientName.toLowerCase());
  const created = db.insert('cases', {
    id: crypto.randomUUID(),
    fileReference: fileReference(data.clientName, data.matterType, db.table('cases').length + 1),
    clientId: existingClient ? existingClient.clientId : crypto.randomUUID(),
    clientName: data.clientName,
    matterType: data.matterType,
    status: 'Open',
    leadAttorneyId: req.user.id,
    openedDate: new Date().toISOString().slice(0, 10),
    ...(data.opposingParty ? { opposingParty: data.opposingParty } : {}),
  });
  res.status(201).json(created);
};

exports.updateStatus = (req, res) => {
  const found = exports.findOr404(req, res);
  if (!found) return;
  const data = validate(req, res, { status: `required|in:${STATUSES.join(',')}` });
  if (!data) return;
  res.json(db.update('cases', found.id, { status: data.status }));
};

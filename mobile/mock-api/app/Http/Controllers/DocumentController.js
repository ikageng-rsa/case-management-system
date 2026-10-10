const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const db = require('../../../database/db');
const validate = require('../Requests/validate');
const Resource = require('../Resources');
const CaseController = require('./CaseController');

const FILES_DIR = path.join(__dirname, '..', '..', '..', 'storage', 'files');
const baseUrl = req => `${req.protocol}://${req.get('host')}`;

exports.index = (req, res) => {
  const found = CaseController.findOr404(req, res);
  if (!found) return;
  const rows = db.table('documents').filter(d => d.caseId === found.id).sort((a, b) => b.uploadedAt.localeCompare(a.uploadedAt));
  res.json(rows.map(row => Resource.document(row, baseUrl(req))));
};

// Multipart upload: field `file` + `confidentiality`. Parsed by multer in routes/api.js.
exports.store = (req, res) => {
  const found = CaseController.findOr404(req, res);
  if (!found) return;

  const data = validate(req, res, { confidentiality: 'required|in:Standard,Restricted,Privileged' });
  if (!data) return;
  if (!req.file) {
    return res.status(422).json({ message: 'The file field is required.', errors: { file: ['The file field is required.'] } });
  }

  const storedAs = `${crypto.randomUUID()}${path.extname(req.file.originalname)}`;
  fs.mkdirSync(FILES_DIR, { recursive: true });
  fs.writeFileSync(path.join(FILES_DIR, storedAs), req.file.buffer);

  // Same file name on the same case → next version, like a real document-management flow.
  const previous = db.table('documents').filter(d => d.caseId === found.id && d.fileName === req.file.originalname);
  const created = db.insert('documents', {
    id: crypto.randomUUID(),
    caseId: found.id,
    fileName: req.file.originalname,
    version: previous.length + 1,
    confidentiality: data.confidentiality,
    uploadedById: req.user.id,
    uploadedAt: new Date().toISOString(),
    storedAs,
  });
  res.status(201).json(Resource.document(created, baseUrl(req)));
};

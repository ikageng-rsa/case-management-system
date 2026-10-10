const crypto = require('crypto');
const db = require('../../../database/db');
const validate = require('../Requests/validate');
const Resource = require('../Resources');

const ROLES = ['Admin', 'Director', 'CA', 'Secretary', 'Messenger'];

// Server-side visibility: a diary entry is only returned to roles listed in visibleToRoles.
const visibleTo = user => row => row.visibleToRoles.includes(user.role);

exports.index = (req, res) => {
  const { ownerId } = req.query;
  const rows = db
    .table('darzations')
    .filter(visibleTo(req.user))
    .filter(d => !ownerId || d.ownerId === ownerId)
    .sort((a, b) => a.nextActionDate.localeCompare(b.nextActionDate));
  res.json(rows.map(Resource.darzation));
};

exports.overdue = (req, res) => {
  res.json(
    db.table('darzations').filter(visibleTo(req.user)).map(Resource.darzation).filter(d => d.overdue)
      .sort((a, b) => a.nextActionDate.localeCompare(b.nextActionDate)),
  );
};

exports.store = (req, res) => {
  const data = validate(req, res, {
    caseId: 'required|string|exists:cases,id',
    ownerId: 'required|string|exists:users,id',
    nextAction: 'required|string|max:500',
    nextActionDate: 'required|date',
    visibleToRoles: 'required|array',
    'visibleToRoles.*': `in:${ROLES.join(',')}`,
  });
  if (!data) return;

  const created = db.insert('darzations', { id: crypto.randomUUID(), ...data });
  res.status(201).json(Resource.darzation(created));
};

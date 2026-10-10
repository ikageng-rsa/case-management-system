const db = require('../../../database/db');
const Resource = require('../Resources');

exports.dashboard = (req, res) => {
  const weekAgo = Date.now() - 7 * 86400000;
  res.json({
    openCases: db.table('cases').filter(c => c.status === 'Open' || c.status === 'In Progress').length,
    overdueDarzations: db.table('darzations').filter(d => d.visibleToRoles.includes(req.user.role)).map(Resource.darzation).filter(d => d.overdue).length,
    unbilledEntries: db.table('billing_entries').filter(b => !b.billed).length,
    documentsThisWeek: db.table('documents').filter(d => Date.parse(d.uploadedAt) >= weekAgo).length,
  });
};

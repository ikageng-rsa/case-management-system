const db = require('../../../database/db');
const Resource = require('../Resources');

exports.index = (req, res) => res.json(db.table('users').map(Resource.user));

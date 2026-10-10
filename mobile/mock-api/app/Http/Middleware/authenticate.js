/**
 * Equivalent of `auth:sanctum`. Expects `Authorization: Bearer {id}|{plain}`.
 * Failure → 401 { "message": "Unauthenticated." } (what the app's axios interceptor reacts to).
 */
const db = require('../../../database/db');
const { sha256 } = require('../../Support/hash');

module.exports = function authenticate(req, res, next) {
  const header = req.headers.authorization || '';
  const bearer = header.startsWith('Bearer ') ? header.slice(7) : '';
  const [id, plain] = bearer.split('|');
  const token = id && plain ? db.find('personal_access_tokens', id) : null;

  if (!token || token.tokenHash !== sha256(plain)) {
    return res.status(401).json({ message: 'Unauthenticated.' });
  }
  const user = db.find('users', token.userId);
  if (!user) return res.status(401).json({ message: 'Unauthenticated.' });

  req.user = user;
  req.token = token;
  next();
};

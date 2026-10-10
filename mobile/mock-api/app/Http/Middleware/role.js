/**
 * Equivalent of a `role:Admin,Director` middleware / Gate check.
 * Keep the lists aligned with TAB_ACCESS in src/constants/roles.ts in the app.
 * Failure → 403 { "message": "This action is unauthorized." }
 */
module.exports = (...roles) =>
  function role(req, res, next) {
    if (!roles.includes(req.user.role)) {
      return res.status(403).json({ message: 'This action is unauthorized.' });
    }
    next();
  };

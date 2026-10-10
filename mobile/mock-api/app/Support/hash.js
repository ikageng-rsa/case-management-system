const crypto = require('crypto');

// Stand-in for Laravel's Hash::make / Hash::check (scrypt instead of bcrypt).
exports.make = plain => {
  const salt = crypto.randomBytes(16).toString('hex');
  return `${salt}:${crypto.scryptSync(plain, salt, 32).toString('hex')}`;
};

exports.check = (plain, hashed) => {
  const [salt, hash] = String(hashed).split(':');
  if (!salt || !hash) return false;
  const candidate = crypto.scryptSync(plain, salt, 32);
  const expected = Buffer.from(hash, 'hex');
  return candidate.length === expected.length && crypto.timingSafeEqual(candidate, expected);
};

exports.sha256 = value => crypto.createHash('sha256').update(value).digest('hex');

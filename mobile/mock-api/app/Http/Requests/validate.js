/**
 * Mini Laravel validator. Usage in a controller:
 *
 *   const data = validate(req, res, { email: 'required|email', role: 'nullable|in:A,B' });
 *   if (!data) return;           // a 422 was already sent
 *
 * On failure it replies exactly like Laravel:
 *   422 { "message": "The email field is required.", "errors": { "email": ["The email field is required."] } }
 *
 * Supported rules: required, nullable, string, email, boolean, date, array,
 * max:n, in:a,b,c, exists:table,column. `field.*` validates each array item.
 */
const db = require('../../../database/db');

const label = field => field.replace(/\.\*$/, '').replace(/([a-z])([A-Z])/g, '$1 $2').replace(/_/g, ' ').toLowerCase();
const isPresent = v => v !== undefined && v !== null && v !== '';
const BOOLEANS = [true, false, 1, 0, '1', '0', 'true', 'false'];

function check(value, rules, field) {
  const errors = [];
  const name = label(field);
  const nullable = rules.includes('nullable');
  if (!isPresent(value)) {
    if (rules.includes('required')) errors.push(`The ${name} field is required.`);
    return nullable || !rules.includes('required') ? [] : errors;
  }
  for (const rule of rules) {
    const [key, arg] = rule.split(/:(.+)/);
    if (key === 'string' && typeof value !== 'string') errors.push(`The ${name} field must be a string.`);
    if (key === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value))) errors.push(`The ${name} field must be a valid email address.`);
    if (key === 'boolean' && !BOOLEANS.includes(value)) errors.push(`The ${name} field must be true or false.`);
    if (key === 'date' && Number.isNaN(Date.parse(value))) errors.push(`The ${name} field must be a valid date.`);
    if (key === 'array' && !Array.isArray(value)) errors.push(`The ${name} field must be an array.`);
    if (key === 'max' && String(value).length > Number(arg)) errors.push(`The ${name} field must not be greater than ${arg} characters.`);
    if (key === 'in' && !arg.split(',').includes(String(value))) errors.push(`The selected ${name} is invalid.`);
    if (key === 'exists') {
      const [table, column = 'id'] = arg.split(',');
      if (!db.table(table).some(row => String(row[column]) === String(value))) errors.push(`The selected ${name} is invalid.`);
    }
  }
  return errors;
}

module.exports = function validate(req, res, ruleMap) {
  const input = { ...(req.body || {}) };
  const errors = {};
  const validated = {};

  for (const [field, ruleString] of Object.entries(ruleMap)) {
    if (field.endsWith('.*')) continue;
    const rules = ruleString.split('|');
    const value = input[field];
    const fieldErrors = check(value, rules, field);

    const itemRules = ruleMap[`${field}.*`];
    if (itemRules && Array.isArray(value)) {
      value.forEach(item => fieldErrors.push(...check(item, itemRules.split('|'), `${field}.*`)));
    }

    if (fieldErrors.length) errors[field] = fieldErrors;
    else if (isPresent(value)) validated[field] = rules.includes('boolean') ? [true, 1, '1', 'true'].includes(value) : value;
  }

  const keys = Object.keys(errors);
  if (keys.length) {
    const first = errors[keys[0]][0];
    const more = keys.length - 1;
    res.status(422).json({
      message: more > 0 ? `${first} (and ${more} more error${more > 1 ? 's' : ''})` : first,
      errors,
    });
    return null;
  }
  return validated;
};

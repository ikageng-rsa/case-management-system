
/**
 * Mini Laravel validator. Usage in a controller:
 *
 *   const data = validate(req, res, {
 *     email: 'required|email',
 *     role: 'nullable|in:A,B'
 *   });
 *   if (!data) return; // A 422 response was already sent.
 *
 * Supported rules:
 * required, nullable, string, email, boolean, date, array,
 * max:n, in:a,b,c, exists:table,column.
 * field.* validates each array item.
 */

const db = require('../../../database/db');
const validator = require('validator');

const label = field =>
  field
    .replace(/\.\*$/, '')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/_/g, ' ')
    .toLowerCase();

const isPresent = value =>
  value !== undefined && value !== null && value !== '';

const BOOLEANS = [true, false, 1, 0, '1', '0', 'true', 'false'];

function check(value, rules, field) {
  const name = label(field);

  if (!isPresent(value)) {
    return checkMissingValue(rules, name);
  }

  const errors = [];

  for (const rule of rules) {
    const [key, arg] = rule.split(/:(.+)/);
    const error = validateRule(key, arg, value, name);

    if (error) {
      errors.push(error);
    }
  }

  return errors;
}

function checkMissingValue(rules, name) {
  if (!rules.includes('required')) {
    return [];
  }

  return [`The ${name} field is required.`];
}

function validateRule(key, arg, value, name) {
  switch (key) {
    case 'required':
    case 'nullable':
      return null;

    case 'string':
      return validateString(value, name);

    case 'email':
      return validateEmail(value, name);

    case 'boolean':
      return validateBoolean(value, name);

    case 'date':
      return validateDate(value, name);

    case 'array':
      return validateArray(value, name);

    case 'max':
      return validateMax(value, arg, name);

    case 'in':
      return validateIn(value, arg, name);

    case 'exists':
      return validateExists(value, arg, name);

    default:
      return null;
  }
}

function validateString(value, name) {
  if (typeof value === 'string') {
    return null;
  }

  return `The ${name} field must be a string.`;
}

function validateEmail(value, name) {
  if (validator.isEmail(String(value))) {
    return null;
  }

  return `The ${name} field must be a valid email address.`;
}

function validateBoolean(value, name) {
  if (BOOLEANS.includes(value)) {
    return null;
  }

  return `The ${name} field must be true or false.`;
}

function validateDate(value, name) {
  if (
    typeof value === 'string' &&
    value.trim() !== '' &&
    !Number.isNaN(Date.parse(value))
  ) {
    return null;
  }

  return `The ${name} field must be a valid date.`;
}

function validateArray(value, name) {
  if (Array.isArray(value)) {
    return null;
  }

  return `The ${name} field must be an array.`;
}

function validateMax(value, arg, name) {
  if (String(value).length <= Number(arg)) {
    return null;
  }

  return `The ${name} field must not be greater than ${arg} characters.`;
}

function validateIn(value, arg, name) {
  if (arg.split(',').includes(String(value))) {
    return null;
  }

  return `The selected ${name} is invalid.`;
}

function validateExists(value, arg, name) {
  const [table, column = 'id'] = arg.split(',');

  const found = db
    .table(table)
    .some(row => String(row[column]) === String(value));

  if (found) {
    return null;
  }

  return `The selected ${name} is invalid.`;
}

function normalizeValue(value, rules) {
  if (!rules.includes('boolean')) {
    return value;
  }

  return [true, 1, '1', 'true'].includes(value);
}

function validateArrayItems(value, itemRules, fieldErrors, field) {
  if (!itemRules || !Array.isArray(value)) {
    return;
  }

  value.forEach(item => {
    fieldErrors.push(
      ...check(item, itemRules.split('|'), `${field}.*`),
    );
  });
}

function buildErrorMessage(errors, keys) {
  const first = errors[keys[0]][0];
  const more = keys.length - 1;

  if (more === 0) {
    return first;
  }

  return `${first} (and ${more} more error${more > 1 ? 's' : ''})`;
}

module.exports = function validate(req, res, ruleMap) {
  const input = { ...(req.body || {}) };
  const errors = {};
  const validated = {};

  for (const [field, ruleString] of Object.entries(ruleMap)) {
    if (field.endsWith('.*')) {
      continue;
    }

    const rules = ruleString.split('|');
    const value = input[field];
    const fieldErrors = check(value, rules, field);
    const itemRules = ruleMap[`${field}.*`];

    validateArrayItems(value, itemRules, fieldErrors, field);

    if (fieldErrors.length > 0) {
      errors[field] = fieldErrors;
      continue;
    }

    if (isPresent(value)) {
      validated[field] = normalizeValue(value, rules);
    }
  }

  const keys = Object.keys(errors);

  if (keys.length > 0) {
    res.status(422).json({
      message: buildErrorMessage(errors, keys),
      errors,
    });

    return null;
  }

  return validated;
};

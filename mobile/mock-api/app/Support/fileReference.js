// initials + matter type + sequence + year, e.g. "NDLLIT0012026" (matches the Case.fileReference comment).
exports.fileReference = (clientName, matterType, sequence, date = new Date()) => {
  const initials = clientName
    .replace(/[^A-Za-z ]/g, '')
    .split(/\s+/)
    .filter(Boolean)
    .map(word => word[0])
    .join('')
    .slice(0, 3)
    .toUpperCase();
  const type = matterType.replace(/[^A-Za-z]/g, '').slice(0, 3).toUpperCase();
  return `${initials}${type}${String(sequence).padStart(3, '0')}${date.getFullYear()}`;
};

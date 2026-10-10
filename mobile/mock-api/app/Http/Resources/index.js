/**
 * API Resources: shape DB rows into the JSON the app's models expect.
 * Like `JsonResource::withoutWrapping()`, responses are bare objects/arrays
 * (no `{ data: ... }` envelope) because that's what src/api/endpoints/* parses.
 */
exports.user = ({ id, fullName, email, role }) => ({ id, fullName, email, role });

exports.darzation = row => ({
  id: row.id,
  caseId: row.caseId,
  ownerId: row.ownerId,
  nextAction: row.nextAction,
  nextActionDate: row.nextActionDate,
  overdue: new Date(row.nextActionDate) < new Date(new Date().toISOString().slice(0, 10)), // accessor
  visibleToRoles: row.visibleToRoles,
});

exports.document = (row, baseUrl) => {
  const { storedAs, ...rest } = row;
  return { ...rest, uri: `${baseUrl}/storage/files/${storedAs}` };
};

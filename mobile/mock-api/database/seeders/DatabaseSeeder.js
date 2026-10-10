/**
 * Seeds users (one per role), cases, narrations, billing, diary entries and
 * documents. Dates are relative to "now" so overdue items stay overdue.
 *
 * LOGIN (password is `password` for all except the demo user):
 *   demo@legalcms.co.za      Demo@1234  Admin   <- matches the app's built-in demo credentials
 *   admin@legalcms.test      password   Admin
 *   director@legalcms.test   password   Director
 *   ca@legalcms.test         password   CA          (lead attorney on most cases)
 *   secretary@legalcms.test  password   Secretary   (no Billing tab)
 *   messenger@legalcms.test  password   Messenger   (Home / Documents / More only)
 */
const crypto = require('crypto');
const hash = require('../../app/Support/hash');
const { fileReference } = require('../../app/Support/fileReference');

const uuid = () => crypto.randomUUID();
const daysFromNow = n => new Date(Date.now() + n * 86400000).toISOString();
const dateOnly = n => daysFromNow(n).slice(0, 10);

module.exports = function seed(db) {
  const users = [
    ['Demo Admin', 'demo@legalcms.co.za', 'Demo@1234', 'Admin'],
    ['Thandi Admin', 'admin@legalcms.test', 'password', 'Admin'],
    ['Pieter Director', 'director@legalcms.test', 'password', 'Director'],
    ['Naledi Attorney', 'ca@legalcms.test', 'password', 'CA'],
    ['Sipho Secretary', 'secretary@legalcms.test', 'password', 'Secretary'],
    ['Mandla Messenger', 'messenger@legalcms.test', 'password', 'Messenger'],
  ].map(([fullName, email, password, role]) =>
    db.insert('users', { id: uuid(), fullName, email, password: hash.make(password), role, createdAt: daysFromNow(-90) }),
  );
  const named = email => users.find(u => u.email === email);
  const ca = named('ca@legalcms.test');
  const director = named('director@legalcms.test');
  const secretary = named('secretary@legalcms.test');

  const matters = [
    ['Nomsa Dlamini', 'Litigation', 'Open', 'Road Accident Fund', -120],
    ['Van der Merwe Trust', 'Conveyancing', 'In Progress', null, -75],
    ['Kgosi Holdings (Pty) Ltd', 'Commercial', 'In Progress', 'Mokoena Logistics CC', -60],
    ['Fatima Patel', 'Family Law', 'On Hold', 'Imran Patel', -45],
    ['Johan Botha', 'Labour', 'Open', 'Cape Freight (Pty) Ltd', -30],
    ['Lerato Mahlangu', 'Estates', 'Open', null, -21],
    ['Sunrise Properties', 'Litigation', 'Closed', 'City of Joburg', -200],
    ['Anele Zulu', 'Criminal', 'Archived', null, -400],
  ];

  const cases = matters.map(([clientName, matterType, status, opposingParty, openedOffset], index) => {
    const row = {
      id: uuid(),
      fileReference: fileReference(clientName, matterType, index + 1, new Date(Date.now() + openedOffset * 86400000)),
      clientId: uuid(),
      clientName,
      matterType,
      status,
      leadAttorneyId: index % 4 === 3 ? director.id : ca.id,
      openedDate: dateOnly(openedOffset),
    };
    if (opposingParty) row.opposingParty = opposingParty;
    return db.insert('cases', row);
  });

  const activities = [
    ['Call', 'Telephone consultation with client regarding next steps', true, 'A1'],
    ['Draft', 'Drafted letter of demand', true, 'B2'],
    ['Appearance', 'Appearance in the High Court — matter postponed', true, 'C3'],
    ['Travel', 'Travel to court and back', false, undefined],
  ];
  cases.slice(0, 6).forEach((c, ci) => {
    activities.slice(0, 2 + (ci % 3)).forEach(([activityType, description, billable, tariffCode], ai) => {
      const narration = db.insert('narrations', {
        id: uuid(), caseId: c.id, authorId: ci % 2 ? secretary.id : ca.id, activityType, description,
        billable, ...(tariffCode ? { tariffCode } : {}), createdAt: daysFromNow(-(ci * 4 + ai * 2 + 1)),
      });
      if (billable) {
        const billed = ai === 0;
        db.insert('billing_entries', {
          id: uuid(), caseId: c.id, narrationId: narration.id, tariffScale: `Scale ${tariffCode}`,
          amount: 650 + ai * 350, billed, ...(billed ? { billedAt: daysFromNow(-(ci + 1)) } : {}),
        });
      }
    });
  });

  const everyone = ['Admin', 'Director', 'CA', 'Secretary'];
  [
    [0, 'File notice of intention to defend', -6, everyone],
    [1, 'Follow up with Deeds Office', -2, everyone],
    [2, 'Prepare heads of argument', 3, ['Admin', 'Director', 'CA']],
    [4, 'CCMA conciliation hearing', 6, everyone],
    [5, 'Lodge Master’s office documents', 10, everyone],
    [3, 'Review settlement proposal', -9, ['Admin', 'Director']],
  ].forEach(([caseIndex, nextAction, offset, visibleToRoles]) =>
    db.insert('darzations', {
      id: uuid(), caseId: cases[caseIndex].id, ownerId: cases[caseIndex].leadAttorneyId, nextAction,
      nextActionDate: dateOnly(offset), visibleToRoles,
    }),
  );

  [
    [0, 'Letter of demand.pdf', 'Standard', 1],
    [0, 'Particulars of claim.pdf', 'Privileged', 2],
    [1, 'Power of attorney.pdf', 'Restricted', 1],
    [2, 'Shareholders agreement.pdf', 'Privileged', 3],
  ].forEach(([caseIndex, fileName, confidentiality, uploadedDaysAgo]) =>
    db.insert('documents', {
      id: uuid(), caseId: cases[caseIndex].id, fileName, version: 1, confidentiality, uploadedById: secretary.id,
      uploadedAt: daysFromNow(-uploadedDaysAgo), storedAs: 'sample.pdf',
    }),
  );
};

/**
 * Tiny JSON-file "database" (think: SQLite for a mock). Tables are arrays on
 * disk at storage/db.json so data survives restarts.
 * `node server.js --fresh` wipes and re-seeds it (like `php artisan migrate:fresh --seed`).
 */
const fs = require('fs');
const path = require('path');

const FILE = path.join(__dirname, '..', 'storage', 'db.json');
let data = {};

function persist() {
  fs.writeFileSync(FILE, JSON.stringify(data, null, 2));
}

const db = {
  load({ fresh, seed }) {
    if (!fresh && fs.existsSync(FILE)) {
      data = JSON.parse(fs.readFileSync(FILE, 'utf8'));
      return false;
    }
    data = {};
    seed(db);
    persist();
    return true;
  },
  table(name) {
    if (!data[name]) data[name] = [];
    return data[name];
  },
  find(name, id) {
    return db.table(name).find(row => String(row.id) === String(id)) || null;
  },
  insert(name, row) {
    db.table(name).push(row);
    persist();
    return row;
  },
  update(name, id, patch) {
    const row = db.find(name, id);
    if (row) {
      Object.assign(row, patch);
      persist();
    }
    return row;
  },
  remove(name, predicate) {
    data[name] = db.table(name).filter(row => !predicate(row));
    persist();
  },
  nextId(name) {
    return db.table(name).reduce((max, row) => Math.max(max, Number(row.id) || 0), 0) + 1;
  },
};

module.exports = db;

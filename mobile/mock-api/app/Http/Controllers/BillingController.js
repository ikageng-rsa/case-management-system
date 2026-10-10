const db = require('../../../database/db');
const CaseController = require('./CaseController');

exports.index = (req,res)=>{
    const found = CaseController.findOr404(req,res);
    if(!found) return;
    res.json(db.table('billing_entries').filter(b => b.caseId === found.id));
};

exports.markBilled = (req,res) =>{
    const entry = db.find('billing_entries', req.params.entryId);
    if(!entry){
        return res.status(404).json({message: `No query results for model [App\\Models\\BillingsEntry] ${req.params.entryId}`});
    }
    res.json(db.update('billing_entries',entry.id, {billed: true, billedAt: new Date().toISOString()}));

};


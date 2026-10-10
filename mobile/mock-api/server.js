/**
 * LegalCMS mock API - a stand-in for the Laravel backend while it's being built
 *  npm run api start on http://localhost:800/api (same port as `php artisan server`)
 *  npm run api:fresh wipe storage/db.json and re-seed (~ migrate: fresh --seed)
 * ENV: PORT (8000), MOCK_LATENCY_MS (300) - fake network delay so spinners are visible.
 */

const express = require('express')
const cors = require('cors');
const fs = require('fs');
const os = require('os');
const path = require('path');

const db = require('./database/db');
const seed = require('./database/seeders/DatabaseSeeder');
const routes = require('./routes/api');

const PORT = Number(process.env.PORT || 8000);
const LATENCY = Number(process.env.MOCK_LATENCY_MS ?? 300);
const FILES_DIR = path.join(__dirname, 'storage','files');

//A tiny valid PDF that the seeded document point at.
function ensureSamplePDF(){
    fs.mkdirSync(FILES_DIR, {recursive: true});
    const target = path.join(FILES_DIR, 'sample.pdf');
    if(fs.existsSync(target)) return;
    const objects =[
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        null,
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    const stream = 'BT /F1 24 Tf 72 760 Td (LegalCMS mock document) Tj ET';
    object[3] = `<< /Length ${stream.length} >>\nstream\n${stream}\nendstream`;
    let pdf = '%PDF-1.4\n';
    const offsets = [];
    objects.forEach((body,i)=>{
        offsets.push(pdf.length);
        pdf += `${i+1} 0 obj\n${body}\nendobj\n`
    });
    const xref = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n0000000000 65535 f\n`;
    offsets.forEach(o => (pdf += `${String(o).padStart(10, '0')} 00000 n \n`));
    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xref}\n%%EOF\n`;
    fs.writeFileSync(target,pdf);
}

const seeded = db.load({ fresh: process.argv.includes('--fresh'), seed});
ensureSamplePDF();

const app = express();
app.use(cors());
app.use(express.json());

app.use((req,res,next)=>{
    const started = Date.now();
    res.on('finish', ()=> console.log(`${req.method.padEnd(6)} ${req.originalUrl.padEnd(40)} ${res.statusCode} ${Date.now() - started}ms`));
    setTimeout(next, LATENCY);
});

app.use('/storage/files', express.static(FILES_DIR));
app.use('/api', routes);

//Lavavel-style fallbacks
app.use((req,res) => res.status(404).json({message: `The route ${req.originalUrl.replace(/^\//,'').split('?')[0]} could not found.`}));
app.use((err,req,res,next) =>{
    console.error(err);
    if (err instanceof SyntaxError) return res.status(400).json({ message: 'Malformed JSON.'});
    res.status(500).json({message: 'Server Error'});
});

app.listen(PORT, '0.0.0.0', ()=>{
    const lan = Object.values(os.networkInterfaces()).flat().find(i => i && i.family === 'IPv4' && !i.internal);
    console.log(`\nLegalCMS mock API ${seeded ? '(freshly seeded)':'(existing data)'}`);
    console.log(` Web /iOS simulator : http://localhost:${PORT}/api`);
    console.log(` Android emulator : http://10.0.2.2:${PORT}/api`);
    if(lan) console.log(` Physical device  : http://${lan.address}:${PORT}/api`);
    console.log('\n Login: demo@legalCMS.co.za / Demo@1234   (or cal@legalcms.test /password)\n');
});


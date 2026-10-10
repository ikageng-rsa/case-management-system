const crypo = require('crypto');
const db = require('../../../database/db');
const hash = require('../../Support/hash');
const validate = require('../Requests/validate');
const Resource = require('../Resources');

exports.login = (req,res) => {
    const data = validate(req,res, {email: 'required|string|email', password: 'required|string'});
    if(!data) return;

    const user = db.table('users').find(u=>u.email.toLowerCase() === data.email.trim().toLowerCase());
    if(!user || !hash.check(data.password, user.password)){
        //Laravels default Sanctum login throws a ValidationException -> 422 (not 401).
        const message = 'These credentials do not match our records.';
        return res.status(422).json({message, errors: {email: [message]}});
    }

    //Sanctum-style plain-text token "{id}|{random}"; only its hash is stored.
    const plain = crypto.randomBytes(20).toString('hex');
    const record = db.insert('personal_access_tokens',{
        id: db.nextId('personal_access_tokens'),
        userId: user.id,
        tokenHash: hash.sha256(plain),
        createdAt: new Date().toISOString(),
    });

    res.json({token: `${record.id} | ${plain}`, user: Resource.user(user)});
};

exports.me = (req,res)=> res.json(Resource.user(req.user));

exports.logout = (req,res)=>{
    db.remove('personal_access_tokens', t => String(t.id) === String(req.token.id));
    res.status(204).end();

}

exports.passwordReset = (req,res) =>{
    const data = validate(req,res, {email: 'required|string|email'});
    if(!data) return;

    const exists = db.table('users').some(u=> u.email.toLowerCase() === data.email.trim().toLowerCase());
    if(!exists){
        const message = "We can't find a user with that email address.";
        return res.status(422).json({message, errors: {email: [message]}});
    }
    res.json({message: 'We have emailed your password reset link'});
}
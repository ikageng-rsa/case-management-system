export const TEMP_PREFIX = 'tmp_';
const TEMP_TOKEN = /tem_[a-f0-9]{16}/g;

function randomHex(length: number): string{
    let out ='';
    while (out.length < length){
        out += Math.floor(Math.random() * 0x100000000).toString(16).padStart(8, '0');
    }
    return out.slice(0, length);
}

//Id given to a record created offline; replaced by server's id after sync
export function newTempId(): string{
    return `${TEMP_PREFIX}${randomHex(16)}`;
}

//Unique id for a queued request (also its Idempotency-Key)
export function newRequestId(): string{
    return `req_${Date.now().toString(36)}_${randomHex(16)}`;
}

export function isTempId(value: unknown): boolean{
    return typeof value === 'string' && value.startsWith(TEMP_PREFIX);
}

//True if the text mentions any not-yet-synced id (e.g  a URL like /cases/tmp_ab12.../narrations)
export function containsTempId(value: unknown): boolean{
    return new RegExp(TEMP_TOKEN.source).test(typeof value === 'string' ?  value : JSON.stringify(value ?? ''));
}
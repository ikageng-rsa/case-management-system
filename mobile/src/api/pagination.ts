import { apiClient } from "./client";
import { Document, Resource } from '@/api/jsonapi';

const MAX_PAGES = 40; //safety step, 40 x 15-25 rows is far more than one person works with

export interface AllPages {
    data: Resource[];
    included: Resource[];
    //meta of the last page 
    meta: Record<string, any>;
}

//follows  `?pages=` until the last page and merges 'data' and 'included'
export async function fetchAllPage(url: string, params: Record<string, unknown> = {}): Promise<AllPages> {
    const data: Resource[] = [];
    const included: Resource[] = [];
    let meta: Record<string, any> = {};

    for (let page = 1; page <= MAX_PAGES; page++) {
        const { data: doc } =await apiClient.get<Document<Resource[]>>(url, { params: { ...params, page } });
        data.push(...(doc.data ?? []));
        included.push(...(doc.included ??[]));
        meta = doc.meta ?? {};
        const last = Number(meta.last_page ?? 1);
        if(!doc.links?.next || page >= last){
            break;
        }
    }
    return {data, included, meta};
}
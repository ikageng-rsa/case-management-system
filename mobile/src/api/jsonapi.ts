//Minimal reader for the backend's JSON:API -style responses

export interface ResourceRef{
    id:string;
    type: string;
}

export interface Resource<A = Record<string,any>> extends ResourceRef{
    attributes?: A;
    relationships?: Record<string, {data?: ResourceRef | ResourceRef[] | null} | undefined>;
    meta?: Record<string,any>;

}

export interface Document<T = Resource | Resource[]>{
    data: T;
    included?: Resource[];
    links?: {next?: string | null};
    meta?: Record<string,any>;
}

export type Included = Map<string, Resource>;

const key = (type: string, id: string) => `${type}:${id}`;

export function indexIncluded(...lists: (Resource[] | undefined)[]): Included{
    const map: Included = new Map();
    for(const list of lists){
        for(const r of list ?? []){
            map.set(key(r.type, String(r.id)), r);
        }
    }
    return map;
}

export function includedGet(included: Included, ref?: ResourceRef | null): Resource | undefined{
    return ref ? included.get(key(ref.type, String(ref.id))) : undefined;
}

//The single related resource named `name` (e.g a matter's 'client'), or null.
export function relOne(resource: Resource, name: string): ResourceRef|null{
    const data = resource.relationships?.[name]?.data;
    return data && !Array.isArray(data) ? data: null;
}

//All related resource named `name` (e.g a matter's `assignments`)
export function relMany(resource: Resource, name: string): ResourceRef[]{
    const data = resource.relationships?.[name]?.data;
    return Array.isArray(data) ? data: [];
}
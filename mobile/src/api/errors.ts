import { isAxiosError } from "axios";

interface ApiErrorItem{//Laravel's error body has a list of these
    status?: string;
    title?: string;
    detail?: string;
    source?: {pointer?: string};
}
interface LaravelErrorBody{
    message?: string,
    /**Every v1 error: `{errors: [{status, title, detail, source: {pointer}}]}` (application/vnd.api+json) */
    errors?: ApiErrorItem[] | Record<string, string[]>;
}

const STATUS_FALLBACKS: Record<string, string> = {
    403: "You don't have permission to do this",
    404: "This could not be found. It may have been removed, or you may no longer have access.",
    429: "Too many requests, Wait a moment and try again."
};

function errorItems(body: LaravelErrorBody | undefined): ApiErrorItem[]{
    return Array.isArray(body?.errors) ? (body.errors as ApiErrorItem[]) : [];
}

//The HTTP status of a failed request, or undefined when there was no response.
export function getApiErrorStatus(error:unknown): number | undefined{
    return isAxiosError(error) ? error.response?.status : undefined;
}

/**
 * Field level validation messages from a 422, keyed by the field name ('source.pointer' "/body" -> "body").
 * Lets a form put each message next to its own input.
 */
export function getFieldErrors(error: unknown): Record<string, string>{
    const out: Record<string, string> = {};
    if(!isAxiosError(error)){
        return out;
    }
    for (const item of errorItems(error.response?.data as LaravelErrorBody | undefined)) { 
        const field = item.source?.pointer?.replace(/^\//, '') ?? '';
        if(field && item.detail && !(field in out)){
            out[field] = item.detail;
        }
    }
    return out;
}
/**
 * Turns anything thrown by apiClient into a user-facing sentance.
 * Understands Laravel's error bodies: a 422 carries
 * `{message, errors: {field: ['...]}}` - we show the first field error
 * (more useful than the "(and N more errors)" summary in 'message') 
 */

export function getApiErrorMessage(error: unknown, fallback ='Something went wrong'): string{
    if(isAxiosError(error)){
        if(!error.response){
            //In development, say which address failed - wrong host/port is the usual cause.
            const target = __DEV__ && error.config?.baseURL ? ` at ${error.config.baseURL}` : '';
            return `Cannot reach the server ${target}. Check your connection and the API address`
        }

        const body =error.response.data as LaravelErrorBody | undefined;
        const detail = errorItems(body).find(i => i.detail)?.detail;
        if(detail){
            return detail;
        }

        const legacy = body?.errors && !Array.isArray(body.errors) ? Object.values(body.errors).flat()[0] : undefined;
        return legacy ?? body?.message ?? STATUS_FALLBACKS[String(error.response.status)] ?? fallback;
    }

    return error instanceof Error && error.message ? error.message : fallback;
}
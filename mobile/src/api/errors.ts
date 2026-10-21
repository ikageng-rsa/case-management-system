import { isAxiosError } from "axios";

interface LaravelErrorBody{
    message?: string,
    errors?: Record<string, string[]>;
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
        const firstFieldError = body?.errors ? Object.values(body.errors).flat()[0] : undefined;
        return firstFieldError ?? body?.message ?? fallback;
    }

    return error instanceof Error && error.message ? error.message : fallback;
}
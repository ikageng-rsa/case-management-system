
export function getErrorMessage(error: unknown): string {
    if (typeof error === 'string') {
        return error;
    }

    if (!error || typeof error !== 'object') {
        return 'An unexpected error occurred. Please try again.';
    }

    const details = error as Record<string, unknown>;

    // Axios errors contain the API response under response.data.
    const response = details.response;

    if (response && typeof response === 'object') {
        const data = (response as Record<string, unknown>).data;

        if (data !== undefined) {
            return getErrorMessage(data);
        }
    }

    // Your API format: { errors: [{ detail: "..." }] }
    if (Array.isArray(details.errors)) {
        const messages = details.errors
            .map(item => {
                if (!item || typeof item !== 'object') {
                    return null;
                }

                const apiError = item as Record<string, unknown>;

                if (typeof apiError.detail === 'string') {
                    return apiError.detail;
                }

                if (typeof apiError.title === 'string') {
                    return apiError.title;
                }

                return null;
            })
            .filter((message): message is string => message !== null);

        if (messages.length > 0) {
            return messages.join('\n');
        }
    }

    if (typeof details.message === 'string') {
        return details.message;
    }

    if (typeof details.detail === 'string') {
        return details.detail;
    }

    return 'An unexpected error occurred. Please try again.';
}

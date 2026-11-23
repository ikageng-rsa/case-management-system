import { toast, ToastTone } from '@/ui';

/**
 * Short, non-blocking messages in place of `Alert.alert`.
 * Successes clear themselves after a few seconds; problems stay until dismissed
 * (the toast defaults), so a validation or save error is never missed.
 */
function show(tone: ToastTone, title: string, message?: string) {
    toast({ tone, message: message ? `${title}. ${message}` : title });
}

export const notifyError = (title: string, message?: string) => show('danger', title, message);
export const notifyWarning = (title: string, message?: string) => show('warning', title, message);
export const notifySuccess = (title: string, message?: string) => show('success', title, message);

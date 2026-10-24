//The short-lived reset token the backend returns after the email code is verified. kept in memory only - 
//not in route params (which can end up in navigation state/log) and not on disk

let resetToken: string |null = null;

export const setResetToken = (token: string | null): void =>{
    resetToken = token;
}
export const takeResetToken = (): string | null => resetToken;
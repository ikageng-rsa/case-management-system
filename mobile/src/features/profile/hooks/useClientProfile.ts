import { useCallback, useState } from 'react';
import { Alert } from 'react-native';
import { useFocusEffect } from 'expo-router';
import {
    addClientContact,
    fetchClient,
    recordClientConsent,
    updateClient,
    ClientChanges,
} from '@/api/endpoint/clients.api';
import { fetchCases } from '@/api/endpoint/cases.api';
import { getApiErrorMessage } from '@/api/errors';
import { useAppSelector } from '@/hooks/useAppDispatch';
import { canEditRecords } from '@/constants/roles';
import { visibleCasesFor } from '@/utils/caseAccess';
import { Case, ClientContactType, ClientProfile, PopiaConsentType } from '@/models/index';

// Same rule the backend enforces for FICA status and risk rating.
const COMPLIANCE_ROLES = new Set(['Admin', 'Director', 'CA']);

export function useClientProfile(clientId: string) {
    const me = useAppSelector(state => state.auth.user);
    const [client, setClient] = useState<ClientProfile | null>(null);
    const [cases, setCases] = useState<Case[]>([]);
    const [offline, setOffline] = useState(false);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const load = useCallback(() => {
        Promise.all([fetchClient(clientId), fetchCases().catch(() => [] as Case[])])
            .then(([result, all]) => {
                setClient(result.client);
                setOffline(result.offline);
                setCases(visibleCasesFor(all, me).filter(c => c.clientId === clientId));
                setFailed(null);
            })
            .catch(e => setFailed(getApiErrorMessage(e, 'This client is not available.')))
            .finally(() => setLoading(false));
    }, [clientId, me]);

    // Reload whenever the screen is shown, so edits made elsewhere (and new cases) appear.
    useFocusEffect(
        useCallback(() => {
            load();
        }, [load]),
    );

    /** One place for "call the API, store the new profile, tell the user if it failed". */
    const run = async (task: () => Promise<ClientProfile>, errorTitle: string, success?: string) => {
        setSaving(true);
        try {
            setClient(await task());
            if (success) {
                Alert.alert('Saved', success);
            }
            return true;
        } catch (e) {
            Alert.alert(errorTitle, getApiErrorMessage(e, 'Please try again.'));
            return false;
        } finally {
            setSaving(false);
        }
    };

    const save = (changes: ClientChanges, success?: string) =>
        run(() => updateClient(clientId, changes), 'Could not save', success);

    const recordConsent = (consentType: PopiaConsentType, granted: boolean) =>
        run(() => recordClientConsent(clientId, { consentType, granted }), 'Could not save');

    const addContact = (type: ClientContactType, value: string) =>
        run(() => addClientContact(clientId, { type, value }), 'Could not add contact');

    const role = me?.role;
    return {
        client,
        cases,
        offline,
        loading,
        failed,
        saving,
        canEdit: Boolean(role) && canEditRecords(role!) && !offline,
        canCompliance: Boolean(role) && COMPLIANCE_ROLES.has(role!) && !offline,
        canCreateCase: canEditRecords(role ?? 'Messenger'),
        save,
        recordConsent,
        addContact,
    };
}
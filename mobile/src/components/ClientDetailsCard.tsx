import React, { useState } from 'react';
import { Alert, Text } from 'react-native';
import Button from '@/components/Button';
import Card from '@/components/Card';
import DetailRow from '@/components/DetailRow';
import Input from '@/components/Input';
import { ClientChanges } from '@/api/endpoint/clients.api';
import { isValidEmail, isValidSAPhoneNumber } from '@/utils/validators';
import { ClientProfile } from '@/models/index';
import { styles } from '@/features/profile/clientProfileStyles';

interface Props {
    client: ClientProfile;
    canEdit: boolean;
    saving: boolean;
    onSave: (changes: ClientChanges) => Promise<boolean>;
}

function validateDetails(phone: string, email: string): { title: string; message: string } | null {
    if (phone.trim() && !isValidSAPhoneNumber(phone)) {
        return { title: 'Check the phone number', message: 'Use a South African number, e.g. 082 123 4567 or +27 82 123 4567.' };
    }
    if (email.trim() && !isValidEmail(email)) {
        return { title: 'Check the e-mail address', message: 'That address does not look right.' };
    }
    return null;
}

function DetailsView({ client, canEdit, onEdit }: Readonly<{ client: ClientProfile; canEdit: boolean; onEdit: () => void }>) {
    const hasContact = Boolean(client.contactNumber || client.email || client.address);
    return (
        <Card style={styles.card}>
            <DetailRow label="Phone" value={client.contactNumber} />
            <DetailRow label="E-mail" value={client.email} />
            <DetailRow label="Address" value={client.address} />
            <DetailRow label="ID number" value={client.idNumberMasked} />
            <DetailRow label="Notes" value={client.notes} />
            {hasContact ? null : <Text style={styles.muted}>No contact details recorded yet.</Text>}
            {canEdit ? <Button label="Edit details" variant="secondary" onPress={onEdit} style={styles.gap} /> : null}
        </Card>
    );
}

function DetailsForm({ client, saving, onSave, onDone }: Omit<Props, 'canEdit'> & { onDone: () => void }) {
    const [phone, setPhone] = useState(client.contactNumber ?? '');
    const [email, setEmail] = useState(client.email ?? '');
    const [address, setAddress] = useState(client.address ?? '');
    const [notes, setNotes] = useState(client.notes ?? '');
    const [idNumber, setIdNumber] = useState('');

    const submit = async () => {
        const problem = validateDetails(phone, email);
        if (problem) {
            Alert.alert(problem.title, problem.message);
            return;
        }
        const changes: ClientChanges = { contactNumber: phone.trim(), email: email.trim(), address: address.trim(), notes: notes.trim() };
        if (idNumber.trim()) {
            changes.idNumber = idNumber.trim();
        }
        if (await onSave(changes)) {
            onDone();
        }
    };

    return (
        <Card style={styles.card}>
            <Text style={styles.cardTitle}>Edit details</Text>
            <Input label="Phone" value={phone} onChangeText={setPhone} keyboardType="phone-pad" placeholder="082 123 4567" />
            <Input label="E-mail" value={email} onChangeText={setEmail} keyboardType="email-address" autoCapitalize="none" />
            <Input label="Address" value={address} onChangeText={setAddress} multiline style={styles.multiline} />
            <Input
                label={client.idNumberMasked ? `ID number (saved: ${client.idNumberMasked}) — leave blank to keep` : 'ID number'}
                value={idNumber}
                onChangeText={setIdNumber}
                keyboardType="number-pad"
                maxLength={13}
            />
            <Input label="Notes" value={notes} onChangeText={setNotes} multiline style={styles.multiline} />
            <Button label="Save details" onPress={submit} loading={saving} />
            <Button label="Cancel" variant="secondary" onPress={onDone} style={styles.gap} />
        </Card>
    );
}

export default function ClientDetailsCard({ client, canEdit, saving, onSave }: Props) {
    const [editing, setEditing] = useState(false);
    return editing ? (
        <DetailsForm client={client} saving={saving} onSave={onSave} onDone={() => setEditing(false)} />
    ) : (
        <DetailsView client={client} canEdit={canEdit} onEdit={() => setEditing(true)} />
    );
}
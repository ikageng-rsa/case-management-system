import React, { useState } from 'react';
import { Alert, Text } from 'react-native';
import Button from '@/components/Button';
import Card from '@/components/Card';
import ChipSelect from '@/components/ChipSelect';
import Input from '@/components/Input';
import SectionHeader from '@/components/SectionHeader';
import { ClientContactType, ClientProfile } from '@/models/index';
import { styles } from '@/features/profile/clientProfileStyles';

const CONTACT_TYPES: ClientContactType[] = ['Phone', 'Email', 'Fax', 'Physical Address'];

interface Props {
    client: ClientProfile;
    canEdit: boolean;
    onAdd: (type: ClientContactType, value: string) => Promise<boolean>;
}

function AddContactForm({ onAdd }: { onAdd: Props['onAdd'] }) {
    const [type, setType] = useState<ClientContactType[]>(['Phone']);
    const [value, setValue] = useState('');
    const [adding, setAdding] = useState(false);

    const submit = async () => {
        if (!value.trim()) {
            Alert.alert('Missing info', 'Enter the contact detail first.');
            return;
        }
        setAdding(true);
        const ok = await onAdd(type[0] ?? 'Phone', value.trim());
        setAdding(false);
        if (ok) {
            setValue('');
        }
    };

    return (
        <Card style={styles.card}>
            <ChipSelect label="Add a contact" options={CONTACT_TYPES.map(t => ({ value: t, label: t }))} selected={type} onChange={setType} />
            <Input label="Detail" value={value} onChangeText={setValue} autoCapitalize="none" />
            <Button label="+ Add contact" variant="secondary" onPress={submit} loading={adding} />
        </Card>
    );
}

export default function ClientContactsSection({ client, canEdit, onAdd }: Props) {
    const contacts = client.contacts ?? [];
    return (
        <>
            <SectionHeader title="Other contacts" />
            {contacts.length === 0 ? <Text style={styles.muted}>No additional contacts.</Text> : null}
            {contacts.map(c => (
                <Card key={c.id}>
                    <Text style={styles.consentLabel}>
                        {c.type}
                        {c.isPrimary ? ' · primary' : ''}
                    </Text>
                    <Text style={styles.body}>{c.value}</Text>
                </Card>
            ))}
            {canEdit ? <AddContactForm onAdd={onAdd} /> : null}
        </>
    );
}
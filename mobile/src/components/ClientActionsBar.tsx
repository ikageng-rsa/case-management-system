import React from 'react';
import { Alert, Linking, Pressable, Text, View } from 'react-native';
import { callLink, emailLink, smsLink, whatsAppLink } from '@/utils/contactLinks';
import { styles } from '@/features/profile/clientProfileStyles';

async function open(url: string, what: string) {
    try {
        await Linking.openURL(url);
    } catch {
        Alert.alert(`Cannot ${what}`, 'This phone has no app that can do that.');
    }
}

function ActionButton({ label, onPress, disabled }:Readonly< { label: string; onPress: () => void; disabled?: boolean }>) {
    return (
        <Pressable
            accessibilityRole="button"
            accessibilityState={{ disabled: Boolean(disabled) }}
            disabled={disabled}
            onPress={onPress}
            style={[styles.action, disabled && styles.actionDisabled]}
        >
            <Text style={[styles.actionText, disabled && styles.actionTextDisabled]}>{label}</Text>
        </Pressable>
    );
}

export default function ClientActionsBar({ phone, email }:Readonly< { phone?: string; email?: string }>) {
    const noPhone = !phone;
    return (
        <View style={styles.actions}>
            <ActionButton label="Call" disabled={noPhone} onPress={() => open(callLink(phone ?? ''), 'call')} />
            <ActionButton label="SMS" disabled={noPhone} onPress={() => open(smsLink(phone ?? ''), 'send an SMS')} />
            <ActionButton label="WhatsApp" disabled={noPhone} onPress={() => open(whatsAppLink(phone ?? ''), 'open WhatsApp')} />
            <ActionButton label="E-mail" disabled={!email} onPress={() => open(emailLink(email ?? ''), 'send an e-mail')} />
        </View>
    );
}
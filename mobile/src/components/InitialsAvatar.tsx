import React from 'react';
import { StyleSheet, Text, View , ViewStyle} from 'react-native';
import { colors, typography } from '@/theme/index';
import {getInitials} from '@/utils/initials';

interface InitialsAvatarProps{
    readonly name: string;
    readonly size?: number;
    readonly style?: ViewStyle;
}

export default function InitialsAvatar({name,size = 40,style}: InitialsAvatarProps){
    const initials = getInitials(name);
    return(
        <View style={[styles.circle, {width: size, height: size, borderRadius: size / 2}, style]}>
            <Text style={[styles.initials, {fontSize: size / 2}]}>{initials}</Text>
        </View>
    );
}

const styles = StyleSheet.create({
    circle:{
        backgroundColor: colors.primary,
        justifyContent: 'center',
        alignItems: 'center',
        borderWidth: 2,
        borderColor: colors.gold
    },
    initials:{
        ...typography.h2,
        color: colors.textOnDark,
    }
})
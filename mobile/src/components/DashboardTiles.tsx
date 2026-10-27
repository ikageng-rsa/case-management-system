import React from 'react';
import { Pressable, Text, View } from 'react-native';
import Card from '@/components/Card';
import { Tile } from '../utils/dashboardTiles';
import { styles } from '@/features/reporting/dashboardStyles';

export default function DashboardTiles({ tiles }: { tiles: Tile[] }) {
    return (
        <View style={styles.grid}>
            {tiles.map(tile => (
                <Pressable key={tile.label} style={styles.tileWrap} disabled={!tile.onPress} onPress={tile.onPress}>
                    <Card style={styles.tile}>
                        <Text style={[styles.tileValue, { color: tile.color }]}>{tile.value}</Text>
                        <Text style={styles.tileLabel}>{tile.label}</Text>
                    </Card>
                </Pressable>
            ))}
        </View>
    );
}
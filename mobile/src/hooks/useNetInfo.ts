import { useEffect, useState } from 'react';
import NetInfo, { NetInfoState } from '@react-native-community/netinfo';

/**
 * Backs OfflineBanner and any screen that needs to know before letting an
 * attorney submit a Narration/Darzation entry from a courtroom with patchy
 * signal — see the offline-first note in the README.
 */
export function useNetInfo(): { isOnline: boolean; state: NetInfoState | null } {
  const [state, setState] = useState<NetInfoState | null>(null);

  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener(setState);
    NetInfo.fetch().then(setState);
    return unsubscribe;
  }, []);

  // isConnected can be null before the first event resolves — treat that as
  // online rather than flashing an offline banner on cold start.
  const isOnline = state?.isConnected !== false;

  return { isOnline, state };
}

import React from 'react';
import { render, waitFor } from '@testing-library/react-native';
import NetInfo from '@react-native-community/netinfo';
import OfflineBanner from '../src/components/OfflineBanner';
import { beforeEach,describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';
const mockedFetch = NetInfo.fetch as jest.Mock;

describe('OfflineBanner', () => {
  beforeEach(() => {
    mockedFetch.mockReset();
  });

  it('renders nothing while online', async () => {
    mockedFetch.mockResolvedValue({ isConnected: true } as never);
    const { queryByTestId } =await render(<OfflineBanner />);

    await waitFor(() => {
      expect(queryByTestId('offline-banner')).toBeNull();
    });
  });

  it('renders the banner message when offline', async () => {
    mockedFetch.mockResolvedValue({ isConnected: false } as never);
    const { findByTestId, findByText } = await render(<OfflineBanner />);

    expect(await findByTestId('offline-banner')).toBeTruthy();
    expect(await findByText(/You're offline/)).toBeTruthy();
  });
});

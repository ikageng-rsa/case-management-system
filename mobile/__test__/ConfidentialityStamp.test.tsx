import React from 'react';
import { render } from '@testing-library/react-native';
import ConfidentialityStamp from '../src/components/ConfidentialityStamp';
import { colors } from '@/theme/index';
import { describe, expect, it } from '@jest/globals';

describe('ConfidentialityStamp', () => {
  it('renders the level label in uppercase', () => {
    const { getByText } = render(<ConfidentialityStamp level="Standard" />);
    expect(getByText('STANDARD')).toBeTruthy();
  });

  it('renders Restricted correctly', () => {
    const { getByText } = render(<ConfidentialityStamp level="Restricted" />);
    expect(getByText('RESTRICTED')).toBeTruthy();
  });

  it('renders Privileged correctly', () => {
    const { getByText } = render(<ConfidentialityStamp level="Privileged" />);
    expect(getByText('PRIVILEGED')).toBeTruthy();
  });

  it('uses the burgundy accent color for Privileged', () => {
    const { getByText } = render(<ConfidentialityStamp level="Privileged" />);
    const label = getByText('PRIVILEGED');
    const flatStyle = [label.props.style].flat();
    const hasColor = flatStyle.some((s: any) => s?.color === colors.burgundy);
    expect(hasColor).toBe(true);
  });
});

import React from 'react';
import { render } from '@testing-library/react-native';
import InitialsAvatar from '../src/components/InitialsAvatar';
import { describe, expect, it } from '@jest/globals';

describe('InitialsAvatar', () => {
  it('renders initials for a full name', () => {
    const { getByText } = render(<InitialsAvatar name="Thandiwe Mokoena" />);
    expect(getByText('TM')).toBeTruthy();
  });

  it('renders initials for a single-word name', () => {
    const { getByText } = render(<InitialsAvatar name="Prince" />);
    expect(getByText('PR')).toBeTruthy();
  });

  it('applies a custom size to the circle', () => {
    const { getByText } = render(<InitialsAvatar name="Thandiwe Mokoena" size={64} />);
    const label = getByText('TM');
    const flatStyle = [label.props.style].flat();
    const hasFontSize = flatStyle.some((s: any) => s?.fontSize === 64 * 0.4);
    expect(hasFontSize).toBe(true);
  });
});

import React from 'react';
import { Text } from 'react-native';
import { render } from '@testing-library/react-native';
import SectionHeader from '../src/components/SectionHeader';
import { describe, expect, it } from '@jest/globals';

describe('SectionHeader', () => {
  it('renders the title', () => {
    const { getByText } = render(<SectionHeader title="Case File" />);
    expect(getByText('Case File')).toBeTruthy();
  });

  it('renders the eyebrow label when provided', () => {
    const { getByText } = render(<SectionHeader title="Case File" eyebrow="Litigation" />);
    expect(getByText('Litigation')).toBeTruthy();
  });

  it('omits the eyebrow when not provided', () => {
    const { queryByText } = render(<SectionHeader title="Case File" />);
    expect(queryByText('Litigation')).toBeNull();
  });

  it('renders trailing content when provided', () => {
    const { getByText } = render(<SectionHeader title="Cases" trailing={<Text>See all</Text>} />);
    expect(getByText('See all')).toBeTruthy();
  });
});

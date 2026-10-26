import React from 'react';
import { Text } from 'react-native';
import { render } from '@testing-library/react-native';
import SectionHeader from '../src/components/SectionHeader';
import { describe, expect, it } from '@jest/globals';

describe('SectionHeader', () => {
  it('renders the title',async () => {
    const { getByText } =await render(<SectionHeader title="Case File" />);
    expect(getByText('Case File')).toBeTruthy();
  });

  it('renders the eyebrow label when provided',async () => {
    const { getByText } =await render(<SectionHeader title="Case File" eyebrow="Litigation" />);
    expect(getByText('Litigation')).toBeTruthy();
  });

  it('omits the eyebrow when not provided',async () => {
    const { queryByText } =await render(<SectionHeader title="Case File" />);
    expect(queryByText('Litigation')).toBeNull();
  });

  it('renders trailing content when provided',async () => {
    const { getByText } =await render(<SectionHeader title="Cases" trailing={<Text>See all</Text>} />);
    expect(getByText('See all')).toBeTruthy();
  });
});

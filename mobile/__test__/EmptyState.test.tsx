import React from 'react';
import { render } from '@testing-library/react-native';
import { describe, expect, it } from '@jest/globals';
import EmptyState from '../src/components/EmptyState';

describe('EmptyState', () => {
  it('renders the title', async() => {
    const { getByText } =await render(<EmptyState title="No cases yet" />);
    expect(getByText('No cases yet')).toBeTruthy();
  });

  it('renders the optional message when provided', async() => {
    const { getByText } =await render(
      <EmptyState title="No cases yet" message="Cases you're assigned to will show up here." />,
    );
    expect(getByText("Cases you're assigned to will show up here.")).toBeTruthy();
  });

  it('does not render a message element when none is provided',async () => {
    const { queryByText } =await render(<EmptyState title="No cases yet" />);
    expect(queryByText(/assigned/)).toBeNull();
  });
});

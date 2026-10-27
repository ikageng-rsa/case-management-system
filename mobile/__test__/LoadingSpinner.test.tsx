import React from 'react';
import { render } from '@testing-library/react-native';
import { ActivityIndicator } from 'react-native';
import LoadingSpinner from '../src/components/LoadingSpinner';
import { describe, expect, it } from '@jest/globals';

describe('LoadingSpinner', () => {
  it('renders an ActivityIndicator', () => {
    const { UNSAFE_getByType } = render(<LoadingSpinner />);
    expect(UNSAFE_getByType(ActivityIndicator)).toBeTruthy();
  });
});

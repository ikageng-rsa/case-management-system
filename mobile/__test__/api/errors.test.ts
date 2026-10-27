import { AxiosError } from 'axios';
import { getApiErrorMessage } from '@/api/errors';
import { describe, expect, it } from '@jest/globals';

const axiosError = (status: number | null, data?: unknown) => {
  const error = new AxiosError('Request failed');
  if (status !== null) {
    error.response = { status, data, statusText: '', headers: {}, config: {} as never };
  }
  return error;
};

describe('getApiErrorMessage', () => {
  it('prefers the first Laravel field error over the summary message', () => {
    const error = axiosError(422, {
      message: 'The email field is required. (and 1 more error)',
      errors: { email: ['The email field is required.'], password: ['The password field is required.'] },
    });
    expect(getApiErrorMessage(error)).toBe('The email field is required.');
  });

  it('falls back to the response message', () => {
    expect(getApiErrorMessage(axiosError(403, { message: 'This action is unauthorized.' }))).toBe('This action is unauthorized.');
  });

  it('explains network failures (no response)', () => {
    expect(getApiErrorMessage(axiosError(null))).toMatch(/cannot reach the server/i);
  });

  it('handles plain errors and unknown values', () => {
    expect(getApiErrorMessage(new Error('boom'))).toBe('boom');
    expect(getApiErrorMessage('???', 'Fallback')).toBe('Fallback');
  });
});

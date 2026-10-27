import { popiaLogger } from '@/utils/popiaLogger';
import { beforeEach, afterEach, describe, expect, it } from '@jest/globals';
import { jest } from '@jest/globals';

describe('popiaLogger', () => {
  let errorSpy: ReturnType<typeof jest.spyOn>;
  let warnSpy: ReturnType<typeof jest.spyOn>;

  beforeEach(() => {
    errorSpy = jest.spyOn(console, 'error').mockImplementation(() => { });
    warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => { });
  });

  afterEach(() => {
    errorSpy.mockRestore();
    warnSpy.mockRestore();
  });

  it('redacts known sensitive top-level keys', () => {
    popiaLogger.error('failed to save client', {
      clientName: 'Thandiwe Mokoena',
      email: 'thandiwe@example.com',
      riskRating: 'Low',
    });

    const loggedPayload = errorSpy.mock.calls[0][1];
    expect(loggedPayload).toEqual({
      clientName: '[redacted]',
      email: '[redacted]',
      riskRating: 'Low',
    });
  });

  it('redacts sensitive keys nested inside objects', () => {
    popiaLogger.warn('narration payload', {
      caseId: 'case-1',
      author: { fullName: 'John Smith', role: 'CA' },
    });

    const loggedPayload = warnSpy.mock.calls[0][1];
    expect(loggedPayload).toEqual({
      caseId: 'case-1',
      author: { fullName: '[redacted]', role: 'CA' },
    });
  });

  it('redacts sensitive keys inside arrays of objects', () => {
    popiaLogger.error('bulk sync', {
      clients: [
        { clientName: 'A', ficaVerified: true },
        { clientName: 'B', ficaVerified: false },
      ],
    });

    const loggedPayload = errorSpy.mock.calls[0][1];
    expect(loggedPayload).toEqual({
      clients: [
        { clientName: '[redacted]', ficaVerified: '[redacted]' },
        { clientName: '[redacted]', ficaVerified: '[redacted]' },
      ],
    });
  });

  it('does not choke on circular references', () => {
    const payload: any = { caseId: 'case-1' };
    payload.self = payload;

    expect(() => popiaLogger.error('circular test', payload)).not.toThrow();
  });

  it('passes non-object payloads through unredacted', () => {
    popiaLogger.error('simple message', 'just a string');
    expect(errorSpy.mock.calls[0][1]).toBe('just a string');
  });

  it('logs without a payload without throwing', () => {
    expect(() => popiaLogger.warn('no payload here')).not.toThrow();
  });
});

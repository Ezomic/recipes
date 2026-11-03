import { describe, expect, it } from 'vitest';
import { MAX_TAGS, normaliseTag } from '@/lib/tags';

describe('normaliseTag', () => {
    it('trims and lowercases', () => {
        expect(normaliseTag('  Kamperen ')).toBe('kamperen');
    });

    it('collapses internal whitespace, including non-breaking spaces', () => {
        expect(normaliseTag('een   pans\t\u00a0gerecht')).toBe(
            'een pans gerecht',
        );
    });

    it('removes zero-width characters', () => {
        expect(normaliseTag('\u200bkamp\u200beren\ufeff')).toBe('kamperen');
    });

    it('lowercases Unicode', () => {
        expect(normaliseTag('CRÈME')).toBe('crème');
    });

    it('returns an empty string for blank input', () => {
        expect(normaliseTag(' \u00a0\u200b ')).toBe('');
    });

    it('exposes the limit the server enforces', () => {
        expect(MAX_TAGS).toBe(20);
    });
});

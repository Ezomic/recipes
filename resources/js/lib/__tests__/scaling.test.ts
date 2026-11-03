import { describe, expect, it } from 'vitest';
import { formatQuantity, scaleQuantity } from '@/lib/scaling';

describe('scaleQuantity', () => {
    it('returns the stored quantity untouched at the recipe servings', () => {
        expect(scaleQuantity(125.5, 'g', 4, 4)).toBe(125.5);
        expect(scaleQuantity(0.3, 'eetlepel', 4, 4)).toBe(0.3);
    });

    it('scales proportionally to the wanted servings', () => {
        expect(scaleQuantity(200, 'g', 4, 2)).toBe(100);
        expect(scaleQuantity(200, 'g', 4, 8)).toBe(400);
        expect(scaleQuantity(1, 'l', 4, 2)).toBe(0.5);
    });

    it('rounds g and ml to whole numbers from 10 up and one decimal below', () => {
        expect(scaleQuantity(100, 'g', 3, 1)).toBe(33);
        expect(scaleQuantity(250, 'ml', 7, 2)).toBe(71);
        expect(scaleQuantity(5, 'g', 3, 2)).toBe(3.3);
        expect(scaleQuantity(9.96, 'ml', 1, 1)).toBe(9.96);
        expect(scaleQuantity(9.96, 'ml', 2, 1)).toBe(5);
    });

    it('rounds kg and l to two decimals', () => {
        expect(scaleQuantity(1, 'kg', 3, 1)).toBe(0.33);
        expect(scaleQuantity(2, 'l', 3, 2)).toBe(1.33);
    });

    it('rounds spoons to the nearest quarter', () => {
        expect(scaleQuantity(1, 'eetlepel', 3, 1)).toBe(0.25);
        expect(scaleQuantity(1, 'theelepel', 3, 2)).toBe(0.75);
        expect(scaleQuantity(1, 'theelepel', 5, 3)).toBe(0.5);
    });

    it('rounds pieces, and rows without a unit, to the nearest half', () => {
        expect(scaleQuantity(2, 'stuks', 4, 3)).toBe(1.5);
        expect(scaleQuantity(3, 'stuks', 4, 3)).toBe(2.5);
        expect(scaleQuantity(3, null, 4, 2)).toBe(1.5);
        expect(scaleQuantity(1, 'stuks', 4, 3)).toBe(1);
    });

    it('never rounds a positive quantity down to zero', () => {
        expect(scaleQuantity(1, 'g', 999, 1)).toBe(0.1);
        expect(scaleQuantity(1, 'kg', 999, 1)).toBe(0.01);
        expect(scaleQuantity(1, 'eetlepel', 999, 1)).toBe(0.25);
        expect(scaleQuantity(1, 'stuks', 999, 1)).toBe(0.5);
    });

    it('does not convert between units', () => {
        expect(scaleQuantity(500, 'g', 1, 4)).toBe(2000);
    });
});

describe('formatQuantity', () => {
    it('writes whole numbers plainly and decimals with a Dutch comma', () => {
        expect(formatQuantity(250, 'g')).toBe('250');
        expect(formatQuantity(0.5, 'kg')).toBe('0,5');
        expect(formatQuantity(125.25, 'g')).toBe('125,25');
        expect(formatQuantity(3.3, 'ml')).toBe('3,3');
    });

    it('writes quarters of spoons and pieces as fractions', () => {
        expect(formatQuantity(0.25, 'eetlepel')).toBe('¼');
        expect(formatQuantity(1.5, 'theelepel')).toBe('1½');
        expect(formatQuantity(2.75, 'eetlepel')).toBe('2¾');
        expect(formatQuantity(0.5, 'stuks')).toBe('½');
        expect(formatQuantity(3, 'stuks')).toBe('3');
        expect(formatQuantity(1.5, null)).toBe('1½');
    });

    it('falls back to a decimal when a spoon quantity is not a quarter', () => {
        expect(formatQuantity(0.3, 'eetlepel')).toBe('0,3');
    });
});

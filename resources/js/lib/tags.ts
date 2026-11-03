export const MAX_TAGS = 20;
export const MAX_TAG_LENGTH = 50;

export function normaliseTag(name: string): string {
    return name
        .replace(/[\u200b-\u200f\u00ad\u180e\u2060\ufeff]/g, '')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

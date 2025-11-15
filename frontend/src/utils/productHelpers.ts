/**
 * Utility functions for handling product data
 */

/**
 * Normalizes product tags to ensure they're always an array
 * Handles cases where tags might be a JSON string or undefined
 */
export function normalizeTags(tags: string[] | string | null | undefined): string[] {
  // If already an array, return as-is
  if (Array.isArray(tags)) {
    return tags;
  }

  // If it's a string, try to parse it as JSON
  if (typeof tags === 'string' && tags.trim()) {
    try {
      const parsed = JSON.parse(tags);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      // If JSON parsing fails, it might be a comma-separated string
      return tags.split(',').map(tag => tag.trim()).filter(Boolean);
    }
  }

  // Default to empty array
  return [];
}

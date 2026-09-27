/** دمج أصناف مشروط، بلا اعتمادية خارجية. */
export function cn(...parts: Array<string | false | null | undefined>): string {
  return parts.filter(Boolean).join(' ');
}

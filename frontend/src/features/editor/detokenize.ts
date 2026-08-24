/**
 * The inverse of the tokenizer, ported from InlineTokenizer::detokenize().
 * The editor swaps one preview node per keystroke and cannot ask the backend to
 * compose the chapter that often. Every rule here mirrors the PHP one on
 * purpose - the tests use the very inputs the PHP tests use.
 */
const TOKEN = /\[(\/?)(\d+)(\/?)\]/g

export function detokenize(text: string, placeholders: Record<string, string>): string {
  let result = ''
  let offset = 0

  TOKEN.lastIndex = 0

  for (let match = TOKEN.exec(text); null !== match; match = TOKEN.exec(text)) {
    result += escapeText(text.slice(offset, match.index))

    const opening = placeholders[match[2]]

    if (undefined === opening) {
      // Nieznany zeton zostaje doslownie - lepiej zostawic slad w tekscie niz
      // po cichu zjesc fragment tresci.
      result += match[0]
    } else if ('' !== match[1]) {
      result += `</${tagName(opening)}>`
    } else {
      result += opening
    }

    offset = match.index + match[0].length
  }

  return result + escapeText(text.slice(offset))
}

/** The tokens a text carries, order-independent. */
export function tokenSignature(text: string): string {
  return [...(text.match(/\[\/?\d+\/?\]/g) ?? [])].sort().join('')
}

function tagName(openingMarkup: string): string {
  return /^<([a-z0-9]+)/i.exec(openingMarkup)?.[1] ?? 'span'
}

function escapeText(text: string): string {
  // htmlspecialchars($text, ENT_NOQUOTES | ENT_XML1) - cudzyslowy zostaja
  // nietkniete, bo tekst nigdy nie trafia do wartosci atrybutu.
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
}

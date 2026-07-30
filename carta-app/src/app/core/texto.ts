/**
 * Normalização para pesquisa em português.
 *
 * Procurar "cedencia" tem de encontrar "cedência" e "PARAGEM" tem de encontrar
 * "paragem": decompõe os caracteres, remove os diacríticos combinantes e
 * aplica minúsculas com as regras de pt.
 */
export function normalizarTexto(texto: string | null | undefined): string {
    return (texto || '')
        .toLocaleLowerCase('pt')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .trim();
}

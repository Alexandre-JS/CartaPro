"""Gera o pacote offline de estudo a partir do PDF oficial do INATRO."""

import json
import re
import sys
from pathlib import Path

import fitz


CATEGORIAS = [
    {
        "id": "sinalizacao",
        "titulo": "Sinais de trânsito",
        "descricao": "Sinalização das vias, sinais, hierarquia das prescrições e veículos prioritários.",
        "icone": "warning-outline",
        "intervalos": [[1, 15]],
    },
    {
        "id": "conduta-condutor",
        "titulo": "Conduta do condutor",
        "descricao": "Comportamento seguro, sinais dos condutores, álcool, segurança e atuação em avarias ou acidentes.",
        "icone": "person-outline",
        "intervalos": [[16, 28], [80, 92]],
    },
    {
        "id": "regras-gerais",
        "titulo": "Regras gerais de circulação",
        "descricao": "Velocidade, prioridade, manobras, estacionamento, transporte, iluminação e circulação em vias especiais.",
        "icone": "car-outline",
        "intervalos": [[29, 79]],
    },
    {
        "id": "peoes-e-veiculos-especiais",
        "titulo": "Peões e veículos especiais",
        "descricao": "Motociclos, ciclomotores, velocípedes, animais e regras de circulação dos peões.",
        "icone": "walk-outline",
        "intervalos": [[93, 107]],
    },
    {
        "id": "veiculos",
        "titulo": "Veículos, inspeções e matrículas",
        "descricao": "Classificação, características, transformação, inspeção, matrícula e identificação dos veículos.",
        "icone": "construct-outline",
        "intervalos": [[108, 124]],
    },
    {
        "id": "habilitacao",
        "titulo": "Carta e habilitação para conduzir",
        "descricao": "Títulos de condução, requisitos, categorias, exames, validade e restrições.",
        "icone": "card-outline",
        "intervalos": [[125, 136]],
    },
    {
        "id": "multas",
        "titulo": "Infrações, multas e sanções",
        "descricao": "Contravenções, classificação, multas, reincidência, inibição e cassação do título.",
        "icone": "cash-outline",
        "intervalos": [[137, 150]],
    },
    {
        "id": "acidentes-e-processo",
        "titulo": "Acidentes, fiscalização e processo",
        "descricao": "Acidentes, seguro, apreensões, remoção de veículos, autos, decisões, pagamentos e recursos.",
        "icone": "shield-checkmark-outline",
        "intervalos": [[151, 186]],
    },
]


def limpar_texto(texto: str) -> str:
    texto = texto.replace("\u00ad", "").replace("\uf0b7", "•")
    texto = re.sub(r"(?m)^\s*\d+\s*$", "", texto)
    texto = re.sub(r"(?m)^CÓDIGO\s+DA\s+ESTRADA\s*$", "", texto, flags=re.I)
    texto = re.sub(r"(?m)^\s*(TÍTULO|CAPÍTULO|SECÇÃO)\s+[IVXLCDM]+\s*$", "", texto, flags=re.I)
    texto = re.sub(r"[ \t]+", " ", texto)
    texto = re.sub(r"\n{3,}", "\n\n", texto)
    return texto.strip()


def extrair_artigos(pdf: Path) -> list[dict]:
    documento = fitz.open(pdf)
    texto = "\n".join(pagina.get_text() for pagina in documento)
    padrao = re.compile(r"(?im)^\s*ARTIGO\s+(\d+)\s*$")
    marcadores = list(padrao.finditer(texto))
    artigos = []

    for indice, marcador in enumerate(marcadores):
        numero = int(marcador.group(1))
        if numero < 1 or numero > 186 or any(item["numero"] == numero for item in artigos):
            continue
        fim = marcadores[indice + 1].start() if indice + 1 < len(marcadores) else len(texto)
        bloco = limpar_texto(texto[marcador.end():fim])
        linhas = [linha.strip() for linha in bloco.splitlines() if linha.strip()]
        titulo = ""
        if linhas and linhas[0].startswith("("):
            partes = []
            while linhas:
                partes.append(linhas.pop(0))
                if partes[-1].endswith(")"):
                    break
            titulo = " ".join(partes).strip("() ")
        elif linhas and numero == 42:
            titulo = linhas.pop(0).strip("() ")
        corpo = limpar_texto("\n".join(linhas))
        artigos.append({"numero": numero, "titulo": titulo or f"Artigo {numero}", "texto": corpo})

    return sorted(artigos, key=lambda artigo: artigo["numero"])


def main() -> None:
    if len(sys.argv) != 3:
        raise SystemExit("uso: extract_codigo_estrada.py entrada.pdf saida.json")
    pdf = Path(sys.argv[1])
    destino = Path(sys.argv[2])
    artigos = extrair_artigos(pdf)
    if len(artigos) != 186:
        raise RuntimeError(f"esperados 186 artigos; extraídos {len(artigos)}")

    por_numero = {artigo["numero"]: artigo for artigo in artigos}
    categorias = []
    for categoria in CATEGORIAS:
        numeros = [
            numero
            for inicio, fim in categoria["intervalos"]
            for numero in range(inicio, fim + 1)
        ]
        categorias.append({
            **categoria,
            "artigos": [por_numero[numero] for numero in numeros],
        })

    pacote = {
        "fonte": {
            "titulo": "Código da Estrada de Moçambique",
            "diploma": "Decreto-Lei n.º 1/2011, de 23 de Março",
            "entidade": "INATRO",
            "ficheiro": "assets/conteudo/codigo-estrada-inatro.pdf",
            "totalArtigos": len(artigos),
        },
        "categorias": categorias,
    }
    destino.parent.mkdir(parents=True, exist_ok=True)
    destino.write_text(json.dumps(pacote, ensure_ascii=False, indent=2), encoding="utf-8")


if __name__ == "__main__":
    main()

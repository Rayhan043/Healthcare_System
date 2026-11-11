from pathlib import Path


def load_document(path: Path) -> str:
    if path.suffix.lower() == ".txt":
        return path.read_text(encoding="utf-8")
    if path.suffix.lower() == ".pdf":
        from pypdf import PdfReader
        return "\n".join(page.extract_text() or "" for page in PdfReader(path).pages)
    raise ValueError(f"Unsupported document type: {path.suffix}")


def load_documents(directory: Path) -> list[tuple[str, str]]:
    documents = []
    for path in sorted(directory.glob("*")):
        if path.suffix.lower() in {".txt", ".pdf"}:
            documents.append((path.name, load_document(path)))
    return documents

